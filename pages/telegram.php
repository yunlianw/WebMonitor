<div class="section">
    <h2>📱 Telegram设置</h2>
    
    <?php
    // 处理表单提交
    $tgMessage = '';
    $tgMessageType = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['telegram_action'])) {
        if ($_POST['telegram_action'] === 'save') {
            try {
                $stmt = $conn->prepare("UPDATE telegram_config SET bot_token = ?, chat_id = ?, enabled = ?, parse_mode = ? WHERE id = 1");
                $stmt->execute([
                    $_POST['bot_token'],
                    $_POST['chat_id'],
                    $_POST['enabled'] ?? 0,
                    $_POST['parse_mode'] ?? 'HTML'
                ]);
                $tgMessage = "✅ 设置已保存";
                $tgMessageType = "success";
            } catch (Exception $e) {
                $tgMessage = "❌ 保存失败: " . $e->getMessage();
                $tgMessageType = "error";
            }
        } elseif ($_POST['telegram_action'] === 'test') {
            require_once dirname(__DIR__) . '/notifications/TelegramNotification.php';
            $tgNotifier = new TelegramNotification($conn);
            $testResult = $tgNotifier->test();
            $tgMessage = $testResult['message'];
            $tgMessageType = $testResult['success'] ? 'success' : 'error';
        }
    }
    
    $stmt = $conn->query("SELECT * FROM telegram_config WHERE id = 1");
    $tgConfig = $stmt->fetch();
    ?>
    
    <?php if ($tgMessage): ?>
    <div class="message <?php echo $tgMessageType === 'success' ? 'success' : 'error'; ?>">
        <?php echo $tgMessage; ?>
    </div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <input type="hidden" name="telegram_action" value="save">
        
        <div style="background: #FFFFFF; border-radius: 16px; padding: 24px; margin-bottom: 20px; border: 1px solid #F2F2F7;">
            <h3 style="margin-bottom: 16px; color: #1D1D1F; font-size: 1rem; font-weight: 600;">🤖 机器人配置</h3>
            
            <div class="form-group">
                <label>Bot Token</label>
                <input type="text" name="bot_token" value="<?php echo htmlspecialchars($tgConfig['bot_token'] ?? ''); ?>" placeholder="例如: 123456:ABC-DEF...">
                <small style="color: #86868B;">从 @BotFather 获取的机器人 Token</small>
            </div>
            
            <div class="form-group">
                <label>Chat ID</label>
                <input type="text" name="chat_id" value="<?php echo htmlspecialchars($tgConfig['chat_id'] ?? ''); ?>" placeholder="例如: 123456789">
                <small style="color: #86868B;">接收消息的聊天ID（可从 @userinfobot 获取）</small>
            </div>
            
            <div class="form-group">
                <label>解析模式</label>
                <select name="parse_mode">
                    <option value="HTML" <?php echo ($tgConfig['parse_mode'] ?? '') === 'HTML' ? 'selected' : ''; ?>>HTML</option>
                    <option value="Markdown" <?php echo ($tgConfig['parse_mode'] ?? '') === 'Markdown' ? 'selected' : ''; ?>>Markdown</option>
                    <option value="" <?php echo ($tgConfig['parse_mode'] ?? '') === '' ? 'selected' : ''; ?>>纯文本</option>
                </select>
            </div>
            
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <input type="checkbox" name="enabled" value="1" <?php echo ($tgConfig['enabled'] ?? 0) ? 'checked' : ''; ?>>
                启用 Telegram 通知
            </label>
        </div>
        
        <div style="display: flex; gap: 12px; margin-bottom: 20px;">
            <button type="submit" class="btn">💾 保存设置</button>
            <button type="submit" name="telegram_action" value="test" class="btn btn-success">🧪 测试发送</button>
        </div>
    </form>
    
    <div style="background: #F9F9FB; border-radius: 16px; padding: 20px; border: 1px solid #F2F2F7;">
        <h3 style="margin-bottom: 8px; color: #1D1D1F; font-size: 1rem; font-weight: 600;">💡 使用说明</h3>
        <ul style="color: #86868B; font-size: 0.875rem; padding-left: 20px; margin: 0;">
            <li style="margin-bottom: 4px;">从 @BotFather 创建机器人并获取 Token</li>
            <li style="margin-bottom: 4px;">从 @userinfobot 获取你的 Chat ID</li>
            <li style="margin-bottom: 4px;">测试发送按钮会立即发送一条测试消息</li>
            <li style="margin-bottom: 4px;">消息支持HTML格式</li>
        </ul>
    </div>
</div>