<div class="section">
    <h2>🔑 监控密钥</h2>
    
    <p style="color: #86868B; margin-bottom: 12px;">用于API调用的监控密钥：</p>
    <div class="monitor-key">
        <?php echo htmlspecialchars($monitorKey); ?>
    </div>
    
    <form method="POST" style="margin-top: 16px;">
        <input type="hidden" name="action" value="regenerate_key">
        <button type="submit" class="btn btn-warning" onclick="return confirm('确定要重新生成监控密钥吗？旧的API调用将失效。');">
            重新生成密钥
        </button>
    </form>
    
    <div class="section" style="margin-top: 32px;">
        <h3 style="font-size: 1.125rem; font-weight: 600; color: #1D1D1F;">📡 API调用说明</h3>
        
        <?php $siteUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']; ?>
        
        <div style="margin-top: 16px;">
            <p style="color: #1D1D1F; font-weight: 500; margin-bottom: 8px;">1. 触发监控检查：</p>
            <div class="api-url">
                <?php echo $siteUrl; ?>/api_refactored.php?action=check&key=<?php echo htmlspecialchars($monitorKey); ?>
            </div>
        </div>
        
        <div style="margin-top: 16px;">
            <p style="color: #1D1D1F; font-weight: 500; margin-bottom: 8px;">2. 获取监控状态：</p>
            <div class="api-url">
                <?php echo $siteUrl; ?>/api.php?action=status&key=<?php echo htmlspecialchars($monitorKey); ?>
            </div>
        </div>
        
        <div style="margin-top: 16px;">
            <p style="color: #1D1D1F; font-weight: 500; margin-bottom: 8px;">3. 宝塔定时任务配置：</p>
            <div class="api-url">
                curl -s "<?php echo $siteUrl; ?>/api_refactored.php?action=check&key=<?php echo htmlspecialchars($monitorKey); ?>"
            </div>
        </div>
        
        <div style="background: #F9F9FB; border-radius: 12px; padding: 16px; margin-top: 20px; border: 1px solid #F2F2F7;">
            <p style="color: #86868B; font-size: 0.875rem; margin: 0;">
                <strong style="color: #1D1D1F;">提示：</strong>将上面的URL添加到宝塔面板的定时任务中，设置执行周期（如每小时执行一次）。
            </p>
        </div>
    </div>
</div>