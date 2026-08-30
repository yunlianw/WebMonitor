<div class="section">
    <h2>🖥️ 节点管理</h2>
    
    <?php
    // 获取所有节点
    $stmt = $conn->query("SELECT * FROM nodes ORDER BY id");
    $nodes = $stmt->fetchAll();
    
    // 统计每个节点的网站数
    $nodeSiteCounts = [];
    foreach ($nodes as $node) {
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM websites WHERE FIND_IN_SET(?, node_ids) > 0");
        $stmt->execute([$node['id']]);
        $nodeSiteCounts[$node['id']] = $stmt->fetch()['cnt'];
    }
    
    // 获取全局密钥
    $globalKey = $settings['global_key'] ?? '';
    ?>
    
    <!-- 零代码部署说明 -->
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 24px; border-radius: 16px; margin-bottom: 24px;">
        <h3 style="margin: 0 0 16px 0; font-size: 1.125rem; font-weight: 600;">🚀 零代码部署探针</h3>
        <div style="background: rgba(255,255,255,0.12); padding: 16px; border-radius: 12px; margin-bottom: 16px;">
            <p style="margin: 0 0 8px 0; font-weight: 500;">📋 部署步骤（三步走）：</p>
            <ol style="margin: 0; padding-left: 20px; opacity: 0.9;">
                <li>在下方节点列表点击「📥 下载探针」</li>
                <li>把下载的文件上传到目标服务器（如 /www/wwwroot/agent.php）</li>
                <li>宝塔添加计划任务：访问 https://你的域名/agent.php?action=push</li>
            </ol>
        </div>
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <span style="opacity: 0.9;"><strong>全局通信密钥：</strong> <code id="globalKeyCode" style="background: rgba(255,255,255,0.15); padding: 4px 10px; border-radius: 8px; font-size: 0.875rem;"><?php echo htmlspecialchars($globalKey); ?></code></span>
            <button type="button" onclick="copyToClipboard(document.getElementById('globalKeyCode').innerText)" class="btn-secondary" style="padding: 6px 12px; background: rgba(255,255,255,0.2); color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 0.8125rem;">📋 复制</button>
            <form method="POST" style="display: inline;" onsubmit="return confirm('⚠️ 确定要重置全局通信密钥吗？\n\n影响：\n- 所有使用旧密钥的探针将失效\n- 需要重新下载探针文件\n- 已分配的节点独立密钥不受影响');">
                <input type="hidden" name="action" value="reset_global_key">
                <button type="submit" style="padding: 6px 12px; background: rgba(255,59,48,0.8); color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 0.8125rem;">🔄 重置密钥</button>
            </form>
        </div>
    </div>
    
    <script>
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            alert('✅ 已复制到剪贴板');
        }, function() {
            alert('复制失败，请手动复制');
        });
    }
    </script>
    
    <!-- 节点列表 -->
    <div style="background: #FFFFFF; border-radius: 16px; padding: 24px; border: 1px solid #F2F2F7; margin-bottom: 24px;">
        <h3 style="margin-bottom: 16px; color: #1D1D1F; font-size: 1rem; font-weight: 600;">当前节点</h3>
        
        <div style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>状态</th>
                        <th>节点名称</th>
                        <th>类型</th>
                        <th>密钥</th>
                        <th>位置</th>
                        <th style="text-align: center;">网站数</th>
                        <th>最后心跳</th>
                        <th style="text-align: center;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($nodes as $node): ?>
                    <tr>
                        <td>
                            <?php if ($node['status'] === 'online'): ?>
                                <span class="node-status-badge online">● 在线</span>
                            <?php elseif ($node['status'] === 'offline'): ?>
                                <span class="node-status-badge offline">● 离线</span>
                            <?php else: ?>
                                <span class="node-status-badge unknown">● 未知</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="font-weight: 500; color: #1D1D1F;"><?php echo htmlspecialchars($node['name']); ?></span>
                            <?php if ($node['id'] == 0): ?>
                                <span style="color: #86868B; font-size: 0.8125rem; margin-left: 4px;">(主控服务器)</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($node['type'] == 0): ?>
                                <span class="type-label website">内置</span>
                            <?php elseif ($node['type'] == 1): ?>
                                <span class="type-label domain">Pull</span>
                            <?php else: ?>
                                <span class="type-label" style="background: rgba(52,199,89,0.12); color: #34C759;">Push</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($node['id'] == 0): ?>
                                <span title="内置节点">🌐</span>
                            <?php elseif (!empty($node['use_global_key'])): ?>
                                <span title="使用全局通信密钥">🌟 全局</span>
                            <?php else: ?>
                                <span title="独立私有密钥">🔑 独立</span>
                            <?php endif; ?>
                        </td>
                        <td style="color: #86868B;"><?php echo htmlspecialchars($node['location'] ?? '-'); ?></td>
                        <td style="text-align: center; font-weight: 500;"><?php echo $nodeSiteCounts[$node['id']] ?? 0; ?></td>
                        <td style="color: #86868B; font-size: 0.875rem;">
                            <?php echo $node['last_heartbeat'] ? date('m-d H:i', strtotime($node['last_heartbeat'])) : '-'; ?>
                        </td>
                        <td style="text-align: center;">
                            <?php if ($node['id'] > 0): ?>
                                <div style="display: flex; gap: 6px; flex-wrap: wrap; justify-content: center;">
                                    <button type="button" onclick="openEditNodeModal(<?php echo $node['id']; ?>, '<?php echo htmlspecialchars($node['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($node['url'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($node['location'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($node['api_key'] ?? '', ENT_QUOTES); ?>', <?php echo $node['type']; ?>)" class="btn btn-secondary" style="padding: 6px 10px; font-size: 0.75rem; border-radius: 8px;">✏️ 编辑</button>
                                    <a href="node_api.php?action=download_agent&node_id=<?php echo $node['id']; ?>&key=<?php echo urlencode($settings['global_key'] ?? ''); ?>" 
                                       class="btn btn-success" style="padding: 6px 10px; font-size: 0.75rem; border-radius: 8px; text-decoration: none;"
                                       onclick="return confirm('确定要下载该节点的探针文件吗？');">
                                       📥 下载
                                    </a>
                                    <?php
                                    if (!empty($node['use_global_key'])) {
                                        $probeKey = $settings['global_key'] ?? '';
                                    } else {
                                        $probeKey = $node['api_key'] ?? '';
                                    }
                                    $probeUrl = '/agent.php?action=push&node_id=' . $node['id'] . '&key=' . urlencode($probeKey);
                                    ?>
                                    <button type="button" onclick="copyToClipboard('<?php echo htmlspecialchars($probeUrl); ?>')" class="btn btn-secondary" style="padding: 6px 10px; font-size: 0.75rem; border-radius: 8px;">🔗 URL</button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('确定要重新生成该节点的独立密钥吗？旧密钥将失效。');">
                                        <input type="hidden" name="action" value="regenerate_node_key">
                                        <input type="hidden" name="id" value="<?php echo $node['id']; ?>">
                                        <button type="submit" class="btn btn-secondary" style="padding: 6px 10px; font-size: 0.75rem; border-radius: 8px;">🔑 密钥</button>
                                    </form>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('确定要删除此节点吗？网站将移回内置节点。');">
                                        <input type="hidden" name="action" value="delete_node">
                                        <input type="hidden" name="id" value="<?php echo $node['id']; ?>">
                                        <button type="submit" class="btn btn-danger" style="padding: 6px 10px; font-size: 0.75rem; border-radius: 8px;">删除</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <span style="color: #86868B;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- 添加节点 -->
    <div style="background: #FFFFFF; border-radius: 16px; padding: 24px; border: 1px solid #F2F2F7; margin-bottom: 24px;">
        <h3 style="margin-bottom: 16px; color: #1D1D1F; font-size: 1rem; font-weight: 600;">添加新节点</h3>
        
        <form method="POST">
            <input type="hidden" name="action" value="add_node">
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label>节点名称 *</label>
                    <input type="text" name="name" required placeholder="如：北京腾讯云">
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label>节点类型 *</label>
                    <select name="type" required>
                        <option value="1">Pull模式（主控主动请求探针）</option>
                        <option value="2">Push模式（探针主动上报）</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label>探针地址（仅Pull模式）</label>
                    <input type="text" name="url" placeholder="https://example.com/agent.php">
                    <small style="color: #86868B; display: block; margin-top: 4px;">探针脚本的公网访问地址</small>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label>密钥方式 *</label>
                    <div class="checkbox-group" style="margin-top: 4px;">
                        <label style="padding: 0;">
                            <input type="radio" name="key_type" value="global" checked style="margin-right: 4px;">
                            🌟 全局通信密钥
                        </label>
                        <label style="padding: 0;">
                            <input type="radio" name="key_type" value="private" style="margin-right: 4px;">
                            🔑 独立私有密钥
                        </label>
                    </div>
                    <small style="color: #86868B; display: block; margin-top: 4px;">全局密钥便于统一管理，独立密钥安全性更高</small>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label>节点位置</label>
                    <input type="text" name="location" placeholder="如：北京、新加坡">
                </div>
            </div>
            
            <button type="submit" class="btn" style="margin-top: 20px;">添加节点</button>
        </form>
    </div>
    
    <div style="background: #F9F9FB; border-radius: 16px; padding: 20px; border: 1px solid #F2F2F7;">
        <h3 style="margin-bottom: 8px; color: #1D1D1F; font-size: 1rem; font-weight: 600;">💡 使用说明</h3>
        <ul style="color: #86868B; font-size: 0.875rem; padding-left: 20px; margin: 0;">
            <li style="margin-bottom: 4px;"><strong style="color: #1D1D1F;">Pull模式：</strong>主控主动请求探针执行检测，适合有公网IP的节点</li>
            <li style="margin-bottom: 4px;"><strong style="color: #1D1D1F;">Push模式：</strong>探针主动向主控领取任务并上报，适合内网节点</li>
            <li style="margin-bottom: 4px;">添加节点后，需要在"网站管理"中把网站分配给对应节点</li>
            <li>节点密钥用于验证通信，请妥善保存</li>
        </ul>
    </div>
    
    <!-- 编辑节点弹窗 -->
    <div id="editNodeModal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>✏️ 编辑节点</h3>
                <button type="button" class="modal-close" onclick="closeEditNodeModal()">×</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="edit_node">
                    <input type="hidden" name="id" id="edit_node_id">
                    
                    <div class="form-group">
                        <label>节点名称 *</label>
                        <input type="text" name="name" id="edit_node_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label>节点类型</label>
                        <div id="edit_node_type_display" style="padding: 12px 16px; background: #F9F9FB; border-radius: 10px; color: #86868B;"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>探针地址（Pull模式）</label>
                        <input type="text" name="url" id="edit_node_url" placeholder="https://example.com/agent.php">
                        <small style="color: #86868B;">探针脚本的公网访问地址</small>
                    </div>
                    
                    <div class="form-group">
                        <label>节点位置</label>
                        <input type="text" name="location" id="edit_node_location" placeholder="如：北京、新加坡">
                    </div>
                    
                    <div class="form-group">
                        <label>API密钥</label>
                        <input type="text" name="api_key" id="edit_node_api_key">
                        <small style="color: #86868B;">留空保持原密钥，修改需同步更新探针配置</small>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-warning">保存修改</button>
                        <button type="button" class="btn btn-secondary" onclick="closeEditNodeModal()">取消</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    function openEditNodeModal(id, name, url, location, apiKey, type) {
        document.getElementById('edit_node_id').value = id;
        document.getElementById('edit_node_name').value = name;
        document.getElementById('edit_node_url').value = url;
        document.getElementById('edit_node_location').value = location;
        document.getElementById('edit_node_api_key').value = '';
        document.getElementById('edit_node_type_display').innerHTML = type == 1 ? '🔄 Pull模式（主控主动请求）' : '📤 Push模式（探针主动上报）';
        document.getElementById('editNodeModal').style.display = 'flex';
    }
    
    function closeEditNodeModal() {
        document.getElementById('editNodeModal').style.display = 'none';
    }
    
    window.onclick = function(event) {
        var modal = document.getElementById('editNodeModal');
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }
    </script>
</div>