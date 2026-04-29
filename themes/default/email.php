<div class="section">
    <h2>📧 邮件配置</h2>
    
    <?php if ($emailConfig): ?>
        <div class="message info">
            <p><strong>当前配置：</strong></p>
            <p>SMTP服务器: <?php echo htmlspecialchars($emailConfig['smtp_host']); ?></p>
            <p>发件人: <?php echo htmlspecialchars($emailConfig['from_email']); ?></p>
            <p>最后测试: <?php echo $emailConfig['last_test'] ? date('Y-m-d H:i:s', strtotime($emailConfig['last_test'])) : '从未测试'; ?></p>
            <p>测试状态: <?php echo htmlspecialchars($emailConfig['test_status'] ?? '未测试'); ?></p>
        </div>
    <?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="action" value="update_email_config">
        
        <div class="form-group">
            <label style="display: flex; align-items: center; gap: 0.5rem;">
                <input type="checkbox" name="enabled" <?php echo ($emailConfig['enabled'] ?? 0) ? 'checked' : ''; ?>>
                启用邮件通知
            </label>
        </div>
        
        <div class="form-group">
            <label>SMTP服务器</label>
            <input type="text" name="smtp_host" value="<?php echo htmlspecialchars($emailConfig['smtp_host'] ?? 'smtp.163.com'); ?>" required>
        </div>
        
        <div class="form-group">
            <label>SMTP端口</label>
            <input type="number" name="smtp_port" value="<?php echo htmlspecialchars($emailConfig['smtp_port'] ?? 465); ?>" required>
        </div>
        
        <div class="form-group">
            <label>加密方式</label>
            <select name="smtp_secure" required>
                <option value="ssl" <?php echo ($emailConfig['smtp_secure'] ?? 'ssl') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                <option value="tls" <?php echo ($emailConfig['smtp_secure'] ?? 'ssl') === 'tls' ? 'selected' : ''; ?>>TLS</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>SMTP用户名</label>
            <input type="text" name="smtp_username" value="<?php echo htmlspecialchars($emailConfig['smtp_username'] ?? ''); ?>" required>
        </div>
        
        <div class="form-group">
            <label>SMTP密码</label>
            <input type="password" name="smtp_password" value="<?php echo htmlspecialchars($emailConfig['smtp_password'] ?? ''); ?>" required>
        </div>
        
        <div class="form-group">
            <label>发件邮箱</label>
            <input type="email" name="from_email" value="<?php echo htmlspecialchars($emailConfig['from_email'] ?? ''); ?>" required>
        </div>
        
        <div class="form-group">
            <label>发件人名称</label>
            <input type="text" name="from_name" value="<?php echo htmlspecialchars($emailConfig['from_name'] ?? '网站监控系统'); ?>" required>
        </div>
        
        <div class="form-group">
            <label>收件邮箱（多个用逗号分隔）</label>
            <?php
            $toEmails = '';
            if ($emailConfig && !empty($emailConfig['to_emails'])) {
                $emails = json_decode($emailConfig['to_emails'], true) ?: [];
                $toEmails = implode(', ', $emails);
            }
            ?>
            <input type="text" name="to_emails" value="<?php echo htmlspecialchars($toEmails); ?>" placeholder="email1@example.com, email2@example.com" required>
        </div>
        
        <button type="submit" class="btn">保存邮件配置</button>
    </form>
</div>

<div class="section">
    <h2>📨 测试邮件发送</h2>
    
    <form method="POST">
        <input type="hidden" name="action" value="test_email">
        <button type="submit" class="btn btn-success">发送测试邮件</button>
    </form>
    
    <div class="email-test-result">
        <p><strong>测试说明：</strong></p>
        <p>1. 点击"发送测试邮件"按钮</p>
        <p>2. 系统会使用当前配置发送测试邮件</p>
        <p>3. 如果配置正确，你会收到测试邮件</p>
        <p>4. 测试结果会显示在上方消息区域</p>
    </div>
</div>
