<?php
/**
 * 节点通信API V2.0
 * 
 * 核心功能：
 * - 双轨制鉴权（全局密钥 + 节点独立密钥）
 * - Push模式任务领取和结果上报
 * - 节点心跳和IP归属地
 * - 探针一键下载
 * 
 * 接口列表：
 * - get_tasks: 探针领取任务
 * - report: 探针上报结果
 * - heartbeat: 心跳更新
 * - download_agent: 下载预设型探针
 * - ip_location: IP归属地查询
 */

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/config/Config.php";
require_once __DIR__ . "/Database.php";
require_once __DIR__ . "/lib/NodeScheduler.php";
require_once __DIR__ . '/notifications/NotificationManager.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // 获取全局密钥
    $stmt = $conn->query("SELECT global_key FROM system_settings ORDER BY id DESC LIMIT 1");
    $result = $stmt->fetch();
    $globalKey = $result['global_key'] ?? '';
    
    switch ($action) {
        case 'get_tasks':
            handleGetTasks($conn, $globalKey);
            break;
        case 'report':
            handleReport($conn, $globalKey);
            break;
        case 'heartbeat':
            handleHeartbeat($conn, $globalKey);
            break;
        case 'download_agent':
            handleDownloadAgent($conn, $globalKey);
            break;
        case 'ip_location':
            handleIpLocation();
            break;
        default:
            jsonResponse(false, "未知操作: $action");
    }
} catch (Exception $e) {
    jsonResponse(false, "系统错误: " . $e->getMessage());
}

/**
 * 验证密钥（双轨制）
 * @return array ['valid' => bool, 'node' => array|null, 'is_global' => bool]
 */
function validateKey($conn, $key, $nodeId = null, $globalKey = '') {
    // 1. 验证全局密钥
    if (!empty($globalKey) && $key === $globalKey) {
        return ['valid' => true, 'node' => null, 'is_global' => true];
    }
    
    // 2. 验证节点独立密钥
    if ($nodeId !== null) {
        $stmt = $conn->prepare("SELECT * FROM nodes WHERE id = ? AND enabled = 1");
        $stmt->execute([$nodeId]);
        $node = $stmt->fetch();
        
        // 检查全局密钥兼容（兼容旧版节点）
        if ($node && !empty($node['global_key']) && $key === $node['global_key']) {
            return ['valid' => true, 'node' => $node, 'is_global' => true];
        }
        
        // 检查独立API密钥
        if ($node && !empty($node['api_key']) && $key === $node['api_key']) {
            return ['valid' => true, 'node' => $node, 'is_global' => false];
        }
    }
    
    return ['valid' => false, 'node' => null, 'is_global' => false];
}

/**
 * get_tasks - 探针领取任务（Push模式）
 * 参数: node_id, key
 * 返回: 该节点负责的网站列表
 */
