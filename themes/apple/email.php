<div class="section">
    <h2>📧 邮件配置</h2>
    
    <?php if ($emailConfig): ?>
        <div class="message info" style="margin-bottom: 20px;">
            <p style="margin: 4px 0;"><strong>当前配置：</strong></p>
            <p style="margin: 4px 0;">SMTP服务器: <?php echo htmlspecialchars($emailConfig['smtp_host']); ?></p>
            <p style="margin: 4px 0;">发件人: <?php echo htmlspecialchars($emailConfig['from_email']); ?></p>
            <p style="margin: 4px 0;">最后测试: <?php echo $emailConfig['last_test'] ? date('Y-m-d H:i:s', strtotime($emailConfig['last_test'])) : '从未测试'; ?></p>
            <p style="margin: 4px 0;">测试状态: <?php echo htmlspecialchars($emailConfig['test_status'] ?? '未测试'); ?></p>
        </div>
    <?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="action" value="update_email_config">
        
        <div class="form-group">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
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
    
    <div style="background: #F9F9FB; border-radius: 12px; padding: 16px; margin-top: 16px; border: 1px solid #F2F2F7;">
        <p style="color: #1D1D1F; font-weight: 500; margin: 0 0 8px 0;"><strong>测试说明：</strong></p>
        <p style="color: #86868B; font-size: 0.875rem; margin: 4px 0;">1. 点击"发送测试邮件"按钮</p>
        <p style="color: #86868B; font-size: 0.875rem; margin: 4px 0;">2. 系统会使用当前配置发送测试邮件</p>
        <p style="color: #86868B; font-size: 0.875rem; margin: 4px 0;">3. 如果配置正确，你会收到测试邮件</p>
        <p style="color: #86868B; font-size: 0.875rem; margin: 4px 0;">4. 测试结果会显示在上方消息区域</p>
    </div>
</div>

<!-- 邮件模板预览 -->
<div class="section">
    <h2>📋 通知模板预览</h2>
    
    <div style="background: #FFFFFF; border-radius: 16px; padding: 24px; border: 1px solid #F2F2F7;">
        <div style="display: flex; gap: 16px; margin-bottom: 20px;">
            <button type="button" class="btn btn-secondary" style="padding: 8px 16px; font-size: 0.875rem;" onclick="showTemplate('email')" id="tplBtnEmail">邮件模板</button>
            <button type="button" class="btn btn-secondary" style="padding: 8px 16px; font-size: 0.875rem;" onclick="showTemplate('tg')" id="tplBtnTg">Telegram模板</button>
        </div>
        
        <!-- 邮件模板 -->
        <div id="tplEmail" style="border: 1px solid #F2F2F7; border-radius: 12px; overflow: hidden;">
            <div style="background: #F9F9FB; padding: 12px 16px; border-bottom: 1px solid #F2F2F7; font-size: 0.8125rem; color: #86868B;">
                邮件标题格式：<code style="background: #E5E5EA; padding: 2px 6px; border-radius: 4px;">网站状态通知 [节点名] - 时间</code>
            </div>
            <div style="padding: 20px; background: #FFFFFF; font-size: 0.875rem;">
                <div style="background: #FFF9F0; border-radius: 8px; padding: 12px 16px; margin-bottom: 12px; border: 1px solid #FFE0B2;">
                    <p style="color: #FF9500; font-size: 0.8125rem; margin: 0;">💡 邮件 + Telegram 双重告警，确保消息送达</p>
                </div>
                <pre style="white-space: pre-wrap; color: #1D1D1F; line-height: 1.6; margin: 0;">网站状态通知
节点: 美国节点
时间: 2026-04-29 13:20:00

HTTP异常 (2个)
我的网站 - HTTP 444 (重试3次)
测试站点 - 连接超时 (10s)

HTTP恢复 (1个)
我的网站 - 已恢复

SSL证书提醒 (1个)
我的网站 - SSL剩余 15 天</pre>
            </div>
        </div>
        
        <!-- Telegram模板 -->
        <div id="tplTg" style="display: none; border: 1px solid #F2F2F7; border-radius: 12px; overflow: hidden;">
            <div style="background: #F9F9FB; padding: 12px 16px; border-bottom: 1px solid #F2F2F7; font-size: 0.8125rem; color: #86868B;">
                Telegram 消息格式（纯文本）
            </div>
            <div style="padding: 20px; background: #FFFFFF; font-size: 0.875rem;">
                <pre style="white-space: pre-wrap; color: #1D1D1F; line-height: 1.6; margin: 0;">网站状态通知
节点: 美国节点
时间: 2026-04-29 13:20:00

HTTP异常 (2个)
我的网站 - HTTP 444 (重试3次)
测试站点 - 连接超时 (10s)

HTTP恢复 (1个)
我的网站 - 已恢复

SSL证书提醒 (1个)
我的网站 - SSL剩余 15 天</pre>
            </div>
        </div>
    </div>
</div>