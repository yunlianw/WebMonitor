<?php
/**
 * WebMonitor 公共头部模板 (Apple Style)
 * 包含：顶部导航栏 + 侧边栏菜单
 */
?>
<div class="header">
    <h1>🟢 WebMonitor</h1>
    <div class="header-right">
        <span class="last-check-time"><?php echo isset($settings['last_check']) ? '上次检查: ' . $settings['last_check'] : ''; ?></span>
        <a href="logout.php" class="logout-btn">退出登录</a>
    </div>
</div>

<div class="main-content">
    <div class="sidebar">
        <ul>
            <li><a href="admin.php?page=dashboard" class="<?php echo $page === 'dashboard' ? 'active' : ''; ?>">📊 仪表盘</a></li>
            <li><a href="admin.php?page=websites" class="<?php echo $page === 'websites' ? 'active' : ''; ?>">🌐 网站管理</a></li>
            <li><a href="admin.php?page=nodes" class="<?php echo $page === 'nodes' ? 'active' : ''; ?>">🖥️ 节点管理</a></li>
            <li><a href="admin.php?page=alert_settings" class="<?php echo $page === 'alert_settings' ? 'active' : ''; ?>">🔔 告警设置</a></li>
            <li><a href="admin.php?page=email" class="<?php echo $page === 'email' ? 'active' : ''; ?>">📧 邮件配置</a></li>
            <li><a href="admin.php?page=telegram" class="<?php echo $page === 'telegram' ? 'active' : ''; ?>">📱 Telegram</a></li>
            <li><a href="admin.php?page=alert_templates" class="<?php echo $page === 'alert_templates' ? 'active' : ''; ?>">📝 告警模板</a></li>
            <li><a href="admin.php?page=settings" class="<?php echo $page === 'settings' ? 'active' : ''; ?>">⚙️ 系统设置</a></li>
            <li><a href="admin.php?page=monitor" class="<?php echo $page === 'monitor' ? 'active' : ''; ?>">🔑 监控密钥</a></li>
            <li><a href="admin.php?page=theme" class="<?php echo $page === 'theme' ? 'active' : ''; ?>">🎨 主题管理</a></li>
        </ul>
    </div>
    
    <div class="content">
        <?php if (isset($message) && $message): ?>
            <div class="message <?php echo $messageType ?? 'info'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>