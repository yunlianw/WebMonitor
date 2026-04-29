<div class="section">
    <h2>🖥️ 节点管理</h2>
    
    <?php
    // 获取所有节点
    $stmt = $conn->query("SELECT * FROM nodes ORDER BY id");
    $nodes = $stmt->fetchAll();
    
    // 统计每个节点的网站数（使用FIND_IN_SET支持多点分配）
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
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1.5rem; border-radius: 12px; margin-bottom: 1.5rem;">
        <h3 style="margin: 0 0 1rem 0;">🚀 零代码部署探针</h3>
        <div style="background: rgba(255,255,255,0.1); padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
            <p style="margin: 0 0 0.5rem 0; font-weight: bold;">📋 部署步骤（三步走）：</p>
            <ol style="margin: 0; padding-left: 1.25rem; opacity: 0.9;">
                <li>在下方节点列表点击「📥 下载探针」</li>
                <li>把下载的文件上传到目标服务器（如 /www/wwwroot/agent.php）</li>
                <li>宝塔添加计划任务：访问 https://你的域名/agent.php?action=push</li>
            </ol>
        </div>
        <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
            <span style="opacity: 0.9;"><strong>全局通信密钥：</strong> <code id="globalKeyCode" style="background: rgba(255,255,255,0.2); padding: 0.25rem 0.5rem; border-radius: 4px;"><?php echo htmlspecialchars($globalKey); ?></code></span>
            <button type="button" onclick="copyToClipboard(document.getElementById('globalKeyCode').innerText)" style="padding: 0.25rem 0.5rem; background: rgba(255,255,255,0.2); border: none; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">📋 复制</button>
            <form method="POST" style="display: inline;" onsubmit="return confirm('⚠️ 确定要重置全局通信密钥吗？\n\n影响：\n- 所有使用旧密钥的探针将失效\n- 需要重新下载探针文件\n- 已分配的节点独立密钥不受影响');">
                <input type="hidden" name="action" value="reset_global_key">
                <button type="submit" style="padding: 0.25rem 0.5rem; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">🔄 重置密钥</button>
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
    <div class="card" style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 1.5rem;">
        <h3 style="margin-bottom: 1rem; color: #333;">当前节点</h3>
        
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid #ddd;">状态</th>
                        <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid #ddd;">节点名称</th>
                        <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid #ddd;">类型</th>
                        <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid #ddd;">密钥</th>
                        <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid #ddd;">位置</th>
                        <th style="padding: 0.75rem; text-align: center; border-bottom: 1px solid #ddd;">网站数</th>
                        <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid #ddd;">最后心跳</th>
                        <th style="padding: 0.75rem; text-align: center; border-bottom: 1px solid #ddd;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($nodes as $node): ?>
                    <tr>
                        <td style="padding: 0.75rem; border-bottom: 1px solid #eee;">
                            <?php if ($node['status'] === 'online'): ?>
                                <span style="color: #28a745; font-weight: 500;">● 在线</span>
                            <?php elseif ($node['status'] === 'offline'): ?>
                                <span style="color: #dc3545; font-weight: 500;">● 离线</span>
                            <?php else: ?>
                                <span style="color: #6c757d; font-weight: 500;">● 未知</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 0.75rem; border-bottom: 1px solid #eee;">
                            <?php echo htmlspecialchars($node['name']); ?>
                            <?php if ($node['id'] == 0): ?>
                                <span style="color: #666; font-size: 0.85rem;">(主控服务器)</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 0.75rem; border-bottom: 1px solid #eee;">
                            <?php if ($node['type'] == 0): ?>
                                <span style="background: #e3f2fd; color: #1976d2; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.85rem;">内置</span>
                            <?php elseif ($node['type'] == 1): ?>
                                <span style="background: #fff3e0; color: #f57c00; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.85rem;">Pull</span>
                            <?php else: ?>
                                <span style="background: #e8f5e9; color: #388e3c; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.85rem;">Push</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 0.75rem; border-bottom: 1px solid #eee;">
                            <?php if ($node['id'] == 0): ?>
                                <span title="内置节点" style="font-size: 0.9rem;">🌐</span>
                            <?php elseif (!empty($node['use_global_key'])): ?>
                                <span title="使用全局通信密钥" style="font-size: 0.9rem;">🌟 全局</span>
                            <?php else: ?>
                                <span title="独立私有密钥" style="font-size: 0.9rem;">🔑 独立</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 0.75rem; border-bottom: 1px solid #eee;"><?php echo htmlspecialchars($node['location'] ?? '-'); ?></td>
                        <td style="padding: 0.75rem; border-bottom: 1px solid #eee; text-align: center;"><?php echo $nodeSiteCounts[$node['id']] ?? 0; ?></td>
                        <td style="padding: 0.75rem; border-bottom: 1px solid #eee; color: #666;">
                            <?php echo $node['last_heartbeat'] ? date('m-d H:i', strtotime($node['last_heartbeat'])) : '-'; ?>
                        </td>
                        <td style="padding: 0.75rem; border-bottom: 1px solid #eee; text-align: center;">
                            <?php if ($node['id'] > 0): ?>
                                <!-- V3.6: 编辑按钮 -->
                                <button type="button" onclick="openEditNodeModal(<?php echo $node['id']; ?>, '<?php echo htmlspecialchars($node['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($node['url'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($node['location'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($node['api_key'] ?? '', ENT_QUOTES); ?>', <?php echo $node['type']; ?>)" style="padding: 0.25rem 0.5rem; background: #ffc107; color: #333; border: none; border-radius: 4px; cursor: pointer; font-size: 0.8rem; margin-right: 0.25rem;">✏️ 编辑</button>
                                <!-- 部署提示+下载探针 -->
                                <div style="margin-top: 0.5rem; margin-bottom: 0.5rem;">
                                    <span style="color: #666; font-size: 0.75rem;">📋 部署：下载 → 上传 → 挂任务</span>
                                </div>
                                <a href="node_api.php?action=download_agent&node_id=<?php echo $node['id']; ?>&key=<?php echo urlencode($settings['global_key'] ?? ''); ?>" 
                                   style="padding: 0.25rem 0.5rem; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.8rem; text-decoration: none; display: inline-block; margin-right: 0.25rem;"
                                   onclick="return confirm('确定要下载该节点的探针文件吗？');">
                                   📥 下载探针
                                </a>
                                <!-- 复制任务URL -->
                                <?php
                                // 根据节点密钥类型生成对应的探针URL
                                if (!empty($node['use_global_key'])) {
                                    // 使用全局密钥
                                    $probeKey = $settings['global_key'] ?? '';
                                } else {
                                    // 使用独立密钥
                                    $probeKey = $node['api_key'] ?? '';
                                }
                                // 生成带密钥的URL（域名留空，用户自己填写）
                                $probeUrl = '/agent.php?action=push&node_id=' . $node['id'] . '&key=' . urlencode($probeKey);
                                ?>
                                <button type="button" onclick="copyToClipboard('<?php echo htmlspecialchars($probeUrl); ?>')" style="padding: 0.25rem 0.5rem; background: #17a2b8; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.8rem; margin-right: 0.25rem;">🔗 复制URL</button>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('确定要重新生成该节点的独立密钥吗？旧密钥将失效。');">
                                    <input type="hidden" name="action" value="regenerate_node_key">
                                    <input type="hidden" name="id" value="<?php echo $node['id']; ?>">
                                    <button type="submit" style="padding: 0.25rem 0.5rem; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">🔑 独立密钥</button>
                                </form>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('确定要删除此节点吗？网站将移回内置节点。');">
                                    <input type="hidden" name="action" value="delete_node">
                                    <input type="hidden" name="id" value="<?php echo $node['id']; ?>">
                                    <button type="submit" style="padding: 0.25rem 0.5rem; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">删除</button>
                                </form>
                            <?php else: ?>
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- 添加节点 -->
    <div class="card" style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <h3 style="margin-bottom: 1rem; color: #333;">添加新节点</h3>
        
        <form method="POST">
            <input type="hidden" name="action" value="add_node">
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">节点名称 *</label>
                    <input type="text" name="name" required placeholder="如：北京腾讯云" style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">节点类型 *</label>
                    <select name="type" required style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;">
                        <option value="1">Pull模式（主控主动请求探针）</option>
                        <option value="2">Push模式（探针主动上报）</option>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">探针地址（仅Pull模式）</label>
                    <input type="text" name="url" placeholder="https://example.com/agent.php" style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;">
                    <small style="color: #666; font-size: 0.8rem;">探针脚本的公网访问地址</small>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">密钥方式 *</label>
                    <div style="display: flex; gap: 1rem; padding: 0.5rem 0;">
                        <label style="display: flex; align-items: center; gap: 0.25rem; cursor: pointer;">
                            <input type="radio" name="key_type" value="global" checked>
                            <span>🌟 使用全局通信密钥</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.25rem; cursor: pointer;">
                            <input type="radio" name="key_type" value="private">
                            <span>🔑 生成独立私有密钥</span>
                        </label>
                    </div>
                    <small style="color: #666; font-size: 0.8rem;">全局密钥便于统一管理，独立密钥安全性更高</small>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">节点位置</label>
                    <input type="text" name="location" placeholder="如：北京、新加坡" style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;">
                </div>
            </div>
            
            <button type="submit" style="margin-top: 1rem; padding: 0.75rem 1.5rem; background: #1a73e8; color: white; border: none; border-radius: 6px; cursor: pointer;">添加节点</button>
        </form>
    </div>
    
    <div class="card" style="background: #f8f9fa; border-radius: 12px; padding: 1.5rem; margin-top: 1.5rem;">
        <h3 style="margin-bottom: 0.5rem; color: #333;">💡 使用说明</h3>
        <ul style="color: #666; font-size: 0.875rem; padding-left: 1.5rem;">
            <li><strong>Pull模式：</strong>主控主动请求探针执行检测，适合有公网IP的节点</li>
            <li><strong>Push模式：</strong>探针主动向主控领取任务并上报，适合内网节点</li>
            <li>添加节点后，需要在"网站管理"中把网站分配给对应节点</li>
            <li>节点密钥用于验证通信，请妥善保存</li>
        </ul>
    </div>
    
    <!-- V3.6: 编辑节点模态框 -->
    <div id="editNodeModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5);">
        <div style="background: white; margin: 5% auto; padding: 2rem; border-radius: 12px; max-width: 500px; position: relative;">
            <span onclick="closeEditNodeModal()" style="position: absolute; right: 1rem; top: 1rem; font-size: 1.5rem; cursor: pointer; color: #666;">&times;</span>
            <h3 style="margin-bottom: 1.5rem; color: #333;">✏️ 编辑节点</h3>
            <form method="POST">
                <input type="hidden" name="action" value="edit_node">
                <input type="hidden" name="id" id="edit_node_id">
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">节点名称 *</label>
                    <input type="text" name="name" id="edit_node_name" required style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;">
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">节点类型</label>
                    <div id="edit_node_type_display" style="padding: 0.5rem; background: #f8f9fa; border-radius: 6px; color: #666;"></div>
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">探针地址（Pull模式）</label>
                    <input type="text" name="url" id="edit_node_url" placeholder="https://example.com/agent.php" style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;">
                    <small style="color: #666; font-size: 0.8rem;">探针脚本的公网访问地址</small>
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">节点位置</label>
                    <input type="text" name="location" id="edit_node_location" placeholder="如：北京、新加坡" style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;">
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">API密钥</label>
                    <input type="text" name="api_key" id="edit_node_api_key" style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;">
                    <small style="color: #666; font-size: 0.8rem;">留空保持原密钥，修改需同步更新探针配置</small>
                </div>
                
                <button type="submit" style="padding: 0.75rem 1.5rem; background: #ffc107; color: #333; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">保存修改</button>
                <button type="button" onclick="closeEditNodeModal()" style="padding: 0.75rem 1.5rem; background: #6c757d; color: white; border: none; border-radius: 6px; cursor: pointer; margin-left: 0.5rem;">取消</button>
            </form>
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
        document.getElementById('editNodeModal').style.display = 'block';
    }
    
    function closeEditNodeModal() {
        document.getElementById('editNodeModal').style.display = 'none';
    }
    
    // 点击模态框外部关闭
    window.onclick = function(event) {
        var modal = document.getElementById('editNodeModal');
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }
    </script>
</div>
