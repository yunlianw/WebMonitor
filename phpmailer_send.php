<?php
/**
 * 使用PHPMailer发送邮件
 */

// 加载PHPMailer
require_once __DIR__ . '/phpmailer/src/Exception.php';
require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

/**
 * 发送邮件
 * @param array $emailConfig 邮件配置
 * @param string $subject 邮件主题
 * @param string $message 邮件内容
 * @param array $toEmails 收件人邮箱数组
 * @return array 发送结果
 */
function sendEmailWithPHPMailer($emailConfig, $subject, $message, $toEmails) {
    try {
        // 创建PHPMailer实例
        $mail = new PHPMailer(true);
        
        // 服务器设置
        $mail->isSMTP();
        $mail->Host = $emailConfig['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $emailConfig['smtp_username'];
        $mail->Password = $emailConfig['smtp_password'];
        $mail->SMTPSecure = $emailConfig['smtp_secure']; // 'ssl' 或 'tls'
        $mail->Port = $emailConfig['smtp_port'];
        
        // 调试模式（可选）
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        
        // 超时设置
        $mail->Timeout = 30;
        
        // 发件人
        $mail->setFrom(
            $emailConfig['from_email'], 
            $emailConfig['from_name']
        );
        
        // 收件人
        foreach ($toEmails as $email) {
            $mail->addAddress(trim($email));
        }
        
        // 内容
        $mail->isHTML(true);
        $mail->Subject = $subject;
        
        // HTML邮件内容
        $htmlMessage = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . htmlspecialchars($subject) . '</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background: #f5f5f5; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; }
                .header { background: #1a73e8; color: white; padding: 20px; text-align: center; }
                .content { padding: 30px; }
                .footer { background: #f8f9fa; padding: 15px; text-align: center; color: #666; font-size: 0.9em; }
                .alert { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 4px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🐮 网站监控系统</h1>
                </div>
                <div class="content">
                    <h2>' . htmlspecialchars($subject) . '</h2>
                    <div class="alert">
                        ' . nl2br(htmlspecialchars($message)) . '
                    </div>
                    <p>发送时间: ' . date('Y-m-d H:i:s') . '</p>
                </div>
                <div class="footer">
                    <p>此邮件由网站监控系统自动发送</p>
                </div>
            </div>
        </body>
        </html>';
        
        $mail->Body = $htmlMessage;
        
        // 纯文本版本（可选）
        $mail->AltBody = strip_tags($message);
        
        // 发送邮件
        $mail->send();
        
        return [
            'success' => true,
            'message' => '邮件发送成功'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => '邮件发送失败: ' . $mail->ErrorInfo
        ];
    }
}

/**
 * 测试邮件发送
 */
function testEmailSending() {
    // 测试配置
    $testConfig = [
        'smtp_host' => 'smtp.163.com',
        'smtp_port' => 465,
        'smtp_secure' => 'ssl',
        'smtp_username' => 'asmrsm@163.com',
        'smtp_password' => 'OGPHSJPEKBTUJVSH',
        'from_email' => 'asmrsm@163.com',
        'from_name' => '网站监控系统'
    ];
    
    $result = sendEmailWithPHPMailer(
        $testConfig,
        'PHPMailer测试邮件',
        '这是一封使用PHPMailer发送的测试邮件。\n\n如果收到此邮件，说明PHPMailer配置正确。',
        ['iyunlian@qq.com']
    );
    
    return $result;
}

// 如果直接运行此文件，进行测试
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    header('Content-Type: application/json');
    $result = testEmailSending();
    echo json_encode($result, JSON_PRETTY_PRINT);
}