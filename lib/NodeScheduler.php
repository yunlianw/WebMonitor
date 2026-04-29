<?php
/**
 * 分布式节点调度器
 * 处理Pull模式节点通信和结果处理
 */

require_once __DIR__ . "/../Database.php";
require_once __DIR__ . "/MonitorService.php";
require_once __DIR__ . "/../notifications/NotificationManager.php";

class NodeScheduler {
    private $conn;
    private $settings;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->loadSettings();
    }
    
    private function loadSettings() {
        $stmt = $this->conn->query("SELECT setting_name, setting_value FROM alert_settings");
        $this->settings = [];
        while ($row = $stmt->fetch()) {
            $this->settings[$row['setting_name']] = $row['setting_value'];
        }
    }
    
    /**
     * 执行所有节点的监控任务
     * @param bool $force 强制检查，忽略时间间隔
     */
    public function runAllNodes($force = false) {
        $results = [
            'local' => [],
            'pull' => [],
            'push' => []
        ];

        // 1. 执行内置节点任务
        $results['local'] = $this->runLocalNode($force);

        // 2. 执行Pull模式节点任务
        $results['pull'] = $this->runPullNodes($force);

        // Push模式节点自己主动上报，这里不管

        return $results;
    }
    
    /**
     * 执行内置节点任务
     * @param bool $force 强制检查
     */
    private function runLocalNode($force = false) {
        $timeout = (int)($this->settings['http_timeout_seconds'] ?? 10);
        $maxRetry = (int)($this->settings['max_retry_count'] ?? 3);
        
        // V3.2修复：查询包含内置节点(0)的网站
        $stmt = $this->conn->query("
            SELECT * FROM websites 
            WHERE enabled = 1 
            AND (node_ids = '0' OR node_ids = '1' OR FIND_IN_SET('0', node_ids) > 0 OR FIND_IN_SET('1', node_ids) > 0 OR node_ids IS NULL)
        ");
        $allSites = $stmt->fetchAll();
        
        if (empty($allSites)) {
            return ['checked' => 0, 'message' => '无内置节点任务'];
        }
        
        // V3.7: 根据 check_interval 过滤需要检测的网站（force模式下跳过检查）
        $sites = [];
        foreach ($allSites as $site) {
            $interval = isset($site['check_interval']) ? (int)$site['check_interval'] : 5;
            if ($interval < 1) $interval = 1; // 最小1分钟
            
            if ($force || $this->shouldCheck($site['id'], 0, $interval)) {
                $sites[] = $site;
            }
        }
        
        // 如果没有需要检测的网站，跳过
        if (empty($sites)) {
            return ['checked' => 0, 'message' => '本次周期内置节点无需检测'];
        }
        
        // V3.8: 只保留需要 HTTP/SSL 检测的网站（纯域名资产不分发）
        $checkSites = [];
        foreach ($sites as $site) {
            $checkHttp = $site['check_http'] ?? true;
            $checkSsl = $site['check_ssl'] ?? true;
            if ($checkHttp || $checkSsl) {
                $checkSites[] = [
                    'id' => $site['id'],
                    'url' => $site['url'],
                    'check_http' => $checkHttp,
                    'check_ssl' => $checkSsl
                ];
            }
        }
        
        if (empty($checkSites)) {
            return ['checked' => 0, 'message' => '无HTTP/SSL检测任务（纯域名资产）'];
        }
        
        $results = MonitorService::parallelCheck($checkSites, $timeout, $maxRetry);
        
        // 处理结果
        $this->processResults($sites, $results, 0);
        
        return ['checked' => count($sites), 'results' => $results];
    }
    
    /**
     * 执行Pull模式节点任务
     * @param bool $force 强制检查
     */
    private function runPullNodes($force = false) {
        // 获取所有启用的Pull模式节点
        $stmt = $this->conn->query("SELECT * FROM nodes WHERE enabled = 1 AND type = 1");
        $nodes = $stmt->fetchAll();
        
        if (empty($nodes)) {
            return [];
        }
        
        // V3.7: 使用 curl_multi 并行请求所有节点（force模式）
        return $this->pullNodesParallel($nodes, $force);
    }
    
    /**
     * V3.7: 检查网站是否需要检测（根据check_interval）
     */
    private function shouldCheck($websiteId, $nodeId, $interval) {
        $currentPeriod = floor(time() / ($interval * 60)) * ($interval * 60);
        
        // 查询该节点对该网站的上次检测时间
        $stmt = $this->conn->prepare("
            SELECT last_check_time, check_period 
            FROM node_check_times 
            WHERE website_id = ? AND node_id = ?
            ORDER BY last_check_time DESC 
            LIMIT 1
        ");
        $stmt->execute([$websiteId, $nodeId]);
        $row = $stmt->fetch();
        
        if (!$row) {
            // 从未检测过，需要检测
            return true;
        }
        
        $lastPeriod = floor(strtotime($row['last_check_time']) / ($interval * 60)) * ($interval * 60);
        
        // 如果当前周期和上次周期不同，则需要检测
        return $currentPeriod > $lastPeriod;
    }
    
    /**
     * V3.6: 并行请求多个Pull节点
     * @param bool $force 强制检查
     */
    private function pullNodesParallel($nodes, $force = false) {
        $timeout = (int)($this->settings['http_timeout_seconds'] ?? 10);
        $maxRetry = (int)($this->settings['max_retry_count'] ?? 3);
        
        $handles = [];
        $nodeData = [];
        $sitesMap = [];
        
        // 1. 为每个节点准备请求
        foreach ($nodes as $node) {
            // 获取该节点的网站
            $stmt = $this->conn->prepare("
                SELECT * FROM websites 
                WHERE enabled = 1 
                AND (FIND_IN_SET(?, node_ids) > 0)
            ");
            $stmt->execute([$node['id']]);
            $allSites = $stmt->fetchAll();
            
            if (empty($allSites)) {
                $nodeData[$node['id']] = [
                    'success' => true,
                    'checked' => 0,
                    'message' => '无任务',
                    'node_id' => $node['id'],
                    'node_name' => $node['name']
                ];
                continue;
            }
            
            // V3.7: 根据 check_interval 过滤需要检测的网站（force模式下跳过检查）
            $sites = [];
            foreach ($allSites as $site) {
                $interval = isset($site['check_interval']) ? (int)$site['check_interval'] : 5;
                if ($interval < 1) $interval = 1; // 最小1分钟
                
                if ($force || $this->shouldCheck($site['id'], $node['id'], $interval)) {
                    $sites[] = $site;
                }
            }
            
            // 如果没有需要检测的网站，跳过
            if (empty($sites)) {
                $nodeData[$node['id']] = [
                    'success' => true,
                    'checked' => 0,
                    'message' => '本次周期无需检测',
                    'node_id' => $node['id'],
                    'node_name' => $node['name']
                ];
                continue;
            }
            
            // 构建任务数据 - 只分发需要检测的网站（至少有一个服务启用）
            $tasks = [];
            foreach ($sites as $site) {
                $checkHttp = $site['check_http'] ?? true;
                $checkSsl = $site['check_ssl'] ?? true;
                // 只分发至少启用了一个检测服务的网站
                if ($checkHttp || $checkSsl) {
                    $tasks[] = [
                        'id' => $site['id'],
                        'url' => $site['url'],
                        'check_http' => $checkHttp,
                        'check_ssl' => $checkSsl
                    ];
                }
            }
            
            // 如果没有需要检测的网站，跳过
            if (empty($tasks)) {
                $nodeData[$node['id']] = [
                    'success' => true,
                    'checked' => 0,
                    'message' => '无HTTP/SSL检测任务',
                    'node_id' => $node['id'],
                    'node_name' => $node['name']
                ];
                continue;
            }
            
            $sitesMap[$node['id']] = $sites;
            
            // 构建 URL
            $agentUrl = $node['url'];
            if (strpos($agentUrl, '?') === false) {
                $agentUrl .= '?';
            }
            $agentUrl .= "action=check&key=" . urlencode($node['api_key']);
            $agentUrl .= "&timeout={$timeout}&max_retry={$maxRetry}";
            
            // 创建 curl handle
            $ch = curl_init($agentUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($tasks),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT => 15,          // V3.6: 总超时从60秒缩短至15秒
                CURLOPT_CONNECTTIMEOUT => 5,    // 连接超时5秒
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ]);
            
            $handles[$node['id']] = $ch;
            $nodeData[$node['id']] = [
                'node_id' => $node['id'],
                'node_name' => $node['name']
            ];
        }
        
        // 2. 并行执行所有请求
        $mh = curl_multi_init();
        foreach ($handles as $ch) {
            curl_multi_add_handle($mh, $ch);
        }
        
        $running = null;
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh, 1);  // 等待1秒
        } while ($running > 0);
        
        // 3. 收集结果
        $results = [];
        foreach ($handles as $nodeId => $ch) {
            $response = curl_multi_getcontent($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            if ($error || $httpCode < 200 || $httpCode >= 300) {
                $results[$nodeId] = [
                    'success' => false,
                    'message' => "请求失败: " . ($error ?: "HTTP $httpCode"),
                    'node_id' => $nodeId,
                    'node_name' => $nodeData[$nodeId]['node_name']
                ];
                // V3.6: 排除内置节点(type=0)
                $this->conn->prepare("UPDATE nodes SET status = 'offline' WHERE id = ? AND type != 0")
                    ->execute([$nodeId]);
            } else {
                $data = json_decode($response, true);
                if (!$data || !$data['success']) {
                    $results[$nodeId] = [
                        'success' => false,
                        'message' => $data['message'] ?? '响应解析失败',
                        'node_id' => $nodeId,
                        'node_name' => $nodeData[$nodeId]['node_name']
                    ];
                    // V3.6: 排除内置节点(type=0)
                    $this->conn->prepare("UPDATE nodes SET status = 'offline' WHERE id = ? AND type != 0")
                        ->execute([$nodeId]);
                } else {
                    // 处理返回的结果
                    $checkResults = $data['data']['results'] ?? [];
                    if (isset($sitesMap[$nodeId])) {
                        $this->processResults($sitesMap[$nodeId], $checkResults, $nodeId);
                    }
                    
                    $results[$nodeId] = [
                        'success' => true,
                        'checked' => count($sitesMap[$nodeId] ?? []),
                        'node_id' => $nodeId,
                        'node_name' => $nodeData[$nodeId]['node_name']
                    ];
                    
                    // V3.6: 排除内置节点(type=0)
                    $this->conn->prepare("UPDATE nodes SET last_heartbeat = NOW(), status = 'online' WHERE id = ? AND type != 0")
                        ->execute([$nodeId]);
                }
            }
            
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        
        curl_multi_close($mh);
        
        // 合并无任务的节点结果
        foreach ($nodeData as $nodeId => $data) {
            if (!isset($results[$nodeId])) {
                $results[$nodeId] = $data;
            }
        }
        
        return $results;
    }
    
    /**
     * 处理检测结果（告警、日志等）
     */
    private function processResults($sites, $results, $nodeId) {
        $sslWarning = (int)($this->settings['ssl_warning_days'] ?? 60);
        $sslInterval = (int)($this->settings['ssl_alert_interval_days'] ?? 1);
        $httpCooldown = (int)($this->settings['alert_cooldown_minutes'] ?? 5);
        
        $alerts = ['http_down' => [], 'http_up' => [], 'ssl_warning' => []];
        
        foreach ($results as $result) {
            // V3.4: 修复：使用site_id而不是数组索引
            $siteId = $result['site_id'] ?? $result['id'] ?? null;
            if (!$siteId) continue;
            
            // 找到网站信息
            $site = null;
            foreach ($sites as $s) {
                if ($s['id'] == $siteId) {
                    $site = $s;
                    break;
                }
            }
            if (!$site) continue;
            
            $status = $result['http_status'];
            $httpCode = $result['http_code'];
            $sslDays = $result['ssl_days'] ?? null;
            $lastStatus = $site['last_http_status'];
            
            // HTTP告警
            if ($status === 'down') {
                $canAlert = true;
                if ($site['last_http_alert']) {
                    $last = strtotime($site['last_http_alert']);
                    if ((time() - $last) < ($httpCooldown * 60)) {
                        $canAlert = false;
                    }
                }
                if ($canAlert) {
                    $alerts['http_down'][] = [
                        'id' => $site['id'],
                        'name' => $site['name'],
                        'url' => $site['url'],
                        'code' => $httpCode ?: 0,
                        'error' => $result['http_error'] ?? "HTTP {$httpCode}",
                        'retries' => $result['retries'] ?? 0
                    ];
                }
            } elseif ($lastStatus === 'down' && $status === 'up') {
                $alerts['http_up'][] = ['id' => $site['id'], 'name' => $site['name'], 'url' => $site['url']];
            }
            
            // SSL告警
            if ($sslDays !== null && $sslDays > 0 && $sslDays <= $sslWarning) {
                $sslAlert = false;
                $lastSsl = $site['last_ssl_alert_date'];
                if (!$lastSsl) {
                    $sslAlert = true;
                } else {
                    if ((time() - strtotime($lastSsl)) >= ($sslInterval * 86400)) {
                        $sslAlert = true;
                    }
                }
                if ($sslAlert) {
                    $alerts['ssl_warning'][] = ['id' => $site['id'], 'name' => $site['name'], 'url' => $site['url'], 'days' => $sslDays];
                    // V3.8: 更新SSL告警时间，防止重复告警
                    $this->conn->prepare("UPDATE websites SET last_ssl_alert = NOW(), last_ssl_alert_date = CURDATE(), ssl_alert_count = ssl_alert_count + 1 WHERE id = ?")
                        ->execute([$site['id']]);
                }
            }
            
            // 更新数据库状态
            $this->conn->prepare("UPDATE websites SET last_http_status = ?, last_check_time = NOW(), last_ssl_days = ? WHERE id = ?")
                ->execute([$status, $sslDays, $site['id']]);
            
            // V3.3: 记录节点独立检查时间
            $interval = 5;
            $currentPeriod = floor(time() / ($interval * 60)) * ($interval * 60);
            $this->conn->prepare("
                INSERT INTO node_check_times (node_id, website_id, last_check_time, check_period)
                VALUES (?, ?, NOW(), ?)
                ON DUPLICATE KEY UPDATE last_check_time = VALUES(last_check_time), check_period = VALUES(check_period)
            ")->execute([$nodeId, $site['id'], $currentPeriod]);
            
            // 记录日志
            $this->logResult($site, $result, $nodeId);
        }
        
        // 发送告警
        if (!empty($alerts['http_down']) || !empty($alerts['http_up']) || !empty($alerts['ssl_warning'])) {
            $this->sendAlerts($alerts, $nodeId);
        }
        
        // V3.6: 更新多点同步计数
        $this->updateMultiSync($sites, $nodeId);
    }
    
    /**
     * V3.6: 更新多点同步计数
     */
    private function updateMultiSync($sites, $nodeId) {
        foreach ($sites as $site) {
            $siteId = $site['id'];
            $nodeIds = $site['node_ids'] ?? '0';
            
            // 计算该网站应该有多少个节点检测（'0' 也算一个节点）
            $nodes = array_map('trim', explode(',', $nodeIds));
            $nodes = array_filter($nodes, function($n) { return $n !== ''; }); // 只过滤空字符串
            $totalNodes = count($nodes);
            if ($totalNodes == 0) $totalNodes = 1; // 至少有一个节点
            
            // 获取当前已同步的节点数（查询node_check_times表）
            $stmt = $this->conn->prepare("
                SELECT COUNT(DISTINCT node_id) as cnt 
                FROM node_check_times 
                WHERE website_id = ? 
                AND last_check_time > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
            ");
            $stmt->execute([$siteId]);
            $row = $stmt->fetch();
            $syncCount = $row['cnt'] ?? 0;
            if ($syncCount > $totalNodes) $syncCount = $totalNodes;
            
            // 更新多点同步计数
            $this->conn->prepare("UPDATE websites SET multi_sync_count = ?, multi_sync_total = ?, last_multi_check_time = NOW() WHERE id = ?")
                ->execute([$syncCount, $totalNodes, $siteId]);
        }
    }
    
    /**
     * 记录监控日志
     */
    private function logResult($site, $result, $nodeId) {
        $sslStatus = 'unknown';
        $sslDays = $result['ssl_days'] ?? null;
        $sslWarning = (int)($this->settings['ssl_warning_days'] ?? 60);
        if ($sslDays !== null && $sslDays > 0) {
            if ($sslDays <= 7) $sslStatus = 'expired';          // 即将过期
            elseif ($sslDays <= $sslWarning) $sslStatus = 'warning'; // 在告警范围内
            else $sslStatus = 'valid';                          // 正常
        }
        
        // V3.3: 记录node_id（0代表内置节点）
        $stmt = $this->conn->prepare("
            INSERT INTO monitor_logs (website_id, node_id, check_type, http_status, http_code, response_time, ssl_status, ssl_days, checked_at)
            VALUES (?, ?, 'both', ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $site['id'],
            $nodeId,
            $result['http_status'],
            $result['http_code'] ?? 0,
            $result['response_time'] ?? 0,
            $sslStatus,
            $sslDays
        ]);
    }
    
    /**
     * 发送告警通知
     */
    private function sendAlerts($alerts, $nodeId) {
        $time = date('Y-m-d H:i:s');
        
        // 获取节点名称
        $nodeName = '内置节点';
        if ($nodeId > 0) {
            $stmt = $this->conn->prepare("SELECT name FROM nodes WHERE id = ?");
            $stmt->execute([$nodeId]);
            $node = $stmt->fetch();
            if ($node) $nodeName = $node['name'];
        }
        
        // 记录告警日志
        foreach ($alerts['http_down'] as $a) {
            $retryInfo = isset($a['retries']) && $a['retries'] > 0 ? " (重试{$a['retries']}次)" : "";
            $this->conn->prepare("INSERT INTO alert_logs (website_id, alert_type, alert_message, sent_at) VALUES (?, 'http_down', ?, NOW())")
                ->execute([$a['id'], "[{$nodeName}] {$a['name']} HTTP异常: {$a['error']}{$retryInfo}"]);
        }
        
        foreach ($alerts['ssl_warning'] as $a) {
            $this->conn->prepare("INSERT INTO alert_logs (website_id, alert_type, alert_message, sent_at) VALUES (?, 'ssl_warning', ?, NOW())")
                ->execute([$a['id'], "[{$nodeName}] {$a['name']} SSL证书剩余 {$a['days']} 天"]);
        }
        
        // 发送通知
        try {
            $manager = new NotificationManager($this->conn);
            
            $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body>";
            $html .= "<h1>网站状态通知</h1><p>节点: {$nodeName}</p><p>时间: {$time}</p>";
            
            $tgMessage = "网站状态通知\n📍 节点: {$nodeName}\n📅 时间: {$time}\n\n";
            
            if (!empty($alerts['http_down'])) {
                $html .= "<h2>HTTP异常 (" . count($alerts['http_down']) . "个)</h2>";
                $tgMessage .= "HTTP异常 (" . count($alerts['http_down']) . "个)\n";
                foreach ($alerts['http_down'] as $a) {
                    $html .= "<p><strong>{$a['name']}</strong> - {$a['error']}</p>";
                    $tgMessage .= "{$a['name']} - {$a['error']}\n";
                }
                $tgMessage .= "\n";
            }
            
            if (!empty($alerts['http_up'])) {
                $html .= "<h2>HTTP恢复 (" . count($alerts['http_up']) . "个)</h2>";
                $tgMessage .= "HTTP恢复 (" . count($alerts['http_up']) . "个)\n";
                foreach ($alerts['http_up'] as $a) {
                    $html .= "<p><strong>{$a['name']}</strong> - 已恢复</p>";
                    $tgMessage .= "{$a['name']} - 已恢复\n";
                }
                $tgMessage .= "\n";
            }
            
            if (!empty($alerts['ssl_warning'])) {
                $html .= "<h2>SSL证书提醒 (" . count($alerts['ssl_warning']) . "个)</h2>";
                $tgMessage .= "SSL证书提醒 (" . count($alerts['ssl_warning']) . "个)\n";
                foreach ($alerts['ssl_warning'] as $a) {
                    $html .= "<p><strong>{$a['name']}</strong> - SSL剩余 {$a['days']} 天</p>";
                    $tgMessage .= "{$a['name']} - SSL剩余 {$a['days']} 天\n";
                }
            }
            
            $html .= "</body></html>";
            
            $manager->sendAll([
                'subject' => "网站状态通知 [{$nodeName}] - $time",
                'message' => strip_tags($html),
                'html' => $html,
                'tg_message' => $tgMessage,
                'type' => '网站监控',
                'time' => $time,
                'node' => $nodeName,
                'website_ids' => array_column(array_merge($alerts['http_down'], $alerts['http_up'], $alerts['ssl_warning']), 'id')
            ]);
            
        } catch (Exception $e) {
            // 静默失败
        }
    }
}
