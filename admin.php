<?php
/**
 * 网站监控系统 - 完整管理后台 (MySQL版本)
 * 包含：邮件配置、批量添加、批量删除、系统设置等所有功能
 */

session_start();

// 简单认证
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 加载配置和数据库类
require_once __DIR__ . '/config/Config.php';
require_once __DIR__ . '/Database.php';

/**
 * SSL证书检测函数 - 精准版（不跟随301/302跳转）
 * 从api.php复制，确保手动检测时使用相同逻辑
 */
function checkSSL($host, $timeout) {
    $result = [
        'status' => 'unknown',
        'days' => null,
        'message' => '检查失败'
    ];

    // 确保host不包含协议和端口
    $host = preg_replace('/^https?:\/\//', '', $host);
    $host = preg_replace('/:\d+$/', '', $host);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://{$host}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,  // 关键：禁用跟随跳转
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_CERTINFO => true,
        CURLOPT_USERAGENT => 'WebsiteMonitor/2.0',
        CURLOPT_NOBODY => true
    ]);

    curl_exec($ch);
    $error = curl_error($ch);

    if ($error) {
        $result['message'] = 'SSL连接失败: ' . $error;
        curl_close($ch);
        return $result;
    }

    $certInfo = curl_getinfo($ch, CURLINFO_CERTINFO);
    curl_close($ch);

    if (!empty($certInfo) && isset($certInfo[0]['Cert'])) {
        $certPem = $certInfo[0]['Cert'];
        $cert = openssl_x509_parse($certPem);

        if ($cert) {
            $validTo = $cert['validTo_time_t'];
            $daysLeft = floor(($validTo - time()) / 86400);

            if ($daysLeft < 0) {
                $result = ['status' => 'expired', 'days' => $daysLeft, 'message' => "SSL证书已过期 " . abs($daysLeft) . " 天"];
            } elseif ($daysLeft <= 7) {
                $result = ['status' => 'warning', 'days' => $daysLeft, 'message' => "SSL证书剩余 {$daysLeft} 天"];
            } else {
                $result = ['status' => 'valid', 'days' => $daysLeft, 'message' => "SSL证书有效，剩余 {$daysLeft} 天"];
            }
        }
    } else {
        $result['message'] = '无法获取SSL证书信息';
    }

    return $result;
}

