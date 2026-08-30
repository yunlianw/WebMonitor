<?php
/**
 * SMTP邮件发送类
 * 支持SSL/TLS加密，支持163、QQ等邮箱
 */

class SMTPMailer {
    private $socket = null;
    private $host;
    private $port;
    private $username;
    private $password;
    private $secure; // ssl, tls, none
    private $timeout = 15;
    private $debug = false;
    private $errorLog = '';
    
    public function __construct($config = []) {
        $this->host = $config['host'] ?? '';
        $this->port = $config['port'] ?? 465;
        $this->username = $config['username'] ?? '';
        $this->password = $config['password'] ?? '';
        $this->secure = $config['secure'] ?? 'ssl';
        $this->timeout = $config['timeout'] ?? 15;
        $this->debug = $config['debug'] ?? false;
    }
    
    /**
     * 发送邮件
     */
    public function send($from, $fromName, $to, $subject, $body, $isHtml = false) {
        try {
            // 验证参数
            if (empty($this->host) || empty($this->username) || empty($this->password)) {
                throw new Exception('SMTP配置不完整');
            }
            
            // 连接服务器
            $this->connect();
            
            // EHLO/HELO
            $this->command("EHLO " . $this->host, 250);
            
            // 加密连接
            if ($this->secure === 'tls') {
                $this->command("STARTTLS", 220);
                if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new Exception('TLS加密失败');
                }
                $this->command("EHLO " . $this->host, 250);
            }
            
            // 登录验证
            $this->command("AUTH LOGIN", 334);
            $this->command(base64_encode($this->username), 334);
            $this->command(base64_encode($this->password), 235);
            
            // 设置发件人
            $this->command("MAIL FROM: <{$this->username}>", 250);
            
            // 设置收件人
            if (is_array($to)) {
                foreach ($to as $recipient) {
                    $this->command("RCPT TO: <{$recipient}>", 250);
                }
            } else {
                $this->command("RCPT TO: <{$to}>", 250);
            }
            
            // 发送邮件内容
            $this->command("DATA", 354);
            
            // 构建邮件头
            $headers = $this->buildHeaders($from, $fromName, $to, $subject, $isHtml);
            $message = $headers . "\r\n" . $body;
            
            // 发送邮件内容
            $this->sendData($message);
            $this->command(".", 250);
            
            // 退出
            $this->command("QUIT", 221);
            
            // 关闭连接
            $this->disconnect();
            
            return true;
            
        } catch (Exception $e) {
            $this->logError($e->getMessage());
            $this->disconnect();
            throw $e;
        }
    }
    
    /**
     * 连接SMTP服务器
     */
    private function connect() {
        $host = $this->secure === 'ssl' ? "ssl://{$this->host}" : $this->host;
        
        $this->socket = @fsockopen($host, $this->port, $errno, $errstr, $this->timeout);
        
        if (!$this->socket) {
            throw new Exception("无法连接到SMTP服务器: {$errstr} ({$errno})");
        }
        
        stream_set_timeout($this->socket, $this->timeout);
        
        // 读取欢迎信息
        $response = $this->getResponse();
        if (substr($response, 0, 3) != '220') {
            throw new Exception("SMTP服务器响应异常: {$response}");
        }
    }
    
    /**
     * 发送命令并检查响应
     */
    private function command($command, $expectedCode) {
        fwrite($this->socket, $command . "\r\n");
        
        if ($this->debug) {
            echo "> {$command}\n";
        }
        
        $response = $this->getResponse();
        
        if ($this->debug) {
            echo "< {$response}\n";
        }
        
        $code = substr($response, 0, 3);
        if ($code != $expectedCode) {
            throw new Exception("SMTP命令失败: {$command} - 响应: {$response}");
        }
        
        return $response;
    }
    
    /**
     * 获取服务器响应
     */
    private function getResponse() {
        $response = '';
        while ($line = fgets($this->socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') {
                break;
            }
        }
        return trim($response);
    }
    
    /**
     * 发送邮件数据
     */
    private function sendData($data) {
        $lines = explode("\n", str_replace("\r", "", $data));
        
        foreach ($lines as $line) {
            // 防止以点开头的行被误解为结束符
            if (strpos($line, '.') === 0) {
                $line = '.' . $line;
            }
            fwrite($this->socket, $line . "\r\n");
        }
    }
    
    /**
     * 构建邮件头
     */
    private function buildHeaders($from, $fromName, $to, $subject, $isHtml) {
        $headers = [];
        
        // 基本头信息
        $headers[] = "Date: " . date('r');
        $headers[] = "From: " . $this->encodeHeader($fromName) . " <{$from}>";
        
        if (is_array($to)) {
            $headers[] = "To: " . implode(', ', array_map(function($email) {
                return "<{$email}>";
            }, $to));
        } else {
            $headers[] = "To: <{$to}>";
        }
        
        $headers[] = "Subject: " . $this->encodeHeader($subject);
        $headers[] = "Message-ID: <" . md5(uniqid()) . "@" . $this->host . ">";
        $headers[] = "X-Mailer: WebsiteMonitor/2.0";
        $headers[] = "MIME-Version: 1.0";
        
        if ($isHtml) {
            $boundary = md5(uniqid());
            $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
        } else {
            $headers[] = "Content-Type: text/plain; charset=\"UTF-8\"";
            $headers[] = "Content-Transfer-Encoding: 8bit";
        }
        
        return implode("\r\n", $headers);
    }
    
    /**
     * 编码邮件头（处理中文等特殊字符）
     */
    private function encodeHeader($str) {
        if (preg_match('/[^\x20-\x7E]/', $str)) {
            return '=?UTF-8?B?' . base64_encode($str) . '?=';
        }
        return $str;
    }
    
    /**
     * 断开连接
     */
    private function disconnect() {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }
    
    /**
     * 记录错误日志
     */
    private function logError($message) {
        $logFile = DATA_DIR . '/smtp_error.log';
        $logEntry = date('Y-m-d H:i:s') . " - " . $message . "\n";
        
        if ($this->debug) {
            echo $logEntry;
        }
        
        // 记录到文件
        @file_put_contents($logFile, $logEntry, FILE_APPEND);
        $this->errorLog .= $logEntry;
    }
    
    /**
     * 获取错误日志
     */
    public function getErrorLog() {
        return $this->errorLog;
    }
    
    /**
     * 测试SMTP连接
     */
    public function testConnection() {
        try {
            $this->connect();
            $this->command("EHLO " . $this->host, 250);
            $this->disconnect();
            return ['success' => true, 'message' => 'SMTP连接测试成功'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'SMTP连接测试失败: ' . $e->getMessage()];
        }
    }
    
    /**
     * 测试邮件发送
     */
    public function testEmail($to) {
        try {
            $subject = '网站监控系统 - SMTP测试邮件';
            $body = "这是一封测试邮件，用于验证SMTP配置是否正确。\n\n";
            $body .= "发送时间: " . date('Y-m-d H:i:s') . "\n";
            $body .= "发件人: " . $this->username . "\n";
            $body .= "收件人: " . $to . "\n\n";
            $body .= "如果收到此邮件，说明SMTP配置正确。\n";
            
            $result = $this->send(
                $this->username,
                '网站监控系统',
                $to,
                $subject,
                $body
            );
            
            return ['success' => true, 'message' => '测试邮件发送成功！请检查收件箱。'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => '测试邮件发送失败: ' . $e->getMessage()];
        }
    }
}