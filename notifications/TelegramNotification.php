<?php
require_once __DIR__ . '/NotificationInterface.php';

/**
 * Telegram通知实现
 */
class TelegramNotification implements NotificationInterface {
    private $config;
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->loadConfig();
    }
    
    private function loadConfig() {
        $stmt = $this->conn->query("SELECT * FROM telegram_config WHERE enabled = 1 LIMIT 1");
        $this->config = $stmt->fetch();
    }
    
    public function send(array $data): bool {
        if (!$this->config) return false;
        
        // 构建消息
        $message = $this->buildMessage($data);
        
        // 发送请求
        return $this->sendToTelegram($message);
    }
    
    private function buildMessage(array $data): string {
        // 优先使用专门为TG构建的消息（已有换行和格式）
        if (!empty($data['tg_message'])) {
            return $data['tg_message'];
        }
        
        // 否则使用模板
        $template = $this->config['message_template'] ?? '🚨 {type}: {name} - {message}';
        
        $replacements = [
            '{type}' => $data['type'] ?? '告警',
            '{name}' => $data['name'] ?? '',
            '{url}' => $data['url'] ?? '',
            '{message}' => $data['message'] ?? '',
            '{time}' => $data['time'] ?? date('Y-m-d H:i:s'),
            '{code}' => $data['code'] ?? '',
            '{days}' => $data['days'] ?? ''
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
    
    private function sendToTelegram(string $message): bool {
        $botToken = $this->config['bot_token'];
        $chatId = $this->config['chat_id'];
        
        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        
        $params = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => $this->config['parse_mode'] ?? 'HTML'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,  // 10秒超时
            CURLOPT_CONNECTTIMEOUT => 5,  // 5秒连接超时
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 200;
    }
    
    public function test(): array {
        if (!$this->config) {
            return ['success' => false, 'message' => 'Telegram配置不存在'];
        }
        
        $testMessage = "🧪 <b>测试消息</b>\n\n这是一条测试消息，发送时间: " . date('Y-m-d H:i:s');
        
        $botToken = $this->config['bot_token'];
        $chatId = $this->config['chat_id'];
        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        
        $params = [
            'chat_id' => $chatId,
            'text' => $testMessage,
            'parse_mode' => 'HTML'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        return [
            'success' => $httpCode === 200 && ($result['ok'] ?? false),
            'message' => $httpCode === 200 ? '发送成功' : "发送失败 (HTTP {$httpCode})",
            'http_code' => $httpCode,
            'response' => $result,
            'error' => $error
        ];
    }
    
    public function getName(): string { return 'Telegram通知'; }
    public function isEnabled(): bool { return !empty($this->config) && $this->config['enabled'] == 1; }
}
