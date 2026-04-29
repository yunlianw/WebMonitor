<div class="section">
    <h2>➕ 添加单个网站</h2>
    
    <?php
    // 获取所有节点
    $stmt = $conn->query("SELECT * FROM nodes WHERE enabled = 1 ORDER BY id");
    $allNodes = $stmt->fetchAll();
    ?>
    
    <form method="POST">
        <input type="hidden" name="action" value="add_website">
        
        <div class="form-group">
            <label>网站名称</label>
            <input type="text" name="name" placeholder="例如：百度" required>
        </div>
        
        <div class="form-group">
            <label>网站URL</label>
            <input type="text" name="url" placeholder="例如：32sm.com 或 https://32sm.com" required>
            <small style="color: #666; display: block; margin-top: 0.25rem;">
                支持格式：域名（32sm.com）、带协议URL（https://32sm.com）、带路径URL（32sm.com/blog）
            </small>
        </div>
        
        <div class="form-group">
            <label>监控节点（可多选）</label>
            <button type="button" onclick="document.querySelectorAll('input[name=\'node_ids[]\']').forEach(cb => cb.checked = true);" style="padding: 0.25rem 0.75rem; background: #17a2b8; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem; margin-bottom: 0.5rem;">☑️ 一键全选</button>
            <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem;">
                <?php foreach ($allNodes as $n): ?>
                    <label style="display: flex; align-items: center; gap: 0.25rem; padding: 0.5rem; background: #f8f9fa; border-radius: 4px; cursor: pointer;">
                        <input type="checkbox" name="node_ids[]" value="<?php echo $n['id']; ?>" <?php echo $n['id'] == 1 || $n['id'] == 0 ? 'checked' : ''; ?>>
                        <?php echo htmlspecialchars($n['name']); ?>
                        <small style="color: #666;"><?php echo $n['type'] == 1 ? '(Pull)' : ($n['type'] == 2 ? '(Push)' : '(内置)'); ?></small>
                    </label>
                <?php endforeach; ?>
            </div>
            <small style="color: #666; display: block; margin-top: 0.25rem;">
                选择由哪些节点共同监控此网站（支持多点联动检测）
            </small>
        </div>
        
        <div class="checkbox-group">
            <label style="display: flex; align-items: center; gap: 0.5rem;">
                <input type="checkbox" name="check_http" checked>
                启用HTTP监控
            </label>
            <label style="display: flex; align-items: center; gap: 0.5rem;">
                <input type="checkbox" name="check_ssl" checked>
                启用SSL监控
            </label>
            <label style="display: flex; align-items: center; gap: 0.5rem;">
                <input type="checkbox" name="check_whois" checked>
                启用域名到期监控
            </label>
        </div>
        
        <div class="form-group">
            <label>HTTP监控频率</label>
            <select name="check_interval" style="padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px; width: 200px;">
                <option value="1">每1分钟</option>
                <option value="5" selected>每5分钟（推荐）</option>
                <option value="10">每10分钟</option>
                <option value="30">每30分钟</option>
                <option value="60">每1小时</option>
                <option value="360">每6小时</option>
            </select>
            <small style="color: #666; display: block; margin-top: 0.25rem;">
                仅针对HTTP访问检测。SSL证书和域名到期检测频率在【告警设置】页面配置（按小时计算）。
            </small>
        </div>
        
        <button type="submit" class="btn">添加网站</button>
    </form>
</div>

<div class="section">
    <h2>📋 批量添加网站</h2>
    
    <form method="POST">
        <input type="hidden" name="action" value="add_batch_websites">
        
        <div class="form-group">
            <label>网站URL列表（每行一个）</label>
            <textarea name="urls" rows="10" placeholder="32sm.com
https://asmrsm.com
http://example.com
example.org/blog
www.google.com" required></textarea>
        </div>
        
        <div class="form-group">
            <label>监控节点（可多选）</label>
            <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem;">
                <?php foreach ($allNodes as $n): ?>
                    <label style="display: flex; align-items: center; gap: 0.25rem; padding: 0.5rem; background: #f8f9fa; border-radius: 4px; cursor: pointer;">
                        <input type="checkbox" name="node_ids[]" value="<?php echo $n['id']; ?>" <?php echo $n['id'] == 0 ? 'checked' : ''; ?>>
                        <?php echo htmlspecialchars($n['name']); ?>
                        <small style="color: #666;"><?php echo $n['type'] == 1 ? '(Pull)' : ($n['type'] == 2 ? '(Push)' : '(内置)'); ?></small>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="form-group">
            <label>HTTP监控频率</label>
            <select name="batch_check_interval" style="padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px; width: 200px;">
                <option value="1">每1分钟</option>
                <option value="5" selected>每5分钟（推荐）</option>
                <option value="10">每10分钟</option>
                <option value="30">每30分钟</option>
                <option value="60">每1小时</option>
                <option value="360">每6小时</option>
            </select>
            <small style="color: #666; display: block; margin-top: 0.25rem;">
                仅针对HTTP访问检测。SSL证书和域名到期检测频率在【告警设置】页面配置。
            </small>
        </div>
        
        <div class="checkbox-group">
            <label style="display: flex; align-items: center; gap: 0.5rem;">
                <input type="checkbox" name="batch_check_whois" checked>
                启用域名到期监控
            </label>
        </div>
        
        <p style="color: #666; margin-bottom: 1rem;">
            <strong>提示：</strong>每行输入一个URL或域名，支持多种格式：
            <br>• 纯域名：32sm.com
            <br>• 带https协议：https://asmrsm.com
            <br>• 带http协议：http://example.com
            <br>• 带路径：example.org/blog
            <br>系统会自动规范化URL并提取域名作为网站名称。
        </p>
        
        <button type="submit" class="btn btn-success">批量添加网站</button>
    </form>
</div>
