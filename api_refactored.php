<?php
/**
 * 网站监控系统 - 分布式版API
 * 支持: 单机模式 + 分布式节点(Pull/Push)
 * 
 * Action列表：
 * - check: HTTP/SSL检测（每分钟）
 * - whois: 域名WHOIS检测（低频，每12-24小时）
 */

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/config/Config.php";
require_once __DIR__ . "/Database.php";
require_once __DIR__ . "/lib/NodeScheduler.php";
require_once __DIR__ . "/lib/WhoisChecker.php";
require_once __DIR__ . "/lib/WhoisMonitorService.php";

// 从数据库读取监控密钥
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->query("SELECT monitor_key FROM system_settings ORDER BY id DESC LIMIT 1");
    $settings = $stmt->fetch();
    $validKey = $settings['monitor_key'] ?? '';
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => "数据库错误: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

// 验证密钥
$monitorKey = $_GET["key"] ?? "";
if (empty($monitorKey) || empty($validKey) || $monitorKey !== $validKey) {
    echo json_encode(["success" => false, "error" => "监控密钥无效"], JSON_UNESCAPED_UNICODE);
    exit;
}

// 获取action参数，默认为check
$action = $_GET["action"] ?? "check";

try {
    // V3.7: 内置节点心跳 - 在每次check时自动更新node_check_times和多点同步状态
    updateBuiltinNodeSync($conn);
    
    if ($action === "check") {
        $start = microtime(true);
        
        // V3.7: 支持force参数强制检查
        $force = isset($_GET['force']) && $_GET['force'] === '1';
        
        // 使用节点调度器执行所有任务
        $scheduler = new NodeScheduler($conn);
        $results = $scheduler->runAllNodes($force);
        
        $totalChecked = 0;
        foreach ($results as $type => $data) {
            if (is_array($data) && isset($data['checked'])) {
                $totalChecked += $data['checked'];
            } elseif (is_array($data)) {
                foreach ($data as $nodeResult) {
                    if (isset($nodeResult['checked'])) {
                        $totalChecked += $nodeResult['checked'];
                    }
                }
            }
        }
        
        // V4.0: 自动执行WHOIS检测（低频，判断时间间隔）
        $whoisResult = runWhoisCheckIfNeeded($conn, $force);
        
        $response = [
            "success" => true,
            "checked" => $totalChecked,
            "whois" => $whoisResult,
            "details" => [
                "local" => $results['local']['checked'] ?? 0,
                "pull_nodes" => count($results['pull']),
                "push_nodes" => "自动上报"
            ],
            "mode" => "distributed",
            "time" => round((microtime(true) - $start) * 1000, 2) . "ms",
            "timestamp" => date("Y-m-d H:i:s"),
            "note" => "分布式监控系统 - NodeScheduler"
        ];
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        
    } elseif ($action === "whois") {
        // 手动触发WHOIS检测
        $result = runWhoisCheck($conn);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        
    } else {
        echo json_encode(["success" => false, "error" => "未知操作: " . $action], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => "执行错误: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

/**
 * V3.7: 内置节点同步更新
 * 每次定时任务执行时自动更新内置节点的检测状态和多点同步统计
 */
function updateBuiltinNodeSync($conn) {
    // 更新内置节点心跳
    $stmt = $conn->prepare("UPDATE nodes SET last_heartbeat = NOW(), status = 'online' WHERE id = 1");
    $stmt->execute();
    
    // 更新node_check_times和多点同步状态
    $interval = 5;
    $currentPeriod = floor(time() / ($interval * 60)) * ($interval * 60);
    
    $stmt = $conn->query("
        SELECT id FROM websites 
        WHERE enabled = 1 
        AND (FIND_IN_SET('1', node_ids) > 0 OR FIND_IN_SET('0', node_ids) > 0)
    ");
    $sites = $stmt->fetchAll();
    
    foreach ($sites as $site) {
        // 更新检测时间记录
        $conn->prepare("
            INSERT INTO node_check_times (node_id, website_id, last_check_time, check_period)
            VALUES (1, ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE last_check_time = VALUES(last_check_time), check_period = VALUES(check_period)
        ")->execute([$site['id'], $currentPeriod]);
        
        // 计算同步状态
        $stmt2 = $conn->prepare("SELECT node_ids FROM websites WHERE id = ?");
        $stmt2->execute([$site['id']]);
        $siteInfo = $stmt2->fetch();
        
        if ($siteInfo && !empty($siteInfo['node_ids'])) {
            $nodeIds = explode(',', $siteInfo['node_ids']);
            $nodeIds = array_map('intval', array_filter($nodeIds));
            // 兼容：node_id=0 视为 node_id=1
            foreach ($nodeIds as $k => $v) {
                if ($v === 0) $nodeIds[$k] = 1;
            }
            $nodeIds = array_unique($nodeIds);
            $totalNodes = count($nodeIds);
            
            $windowStart = $currentPeriod - 15;
            $windowEnd = $currentPeriod + ($interval * 60) + 15;
            
            $placeholders = implode(',', array_fill(0, count($nodeIds), '?'));
            $stmt2 = $conn->prepare("
                SELECT COUNT(*) as cnt FROM node_check_times 
                WHERE website_id = ? AND node_id IN ($placeholders) 
                AND check_period >= ? AND check_period <= ?
            ");
            $params = array_merge([$site['id']], $nodeIds, [$windowStart, $windowEnd]);
            $stmt2->execute($params);
            $checkedCount = $stmt2->fetch()['cnt'];
            
            $conn->prepare("UPDATE websites SET multi_sync_count = ?, multi_sync_total = ?, last_multi_check_time = NOW() WHERE id = ?")
                ->execute([$checkedCount, $totalNodes, $site['id']]);
        }
    }
}

/**
 * V4.0: 判断是否需要执行WHOIS检测，需要则执行
 * @return array WHOIS检测结果
 */
function runWhoisCheckIfNeeded($conn, $force = false) {
    // 获取设置
    $settings = loadWhoisSettings($conn);
    
    // 检查是否启用WHOIS监控
    if (($settings['enable_whois_alerts'] ?? '0') !== '1') {
        return ['checked' => 0, 'skipped' => true, 'reason' => 'WHOIS监控未启用'];
    }
    
    // V4.1: force=true 时跳过间隔检查
    if (!$force) {
        $intervalHours = intval($settings['whois_check_interval_hours'] ?? 24);
        if ($intervalHours < 12) $intervalHours = 12;
        
        // 全局判断：最后一次WHOIS检测是否在间隔期内
        $stmt = $conn->query("SELECT MIN(last_whois_check) as oldest, MAX(last_whois_check) as latest FROM websites WHERE enabled = 1");
        $lastCheckInfo = $stmt->fetch();
        
        // 如果最近一次检测在间隔期内，跳过
        if ($lastCheckInfo['latest']) {
            $lastCheckTime = strtotime($lastCheckInfo['latest']);
            $nextCheckTime = $lastCheckTime + ($intervalHours * 3600);
            
            if (time() < $nextCheckTime) {
                $waitMinutes = round(($nextCheckTime - time()) / 60);
                return [
                    'checked' => 0, 
                    'skipped' => true, 
                    'reason' => 'WHOIS检测间隔未到，还需等待约' . $waitMinutes . '分钟',
                    'next_check' => date('Y-m-d H:i:s', $nextCheckTime)
                ];
            }
        }
    }
    
    // 需要执行检测
    return runWhoisCheck($conn, $force);
}

/**
 * V4.0: 执行WHOIS检测
 * @return array 检测结果
 */
function runWhoisCheck($conn, $force = false) {
    $start = microtime(true);

    // 获取设置
    $settings = loadWhoisSettings($conn);
    $intervalHours = intval($settings['whois_check_interval_hours'] ?? 24);

    // 获取需要检测的网站
    $websites = WhoisMonitorService::getWebsitesToCheck($conn, $intervalHours, $force);
    
    if (empty($websites)) {
        return [
            'success' => true,
            'checked' => 0,
            'message' => '暂无需要检测的域名',
            'time' => round((microtime(true) - $start) * 1000, 2) . 'ms'
        ];
    }
    
    // 执行检测
    $results = WhoisMonitorService::checkBatch($conn, $websites);
    
    // 检查告警
    $alerts = WhoisMonitorService::checkAlerts($conn, $results, $force);
    
    // 统计
    $successCount = count(array_filter($results, fn($r) => $r['status'] === 'success'));
    $alertCount = count($alerts);
    
    // 发送告警通知
    if ($alertCount > 0) {
        sendWhoisAlerts($conn, $alerts);
    }
    
    return [
        'success' => true,
        'checked' => count($websites),
        'success_count' => $successCount,
        'failed' => count($websites) - $successCount,
        'alerts' => $alertCount,
        'time' => round((microtime(true) - $start) * 1000, 2) . 'ms'
    ];
}

/**
 * V4.0: 加载WHOIS设置
 */
function loadWhoisSettings($conn) {
    // 查询所有WHOIS相关设置，包括 enable_whois_alerts
    $stmt = $conn->query("
        SELECT setting_name, setting_value 
        FROM alert_settings 
        WHERE setting_name LIKE 'whois_%' 
           OR setting_name = 'enable_whois_alerts'
    ");
    
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_name']] = $row['setting_value'];
    }
    
    return $settings;
}

/**
 * V4.0: 发送WHOIS告警通知
 */
function sendWhoisAlerts($conn, $alerts) {
    try {
        require_once __DIR__ . '/notifications/NotificationManager.php';
        $manager = new NotificationManager($conn);
        
        $time = date('Y-m-d H:i:s');
        
        $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body>";
        $html .= "<h1>🔔 域名到期告警</h1><p>时间: {$time}</p>";
        
        $tgMessage = "🔔 <b>域名到期告警</b>\n📅 时间: {$time}\n\n";
        
        $html .= "<h2>⚠️ 以下域名需要关注</h2><table border='1' cellpadding='8'><tr><th>域名</th><th>状态</th><th>到期日期</th></tr>";
        
        foreach ($alerts as $alert) {
            $days = $alert['days'];
            if ($days <= 0) {
                $status = "❌ 已过期 " . abs($days) . " 天";
                $tgStatus = "❌ <b>已过期 " . abs($days) . " 天</b>";
            } else {
                $status = "⚠️ 剩余 " . $days . " 天";
                $tgStatus = "⚠️ 剩余 " . $days . " 天";
            }
            $html .= "<tr><td>{$alert['domain']}</td><td>{$status}</td><td>{$alert['expire_date']}</td></tr>";
            $tgMessage .= "{$alert['domain']}\n   {$tgStatus} (到期: {$alert['expire_date']})\n\n";
        }
        
        $html .= "</table></body></html>";
        
        $manager->sendAll([
            'subject' => "域名到期告警 - $time",
            'message' => "以下域名需要关注:\n" . implode("\n", array_map(function($a) {
                $d = $a['days'];
                return $d <= 0 ? "- {$a['domain']}: ❌ 已过期 " . abs($d) . " 天" : "- {$a['domain']}: ⚠️ 剩余 {$d} 天";
            }, $alerts)),
            'html' => $html,
            'tg_message' => $tgMessage,
            'type' => '域名监控',
            'time' => $time
        ]);
        
    } catch (Exception $e) {
        error_log("WHOIS告警发送失败: " . $e->getMessage());
    }
}
