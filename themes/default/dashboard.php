<div class="section">
    <h2>📊 系统概览</h2>
    
    <div class="stats-grid">
        <div class="stat-card total">
            <h3>总资产数</h3>
            <div class="value"><?php echo $totalWebsites; ?></div>
        </div>
        <div class="stat-card up">
            <h3>HTTP正常</h3>
            <div class="value"><?php echo $upCount; ?></div>
        </div>
        <div class="stat-card down">
            <h3>HTTP异常</h3>
            <div class="value"><?php echo $downCount; ?></div>
        </div>
        <div class="stat-card ssl-warning">
            <h3>SSL/域名告警</h3>
            <div class="value"><?php echo $sslWarningCount + $sslExpiredCount; ?></div>
        </div>
    </div>
    
    <form method="POST">
        <input type="hidden" name="action" value="check_now">
        <button type="submit" class="btn btn-success">🔄 立即检查所有资产</button>
    </form>
</div>

<div class="section">
    <h2>🌐 资产状态</h2>
    
    <!-- iOS风格筛选器 -->
    <div class="filter-tabs" style="display: flex; gap: 0.5rem; margin-bottom: 1rem; background: #f0f0f0; padding: 4px; border-radius: 8px; width: fit-content;">
        <button type="button" class="filter-tab active" data-filter="all" onclick="filterAssets('all')" style="padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; background: #fff; color: #333; font-weight: 500; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">全部</button>
        <button type="button" class="filter-tab" data-filter="website" onclick="filterAssets('website')" style="padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; background: transparent; color: #666;">网站</button>
        <button type="button" class="filter-tab" data-filter="domain" onclick="filterAssets('domain')" style="padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; background: transparent; color: #666;">纯域名</button>
    </div>
    
    <div class="batch-panel">
        <h3>批量操作</h3>
        <div class="select-all">
            <label>
                <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)">
                全选/取消全选
            </label>
        </div>
        
        <form method="POST" id="batchForm" onsubmit="return confirm('确定要删除选中的资产吗？');">
            <input type="hidden" name="action" value="delete_selected_websites">
            <div class="action-buttons">
                <button type="submit" class="btn btn-danger">🗑️ 删除选中</button>
                <button type="button" class="btn btn-warning" onclick="if(confirm('确定要删除所有资产吗？')) { document.getElementById('deleteAllForm').submit(); }">
                    🗑️ 删除全部
                </button>
            </div>
        </form>
        
        <form method="POST" id="deleteAllForm" style="display: none;">
            <input type="hidden" name="action" value="delete_all_websites">
        </form>
    </div>
    
    <table class="table" id="assetsTable">
        <thead>
            <tr>
                <th width="30"><input type="checkbox" id="masterCheckbox" onchange="toggleAllCheckboxes(this)"></th>
                <th>名称</th>
                <th>URL</th>
                <th>HTTP</th>
                <th>SSL</th>
                <th>域名到期</th>
                <th>节点</th>
                <th>HTTP频率</th>
                <th>检查时间</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($websites as $website): 
                $checkHttp = $website['check_http'] ?? 1;
                $checkSsl = $website['check_ssl'] ?? 1;
                $checkWhois = $website['check_whois'] ?? 1;
                $interval = intval($website['check_interval'] ?? 5);
                
                // 判断类型：有HTTP检测的是"网站"，否则是"纯域名"
                $assetType = $checkHttp ? 'website' : 'domain';
                $typeLabel = $checkHttp 
                    ? '<span style="font-size: 10px; padding: 2px 6px; background: #e3f2fd; color: #1976d2; border-radius: 4px; margin-left: 4px;">网站</span>' 
                    : '<span style="font-size: 10px; padding: 2px 6px; background: #fff3e0; color: #f57c00; border-radius: 4px; margin-left: 4px;">域名</span>';
            ?>
                <tr data-type="<?php echo $assetType; ?>">
                    <td>
                        <input type="checkbox" name="selected_ids[]" value="<?php echo $website['id']; ?>" form="batchForm">
                    </td>
                    <td><?php echo htmlspecialchars($website['name']); ?><?php echo $typeLabel; ?></td>
                    <td>
                        <a href="<?php echo htmlspecialchars($website['url']); ?>" target="_blank" style="color: #1a73e8; text-decoration: none;">
                            <?php echo htmlspecialchars($website['url']); ?>
                        </a>
                    </td>
                    <!-- HTTP状态：没勾选显示横线 -->
                    <td>
                        <?php if ($checkHttp): ?>
                            <?php 
                            $httpStatus = $website['http_status'] ?? 'unknown';
                            if ($httpStatus === 'up') {
                                echo "<span style='color: #28a745;'>✅ 正常</span>";
                            } elseif ($httpStatus === 'down') {
                                echo "<span style='color: #dc3545;'>❌ 异常</span>";
                            } else {
                                echo "<span style='color: #999;'>⏳ 未检查</span>";
                            }
                            ?>
                        <?php else: ?>
                            <span style="color: #ccc;">—</span>
                        <?php endif; ?>
                    </td>
                    <!-- SSL状态：没勾选显示横线 -->
                    <td>
                        <?php if ($checkSsl): ?>
                            <?php 
                            $sslDays = $website['ssl_days'] ?? null;
                            if ($sslDays !== null) {
                                if ($sslDays <= 7) {
                                    echo "<span style='color: #dc3545; font-weight: bold;'>⚠️ {$sslDays}天</span>";
                                } elseif ($sslDays <= 30) {
                                    echo "<span style='color: #ffc107;'>{$sslDays}天</span>";
                                } else {
                                    echo "<span style='color: #28a745;'>{$sslDays}天</span>";
                                }
                            } else {
                                echo "<span style='color: #999;'>未检查</span>";
                            }
                            ?>
                        <?php else: ?>
                            <span style="color: #ccc;">—</span>
                        <?php endif; ?>
                    </td>
                    <!-- 域名到期：没勾选显示横线 -->
                    <td>
                        <?php if ($checkWhois): ?>
                            <?php 
                            $whoisDays = $website['whois_days'] ?? null;
                            $whoisExpire = $website['whois_expire_date'] ?? null;
                            if ($whoisDays !== null) {
                                if ($whoisDays <= 7) {
                                    echo "<span style='color: #dc3545; font-weight: bold;'>⚠️ {$whoisDays}天</span>";
                                } elseif ($whoisDays <= 30) {
                                    echo "<span style='color: #ffc107;'>{$whoisDays}天</span>";
                                } else {
                                    echo "<span style='color: #28a745;'>{$whoisDays}天</span>";
                                }
                                if ($whoisExpire) {
                                    echo "<br><small style='color:#666;'>" . date('Y-m-d', strtotime($whoisExpire)) . "</small>";
                                }
                            } else {
                                echo "<span style='color: #999;'>未检查</span>";
                            }
                            ?>
                        <?php else: ?>
                            <span style="color: #ccc;">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($website['node_name']); ?></td>
                    <td>
                        <?php if ($checkHttp): ?>
                            <?php 
                            if ($interval >= 60) {
                                echo ($interval / 60) . '小时';
                            } else {
                                echo $interval . '分钟';
                            }
                            ?>
                        <?php else: ?>
                            <span style="color: #ccc;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                        $lastCheck = $website['last_check'] ?? null;
                        $responseMs = $website['response_ms'] ?? 0;
                        if ($lastCheck) {
                            echo date('m-d H:i', strtotime($lastCheck));
                            if ($responseMs > 0 && $checkHttp) {
                                echo "<br><small style='color:#666;'>{$responseMs}ms</small>";
                            }
                        } else {
                            echo '<span style="color:#999;">—</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <button type="button" class="btn" style="padding: 0.25rem 0.5rem; font-size: 0.875rem; background: #17a2b8; color: white;" onclick="showEditWebsiteForm(<?php echo $website['id']; ?>, '<?php echo htmlspecialchars($website['name']); ?>', '<?php echo htmlspecialchars($website['url']); ?>', <?php echo $website['check_http']; ?>, <?php echo $website['check_ssl']; ?>, <?php echo $website['check_whois'] ?? 1; ?>, <?php echo $website['enabled']; ?>, <?php echo intval($website['check_interval']); ?>, '<?php echo htmlspecialchars($website['node_ids']); ?>')">编辑</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- 编辑网站弹窗 -->
<div id="editWebsiteModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;">
    <div style="background: white; padding: 2rem; border-radius: 12px; max-width: 500px; margin: 50px auto; position: relative;">
        <button type="button" style="position: absolute; top: 10px; right: 10px; background: none; border: none; font-size: 1.5rem; cursor: pointer;" onclick="document.getElementById('editWebsiteModal').style.display='none'">×</button>
        <h3 style="margin-top: 0;">编辑资产</h3>
        <form method="POST" action="?page=dashboard">
            <input type="hidden" name="action" value="update_website">
            <input type="hidden" name="id" id="editWebsiteId">
            
            <div class="form-group">
                <label>名称</label>
                <input type="text" name="name" id="editWebsiteName" required>
            </div>
            
            <div class="form-group">
                <label>URL</label>
                <input type="text" name="url" id="editWebsiteUrl" required>
            </div>
            
            <div class="form-group">
                <label>HTTP监控频率</label>
                <select name="check_interval" id="editCheckInterval" style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;">
                    <option value="1">每1分钟</option>
                    <option value="5">每5分钟（推荐）</option>
                    <option value="10">每10分钟</option>
                    <option value="30">每30分钟</option>
                    <option value="60">每1小时</option>
                    <option value="360">每6小时</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>监控节点</label>
                <div id="editNodeCheckboxes" style="display: flex; flex-wrap: wrap; gap: 0.5rem;"></div>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" name="check_http" id="editCheckHttp">
                    HTTP访问检测
                </label>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" name="check_ssl" id="editCheckSsl">
                    SSL证书检测
                </label>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" name="check_whois" id="editCheckWhois">
                    域名到期监控
                </label>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" name="enabled" id="editEnabled">
                    启用监控
                </label>
            </div>
            
            <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                <button type="submit" class="btn btn-success">保存修改</button>
                <button type="button" class="btn" onclick="document.getElementById('editWebsiteModal').style.display='none'">取消</button>
            </div>
        </form>
    </div>
</div>

<script>
// 筛选资产类型
function filterAssets(type) {
    var tabs = document.querySelectorAll('.filter-tab');
    tabs.forEach(function(tab) {
        if (tab.dataset.filter === type) {
            tab.style.background = '#fff';
            tab.style.color = '#333';
            tab.style.fontWeight = '500';
            tab.style.boxShadow = '0 1px 3px rgba(0,0,0,0.1)';
        } else {
            tab.style.background = 'transparent';
            tab.style.color = '#666';
            tab.style.fontWeight = 'normal';
            tab.style.boxShadow = 'none';
        }
    });
    
    var rows = document.querySelectorAll('#assetsTable tbody tr');
    rows.forEach(function(row) {
        if (type === 'all' || row.dataset.type === type) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// 编辑网站弹窗
function showEditWebsiteForm(id, name, url, checkHttp, checkSsl, checkWhois, enabled, interval, nodeIds) {
    document.getElementById('editWebsiteId').value = id;
    document.getElementById('editWebsiteName').value = name;
    document.getElementById('editWebsiteUrl').value = url;
    document.getElementById('editCheckHttp').checked = checkHttp === 1;
    document.getElementById('editCheckSsl').checked = checkSsl === 1;
    document.getElementById('editCheckWhois').checked = checkWhois === 1;
    document.getElementById('editEnabled').checked = enabled === 1;
    document.getElementById('editCheckInterval').value = interval;
    
    // 生成节点复选框
    var nodeCheckboxes = document.getElementById('editNodeCheckboxes');
    nodeCheckboxes.innerHTML = '';
    
    var nodes = <?php echo json_encode($allNodes); ?>;
    var selectedNodes = nodeIds.split(',').map(function(x) { return parseInt(x.trim()) || 0; });
    
    nodes.forEach(function(node) {
        var checked = selectedNodes.indexOf(node.id) !== -1 || (node.id === 0 && selectedNodes.indexOf(0) !== -1) || (node.id === 1 && selectedNodes.indexOf(1) !== -1);
        var label = document.createElement('label');
        label.style.cssText = 'display: flex; align-items: center; gap: 0.25rem; padding: 0.5rem; background: #f8f9fa; border-radius: 4px; cursor: pointer;';
        label.innerHTML = '<input type="checkbox" name="node_ids[]" value="' + node.id + '"' + (checked ? ' checked' : '') + '> ' + node.name + ' <small style="color:#666;">' + (node.type == 1 ? '(Pull)' : (node.type == 2 ? '(Push)' : '(内置)')) + '</small>';
        nodeCheckboxes.appendChild(label);
    });
    
    document.getElementById('editWebsiteModal').style.display = 'block';
}

// 全选/取消全选
function toggleSelectAll(checkbox) {
    var checkboxes = document.querySelectorAll('input[name="selected_ids[]"]');
    checkboxes.forEach(function(cb) {
        cb.checked = checkbox.checked;
    });
}

function toggleAllCheckboxes(checkbox) {
    toggleSelectAll(checkbox);
}
</script>