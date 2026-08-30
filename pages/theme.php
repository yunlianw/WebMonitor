<?php
/**
 * 主题管理页面 - POST处理已移至admin.php（必须在HTML输出前执行）
 */

$themeMessage = '';

if (isset($_GET['msg']) && $_GET['msg'] === 'switched') {
    $themeMessage = "✅ 主题已切换，当前样式已更新";
}

if (isset($themeSwitchError)) {
    $themeMessage = "❌ " . $themeSwitchError;
}
?>

<div class="section">
    <h2>🎨 主题管理</h2>
    
    <?php if ($themeMessage): ?>
    <div class="message success">
        <?php echo $themeMessage; ?>
    </div>
    <?php endif; ?>
    
    <p style="color: #86868B; margin-bottom: 20px;">
        当前主题：<strong style="color: #1D1D1F;"><?php echo htmlspecialchars($themeName); ?></strong>
        <span style="color: #86868B; margin-left: 8px;">(themes/<?php echo htmlspecialchars($currentTheme); ?>)</span>
    </p>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px;">
        <?php foreach ($availableThemes as $themeKey => $theme): ?>
            <div style="background: #FFFFFF; border-radius: 16px; padding: 24px; border: 1px solid #F2F2F7; <?php echo $theme['is_current'] ? 'border-color: #007AFF; box-shadow: 0 0 0 3px rgba(0,122,255,0.12);' : ''; ?>">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                    <div>
                        <h3 style="margin: 0; font-size: 1.125rem; font-weight: 600; color: #1D1D1F;">
                            <?php echo htmlspecialchars($theme['name']); ?>
                        </h3>
                        <p style="margin: 4px 0 0 0; color: #86868B; font-size: 0.8125rem;">
                            v<?php echo htmlspecialchars($theme['version']); ?> · themes/<?php echo htmlspecialchars($themeKey); ?>
                        </p>
                    </div>
                    <?php if ($theme['is_current']): ?>
                        <span style="background: rgba(0,122,255,0.12); color: #007AFF; padding: 4px 12px; border-radius: 8px; font-size: 0.75rem; font-weight: 500;">
                            当前使用
                        </span>
                    <?php endif; ?>
                </div>
                
                <p style="color: #86868B; font-size: 0.875rem; margin: 0 0 16px 0;">
                    <?php echo htmlspecialchars($theme['description'] ?: '暂无描述'); ?>
                </p>
                
                <div style="display: flex; gap: 8px;">
                    <?php if (!$theme['is_current']): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="switch_theme" value="<?php echo htmlspecialchars($themeKey); ?>">
                            <button type="submit" class="btn" style="padding: 8px 16px; font-size: 0.875rem;">切换主题</button>
                        </form>
                    <?php endif; ?>
                    
                    <a href="themes/<?php echo htmlspecialchars($themeKey); ?>/style.css" target="_blank" class="btn btn-secondary" style="padding: 8px 16px; font-size: 0.875rem; text-decoration: none;">
                        查看样式
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div style="background: #F9F9FB; border-radius: 16px; padding: 20px; margin-top: 24px; border: 1px solid #F2F2F7;">
        <h3 style="margin-bottom: 8px; color: #1D1D1F; font-size: 1rem; font-weight: 600;">💡 如何开发新主题？</h3>
        <ol style="color: #86868B; font-size: 0.875rem; padding-left: 20px; margin: 0;">
            <li style="margin-bottom: 6px;">在 <code style="background: #E5E5EA; padding: 2px 6px; border-radius: 4px;">themes/</code> 目录下创建新文件夹，如 <code style="background: #E5E5EA; padding: 2px 6px; border-radius: 4px;">themes/my-theme/</code></li>
            <li style="margin-bottom: 6px;">创建 <code style="background: #E5E5EA; padding: 2px 6px; border-radius: 4px;">theme.json</code> 描述文件（参考现有主题）</li>
            <li style="margin-bottom: 6px;">创建 <code style="background: #E5E5EA; padding: 2px 6px; border-radius: 4px;">style.css</code> 样式文件</li>
            <li style="margin-bottom: 6px;">创建 <code style="background: #E5E5EA; padding: 2px 6px; border-radius: 4px;">header.php</code> 和各页面模板（dashboard.php 等）</li>
            <li>回到本页面即可看到新主题，点击切换即可使用</li>
        </ol>
    </div>
    
    <div style="background: #FFFFFF; border-radius: 16px; padding: 20px; margin-top: 16px; border: 1px solid #F2F2F7;">
        <h3 style="margin-bottom: 8px; color: #1D1D1F; font-size: 1rem; font-weight: 600;">📁 主题文件结构</h3>
        <pre style="background: #F9F9FB; padding: 16px; border-radius: 12px; font-size: 0.8125rem; color: #86868B; overflow-x: auto; margin: 0;">
themes/
├── apple/              # Apple 风格主题
│   ├── theme.json      # 主题描述
│   ├── style.css       # 样式文件
│   ├── header.php      # 顶部导航
│   ├── dashboard.php   # 仪表盘页面
│   ├── websites.php    # 网站管理页面
│   └── ...             # 其他页面模板
└── default/            # 默认主题（经典蓝色）
    └── ...
        </pre>
    </div>
</div>