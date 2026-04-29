<?php
require_once __DIR__ . '/NotificationInterface.php';
require_once __DIR__ . '/EmailNotification.php';
require_once __DIR__ . '/TelegramNotification.php';

/**
 * 通知管理器 - 管理所有通知渠道
 */
class NotificationManager {
    private $channels = [];
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->loadChannels();
    }
    
    /**
     * 加载所有启用的通知渠道
     */
    private function loadChannels() {
        // 从数据库加载启用的渠道
        $stmt = $this->conn->query("SELECT * FROM notification_channels WHERE enabled = 1 ORDER BY priority");
        
        while ($row = $stmt->fetch()) {
            $channel = $this->createChannel($row['channel_type']);
            if ($channel && $channel->isEnabled()) {
                $this->channels[] = $channel;
            }
        }
    }
    
    /**
     * 创建通知渠道实例
     */
    private function createChannel(string $type): ?NotificationInterface {
        switch ($type) {
            case 'email':
                return new EmailNotification($this->conn);
            case 'telegram':
                return new TelegramNotification($this->conn);
            // 以后可以轻松添加其他渠道
            // case 'sms':
            //     return new SmsNotification($this->conn);
            // case 'dingtalk':
            //     return new DingTalkNotification($this->conn);
            default:
                return null;
        }
    }
    
    /**
     * 发送通知到所有启用的渠道
     */
    public function sendAll(array $data): array {
        $results = [];
        
        foreach ($this->channels as $channel) {
            $name = $channel->getName();
            try {
                $success = $channel->send($data);
                $results[$name] = [
                    'success' => $success,
                    'message' => $success ? '发送成功' : '发送失败'
                ];
            } catch (Exception $e) {
                $results[$name] = [
                    'success' => false,
                    'message' => '异常: ' . $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * 测试指定渠道
     */
    public function testChannel(string $type): array {
        $channel = $this->createChannel($type);
        if (!$channel) {
            return ['success' => false, 'message' => '未知的通知渠道'];
        }
        return $channel->test();
    }
    
    /**
     * 获取所有启用的渠道名称
     */
    public function getEnabledChannels(): array {
        return array_map(function($ch) { return $ch->getName(); }, $this->channels);
    }
}
