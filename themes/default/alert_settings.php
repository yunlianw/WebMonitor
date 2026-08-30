<div class="section">
    <h2>🔔 告警设置</h2>
    <?php
    // 处理表单提交
    $message = '';
    $messageType = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alert_cooldown_minutes'])) {
        try {
            $settings_arr = [
                'enable_email_alerts' => $_POST['enable_email_alerts'] ?? '0',
                'enable_http_alerts' => $_POST['enable_http_alerts'] ?? '0',
                'enable_ssl_alerts' => $_POST['enable_ssl_alerts'] ?? '0',
                'enable_recovery_alerts' => $_POST['enable_recovery_alerts'] ?? '0',
                'first_alert_immediate' => $_POST['first_alert_immediate'] ?? '0',
                'alert_cooldown_minutes' => $_POST['alert_cooldown_minutes'] ?? '5',
                'ssl_warning_days' => $_POST['ssl_warning_days'] ?? '60',
                'ssl_check_interval_hours' => $_POST['ssl_check_interval_hours'] ?? '24',
                'http_timeout_seconds' => $_POST['http_timeout_seconds'] ?? '10',
                'max_retry_count' => $_POST['max_retry_count'] ?? '3',
                'enable_daily_summary' => $_POST['enable_daily_summary'] ?? '0',
                'ssl_alert_interval_days' => $_POST['ssl_alert_interval_days'] ?? '1',
                // WHOIS域名到期监控
                'enable_whois_alerts' => $_POST['enable_whois_alerts'] ?? '0',
                'whois_warning_days' => $_POST['whois_warning_days'] ?? '30',
                'whois_alert_cooldown_hours' => $_POST['whois_alert_cooldown_hours'] ?? '168',
                'whois_check_interval_hours' => $_POST['whois_check_interval_hours'] ?? '24'
            ];
            foreach ($settings_arr as $key => $value) {
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM alert_settings WHERE setting_name = ?");
                $stmt->execute([$key]);
                if ($stmt->fetch()['count'] > 0) {
                    $stmt = $conn->prepare("UPDATE alert_settings SET setting_value = ?, updated_at = NOW() WHERE setting_name = ?");
                } else {
                    $stmt = $conn->prepare("INSERT INTO alert_settings (setting_name, setting_value, created_at) VALUES (?, ?, NOW())");
                }
                $stmt->execute([$value, $key]);
            }
            $message = "✅ 告警设置已保存";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "❌ 保存失败: " . $e->getMessage();
            $messageType = "error";
        }
    }
    
    // 获取设置
    $stmt = $conn->query("SELECT setting_name, setting_value FROM alert_settings");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_name']] = $row['setting_value'];
    }
    ?>
    
    <?php if ($message): ?>
    <div class="alert" style="padding: 1rem; margin-bottom: 1rem; border-radius: 8px; background: <?php echo $messageType === 'success' ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $messageType === 'success' ? '#155724' : '#721c24'; ?>;">
        <?php echo $message; ?>
    </div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <div class="settings-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1.5rem; margin-top: 1rem;">
            
            <div class="card" style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <h3 style="margin-bottom: 1rem; color: #333;">🌐 HTTP监控</h3>
                <label style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                    <input type="checkbox" name="enable_http_alerts" value="1" <?php echo ($settings['enable_http_alerts'] ?? '0') === '1' ? 'checked' : ''; ?>> 启用HTTP监控
                </label>
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">告警冷却时间（分钟）</label>
                <input type="number" name="alert_cooldown_minutes" value="<?php echo $settings['alert_cooldown_minutes'] ?? '5'; ?>" min="1" max="1440" style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;">
                <small style="color: #666; display: block; margin-top: 0.25rem;">每隔多久发送一次告警</small>
            </div>
            
            <div class="card" style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <h3 style="margin-bottom: 1rem; color: #333;">🔐 SSL证书监控</h3>
                <label style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                    <input type="checkbox" name="enable_ssl_alerts" value="1" <?php echo ($settings['enable_ssl_alerts'] ?? '0') === '1' ? 'checked' : ''; ?>> 启用SSL监控
                </label>
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">SSL告警阈值（天）</label>
                <input type="number" name="ssl_warning_days" value="<?php echo $settings['ssl_warning_days'] ?? '60'; ?>" min="1" max="365" style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; margin-top: 1rem;">SSL检测间隔（小时）</label>
                <input type="number" name="ssl_check_interval_hours" value="<?php echo $settings['ssl_check_interval_hours'] ?? '24'; ?>" min="1" max="168" style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;">
                <small style="color: #666;">每1-168小时检测一次SSL证书状态</small>
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; margin-top: 1rem;">SSL告警间隔（天）</label>
                <input type="number" name="ssl_alert_interval_days" value="<?php echo $settings['ssl_alert_interval_days'] ?? '1'; ?>" min="1" max="30" style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;">
            </div>
            
            <div class="card" style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <h3 style="margin-bottom: 1rem; color: #333;">🌐 域名到期监控</h3>
                <label style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                    <input type="checkbox" name="enable_whois_alerts" value="1" <?php echo ($settings['enable_whois_alerts'] ?? '0') === '1' ? 'checked' : ''; ?>> 启用域名到期监控
                </label>
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">域名到期提醒阈值（天）</label>
                <input type="number" name="whois_warning_days" value="<?php echo $settings['whois_warning_days'] ?? '30'; ?>" min="1" max="365" style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; margin-top: 1rem;">域名告警冷却时间（小时）</label>
                <input type="number" name="whois_alert_cooldown_hours" value="<?php echo $settings['whois_alert_cooldown_hours'] ?? '168'; ?>" min="1" max="999" style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; margin-top: 1rem;">WHOIS检测间隔（小时）</label>
                <input type="number" name="whois_check_interval_hours" value="<?php echo $settings['whois_check_interval_hours'] ?? '24'; ?>" min="12" max="72" style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;">
                <small style="color: #666;">每12-72小时检测一次，避免频繁请求被封</small>
            </div>
            
            <div class="card" style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <h3 style="margin-bottom: 1rem; color: #333;">📧 邮件通知</h3>
                <label style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                    <input type="checkbox" name="enable_email_alerts" value="1" <?php echo ($settings['enable_email_alerts'] ?? '0') === '1' ? 'checked' : ''; ?>> 启用邮件告警
                </label>
                <label style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" name="enable_recovery_alerts" value="1" <?php echo ($settings['enable_recovery_alerts'] ?? '0') === '1' ? 'checked' : ''; ?>> 启用恢复通知
                </label>
            </div>
            
            <div class="card" style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <h3 style="margin-bottom: 1rem; color: #333;">⚙️ 高级设置</h3>
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">HTTP超时时间（秒）</label>
                <input type="number" name="http_timeout_seconds" value="<?php echo $settings['http_timeout_seconds'] ?? '10'; ?>" min="5" max="60" style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">最大重试次数</label>
                <input type="number" name="max_retry_count" value="<?php echo $settings['max_retry_count'] ?? '3'; ?>" min="1" max="10" style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;">
            </div>
        </div>
        
        <div style="margin-top: 1.5rem;">
            <button type="submit" class="btn btn-primary">💾 保存设置</button>
        </div>
    </form>
</div>
