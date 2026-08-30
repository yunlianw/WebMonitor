<?php
require_once __DIR__ . '/NotificationInterface.php';

class EmailNotification implements NotificationInterface {
    private $config;
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->loadConfig();
    }
    
    private function loadConfig() {
        $stmt = $this->conn->query("SELECT * FROM email_config WHERE enabled = 1 LIMIT 1");
        $this->config = $stmt->fetch();
    }
    
    public function send(array $data): bool {
        if (!$this->config) return false;
        
        require_once __DIR__ . '/../phpmailer/src/PHPMailer.php';
        require_once __DIR__ . '/../phpmailer/src/SMTP.php';
        require_once __DIR__ . '/../phpmailer/src/Exception.php';
        
        $success = false;
        $errorMessage = '';
        
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $this->config['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['smtp_username'];
            $mail->Password = $this->config['smtp_password'];
            $mail->SMTPSecure = $this->config['smtp_secure'] === 'ssl' ? 
                PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : 
                PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->config['smtp_port'];
            $mail->CharSet = 'UTF-8';
            $mail->setFrom($this->config['from_email'], $this->config['from_name']);
            
            foreach (json_decode($this->config['to_emails']) ?: [$this->config['to_emails']] as $email) {
                $mail->addAddress($email);
            }
            
            $mail->isHTML(true);
            $mail->Subject = $data['subject'] ?? '网站监控告警';
            $mail->Body = $data['html'] ?? $data['message'];
            $mail->AltBody = strip_tags($data['message']);
            
            $success = $mail->send();
            
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            $success = false;
        }
        
        // 记录邮件日志
        $this->logEmail($data, $success, $errorMessage);
        
        return $success;
    }
    
    /**
     * 记录邮件日志到数据库
     */
    private function logEmail(array $data, bool $success, string $errorMessage = '') {
        try {
            // 获取网站ID（如果有）- 支持单个或多个
            $websiteId = $data['website_id'] ?? null;
            if (!$websiteId && !empty($data['website_ids'])) {
                $websiteIds = is_array($data['website_ids']) ? $data['website_ids'] : [$data['website_ids']];
                $websiteId = implode(',', $websiteIds);
            }
            
            // 确定告警类型
            $alertType = $this->getAlertType($data['type'] ?? '');
            
            // 获取收件人
            $recipients = $this->config['to_emails'] ?? '[]';
            
            // 插入日志记录
            $stmt = $this->conn->prepare("
                INSERT INTO email_logs 
                (website_id, alert_type, subject, recipients, status, error_message, sent_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $websiteId,
                $alertType,
                $data['subject'] ?? '网站监控告警',
                $recipients,
                $success ? 'success' : 'failed',
                $errorMessage
            ]);
            
        } catch (Exception $e) {
            // 记录日志失败不影响邮件发送结果
            error_log("邮件日志记录失败: " . $e->getMessage());
        }
    }
    
    /**
     * 根据类型确定告警类型
     */
    private function getAlertType(string $type): string {
        $typeMap = [
            'http_down' => 'http_down',
            'http_recovery' => 'http_down',
            'ssl_warning' => 'ssl_warning',
            'ssl_expired' => 'ssl_expired',
            'node_offline' => 'other',
            'node_recovered' => 'other',
            '节点告警' => 'other',
            '节点恢复' => 'other'
        ];
        
        return $typeMap[$type] ?? 'other';
    }
    
    public function test(): array {
        $result = $this->send([
            'subject' => '测试邮件',
            'message' => '测试邮件内容',
            'html' => '<h1>测试邮件</h1><p>时间: ' . date('Y-m-d H:i:s') . '</p>'
        ]);
        return ['success' => $result, 'message' => $result ? '发送成功' : '发送失败'];
    }
    
    public function getName(): string { return '邮件通知'; }
    public function isEnabled(): bool { return !empty($this->config) && $this->config['enabled'] == 1; }
}