function handleGetTasks($conn, $globalKey) {
    $nodeId = intval($_GET['node_id'] ?? 0);
    $key = $_GET['key'] ?? '';
    
    // 验证参数
    if ($nodeId <= 0 || empty($key)) {
        jsonResponse(false, "参数错误: 需要 node_id 和 key");
    }
    
    // 验证密钥
    $auth = validateKey($conn, $key, $nodeId, $globalKey);
    if (!$auth['valid']) {
        jsonResponse(false, "密钥验证失败");
    }
    
    // 获取节点信息
    if ($auth['is_global']) {
        // 全局密钥：获取指定节点
        $stmt = $conn->prepare("SELECT * FROM nodes WHERE id = ? AND enabled = 1");
        $stmt->execute([$nodeId]);
    } else {
        $stmt = $conn->prepare("SELECT * FROM nodes WHERE id = ? AND api_key = ? AND enabled = 1");
        $stmt->execute([$nodeId, $key]);
    }
    $node = $stmt->fetch();
    
    if (!$node) {
        jsonResponse(false, "节点不存在或已禁用");
    }
    
    // 只允许Push模式或内置节点调用
    if (!in_array($node['type'], [0, 2])) {
        jsonResponse(false, "此接口仅限Push模式或内置节点使用");
    }
    
    // 更新心跳和IP
    updateNodeHeartbeat($conn, $node['id']);
    
    // V3.1：整点周期调度 + 30秒时间窗口 + 任意节点先更新
    // 核心：使用整5分钟周期（如 06:40:00 - 06:44:59），但允许30秒时间窗口误差
    $interval = 5; // 检测周期（分钟）
    $windowSize = 30; // 时间窗口秒数
    $currentPeriod = floor(time() / ($interval * 60)) * ($interval * 60); // 当前周期时间戳
    
    // 查询该节点在本周期（或30秒窗口内）尚未检测的网站
    $stmt = $conn->prepare("
        SELECT w.id, w.name, w.url, w.check_http, w.check_ssl, w.check_interval,
               w.last_response_time,
               w.multi_sync_count,
               w.multi_sync_total,
               nct.last_check_time as node_last_check,
               nct.check_period as node_check_period
        FROM websites w
        LEFT JOIN node_check_times nct ON w.id = nct.website_id AND nct.node_id = ?
        WHERE w.enabled = 1 
        AND (FIND_IN_SET(?, w.node_ids) > 0)
        AND (
            nct.check_period IS NULL 
            OR nct.check_period < ?
        )
    ");
    $stmt->execute([$nodeId, $nodeId, $currentPeriod]);
    $sites = $stmt->fetchAll();
    
    // 获取检测配置
    $stmt = $conn->query("SELECT setting_name, setting_value FROM alert_settings");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_name']] = $row['setting_value'];
    }
    
    // 返回任务列表
    jsonResponse(true, "任务列表", [
        'node' => [
            'id' => $node['id'],
            'name' => $node['name'],
            'location' => $node['location'],
            'type' => $node['type']
        ],
        'config' => [
            'timeout' => (int)($settings['http_timeout_seconds'] ?? 10),
            'max_retry' => (int)($settings['max_retry_count'] ?? 3)
        ],
        'sites' => array_map(function($site) {
            return [
                'id' => (int)$site['id'],
                'name' => $site['name'],
                'url' => $site['url'],
                'check_http' => (bool)$site['check_http'],
                'check_ssl' => (bool)$site['check_ssl']
            ];
        }, $sites),
        'total' => count($sites),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * report - 探针上报检测结果
 * 参数: node_id, key, data (POST JSON)
 * V2.6: 实时处理，直接更新websites表，记录日志，触发告警
 */
function handleReport($conn, $globalKey) {
    $nodeId = intval($_POST['node_id'] ?? $_GET['node_id'] ?? 0);
    $key = $_POST['key'] ?? $_GET['key'] ?? '';
    
    // 验证参数
    if ($nodeId <= 0 || empty($key)) {
        jsonResponse(false, "参数错误: 需要 node_id 和 key");
    }
    
    // 验证密钥
    $auth = validateKey($conn, $key, $nodeId, $globalKey);
    if (!$auth['valid']) {
        jsonResponse(false, "密钥验证失败");
    }
    
    // 获取节点信息
    $stmt = $conn->prepare("SELECT * FROM nodes WHERE id = ? AND enabled = 1");
    $stmt->execute([$nodeId]);
    $node = $stmt->fetch();
    if (!$node) {
        jsonResponse(false, "节点不存在或已禁用");
    }
    $nodeName = $node['name'];
    
    // 获取上报数据
    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true);
    
    if (!$data || !is_array($data)) {
        jsonResponse(false, "数据格式错误: 需要JSON数组");
    }
    
    // 更新心跳和IP
    updateNodeHeartbeat($conn, $nodeId);
    
    // 获取告警设置
    $stmt = $conn->query("SELECT setting_name, setting_value FROM alert_settings");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_name']] = $row['setting_value'];
    }
    $sslWarning = (int)($settings['ssl_warning_days'] ?? 60);
    $sslInterval = (int)($settings['ssl_alert_interval_days'] ?? 1);
    $httpCooldown = (int)($settings['alert_cooldown_minutes'] ?? 5);
    
    // 告警收集
    $alerts = ['http_down' => [], 'http_up' => [], 'ssl_warning' => []];
    
    // 处理每条上报数据
    foreach ($data as $item) {
        $siteId = $item['site_id'] ?? 0;
        if ($siteId <= 0) continue;
        
        $httpStatus = $item['http_status'] ?? 'unknown';
        $httpCode = $item['http_code'] ?? 0;
        $sslDays = $item['ssl_days'] ?? null;
        $responseTime = $item['response_time'] ?? 0;
        $httpError = $item['http_error'] ?? null;
        $retries = $item['retries'] ?? 0;
        
        // 获取网站当前状态（V4.2：只处理启用中的网站，已禁用的直接跳过）
        $stmt = $conn->prepare("SELECT * FROM websites WHERE id = ? AND enabled = 1");
        $stmt->execute([$siteId]);
        $site = $stmt->fetch();
        if (!$site) continue;
        
        $lastStatus = $site['last_http_status'];
        $lastSslDays = $site['last_ssl_days'];
        
        // V4.2：check_http=0 时探针会上报 http_status=skipped，跳过HTTP相关逻辑
        $httpSkipped = ($httpStatus === 'skipped') || !empty($item['http_skipped']);
        $sslSkipped = !empty($item['ssl_skipped']);
        // 跳过HTTP检测时不覆盖原HTTP状态
        $dbHttpStatus = $httpSkipped ? ($site['last_http_status'] ?? 'unknown') : $httpStatus;
        
        // ========== 记录监控日志 ==========
        $sslStatus = 'unknown';
        if ($sslDays !== null) {
            if ($sslDays <= 0) $sslStatus = 'expired';
            elseif ($sslDays <= 7) $sslStatus = 'expired';
            elseif ($sslDays <= 30) $sslStatus = 'warning';
            else $sslStatus = 'valid';
        }
        $checkType = 'both';
        if ($httpSkipped && !$sslSkipped) $checkType = 'ssl';
        elseif ($sslSkipped && !$httpSkipped) $checkType = 'http';
        elseif ($httpSkipped && $sslSkipped) $checkType = 'none';
        
        $conn->prepare("
            INSERT INTO monitor_logs (website_id, node_id, check_type, http_status, http_code, response_time, ssl_status, ssl_days, checked_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ")->execute([$siteId, $nodeId, $checkType, $httpStatus, $httpCode, $responseTime, $sslStatus, $sslDays]);
        
        // ========== 更新websites表（关键：SSL数据保护策略） ==========
        // SSL数据保护：只有新数据有效时才更新，避免检测失败时覆盖已有数据
        // V4.2：HTTP被跳过时不覆盖原HTTP状态
        if ($sslDays !== null && $sslDays > 0) {
            // 有有效SSL数据，更新
            $conn->prepare("
                UPDATE websites 
                SET last_http_status = ?, last_check_time = NOW(), last_ssl_status = ?, last_ssl_days = ? 
                WHERE id = ?
            ")->execute([$dbHttpStatus, $sslStatus, $sslDays, $siteId]);
        } else {
            // 无有效SSL数据，只更新HTTP状态（保留原有SSL数据）
            $conn->prepare("
                UPDATE websites 
                SET last_http_status = ?, last_check_time = NOW() 
                WHERE id = ?
            ")->execute([$dbHttpStatus, $siteId]);
        }
        
        // ========== 更新节点独立检查时间（V3.0整点周期调度） ==========
        // 记录该节点对此网站的最后检查时间和周期
        $interval = 5;
        $currentPeriod = floor(time() / ($interval * 60)) * ($interval * 60);
        $conn->prepare("
            INSERT INTO node_check_times (node_id, website_id, last_check_time, check_period)
            VALUES (?, ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE last_check_time = VALUES(last_check_time), check_period = VALUES(check_period)
        ")->execute([$nodeId, $siteId, $currentPeriod]);
        
        // ========== 更新网站响应时间（用于对比显示） ==========
        if ($responseTime > 0) {
            $conn->prepare("UPDATE websites SET last_response_time = ? WHERE id = ?")
                ->execute([$responseTime, $siteId]);
        }
        
        // ========== V3.3：多点同步逻辑 - 任意节点上报立即更新 ==========
        // 获取该网站配置的所有节点
        $siteNodes = explode(',', $site['node_ids']);
        $siteNodes = array_map('intval', array_filter($siteNodes));
        // V3.7修复：兼容逻辑修正 - 如果只配了node_id=0（旧配置），当作node_id=1处理
        // 而不是给配了node_id=1的添加node_id=0
        if (in_array(0, $siteNodes) && !in_array(1, $siteNodes)) {
            // 替换0为1，保持数组键值
            foreach ($siteNodes as $k => $v) {
                if ($v === 0) $siteNodes[$k] = 1;
            }
        }
        $siteNodes = array_unique($siteNodes);
        $totalNodes = count($siteNodes);
        
        // 检查这些节点是否在本周期（或30秒窗口内）完成了检测
        // 30秒窗口：当前周期±15秒内的上报都算这一波
        $windowStart = $currentPeriod - 15;
        $windowEnd = $currentPeriod + ($interval * 60) + 15;
        
        $placeholders = implode(',', array_fill(0, count($siteNodes), '?'));
        $stmt = $conn->prepare("
            SELECT COUNT(*) as cnt FROM node_check_times 
            WHERE website_id = ? AND node_id IN ($placeholders) 
            AND check_period >= ? AND check_period <= ?
        ");
        $params = array_merge([$siteId], $siteNodes, [$windowStart, $windowEnd]);
        $stmt->execute($params);
        $checkedCount = $stmt->fetch()['cnt'];
        
        // V3.3: 任意节点汇报立即更新主状态（HTTP和SSL）
        if ($sslDays !== null && $sslDays > 0) {
            $conn->prepare("UPDATE websites SET last_http_status = ?, last_check_time = NOW(), last_ssl_status = ?, last_ssl_days = ? WHERE id = ?")
                ->execute([$dbHttpStatus, $sslStatus, $sslDays, $siteId]);
        } else {
            $conn->prepare("UPDATE websites SET last_http_status = ?, last_check_time = NOW() WHERE id = ?")
                ->execute([$dbHttpStatus, $siteId]);
        }
        
        // V3.3: 任意节点汇报都立即更新多点同步时间（显示最新进度）
        $conn->prepare("UPDATE websites SET multi_sync_count = ?, multi_sync_total = ?, last_multi_check_time = NOW() WHERE id = ?")
            ->execute([$checkedCount, $totalNodes, $siteId]);
        
        // ========== HTTP告警判断（V4.2：跳过HTTP检测的网站不告警） ==========
        if (!$httpSkipped && $httpStatus === 'down') {
            // 检查告警冷却期
            $canAlert = true;
            if (!empty($site['last_http_alert'])) {
                $lastAlert = strtotime($site['last_http_alert']);
                if ((time() - $lastAlert) < ($httpCooldown * 60)) {
                    $canAlert = false;
                }
            }
            if ($canAlert) {
                $errorMsg = $httpError ?: "HTTP {$httpCode}";
                $alerts['http_down'][] = [
                    'id' => $siteId,
                    'name' => $site['name'],
                    'url' => $site['url'],
                    'code' => $httpCode ?: 0,
                    'error' => $errorMsg,
                    'retries' => $retries
                ];
                // 更新告警时间
                $conn->prepare("UPDATE websites SET last_http_alert = NOW(), http_alert_count = http_alert_count + 1 WHERE id = ?")
                    ->execute([$siteId]);
            }
        } elseif (!$httpSkipped && $lastStatus === 'down' && $httpStatus === 'up') {
            // 恢复通知
            $alerts['http_up'][] = [
                'id' => $siteId,
                'name' => $site['name'],
                'url' => $site['url']
            ];
            $conn->prepare("UPDATE websites SET last_http_alert = NOW() WHERE id = ?")->execute([$siteId]);
        }
        
        // ========== SSL告警判断（V4.2：跳过SSL检测的网站不告警；已过期证书 days<=0 也告警，与主控端一致） ==========
        if (!$sslSkipped && $sslDays !== null && $sslDays <= $sslWarning) {
            // 检查SSL告警间隔
            $sslAlert = false;
            $lastSslAlert = $site['last_ssl_alert_date'] ?? null;
            if (!$lastSslAlert) {
                $sslAlert = true;
            } else {
                if ((time() - strtotime($lastSslAlert)) >= ($sslInterval * 86400)) {
                    $sslAlert = true;
                }
            }
            if ($sslAlert) {
                $alerts['ssl_warning'][] = [
                    'id' => $siteId,
                    'name' => $site['name'],
                    'url' => $site['url'],
                    'days' => $sslDays
                ];
                $conn->prepare("UPDATE websites SET last_ssl_alert_date = CURDATE() WHERE id = ?")
                    ->execute([$siteId]);
            }
        }
        
        // 记录告警日志
        if (!$httpSkipped && $httpStatus === 'down' && !empty($alerts['http_down'])) {
            foreach ($alerts['http_down'] as $a) {
                if ($a['id'] == $siteId) {
                    $retryInfo = $retries > 0 ? " (重试{$retries}次)" : "";
                    $conn->prepare("INSERT INTO alert_logs (website_id, alert_type, alert_message, sent_at) VALUES (?, 'http_down', ?, NOW())")
                        ->execute([$siteId, "[{$nodeName}] {$site['name']} HTTP异常: {$errorMsg}{$retryInfo}"]);
                }
            }
        }
        
        if (!$sslSkipped && $sslDays !== null && $sslDays <= $sslWarning) {
            foreach ($alerts['ssl_warning'] as $a) {
                if ($a['id'] == $siteId) {
                    $conn->prepare("INSERT INTO alert_logs (website_id, alert_type, alert_message, sent_at) VALUES (?, 'ssl_warning', ?, NOW())")
                        ->execute([$siteId, "[{$nodeName}] {$site['name']} SSL证书剩余 {$sslDays} 天"]);
                }
            }
        }
    }
    
    // ========== 发送告警通知 ==========
    if (!empty($alerts['http_down']) || !empty($alerts['http_up']) || !empty($alerts['ssl_warning'])) {
        try {
            $manager = new NotificationManager($conn);
            $time = date('Y-m-d H:i:s');
            
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
                'node' => $nodeName
            ]);
        } catch (Exception $e) {
            error_log("告警发送失败: " . $e->getMessage());
        }
    }
    
    // 返回成功
    jsonResponse(true, "上报成功", [
        'received' => count($data),
        'node_id' => $nodeId,
        'alerts' => count($alerts['http_down']) + count($alerts['ssl_warning'])
    ]);
}

/**
 * heartbeat - 心跳更新
 */
function handleHeartbeat($conn, $globalKey) {
    $nodeId = intval($_GET['node_id'] ?? 0);
    $key = $_GET['key'] ?? '';
    
    if ($nodeId <= 0 || empty($key)) {
        jsonResponse(false, "参数错误");
    }
    
    // 验证密钥
    $auth = validateKey($conn, $key, $nodeId, $globalKey);
    if (!$auth['valid']) {
        jsonResponse(false, "密钥验证失败");
    }
    
    updateNodeHeartbeat($conn, $nodeId);
    
    jsonResponse(true, "心跳更新成功");
}

/**
 * download_agent - 下载预设型探针
 * 参数: node_id, key
 */
function handleDownloadAgent($conn, $globalKey) {
    $nodeId = intval($_GET['node_id'] ?? 0);
    $key = $_GET['key'] ?? '';
    
    if ($nodeId <= 0 || empty($key)) {
        jsonResponse(false, "参数错误: 需要 node_id 和 key");
    }
    
    // 验证密钥
    $auth = validateKey($conn, $key, $nodeId, $globalKey);
    if (!$auth['valid']) {
        jsonResponse(false, "密钥验证失败");
    }
    
    // 获取节点信息
    $stmt = $conn->prepare("SELECT * FROM nodes WHERE id = ? AND enabled = 1");
    $stmt->execute([$nodeId]);
    $node = $stmt->fetch();
    
    if (!$node) {
        jsonResponse(false, "节点不存在或已禁用");
    }
    
    // 获取主控URL
    $masterUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') 
        . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
    
    // 获取节点API密钥（根据use_global_key决定使用全局密钥还是独立密钥）
    $useGlobalKey = !empty($node['use_global_key']);
    if ($useGlobalKey) {
        // 使用全局密钥
        $apiKey = $globalKey;
    } else {
        // 使用独立密钥
        $apiKey = $node['api_key'] ?? '';
    }
    
    // 读取探针模板
    $templateFile = __DIR__ . '/agent_templates/agent_template.php';
    if (!file_exists($templateFile)) {
        $templateFile = __DIR__ . '/agent.php';
    }
    
    $template = file_get_contents($templateFile);
    
    // 替换占位符
    $template = str_replace('{{MASTER_URL}}', $masterUrl, $template);
    $template = str_replace('{{NODE_ID}}', $nodeId, $template);
    $template = str_replace('{{API_KEY}}', $apiKey, $template);
    
    // 输出下载
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="agent_' . $nodeId . '.php"');
    header('Content-Length: ' . strlen($template));
    echo $template;
    exit;
}

/**
 * ip_location - IP归属地查询
 */
function handleIpLocation() {
    $ip = $_GET['ip'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    
    if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
        jsonResponse(false, "无效的IP地址");
    }
    
    // 使用IPIP.NET免费API（国内访问较快）
    $url = "http://ip-api.com/json/{$ip}?lang=zh-CN";
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if ($data && $data['status'] === 'success') {
            jsonResponse(true, "查询成功", [
                'ip' => $ip,
                'country' => $data['country'] ?? '',
                'region' => $data['regionName'] ?? '',
                'city' => $data['city'] ?? '',
                'isp' => $data['isp'] ?? '',
                'org' => $data['org'] ?? '',
                'location' => ($data['country'] ?? '') . ' ' . ($data['isp'] ?? '')
            ]);
        }
    }
    
    jsonResponse(false, "查询失败");
}

/**
 * 更新节点心跳和IP
 */
function updateNodeHeartbeat($conn, $nodeId) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $location = '';
    
    // 获取IP归属地
    if (!empty($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
        $url = "http://ip-api.com/json/{$ip}?lang=zh-CN";
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3]);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        if ($data && $data['status'] === 'success') {
            $location = ($data['country'] ?? '') . ' ' . ($data['isp'] ?? '');
        }
    }
    
    $stmt = $conn->prepare("
        UPDATE nodes 
        SET last_heartbeat = NOW(), 
            status = 'online', 
            last_ip = ?,
            ip_location = ?
        WHERE id = ?
    ");
    $stmt->execute([$ip, $location, $nodeId]);
}

/**
 * JSON响应
 */
function jsonResponse(bool $success, string $message, array $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
