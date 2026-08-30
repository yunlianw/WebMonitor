<?php
/**
 * WHOIS域名到期检测 - 低频定时任务API
 * 
 * 调用方式：
 * - 手动：curl "https://api.5276.net/whois_check.php?key=YOUR_KEY"
 * - 定时：每小时执行一次（crontab）
 * 
 * 频率控制：
 * - 每个域名每24小时最多检测一次
 * - 避免频繁请求WHOIS接口被封
 */

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/config/Config.php";
require_once __DIR__ . "/Database.php";
require_once __DIR__ . "/lib/WhoisMonitorService.php";

// 验证密钥
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->query("SELECT monitor_key FROM system_settings ORDER BY id DESC LIMIT 1");
    $settings = $stmt->fetch();
    $validKey = $settings['monitor_key'] ?? '';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => '数据库错误'], JSON_UNESCAPED_UNICODE);
    exit;
}

$inputKey = $_GET['key'] ?? '';
if (empty($inputKey) || $inputKey !== $validKey) {
    echo json_encode(['success' => false, 'error' => '密钥无效'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 获取检测间隔（默认24小时）
$intervalHours = intval($_GET['interval'] ?? 24);
if ($intervalHours < 12) $intervalHours = 12; // 最小12小时

$startTime = microtime(true);

// 1. 获取需要检测的网站
$websites = WhoisMonitorService::getWebsitesToCheck($conn, $intervalHours);

if (empty($websites)) {
    echo json_encode([
        'success' => true,
        'message' => '暂无需要检测的域名',
        'checked' => 0,
        'time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. 执行WHOIS检测
$results = WhoisMonitorService::checkBatch($conn, $websites);

// 3. 检查告警
$alerts = WhoisMonitorService::checkAlerts($conn, $results);

// 4. 发送告警通知（如果配置了）
if (!empty($alerts)) {
    sendWhoisAlerts($conn, $alerts);
}

// 统计信息
$successCount = count(array_filter($results, fn($r) => $r['status'] === 'success'));
$alertCount = count($alerts);

$response = [
    'success' => true,
    'message' => "WHOIS检测完成",
    'checked' => count($websites),
    'success' => $successCount,
    'failed' => count($websites) - $successCount,
    'alerts' => $alertCount,
    'details' => $results,
    'alert_list' => $alerts,
    'time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms',
    'timestamp' => date('Y-m-d H:i:s')
];

echo json_encode($response, JSON_UNESCAPED_UNICODE);

/**
 * 发送WHOIS告警通知
 */
function sendWhoisAlerts($conn, $alerts) {
    try {
        require_once __DIR__ . '/notifications/NotificationManager.php';
        $manager = new NotificationManager($conn);
        
        $time = date('Y-m-d H:i:s');
        
        $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body>";
        $html .= "<h1>🔔 域名到期告警</h1><p>时间: {$time}</p>";
        
        $tgMessage = "🔔 <b>域名到期告警</b>\n📅 时间: {$time}\n\n";
        
        $html .= "<h2>⚠️ 以下域名即将到期</h2><table border='1' cellpadding='8'><tr><th>域名</th><th>剩余天数</th><th>到期日期</th></tr>";
        
        foreach ($alerts as $alert) {
            $html .= "<tr><td>{$alert['domain']}</td><td>{$alert['days']}天</td><td>{$alert['expire_date']}</td></tr>";
            $tgMessage .= "⚠️ {$alert['domain']}\n   剩余 {$alert['days']} 天 (到期: {$alert['expire_date']})\n\n";
        }
        
        $html .= "</table></body></html>";
        
        $manager->sendAll([
            'subject' => "域名到期告警 - $time",
            'message' => "以下域名即将到期:\n" . implode("\n", array_map(fn($a) => "- {$a['domain']}: 剩余{$a['days']}天", $alerts)),
            'html' => $html,
            'tg_message' => $tgMessage,
            'type' => '域名监控',
            'time' => $time
        ]);
        
    } catch (Exception $e) {
        error_log("WHOIS告警发送失败: " . $e->getMessage());
    }
}
