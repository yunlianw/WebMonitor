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
    <div class="filter-tabs">
        <button type="button" class="filter-tab active" data-filter="all" onclick="filterAssets('all')">全部</button>
        <button type="button" class="filter-tab" data-filter="website" onclick="filterAssets('website')">网站</button>
        <button type="button" class="filter-tab" data-filter="domain" onclick="filterAssets('domain')">纯域名</button>
    </div>
    
    <!-- 批量操作 -->
    <div class="batch-panel">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: #86868B; font-size: 0.875rem;">
                <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)">
                全选
            </label>
            <form method="POST" id="batchForm" onsubmit="return confirm('确定要删除选中的资产吗？');" style="display: flex; gap: 8px;">
                <input type="hidden" name="action" value="delete_selected_websites">
                <button type="submit" class="btn btn-danger" style="padding: 8px 16px; font-size: 0.875rem;">🗑️ 删除选中</button>
            </form>
            <form method="POST" id="deleteAllForm" style="display: none;">
                <input type="hidden" name="action" value="delete_all_websites">
            </form>
            <button type="button" class="btn btn-secondary" style="padding: 8px 16px; font-size: 0.875rem;" onclick="if(confirm('确定要删除所有资产吗？')) { document.getElementById('deleteAllForm').submit(); }">
                🗑️ 清空全部
            </button>
        </div>
    </div>
    
    <!-- 资产卡片列表 -->
    <div class="assets-grid" id="assetsGrid">
        <?php foreach ($websites as $website):
            $checkHttp = $website['check_http'] ?? 1;
            $checkSsl = $website['check_ssl'] ?? 1;
            $checkWhois = $website['check_whois'] ?? 1;
            $interval = intval($website['check_interval'] ?? 5);
            
            // 判断类型
            $assetType = $checkHttp ? 'website' : 'domain';
            
            // 状态判定
            $httpStatus = $website['http_status'] ?? 'unknown';
            if ($httpStatus === 'up') {
                $statusClass = 'up';
                $httpLabel = '正常';
            } elseif ($httpStatus === 'down') {
                $statusClass = 'down';
                $httpLabel = '异常';
            } else {
                $statusClass = 'unknown';
                $httpLabel = '未检查';
            }
            
            // SSL
            $sslDays = $website['ssl_days'] ?? null;
            $sslHtml = '—';
            $sslClass = 'neutral';
            if ($checkSsl && $sslDays !== null) {
                if ($sslDays <= 7) {
                    $sslHtml = "⚠️ {$sslDays}天";
                    $sslClass = 'error';
                } elseif ($sslDays <= 30) {
                    $sslHtml = "{$sslDays}天";
                    $sslClass = 'warning';
                } else {
                    $sslHtml = "{$sslDays}天";
                    $sslClass = 'success';
                }
            } elseif (!$checkSsl) {
                $sslHtml = '—';
            }
            
            // WHOIS
            $whoisDays = $website['whois_days'] ?? null;
            $whoisExpire = $website['whois_expire_date'] ?? null;
            $whoisHtml = '—';
            $whoisClass = 'neutral';
            if ($checkWhois && $whoisDays !== null) {
                if ($whoisDays <= 7) {
                    $whoisHtml = "⚠️ {$whoisDays}天";
                    $whoisClass = 'error';
                } elseif ($whoisDays <= 30) {
                    $whoisHtml = "{$whoisDays}天";
                    $whoisClass = 'warning';
                } else {
                    $whoisHtml = "{$whoisDays}天";
                    $whoisClass = 'success';
                }
            }
            
            // 频率
            $freqHtml = '—';
            if ($checkHttp) {
                $freqHtml = ($interval >= 60) ? ($interval / 60) . '小时' : $interval . '分钟';
            }
            
            // 最后检查
            $lastCheck = $website['last_check'] ?? null;
            $responseMs = $website['response_ms'] ?? 0;
            $checkTimeHtml = '—';
            if ($lastCheck) {
                $checkTimeHtml = date('m-d H:i', strtotime($lastCheck));
                if ($responseMs > 0 && $checkHttp) {
                    $checkTimeHtml .= "<span style='color:#86868B; font-size:0.75rem; margin-left:4px;'>{$responseMs}ms</span>";
                }
            }
        ?>
            <div class="asset-card" data-type="<?php echo $assetType; ?>">
                <div style="display: flex; align-items: center; gap: 16px;">
                    <input type="checkbox" name="selected_ids[]" value="<?php echo $website['id']; ?>" form="batchForm">
                    <div class="asset-status-dot <?php echo $statusClass; ?>"></div>
                </div>
                
                <div class="asset-main">
                    <div>
                        <span class="asset-name"><?php echo htmlspecialchars($website['name']); ?></span>
                        <span class="type-label <?php echo $assetType; ?>"><?php echo $assetType === 'website' ? '网站' : '域名'; ?></span>
                    </div>
                    <div class="asset-url">
                        <a href="<?php echo htmlspecialchars($website['url']); ?>" target="_blank"><?php echo htmlspecialchars($website['url']); ?></a>
                    </div>
                    <div style="font-size: 0.8125rem; color: #86868B; margin-top: 2px;">
                        📍 <?php echo htmlspecialchars($website['node_name']); ?>
                        <?php if ($whoisExpire && $checkWhois): ?>
                            &nbsp;·&nbsp; 到期 <?php echo date('Y-m-d', strtotime($whoisExpire)); ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="asset-meta">
                    <?php if ($checkHttp): ?>
                    <div class="asset-meta-item">
                        <span class="asset-meta-label">HTTP</span>
                        <span class="asset-meta-value <?php echo $statusClass === 'up' ? 'success' : ($statusClass === 'down' ? 'error' : 'neutral'); ?>">
                            <?php echo $httpLabel; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($checkSsl): ?>
                    <div class="asset-meta-item">
                        <span class="asset-meta-label">SSL</span>
                        <span class="asset-meta-value <?php echo $sslClass; ?>">
                            <?php echo $sslHtml; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($checkWhois): ?>
                    <div class="asset-meta-item">
                        <span class="asset-meta-label">域名</span>
                        <span class="asset-meta-value <?php echo $whoisClass; ?>">
                            <?php echo $whoisHtml; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="asset-meta-item">
                        <span class="asset-meta-label">频率</span>
                        <span class="asset-meta-value neutral"><?php echo $freqHtml; ?></span>
                    </div>
                    
                    <div class="asset-meta-item">
                        <span class="asset-meta-label">检查</span>
                        <span class="asset-meta-value neutral" style="font-size: 0.8125rem;"><?php echo $checkTimeHtml; ?></span>
                    </div>
                    
                    <div>
                        <button type="button" class="btn btn-secondary" style="padding: 6px 14px; font-size: 0.8125rem; border-radius: 8px;" onclick="showEditWebsiteForm(<?php echo $website['id']; ?>, '<?php echo htmlspecialchars(addslashes($website['name'])); ?>', '<?php echo htmlspecialchars(addslashes($website['url'])); ?>', <?php echo $website['check_http']; ?>, <?php echo $website['check_ssl']; ?>, <?php echo $website['check_whois'] ?? 1; ?>, <?php echo $website['enabled']; ?>, <?php echo intval($website['check_interval']); ?>, '<?php echo htmlspecialchars($website['node_ids']); ?>')">编辑</button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- 编辑网站弹窗 -->
