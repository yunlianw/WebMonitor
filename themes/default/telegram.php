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
    <div class="alert" style="padding: 1rem; margin-bottom: 1rem; border-radius: 8px; background: <?php echo $tgMessageType === 'success' ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $tgMessageType === 'success' ? '#155724' : '#721c24'; ?>;">
        <?php echo $tgMessage; ?>
    </div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <input type="hidden" name="telegram_action" value="save">
        
        <div class="card" style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 1.5rem;">
            <h3 style="margin-bottom: 1rem; color: #333;">🤖 机器人配置</h3>
            
            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Bot Token</label>
                <input type="text" name="bot_token" value="<?php echo htmlspecialchars($tgConfig['bot_token'] ?? ''); ?>" style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;" placeholder="例如: 123456:ABC-DEF...">
                <small style="color: #666;">从 @BotFather 获取的机器人 Token</small>
            </div>
            
            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Chat ID</label>
                <input type="text" name="chat_id" value="<?php echo htmlspecialchars($tgConfig['chat_id'] ?? ''); ?>" style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;" placeholder="例如: 123456789">
                <small style="color: #666;">接收消息的聊天ID（可从 @userinfobot 获取）</small>
            </div>
            
            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">解析模式</label>
                <select name="parse_mode" style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;">
                    <option value="HTML" <?php echo ($tgConfig['parse_mode'] ?? '') === 'HTML' ? 'selected' : ''; ?>>HTML</option>
                    <option value="Markdown" <?php echo ($tgConfig['parse_mode'] ?? '') === 'Markdown' ? 'selected' : ''; ?>>Markdown</option>
                    <option value="" <?php echo ($tgConfig['parse_mode'] ?? '') === '' ? 'selected' : ''; ?>>纯文本</option>
                </select>
            </div>
            
            <label style="display: flex; align-items: center; gap: 0.5rem; margin-top: 1rem;">
                <input type="checkbox" name="enabled" value="1" <?php echo ($tgConfig['enabled'] ?? 0) ? 'checked' : ''; ?>> 启用 Telegram 通知
            </label>
        </div>
        
        <div style="margin-bottom: 1rem;">
            <button type="submit" class="btn btn-primary">💾 保存设置</button>
            <button type="submit" name="telegram_action" value="test" class="btn" style="margin-left: 1rem; background: #28a745;">🧪 测试发送</button>
        </div>
    </form>
    
    <div class="card" style="background: #f8f9fa; border-radius: 12px; padding: 1.5rem; margin-top: 1.5rem;">
        <h3 style="margin-bottom: 0.5rem; color: #333;">💡 使用说明</h3>
        <ul style="color: #666; font-size: 0.875rem; padding-left: 1.5rem;">
            <li>从 @BotFather 创建机器人并获取 Token</li>
            <li>从 @userinfobot 获取你的 Chat ID</li>
            <li>测试发送按钮会立即发送一条测试消息</li>
            <li>消息支持HTML格式</li>
        </ul>
    </div>
</div>