// V3.3: API接口 - 获取节点详情（AJAX）
if (isset($_GET['api']) && $_GET['api'] === 'node_details') {
    header('Content-Type: application/json; charset=utf-8');
    $websiteId = intval($_GET['website_id'] ?? 0);
    
    if ($websiteId > 0) {
        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            // 获取网站信息
            $stmt = $conn->prepare("SELECT id, name, node_ids FROM websites WHERE id = ?");
            $stmt->execute([$websiteId]);
            $website = $stmt->fetch();
            
            if ($website) {
                $nodeIds = explode(',', $website['node_ids']);
                $nodeIds = array_map('intval', array_filter($nodeIds));
                
                // 获取每个节点的详细状态
                $nodeDetails = [];
                foreach ($nodeIds as $nid) {
                    // 获取节点信息
                    $nodeStmt = $conn->prepare("SELECT id, name, type, last_heartbeat, status FROM nodes WHERE id = ?");
                    $nodeStmt->execute([$nid]);
                    $node = $nodeStmt->fetch();
                    
                    // 获取该节点对该网站的检测状态
                    $checkStmt = $conn->prepare("
                        SELECT last_check_time, check_period 
                        FROM node_check_times 
                        WHERE website_id = ? AND node_id = ?
                    ");
                    $checkStmt->execute([$websiteId, $nid]);
                    $checkInfo = $checkStmt->fetch();
                    
                    // 获取最近的检测日志
                    $logStmt = $conn->prepare("
                        SELECT http_status, response_time, checked_at 
                        FROM monitor_logs 
                        WHERE website_id = ? AND node_id = ?
                        ORDER BY checked_at DESC LIMIT 1
                    ");
                    $logStmt->execute([$websiteId, $nid]);
                    $logInfo = $logStmt->fetch();
                    
                    // 判断节点状态
                    $status = 'unknown';
                    if ($node) {
                        if ($node['status'] === 'online') {
                            if ($checkInfo && $logInfo) {
                                $lastCheck = strtotime($checkInfo['last_check_time']);
                                $now = time();
                                if (($now - $lastCheck) < 600) { // 10分钟内
                                    $status = 'online';
                                } else {
                                    $status = 'timeout'; // 超过10分钟未检测
                                }
                            } else {
                                $status = 'no_data';
                            }
                        } else {
                            $status = 'offline';
                        }
                    } else {
                        $status = 'not_found';
                    }
                    
                    $nodeDetails[] = [
                        'node_id' => $nid,
                        'node_name' => $node ? $node['name'] : "节点{$nid}",
                        'status' => $status,
                        'last_check_time' => $checkInfo['last_check_time'] ?? null,
                        'http_status' => $logInfo['http_status'] ?? null,
                        'response_time' => $logInfo['response_time'] ?? null,
                        'last_heartbeat' => $node['last_heartbeat'] ?? null
                    ];
                }
                
                echo json_encode([
                    'success' => true,
                    'website' => $website,
                    'nodes' => $nodeDetails
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['success' => false, 'error' => '网站不存在']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => '无效的网站ID']);
    }
    exit;
}

// 检查是否已安装（通过检查lock文件）
if (!file_exists(__DIR__ . '/install.lock')) {
    header('Location: install.php');
    exit;
}

// 数据库连接
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
} catch (Exception $e) {
    die('数据库连接失败: ' . $e->getMessage());
}

/**
 * 智能URL处理函数
 * 功能：
 * 1. 自动添加协议（如果没有）
 * 2. 规范化URL格式
 * 3. 提取主机名
 * 
 * @param string $input 用户输入的URL或域名
 * @return array [normalized_url, host, display_name]
 */
function normalizeWebsiteUrl($input) {
    $input = trim($input);
    
    // 如果输入为空，返回空
    if (empty($input)) {
        return ['', '', ''];
    }
    
    // 移除开头的www.（可选）
    $displayName = $input;
    if (strpos($input, 'www.') === 0) {
        $displayName = substr($input, 4);
    }
    
    // 检查是否已有协议
    $hasProtocol = false;
    $normalizedUrl = $input;
    
    if (strpos($input, 'http://') === 0 || strpos($input, 'https://') === 0) {
        $hasProtocol = true;
    }
    
    // 如果没有协议，自动添加https://
    if (!$hasProtocol) {
        // 检查是否包含路径或查询参数
        if (strpos($input, '/') !== false || strpos($input, '?') !== false || strpos($input, '#') !== false) {
            // 如果包含路径，需要先解析
            $normalizedUrl = 'https://' . $input;
        } else {
            // 纯域名，直接添加https://
            $normalizedUrl = 'https://' . $input;
        }
    }
    
    // 确保URL格式正确
    $parsed = parse_url($normalizedUrl);
    if (!$parsed || !isset($parsed['host'])) {
        // 如果解析失败，尝试直接使用输入
        $host = $input;
    } else {
        $host = $parsed['host'];
        
        // 重建规范化URL（去掉末尾斜杠）
        $scheme = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : 'https://';
        $port = isset($parsed['port']) ? ':' . (int)$parsed['port'] : '';
        $normalizedUrl = $scheme . $host . $port;
        
        // 如果有路径，保留（但去掉开头的斜杠）
        if (isset($parsed['path']) && $parsed['path'] !== '/') {
            $normalizedUrl .= $parsed['path'];
        }
        
        // 如果有查询参数，保留
        if (isset($parsed['query'])) {
            $normalizedUrl .= '?' . $parsed['query'];
        }
        
        // 如果有锚点，保留
        if (isset($parsed['fragment'])) {
            $normalizedUrl .= '#' . $parsed['fragment'];
        }
    }
    
    // 提取显示名称（主机名）
    $displayName = $host;
    
    return [$normalizedUrl, $host, $displayName];
}

// 处理表单提交
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // 1. 添加单个网站
    if ($action === 'add_website') {
        $name = trim($_POST['name'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $checkHttp = isset($_POST['check_http']) ? 1 : 0;
        $checkSsl = isset($_POST['check_ssl']) ? 1 : 0;
        $checkWhois = isset($_POST['check_whois']) ? 1 : 0;
        $checkInterval = intval($_POST['check_interval'] ?? 5);
        // V2.0: 支持多点分配，node_ids是数组
        $nodeIds = $_POST['node_ids'] ?? [0];
        if (!is_array($nodeIds)) $nodeIds = [$nodeIds];
        $nodeIdsStr = implode(',', array_map('intval', $nodeIds));
        if (empty($nodeIdsStr)) $nodeIdsStr = '0';
        
        if (empty($name) || empty($url)) {
            $message = '❌ 网站名称和URL不能为空';
            $messageType = 'error';
        } else {
            // 使用智能URL处理
            list($normalizedUrl, $host, $displayName) = normalizeWebsiteUrl($url);
            
            // 如果用户没有输入名称，使用域名作为名称
            if (empty($name)) {
                $name = $displayName;
            }
            
            // 使用规范化后的URL
            $url = $normalizedUrl;
            
            try {
                // V4.2修复：重复添加（uniq_host冲突）时自动重新启用已有网站并更新检测配置，
                // 解决"重新添加网站后仪表盘不显示"问题（原逻辑直接报错，已禁用网站无法重新显示）
                $stmt = $conn->prepare("
                    INSERT INTO websites (name, url, host, check_http, check_ssl, check_whois, node_ids, enabled, check_interval) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)
                    ON DUPLICATE KEY UPDATE
                        name = VALUES(name),
                        url = VALUES(url),
                        check_http = VALUES(check_http),
                        check_ssl = VALUES(check_ssl),
                        check_whois = VALUES(check_whois),
                        node_ids = VALUES(node_ids),
                        enabled = 1,
                        check_interval = VALUES(check_interval),
                        updated_at = NOW()
                ");
                $stmt->execute([$name, $url, $host, $checkHttp, $checkSsl, $checkWhois, $nodeIdsStr, $checkInterval]);
                
                $message = '✅ 网站添加成功，分配给节点: ' . $nodeIdsStr;
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = '❌ 添加失败: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    // 2. 批量添加网站
    if ($action === 'add_batch_websites') {
        $urlsText = trim($_POST['urls'] ?? '');
        // V2.0: 支持多点分配
        $nodeIds = $_POST['node_ids'] ?? [0];
        if (!is_array($nodeIds)) $nodeIds = [$nodeIds];
        $nodeIdsStr = implode(',', array_map('intval', $nodeIds));
        if (empty($nodeIdsStr)) $nodeIdsStr = '0';
        // 批量添加的监控频率和域名到期检测
        $batchCheckInterval = intval($_POST['batch_check_interval'] ?? 5);
        $batchCheckHttp = isset($_POST['batch_check_http']) ? 1 : 0;
        $batchCheckSsl = isset($_POST['batch_check_ssl']) ? 1 : 0;
        $batchCheckWhois = isset($_POST['batch_check_whois']) ? 1 : 0;
        
        if (empty($urlsText)) {
            $message = '❌ 请输入网站URL';
            $messageType = 'error';
        } else {
            $urls = array_filter(array_map('trim', explode("\n", $urlsText)));
            $added = 0;
            $errors = [];
            
            foreach ($urls as $url) {
                if (empty($url)) continue;
                
                // 使用智能URL处理
                list($normalizedUrl, $host, $displayName) = normalizeWebsiteUrl($url);
                $name = $displayName;
                $url = $normalizedUrl;
                
                try {
                    $stmt = $conn->prepare("
                        INSERT INTO websites (name, url, host, check_http, check_ssl, check_whois, node_ids, enabled, check_interval) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)
                        ON DUPLICATE KEY UPDATE 
                            name = VALUES(name),
                            url = VALUES(url),
                            node_ids = VALUES(node_ids)
                    ");
                    $stmt->execute([$name, $url, $host, $batchCheckHttp, $batchCheckSsl, $batchCheckWhois, $nodeIdsStr, $batchCheckInterval]);
                    $added++;
                } catch (PDOException $e) {
                    $errors[] = $url . ': ' . $e->getMessage();
                }
            }
            
            if ($added > 0) {
                $message = "✅ 批量添加完成，成功添加 {$added} 个网站";
                if (!empty($errors)) {
                    $message .= '<br>❌ 部分失败: ' . implode('<br>', $errors);
                }
                $messageType = 'success';
            } else {
                $message = '❌ 添加失败，请检查URL格式';
                $messageType = 'error';
            }
        }
    }
    
    // 3. 删除单个网站
    if ($action === 'delete_website') {
        $id = intval($_POST['id'] ?? 0);
        
        if ($id) {
            try {
                $stmt = $conn->prepare("DELETE FROM websites WHERE id = ?");
                $stmt->execute([$id]);
                
                $message = '✅ 网站删除成功';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = '❌ 删除失败: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    // 4. 批量删除选中网站
    if ($action === 'delete_selected_websites') {
        $selectedIds = $_POST['selected_ids'] ?? [];
        
        if (!empty($selectedIds)) {
            $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
            
            try {
                $stmt = $conn->prepare("DELETE FROM websites WHERE id IN ($placeholders)");
                $stmt->execute($selectedIds);
                
                $message = '✅ 已删除选中的 ' . count($selectedIds) . ' 个网站';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = '❌ 删除失败: ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = '❌ 请选择要删除的网站';
            $messageType = 'error';
        }
    }
    
    // 5. 删除所有网站
    if ($action === 'delete_all_websites') {
        try {
            $stmt = $conn->prepare("DELETE FROM websites");
            $stmt->execute();
            
            $message = '✅ 已删除所有网站';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = '❌ 删除失败: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
    
    // 6. 更新网站信息
    if ($action === 'update_website') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $checkHttp = isset($_POST['check_http']) ? 1 : 0;
        $checkSsl = isset($_POST['check_ssl']) ? 1 : 0;
        $checkWhois = isset($_POST['check_whois']) ? 1 : 0;
        $enabled = isset($_POST['enabled']) ? 1 : 0;
        $checkInterval = intval($_POST['check_interval'] ?? 5);
        
        // V2.0: 支持多点分配，node_ids是数组
        $nodeIds = $_POST['node_ids'] ?? [0];
        if (!is_array($nodeIds)) $nodeIds = [$nodeIds];
        $nodeIdsStr = implode(',', array_map('intval', $nodeIds));
        if (empty($nodeIdsStr)) $nodeIdsStr = '0';
        
        if ($id <= 0) {
            $message = '❌ 网站ID无效';
            $messageType = 'error';
        } elseif (empty($name) || empty($url)) {
            $message = '❌ 网站名称和URL不能为空';
            $messageType = 'error';
        } else {
            // 使用智能URL处理
            list($normalizedUrl, $host, $displayName) = normalizeWebsiteUrl($url);
            
            try {
                $stmt = $conn->prepare("
                    UPDATE websites 
                    SET name = ?, url = ?, host = ?, check_http = ?, check_ssl = ?, check_whois = ?,
                        node_ids = ?, enabled = ?, check_interval = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $name, $normalizedUrl, $host, $checkHttp, $checkSsl, $checkWhois,
                    $nodeIdsStr, $enabled, $checkInterval, $id
                ]);
                
                $message = '✅ 网站更新成功';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = '❌ 更新失败: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    // 7. 更新邮件配置
    if ($action === 'update_email_config') {
        $enabled = isset($_POST['enabled']) ? 1 : 0;
        $smtpHost = trim($_POST['smtp_host'] ?? '');
        $smtpPort = intval($_POST['smtp_port'] ?? 465);
        $smtpSecure = trim($_POST['smtp_secure'] ?? 'ssl');
        $smtpUsername = trim($_POST['smtp_username'] ?? '');
        $smtpPassword = trim($_POST['smtp_password'] ?? '');
        $fromEmail = trim($_POST['from_email'] ?? '');
        $fromName = trim($_POST['from_name'] ?? '');
        $toEmails = trim($_POST['to_emails'] ?? '');
        
        // 验证邮箱格式
        $toEmailsArray = array_filter(array_map('trim', explode(',', $toEmails)));
        $validEmails = [];
        foreach ($toEmailsArray as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $validEmails[] = $email;
            }
        }
        
        try {
            // 删除旧配置
            $stmt = $conn->prepare("DELETE FROM email_config");
            $stmt->execute();
            
            // 插入新配置
            $stmt = $conn->prepare("
                INSERT INTO email_config (enabled, smtp_host, smtp_port, smtp_secure, smtp_username, smtp_password, from_email, from_name, to_emails)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $enabled,
                $smtpHost,
                $smtpPort,
                $smtpSecure,
                $smtpUsername,
                $smtpPassword,
                $fromEmail,
                $fromName,
                json_encode($validEmails)
            ]);
            
            $message = '✅ 邮件配置已保存';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = '❌ 保存失败: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
    
    // 7. 测试邮件发送
    if ($action === 'test_email') {
        try {
            // 获取邮件配置
            $stmt = $conn->prepare("SELECT * FROM email_config ORDER BY id DESC LIMIT 1");
            $stmt->execute();
            $emailConfig = $stmt->fetch();
            
            if (!$emailConfig || !$emailConfig['enabled']) {
                $message = '❌ 邮件功能未启用或未配置';
                $messageType = 'error';
            } else {
                // 发送测试邮件
                require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
                require_once __DIR__ . '/phpmailer/src/SMTP.php';
                require_once __DIR__ . '/phpmailer/src/Exception.php';
                
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
                $toEmails = json_decode($emailConfig['to_emails'], true) ?: [];
                foreach ($toEmails as $email) {
                    $mail->addAddress($email);
                }
                
                // 邮件内容
                $mail->isHTML(true);
                $mail->Subject = '网站监控系统测试邮件';
                
                $htmlMessage = '<!DOCTYPE html>
                <html lang="zh-CN">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>测试邮件</title>
                </head>
                <body>
                    <div style="max-width: 600px; margin: 0 auto; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                        <h2 style="color: #1a73e8;">✅ 网站监控系统测试邮件</h2>
                        <p>这是一封测试邮件，用于验证邮件配置是否正确。</p>
                        <p>如果收到此邮件，说明邮件配置已正确设置。</p>
                        <p><strong>发送时间:</strong> ' . date('Y-m-d H:i:s') . '</p>
                        <p><strong>发件人:</strong> ' . htmlspecialchars($emailConfig['from_name'], ENT_QUOTES, 'UTF-8') . '</p>
                    </div>
                </body>
                </html>';
                
                $mail->Body = $htmlMessage;
                $mail->AltBody = "网站监控系统测试邮件\n\n这是一封测试邮件，用于验证邮件配置是否正确。\n\n发送时间: " . date('Y-m-d H:i:s');
                
                // 发送邮件
                $mail->send();
                
                // 更新测试状态
                $stmt = $conn->prepare("
                    UPDATE email_config 
                    SET last_test = NOW(), test_status = '测试成功'
                    WHERE id = ?
                ");
                $stmt->execute([$emailConfig['id']]);
                
                $message = '✅ 测试邮件发送成功';
                $messageType = 'success';
            }
        } catch (Exception $e) {
            $message = '❌ 测试邮件发送失败: ' . $e->getMessage();
            $messageType = 'error';
            
            // 更新测试状态
            if (isset($emailConfig['id'])) {
                $stmt = $conn->prepare("
                    UPDATE email_config 
                    SET last_test = NOW(), test_status = ?
                    WHERE id = ?
                ");
                $stmt->execute(['测试失败: ' . $e->getMessage(), $emailConfig['id']]);
            }
        }
    }
    
    // 8. 更新系统设置
    if ($action === 'update_settings') {
        $checkInterval = intval($_POST['check_interval'] ?? 60);
        $sslWarningDays = intval($_POST['ssl_warning_days'] ?? 7);
        $historyRetentionDays = intval($_POST['history_retention_days'] ?? 30);
        $timeoutSeconds = intval($_POST['timeout_seconds'] ?? 10);
        
        try {
            // 删除旧设置
            $stmt = $conn->prepare("DELETE FROM system_settings");
            $stmt->execute();
            
            // 插入新设置
            $stmt = $conn->prepare("
                INSERT INTO system_settings (monitor_key, check_interval, ssl_warning_days, history_retention_days, timeout_seconds)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                bin2hex(random_bytes(16)),
                $checkInterval,
                $sslWarningDays,
                $historyRetentionDays,
                $timeoutSeconds
            ]);
            
            $message = '✅ 系统设置已保存';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = '❌ 保存失败: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
    
    // 9. 重新生成监控密钥
    if ($action === 'regenerate_key') {
        try {
            $stmt = $conn->prepare("
                UPDATE system_settings 
                SET monitor_key = ?
                ORDER BY id DESC 
                LIMIT 1
            ");
            $stmt->execute([bin2hex(random_bytes(16))]);
            
            $message = '✅ 监控密钥已重新生成';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = '❌ 操作失败: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
    
    // 10. 添加节点
    if ($action === 'add_node') {
        $name = trim($_POST['name'] ?? '');
        $type = intval($_POST['type'] ?? 1);
        $url = trim($_POST['url'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $keyType = $_POST['key_type'] ?? 'global';
        
        // 根据密钥类型决定API密钥
        if ($keyType === 'global') {
            // 使用全局密钥
            $apiKey = $settings['global_key'] ?? '';
        } else {
            // 生成独立私有密钥
            $apiKey = bin2hex(random_bytes(16));
        }
        
        if (empty($name)) {
            $message = '❌ 节点名称不能为空';
            $messageType = 'error';
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO nodes (name, type, url, api_key, location, status, enabled, use_global_key) VALUES (?, ?, ?, ?, ?, 'unknown', 1, ?)");
                $stmt->execute([$name, $type, $type == 1 ? $url : null, $apiKey, $location, $keyType === 'global' ? 1 : 0]);
                $keyMsg = $keyType === 'global' ? '使用全局通信密钥' : "独立密钥: <code>$apiKey</code>";
                $message = "✅ 节点添加成功，{$keyMsg}（请保存）";
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = '❌ 添加失败: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    // 11. 删除节点
    if ($action === 'delete_node') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                // 从网站的node_ids中移除该节点ID（支持多点分配）
                // 1. 找到包含该节点的网站
                $stmt = $conn->query("SELECT id, node_ids FROM websites WHERE FIND_IN_SET('$id', node_ids) > 0");
                $sitesToUpdate = $stmt->fetchAll();
                
                foreach ($sitesToUpdate as $site) {
                    $nodeIds = array_filter(explode(',', $site['node_ids']));
                    $nodeIds = array_diff($nodeIds, [$id]);
                    $newNodeIds = empty($nodeIds) ? '0' : implode(',', $nodeIds);
                    $conn->prepare("UPDATE websites SET node_ids = ? WHERE id = ?")->execute([$newNodeIds, $site['id']]);
                }
                
                // 删除节点
                $conn->prepare("DELETE FROM nodes WHERE id = ?")->execute([$id]);
                $message = '✅ 节点已删除，网站已重新分配';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = '❌ 删除失败: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    // 12. 重新生成节点密钥
    if ($action === 'regenerate_node_key') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $newKey = bin2hex(random_bytes(16));
            try {
                $conn->prepare("UPDATE nodes SET api_key = ? WHERE id = ?")->execute([$newKey, $id]);
                $message = "✅ 新密钥: <code>$newKey</code>（请保存）";
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = '❌ 操作失败: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    // V3.6: 编辑节点
    if ($action === 'edit_node') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $apiKey = trim($_POST['api_key'] ?? '');
        
        if ($id > 0 && !empty($name)) {
            try {
                // 获取当前节点信息
                $stmt = $conn->prepare("SELECT * FROM nodes WHERE id = ?");
                $stmt->execute([$id]);
                $currentNode = $stmt->fetch();
                
                if ($currentNode) {
                    // 如果提供了新密钥则更新，否则保持原密钥
                    $newApiKey = !empty($apiKey) ? $apiKey : $currentNode['api_key'];
                    
                    // 更新节点信息
                    $conn->prepare("UPDATE nodes SET name = ?, url = ?, location = ?, api_key = ? WHERE id = ?")
                        ->execute([$name, $url, $location, $newApiKey, $id]);
                    
                    $message = '✅ 节点信息已更新';
                    $messageType = 'success';
                }
            } catch (PDOException $e) {
                $message = '❌ 更新失败: ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = '❌ 节点名称不能为空';
            $messageType = 'error';
        }
    }
    
    // 12.1 重置全局通信密钥
    if ($action === 'reset_global_key') {
        $newGlobalKey = bin2hex(random_bytes(16));
        try {
            $conn->prepare("UPDATE system_settings SET global_key = ? WHERE id = 1")->execute([$newGlobalKey]);
            $message = "✅ 全局通信密钥已重置: <code>$newGlobalKey</code>（请保存，所有探针需重新下载）";
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = '❌ 重置失败: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
    
    // 13. 立即检查所有网站
    if ($action === 'check_now') {
        try {
            $stmt = $conn->prepare("SELECT monitor_key FROM system_settings ORDER BY id DESC LIMIT 1");
            $stmt->execute();
            $settings = $stmt->fetch();
            
            if (!$settings) {
                $message = '❌ 系统设置未初始化';
                $messageType = 'error';
            } else {
                // 调用API - 使用curl避免SSL问题
                // V3.7: 添加force=1参数强制检查，忽略时间间隔
                $siteUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME']);
                $apiUrl = $siteUrl . "/api_refactored.php?action=check&key=" . urlencode($settings['monitor_key']) . "&force=1";
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $apiUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);  // V3.7: 减少超时时间到30秒
                
                $result = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                $data = json_decode($result, true);
                
                if ($data && isset($data['success']) && $data['success']) {
                    $alertCount = $data['alerts'] ?? 0;
                    $checkedCount = $data['checked'] ?? 0;
                    // V3.7: 获取真实网站数量（去重）
                    $siteStmt = $conn->query("SELECT COUNT(*) as total FROM websites WHERE enabled = 1");
                    $siteCount = $siteStmt->fetch()['total'] ?? 0;
                    $message = "✅ 监控检查完成，共检测 {$siteCount} 个网站（总检测 {$checkedCount} 次）";
                    if ($alertCount > 0) {
                        $message .= "，发现 {$alertCount} 个告警";
                    }
                    $messageType = 'success';
                } else {
                    $message = '❌ 监控检查失败: ' . ($data['error'] ?? '未知错误');
                    $messageType = 'error';
                }
            }
        } catch (Exception $e) {
            $message = '❌ 检查失败: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// 获取数据
try {
    // 获取网站列表（直接从websites表读取状态，关联节点信息）
    // V3.0：添加多点同步时间字段
    $stmt = $conn->prepare("
        SELECT 
            w.*,
            w.last_http_status as http_status,
            CASE WHEN w.last_http_status = 'up' THEN 200 WHEN w.last_http_status = 'down' THEN 0 ELSE 0 END as http_code,
            CASE 
                WHEN w.last_http_status = 'up' THEN '正常'
                WHEN w.last_http_status = 'down' THEN '异常'
                ELSE '未检查'
            END as http_message,
            CASE
                WHEN w.last_ssl_days IS NULL THEN 'unknown'
                WHEN w.last_ssl_days <= 0 THEN 'expired'
                WHEN w.last_ssl_days <= 7 THEN 'expired'
                WHEN w.last_ssl_days <= 30 THEN 'warning'
                ELSE 'normal'
            END as ssl_status,
            w.last_ssl_days as ssl_days,
            CASE
                WHEN w.last_ssl_days IS NULL THEN '未检查'
                WHEN w.last_ssl_days <= 0 THEN CONCAT('❌ 已过期', ABS(w.last_ssl_days), '天')
                WHEN w.last_ssl_days <= 7 THEN CONCAT('⚠️ 剩余', w.last_ssl_days, '天')
                WHEN w.last_ssl_days <= 30 THEN CONCAT('⏰ 剩余', w.last_ssl_days, '天')
                ELSE CONCAT('剩余', w.last_ssl_days, '天')
            END as ssl_message,
            w.whois_days as whois_days,
            w.whois_expire_date as whois_expire_date,
            CASE 
                WHEN w.whois_days IS NULL THEN '未检查'
                WHEN w.whois_days <= 7 THEN CONCAT('⚠️ 仅剩', w.whois_days, '天')
                WHEN w.whois_days <= 30 THEN CONCAT('⏰ ', w.whois_days, '天')
                ELSE CONCAT(w.whois_days, '天')
            END as whois_message,
            w.last_check_time as last_check,
            w.last_multi_check_time as multi_check,
            w.last_response_time as response_ms,
            w.multi_sync_count,
            w.multi_sync_total,
            w.node_ids,
            w.check_interval
        FROM websites w
        ORDER BY w.enabled DESC, w.id
    ");
    $stmt->execute();
    $websites = $stmt->fetchAll();
    
    // 获取邮件配置
    $stmt = $conn->prepare("SELECT * FROM email_config ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $emailConfig = $stmt->fetch();
    
    // 获取系统设置
    $stmt = $conn->prepare("SELECT * FROM system_settings ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $settings = $stmt->fetch();
    
    // 获取节点列表（用于显示节点名称）
    $stmt = $conn->query("SELECT id, name, type FROM nodes");
    $nodesMap = [];
    while ($row = $stmt->fetch()) {
        $nodesMap[$row['id']] = $row;
    }
    
    // 为每个网站添加节点名称
    foreach ($websites as &$website) {
        $nodeIds = $website['node_ids'] ?? '0';
        $nodeNames = [];
        foreach (explode(',', $nodeIds) as $nid) {
            $nid = trim($nid);
            if (isset($nodesMap[$nid])) {
                $nodeNames[] = $nodesMap[$nid]['name'];
            }
        }
        $website['node_name'] = implode(', ', $nodeNames) ?: '内置节点';
    }
    unset($website);
    
    // 统计信息
    $totalWebsites = count($websites);
    $upCount = 0;
    $downCount = 0;
    $unknownCount = 0;
    $sslWarningCount = 0;
    $sslExpiredCount = 0;
    
    foreach ($websites as $website) {
        $status = $website['http_status'] ?? 'unknown';
        $sslStatus = $website['ssl_status'] ?? 'unknown';
        
        if ($status === 'up') $upCount++;
        elseif ($status === 'down') $downCount++;
        else $unknownCount++;
        
        if ($sslStatus === 'warning') $sslWarningCount++;
        elseif ($sslStatus === 'expired' || $sslStatus === 'invalid') $sslExpiredCount++;
    }
    
} catch (PDOException $e) {
    die('数据库错误: ' . $e->getMessage());
}

// 获取当前页面
$page = $_GET['page'] ?? 'dashboard';
$monitorKey = $settings['monitor_key'] ?? '未设置';

// ===== 主题切换处理（必须在HTML输出前执行） =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['switch_theme'])) {
    $newTheme = $_POST['switch_theme'];
    if (preg_match('/^[a-zA-Z0-9_-]+$/', $newTheme) && is_dir(__DIR__ . '/themes/' . $newTheme)) {
        try {
            $stmt = $conn->prepare("UPDATE system_settings SET current_theme = ?");
            $stmt->execute([$newTheme]);
            header('Location: admin.php?page=theme&msg=switched');
            exit;
        } catch (PDOException $e) {
            $themeSwitchError = "切换失败: " . $e->getMessage();
        }
    }
}
$themeSwitchError = $themeSwitchError ?? null;

// ===== 主题系统 =====
$currentTheme = $settings['current_theme'] ?? 'apple';
$themeDir = __DIR__ . '/themes/' . $currentTheme;

// 主题安全检查：防止目录穿越
if (!preg_match('/^[a-zA-Z0-9_-]+$/', $currentTheme) || !is_dir($themeDir)) {
    $currentTheme = 'apple';
    $themeDir = __DIR__ . '/themes/apple';
}

// 加载主题配置
$themeConfig = [];
if (file_exists($themeDir . '/theme.json')) {
    $themeConfig = json_decode(file_get_contents($themeDir . '/theme.json'), true) ?: [];
}
$themeName = $themeConfig['name'] ?? $currentTheme;

// 扫描可用主题
$availableThemes = [];
$themesScanDir = __DIR__ . '/themes/';
if (is_dir($themesScanDir)) {
    foreach (scandir($themesScanDir) as $dir) {
        if ($dir === '.' || $dir === '..' || !is_dir($themesScanDir . $dir)) continue;
        $jsonFile = $themesScanDir . $dir . '/theme.json';
        if (file_exists($jsonFile)) {
            $json = json_decode(file_get_contents($jsonFile), true) ?: [];
            $availableThemes[$dir] = [
                'name' => $json['name'] ?? $dir,
                'version' => $json['version'] ?? '1.0',
                'description' => $json['description'] ?? '',
                'is_current' => ($dir === $currentTheme)
            ];
        }
    }
}

// 首页也需要加载节点列表（编辑网站功能需要）
$allNodesStmt = $conn->query("SELECT * FROM nodes ORDER BY id");
$allNodes = $allNodesStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebMonitor - 网站监控系统</title>
    <?php
    // 加载主题样式（加版本号强制刷新缓存）
    $themeCss = $themeDir . '/style.css';
    $cssVersion = file_exists($themeCss) ? filemtime($themeCss) : time();
    if (file_exists($themeCss)) {
        echo '<link rel="stylesheet" href="themes/' . htmlspecialchars($currentTheme) . '/style.css?v=' . $cssVersion . '">';
    } else {
        echo '<link rel="stylesheet" href="assets/css/admin.css?v=' . $cssVersion . '">';
    }
    ?>
</head>
<body>
    <div class="container">
        <?php
        // 加载主题头部
        $themeHeader = $themeDir . '/header.php';
        if (file_exists($themeHeader)) {
            include $themeHeader;
        } else {
            include __DIR__ . '/header.php';
        }
        ?>
        
        <?php
        $allowed = ['dashboard', 'websites', 'nodes', 'email', 'telegram', 'alert_settings', 'alert_templates', 'settings', 'monitor', 'theme'];
        if (in_array($page, $allowed)) {
            // 主题管理页面走独立逻辑
            if ($page === 'theme') {
                include __DIR__ . '/pages/theme.php';
            } else {
                // 从主题目录加载页面模板
                $pageFile = $themeDir . '/' . $page . '.php';
                $fallbackFile = __DIR__ . '/pages/' . $page . '.php';
                if (file_exists($pageFile)) {
                    include $pageFile;
                } elseif (file_exists($fallbackFile)) {
                    include $fallbackFile;
                }
            }
        } else {
            $dashFile = $themeDir . '/dashboard.php';
            $dashFallback = __DIR__ . '/pages/dashboard.php';
            if (file_exists($dashFile)) {
                include $dashFile;
            } elseif (file_exists($dashFallback)) {
                include $dashFallback;
            }
        }
        ?>
        
    </div>
</div>
</body>
</html>