<div id="editWebsiteModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>编辑资产</h3>
            <button type="button" class="modal-close" onclick="document.getElementById('editWebsiteModal').style.display='none'">×</button>
        </div>
        <div class="modal-body">
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
                    <select name="check_interval" id="editCheckInterval">
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
                    <div id="editNodeCheckboxes"></div>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="check_http" id="editCheckHttp">
                        HTTP访问检测
                    </label>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="check_ssl" id="editCheckSsl">
                        SSL证书检测
                    </label>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="check_whois" id="editCheckWhois">
                        域名到期监控
                    </label>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="enabled" id="editEnabled">
                        启用监控
                    </label>
                </div>
                
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">保存修改</button>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('editWebsiteModal').style.display='none'">取消</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// 筛选资产类型
function filterAssets(type) {
    var tabs = document.querySelectorAll('.filter-tab');
    tabs.forEach(function(tab) {
        tab.classList.toggle('active', tab.dataset.filter === type);
    });
    
    var cards = document.querySelectorAll('.asset-card');
    cards.forEach(function(card) {
        card.style.display = (type === 'all' || card.dataset.type === type) ? '' : 'none';
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
        var checked = selectedNodes.indexOf(node.id) !== -1 || (node.id === 1 && selectedNodes.indexOf(1) !== -1);
        var label = document.createElement('label');
        label.innerHTML = '<input type="checkbox" name="node_ids[]" value="' + node.id + '"' + (checked ? ' checked' : '') + '> <span>' + node.name + ' <small style="color:#86868B;">' + (node.type == 1 ? '(Pull)' : (node.type == 2 ? '(Push)' : '(内置)')) + '</small></span>';
        nodeCheckboxes.appendChild(label);
    });
    
    document.getElementById('editWebsiteModal').style.display = 'flex';
}

// 全选/取消全选
function toggleSelectAll(checkbox) {
    var checkboxes = document.querySelectorAll('input[name="selected_ids[]"]');
    checkboxes.forEach(function(cb) {
        cb.checked = checkbox.checked;
    });
}
</script>