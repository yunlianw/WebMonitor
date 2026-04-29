<div class="section">
    <h2>🔑 监控密钥</h2>
    
    <p>用于API调用的监控密钥：</p>
    <div class="monitor-key">
        <?php echo htmlspecialchars($monitorKey); ?>
    </div>
    
    <form method="POST" style="margin-top: 1rem;">
        <input type="hidden" name="action" value="regenerate_key">
        <button type="submit" class="btn btn-warning" onclick="return confirm('确定要重新生成监控密钥吗？旧的API调用将失效。');">
            重新生成密钥
        </button>
    </form>
    
    <div class="section" style="margin-top: 2rem;">
        <h3>📡 API调用说明</h3>
        
        <?php $siteUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']; ?>
        
        <p><strong>1. 触发监控检查：</strong></p>
        <div class="api-url">
            <?php echo $siteUrl; ?>/api_refactored.php?action=check&key=<?php echo htmlspecialchars($monitorKey); ?>
        </div>
        
        <p><strong>2. 获取监控状态：</strong></p>
        <div class="api-url">
            <?php echo $siteUrl; ?>/api.php?action=status&key=<?php echo htmlspecialchars($monitorKey); ?>
        </div>
        
        <p><strong>3. 宝塔定时任务配置：</strong></p>
        <div class="api-url">
            curl -s "<?php echo $siteUrl; ?>/api_refactored.php?action=check&key=<?php echo htmlspecialchars($monitorKey); ?>"
        </div>
        
        <p style="color: #666; margin-top: 1rem;">
            <strong>提示：</strong>将上面的URL添加到宝塔面板的定时任务中，设置执行周期（如每小时执行一次）。
        </p>
    </div>
</div>
