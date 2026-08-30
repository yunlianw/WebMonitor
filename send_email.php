<?php
/**
 * 使用PHPMailer发送邮件 - MySQL版本
 */

// 加载配置和数据库类
require_once __DIR__ . '/config/Config.php';
require_once __DIR__ . '/Database.php';

// 检查是否使用MySQL
$config = Config::getInstance();
if ($config->getStorageType() !== Config::STORAGE_MYSQL) {
    echo json_encode(['success' => false, 'message' => '系统未使用MySQL存储']);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // 获取邮件配置
    $stmt = $conn->prepare("SELECT * FROM email_config ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $emailConfig = $stmt->fetch();
    
    // 检查是否启用邮件
    if (!$emailConfig || !$emailConfig['enabled']) {
        echo json_encode(['success' => false, 'message' => '邮件功能未启用或未配置']);
        exit;
    }
    
    // 获取参数
    $subject = $_POST['subject'] ?? '网站监控提醒';
    $message = $_POST['message'] ?? '';
    $toEmailsParam = $_POST['to_emails'] ?? '';
    
    // 确定收件人
    if (!empty($toEmailsParam)) {
        // 使用参数中的收件人
        $toEmails = is_string($toEmailsParam) ? array_filter(explode(',', $toEmailsParam)) : $toEmailsParam;
    } else {
        // 使用配置中的收件人
        $toEmails = json_decode($emailConfig['to_emails'], true) ?: [];
    }
    
    if (empty($toEmails)) {
        echo json_encode(['success' => false, 'message' => '收件人邮箱为空']);
        exit;
    }
    
    // 发送邮件
    $result = sendEmailWithPHPMailer($emailConfig, $subject, $message, $toEmails);
    
    // 记录邮件日志
    if ($result['success']) {
        $stmt = $conn->prepare("
            INSERT INTO email_logs (alert_type, subject, recipients, status)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            'test',
            $subject,
            json_encode($toEmails),
            'success'
        ]);
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '邮件发送失败: ' . $e->getMessage()]);
}

/**
 * 使用PHPMailer发送邮件
 */
function sendEmailWithPHPMailer($emailConfig, $subject, $message, $toEmails) {
    // 加载PHPMailer
    require_once __DIR__ . '/phpmailer/src/Exception.php';
    require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
    require_once __DIR__ . '/phpmailer/src/SMTP.php';
    
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        
        // SMTP配置
        $mail->isSMTP();
        $mail->Host = $emailConfig['smtp_host'];
        $mail->Port = $emailConfig['smtp_port'];
        $mail->SMTPSecure = $emailConfig['smtp_secure'];
        $mail->SMTPAuth = true;
        $mail->Username = $emailConfig['smtp_username'];
        $mail->Password = $emailConfig['smtp_password'];
        
        // 发件人
        $mail->setFrom($emailConfig['from_email'], $emailConfig['from_name']);
        
        // 收件人
        foreach ($toEmails as $email) {
            $mail->addAddress(trim($email));
        }
        
        // 邮件内容
        $mail->isHTML(true);
        $mail->Subject = $subject;
        
        $htmlMessage = '<!DOCTYPE html>
        <html lang="zh-CN">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</title>
        </head>
        <body>
            <div style="max-width: 600px; margin: 0 auto; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                <h2 style="color: #1a73e8;">' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</h2>
                <div style="margin: 20px 0; padding: 15px; background: white; border-radius: 4px; border-left: 4px solid #1a73e8;">
                    ' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '
                </div>
                <p><strong>发送时间:</strong> ' . date('Y-m-d H:i:s') . '</p>
                <p><strong>发件人:</strong> ' . htmlspecialchars($emailConfig['from_name'], ENT_QUOTES, 'UTF-8') . '</p>
            </div>
        </body>
        </html>';
        
        $mail->Body = $htmlMessage;
        $mail->AltBody = $subject . "\n\n" . strip_tags($message) . "\n\n发送时间: " . date('Y-m-d H:i:s');
        
        // 发送邮件
        $mail->send();
        
        return [
            'success' => true,
            'message' => '邮件发送成功',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => '邮件发送失败: ' . $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}
?>