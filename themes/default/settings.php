<div class="section">
    <h2>⚙️ 系统设置</h2>
    
    <?php
    // 处理数据清理
    $cleanMessage = '';
    $cleanMessageType = '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clean_action'])) {
        $cleanAction = $_POST['clean_action'];
        $days = (int)($_POST['clean_days'] ?? 30);
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        try {
            $cleaned = [];
            
            if ($cleanAction === 'monitor_logs' || $cleanAction === 'all') {
                $stmt = $conn->prepare("DELETE FROM monitor_logs WHERE checked_at < ?");
                $stmt->execute([$cutoffDate]);
                $cleaned['monitor_logs'] = $stmt->rowCount();
            }
            
            if ($cleanAction === 'email_logs' || $cleanAction === 'all') {
                $stmt = $conn->prepare("DELETE FROM email_logs WHERE sent_at < ?");
                $stmt->execute([$cutoffDate]);
                $cleaned['email_logs'] = $stmt->rowCount();
            }
            
            if ($cleanAction === 'alert_logs' || $cleanAction === 'all') {
                $stmt = $conn->prepare("DELETE FROM alert_logs WHERE created_at < ?");
                $stmt->execute([$cutoffDate]);
                $cleaned['alert_logs'] = $stmt->rowCount();
            }
            
            $cleanMessage = "✅ 清理完成: ";
            $parts = [];
            foreach ($cleaned as $table => $count) {
                if ($count > 0) $parts[] = "{$table} {$count}条";
            }
            $cleanMessage .= implode(', ', $parts) ?: '无数据需要清理';
            $cleanMessageType = 'success';
        } catch (Exception $e) {
            $cleanMessage = "❌ 清理失败: " . $e->getMessage();
            $cleanMessageType = 'error';
        }
    }
    
    // 获取当前数据统计
    $stats = [];
    $stmt = $conn->query("SELECT COUNT(*) as cnt FROM monitor_logs");
    $stats['monitor_logs'] = $stmt->fetch()['cnt'];
    
    $stmt = $conn->query("SELECT COUNT(*) as cnt FROM email_logs");
    $stats['email_logs'] = $stmt->fetch()['cnt'];
    
    $stmt = $conn->query("SELECT COUNT(*) as cnt FROM alert_logs");
    $stats['alert_logs'] = $stmt->fetch()['cnt'];
    
    // V3.6: 获取数据库大小
    $dbSize = [];
    $stmt = $conn->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size FROM information_schema.tables WHERE table_schema = 'yunlian'");
    $dbSize['total'] = $stmt->fetch()['size'] ?? 0;
    ?>
    
    <?php if ($cleanMessage): ?>
    <div class="alert" style="padding: 1rem; margin-bottom: 1rem; border-radius: 8px; background: <?php echo $cleanMessageType === 'success' ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $cleanMessageType === 'success' ? '#155724' : '#721c24'; ?>;">
        <?php echo $cleanMessage; ?>
    </div>
    <?php endif; ?>
    
    <!-- 历史记录设置 -->
    <div class="card" style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 1.5rem;">
        <h3 style="margin-bottom: 1rem; color: #333;">📊 历史记录设置</h3>
        
        <form method="POST">
            <input type="hidden" name="action" value="update_settings">
            
            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">历史记录保留天数</label>
                <input type="number" name="history_retention_days" value="<?php echo htmlspecialchars($settings['history_retention_days'] ?? 30); ?>" min="1" max="365" style="width: 100%; max-width: 300px; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px;">
                <p style="color: #666; font-size: 0.875rem; margin-top: 0.25rem;">监控历史记录保留的天数</p>
            </div>
            
            <button type="submit" class="btn btn-primary">💾 保存设置</button>
        </form>
    </div>
    
    <!-- 日志快速查看 -->
    <div class="card" style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 1.5rem;">
        <h3 style="margin-bottom: 1rem; color: #333;">📜 日志查看 <small style="color: #999; font-size: 0.75rem;">最新50条记录</small></h3>
        
        <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
            <button type="button" onclick="switchLogTab('monitor')" id="btn-log-monitor" style="padding: 0.5rem 1rem; border: none; border-radius: 6px; cursor: pointer; background: #1a73e8; color: white; font-size: 0.875rem;">📋 监控日志</button>
            <button type="button" onclick="switchLogTab('email')" id="btn-log-email" style="padding: 0.5rem 1rem; border: none; border-radius: 6px; cursor: pointer; background: #e9ecef; color: #333; font-size: 0.875rem;">📧 邮件日志</button>
            <button type="button" onclick="switchLogTab('alert')" id="btn-log-alert" style="padding: 0.5rem 1rem; border: none; border-radius: 6px; cursor: pointer; background: #e9ecef; color: #333; font-size: 0.875rem;">🔔 告警日志</button>
        </div>
        
        <!-- 监控日志 -->
        <div id="log-panel-monitor">
            <?php
            // 获取筛选参数
            $logNodeFilter = isset($_GET['log_node']) ? $_GET['log_node'] : '';
            
            // 获取节点列表用于筛选
            $allNodesForLog = $conn->query("SELECT id, name, type FROM nodes ORDER BY id")->fetchAll();
            
            // V3.6: 简化查询，移除子查询提升性能
            if ($logNodeFilter !== '' && $logNodeFilter !== '0') {
                $stmt = $conn->prepare("
                    SELECT m.*, w.name as website_name, n.name as node_name
                    FROM monitor_logs m 
                    LEFT JOIN websites w ON m.website_id = w.id 
                    LEFT JOIN nodes n ON m.node_id = n.id 
                    WHERE m.node_id = ? 
                    ORDER BY m.checked_at DESC LIMIT 50");
                $stmt->execute([$logNodeFilter]);
            } elseif ($logNodeFilter === '0') {
                $stmt = $conn->query("
                    SELECT m.*, w.name as website_name, '内置节点' as node_name
                    FROM monitor_logs m 
                    LEFT JOIN websites w ON m.website_id = w.id 
                    WHERE m.node_id = 0 
                    ORDER BY m.checked_at DESC LIMIT 50");
            } else {
                $stmt = $conn->query("
                    SELECT m.*, w.name as website_name, n.name as node_name
                    FROM monitor_logs m 
                    LEFT JOIN websites w ON m.website_id = w.id 
                    LEFT JOIN nodes n ON m.node_id = n.id 
                    ORDER BY m.checked_at DESC LIMIT 50");
            }
            $monitorLogs = $stmt->fetchAll();
            ?>
            <!-- 筛选控件 -->
            <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem; align-items: center;">
                <span style="color: #666; font-size: 0.875rem;">筛选：</span>
                <select onchange="window.location.href='?page=settings&log_node='+this.value" style="padding: 0.4rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.875rem;">
                    <option value="" <?php echo $logNodeFilter === '' ? 'selected' : ''; ?>>全部节点</option>
                    <option value="0" <?php echo $logNodeFilter === '0' ? 'selected' : ''; ?>>内置节点(主控)</option>
                    <?php foreach ($allNodesForLog as $n): ?>
                        <?php if ($n['type'] != 0): ?>
                            <option value="<?php echo $n['id']; ?>" <?php echo $logNodeFilter == $n['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($n['name']); ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="overflow-x: auto; max-height: 400px; overflow-y: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                    <thead style="position: sticky; top: 0; background: #f8f9fa;">
                        <tr>
                            <th style="padding: 0.5rem; text-align: left; border-bottom: 1px solid #ddd; white-space: nowrap;">时间</th>
                            <th style="padding: 0.5rem; text-align: left; border-bottom: 1px solid #ddd;">网站</th>
                            <th style="padding: 0.5rem; text-align: center; border-bottom: 1px solid #ddd;">节点</th>
                            <th style="padding: 0.5rem; text-align: center; border-bottom: 1px solid #ddd;">HTTP</th>
                            <th style="padding: 0.5rem; text-align: center; border-bottom: 1px solid #ddd;">SSL(天)</th>
                            <th style="padding: 0.5rem; text-align: center; border-bottom: 1px solid #ddd;">响应</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monitorLogs as $log): ?>
                        <tr>
                            <td style="padding: 0.5rem; border-bottom: 1px solid #eee; white-space: nowrap; color: #666;"><?php echo date('m-d H:i', strtotime($log['checked_at'])); ?></td>
                            <td style="padding: 0.5rem; border-bottom: 1px solid #eee;"><?php echo htmlspecialchars($log['website_name'] ?? 'ID:'.$log['website_id']); ?></td>
                            <td style="padding: 0.5rem; border-bottom: 1px solid #eee; text-align: center;">
                                <?php if ($log['node_id'] == 0 || $log['node_id'] === null): ?>
                                    <span style="background: #e3f2fd; color: #1976d2; padding: 0.15rem 0.4rem; border-radius: 3px; font-size: 0.75rem;">主控</span>
                                <?php else: ?>
                                    <span style="background: #fff3e0; color: #f57c00; padding: 0.15rem 0.4rem; border-radius: 3px; font-size: 0.75rem;"><?php echo htmlspecialchars($log['node_name'] ?? '节点'.$log['node_id']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 0.5rem; border-bottom: 1px solid #eee; text-align: center;">
                                <?php if ($log['http_status'] === 'up'): ?>
                                    <span style="color: #28a745;">✓ <?php echo $log['http_code']; ?></span>
                                <?php elseif ($log['http_status'] === 'down'): ?>
                                    <span style="color: #dc3545;">✗ <?php echo $log['http_code'] ?: '超时'; ?></span>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 0.5rem; border-bottom: 1px solid #eee; text-align: center;">
                                <?php if ($log['ssl_days'] !== null): ?>
                                    <?php if ($log['ssl_days'] > 30): ?>
                                        <span style="color: #28a745;"><?php echo $log['ssl_days']; ?></span>
                                    <?php elseif ($log['ssl_days'] > 7): ?>
                                        <span style="color: #ffc107;"><?php echo $log['ssl_days']; ?></span>
                                    <?php else: ?>
                                        <span style="color: #dc3545;"><?php echo $log['ssl_days']; ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 0.5rem; border-bottom: 1px solid #eee; text-align: center;">
                                <?php if ($log['response_time']): ?>
                                    <?php echo $log['response_time']; ?>ms
                                    <?php if (!empty($log['prev_response_time']) && $log['prev_response_time'] > 0): ?>
                                        <?php 
                                            $diff = $log['response_time'] - $log['prev_response_time'];
                                            $percent = round(($diff / $log['prev_response_time']) * 100);
                                            if (abs($percent) >= 5): // 变化超过5%才显示
                                                if ($diff > 0): ?>
                                                    <span style="color: #dc3545; font-size: 0.7rem;"> ↑+<?php echo $percent; ?>%</span>
                                                <?php else: ?>
                                                    <span style="color: #28a745; font-size: 0.7rem;"> ↓<?php echo $percent; ?>%</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (empty($monitorLogs)): ?>
                    <p style="text-align: center; color: #999; padding: 2rem;">暂无监控日志</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 邮件日志 -->
        <div id="log-panel-email" style="display: none;">
            <?php
            $stmt = $conn->query("SELECT e.*, w.name as website_name FROM email_logs e LEFT JOIN websites w ON e.website_id = w.id ORDER BY e.sent_at DESC LIMIT 50");
            $emailLogs = $stmt->fetchAll();
            ?>
            <div style="overflow-x: auto; max-height: 400px; overflow-y: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                    <thead style="position: sticky; top: 0; background: #f8f9fa;">
                        <tr>
                            <th style="padding: 0.5rem; text-align: left; border-bottom: 1px solid #ddd;">时间</th>
                            <th style="padding: 0.5rem; text-align: left; border-bottom: 1px solid #ddd;">网站</th>
                            <th style="padding: 0.5rem; text-align: left; border-bottom: 1px solid #ddd;">类型</th>
                            <th style="padding: 0.5rem; text-align: left; border-bottom: 1px solid #ddd;">收件人</th>
                            <th style="padding: 0.5rem; text-align: center; border-bottom: 1px solid #ddd;">状态</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($emailLogs as $log): ?>
                        <tr>
                            <td style="padding: 0.5rem; border-bottom: 1px solid #eee; white-space: nowrap; color: #666;"><?php echo date('m-d H:i', strtotime($log['sent_at'])); ?></td>
                            <td style="padding: 0.5rem; border-bottom: 1px solid #eee;"><?php echo htmlspecialchars($log['website_name'] ?? '-'); ?></td>
                            <td style="padding: 0.5rem; border-bottom: 1px solid #eee;"><?php echo htmlspecialchars($log['alert_type']); ?></td>
                            <td style="padding: 0.5rem; border-bottom: 1px solid #eee; font-size: 0.8rem; color: #666;"><?php echo htmlspecialchars($log['recipients']); ?></td>
                            <td style="padding: 0.5rem; border-bottom: 1px solid #eee; text-align: center;">
                                <?php if ($log['status'] === 'success'): ?>
                                    <span style="color: #28a745;">✓ 成功</span>
                                <?php else: ?>
                                    <span style="color: #dc3545;">✗ 失败</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (empty($emailLogs)): ?>
                    <p style="text-align: center; color: #999; padding: 2rem;">暂无邮件日志</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 告警日志 -->
        <div id="log-panel-alert" style="display: none;">
            <?php
            $stmt = $conn->query("SELECT a.*, w.name as website_name FROM alert_logs a LEFT JOIN websites w ON a.website_id = w.id ORDER BY a.created_at DESC LIMIT 50");
            $alertLogs = $stmt->fetchAll();
            ?>
            <div style="overflow-x: auto; max-height: 400px; overflow-y: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                    <thead style="position: sticky; top: 0; background: #f8f9fa;">
                        <tr>
                            <th style="padding: 0.5rem; text-align: left; border-bottom: 1px solid #ddd;">时间</th>
                            <th style="padding: 0.5rem; text-align: left; border-bottom: 1px solid #ddd;">网站</th>
                            <th style="padding: 0.5rem; text-align: left; border-bottom: 1px solid #ddd;">类型</th>
                            <th style="padding: 0.5rem; text-align: left; border-bottom: 1px solid #ddd;">消息</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alertLogs as $log): ?>
                        <tr>
                            <td style="padding: 0.5rem; border-bottom: 1px solid #eee; white-space: nowrap; color: #666;"><?php echo date('m-d H:i', strtotime($log['created_at'])); ?></td>
                            <td style="padding: 0.5rem; border-bottom: 1px solid #eee;"><?php echo htmlspecialchars($log['website_name'] ?? '-'); ?></td>
                            <td style="padding: 0.5rem; border-bottom: 1px solid #eee;">
                                <?php 
                                $typeColors = [
                                    'http_down' => '#dc3545',
                                    'http_recovery' => '#28a745',
                                    'ssl_warning' => '#ffc107',
                                    'ssl_expired' => '#dc3545',
                                    'ssl_recovery' => '#28a745'
                                ];
                                $typeLabels = [
                                    'http_down' => 'HTTP异常',
                                    'http_recovery' => 'HTTP恢复',
                                    'ssl_warning' => 'SSL警告',
                                    'ssl_expired' => 'SSL过期',
                                    'ssl_recovery' => 'SSL恢复'
                                ];
                                $color = $typeColors[$log['alert_type']] ?? '#666';
                                $label = $typeLabels[$log['alert_type']] ?? $log['alert_type'];
                                ?>
                                <span style="color: <?php echo $color; ?>;"><?php echo $label; ?></span>
                            </td>
                            <td style="padding: 0.5rem; border-bottom: 1px solid #eee; font-size: 0.8rem; color: #666;"><?php echo htmlspecialchars(mb_substr($log['alert_message'], 0, 50)); ?><?php echo strlen($log['alert_message']) > 50 ? '...' : ''; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (empty($alertLogs)): ?>
                    <p style="text-align: center; color: #999; padding: 2rem;">暂无告警日志</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
    function switchLogTab(type) {
        // 隐藏所有面板
        document.getElementById('log-panel-monitor').style.display = 'none';
        document.getElementById('log-panel-email').style.display = 'none';
        document.getElementById('log-panel-alert').style.display = 'none';
        
        // 重置所有按钮样式
        document.getElementById('btn-log-monitor').style.background = '#e9ecef';
        document.getElementById('btn-log-monitor').style.color = '#333';
        document.getElementById('btn-log-email').style.background = '#e9ecef';
        document.getElementById('btn-log-email').style.color = '#333';
        document.getElementById('btn-log-alert').style.background = '#e9ecef';
        document.getElementById('btn-log-alert').style.color = '#333';
        
        // 显示选中面板和按钮高亮
        document.getElementById('log-panel-' + type).style.display = 'block';
        document.getElementById('btn-log-' + type).style.background = '#1a73e8';
        document.getElementById('btn-log-' + type).style.color = 'white';
    }
    
    // V3.3: 显示节点详情弹窗
    function showNodeDetails(websiteId, websiteName) {
        fetch('admin.php?api=node_details&website_id=' + websiteId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let html = '<div style="padding: 1rem;">';
                    html += '<h3 style="margin-bottom: 1rem;">📡 ' + websiteName + ' - 节点状态</h3>';
                    html += '<table style="width: 100%; border-collapse: collapse;">';
                    html += '<tr style="background: #f5f5f5;"><th style="padding: 8px; text-align: left;">节点</th><th style="padding: 8px; text-align: left;">状态</th><th style="padding: 8px; text-align: left;">最后检测</th><th style="padding: 8px; text-align: left;">HTTP</th><th style="padding: 8px; text-align: left;">响应</th></tr>';
                    
                    data.nodes.forEach(node => {
                        let statusText = '';
                        let statusColor = '#666';
                        
                        switch(node.status) {
                            case 'online':
                                statusText = '✅ 正常';
                                statusColor = '#28a745';
                                break;
                            case 'timeout':
                                statusText = '⚠️ 超时';
                                statusColor = '#ffc107';
                                break;
                            case 'offline':
                                statusText = '❌ 离线';
                                statusColor = '#dc3545';
                                break;
                            case 'no_data':
                                statusText = '⏸️ 无数据';
                                statusColor = '#999';
                                break;
                            default:
                                statusText = '❓ 未知';
                                statusColor = '#666';
                        }
                        
                        let lastCheck = node.last_check_time ? node.last_check_time.replace(' ', '<br>') : '-';
                        let httpStatus = node.http_status ? node.http_status.toUpperCase() : '-';
                        let respTime = node.response_time ? node.response_time + 'ms' : '-';
                        
                        html += '<tr style="border-bottom: 1px solid #eee;">';
                        html += '<td style="padding: 8px;">' + node.node_name + '</td>';
                        html += '<td style="padding: 8px; color: ' + statusColor + ';">' + statusText + '</td>';
                        html += '<td style="padding: 8px;">' + lastCheck + '</td>';
                        html += '<td style="padding: 8px;">' + httpStatus + '</td>';
                        html += '<td style="padding: 8px;">' + respTime + '</td>';
                        html += '</tr>';
                    });
                    
                    html += '</table></div>';
                    
                    // 创建弹窗
                    const modal = document.createElement('div');
                    modal.id = 'node-details-modal';
                    modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; display: flex; align-items: center; justify-content: center;';
                    modal.onclick = function(e) { if(e.target === modal) modal.remove(); };
                    
                    const modalContent = document.createElement('div');
                    modalContent.style.cssText = 'background: white; border-radius: 12px; padding: 1.5rem; max-width: 600px; max-height: 80vh; overflow-y: auto;';
                    modalContent.innerHTML = html + '<button onclick="document.getElementById(\'node-details-modal\').remove()" style="margin-top: 1rem; padding: 8px 16px; background: #1a73e8; color: white; border: none; border-radius: 6px; cursor: pointer;">关闭</button>';
                    
                    modal.appendChild(modalContent);
                    document.body.appendChild(modal);
                } else {
                    alert('获取节点详情失败: ' + data.error);
                }
            })
            .catch(err => alert('请求失败: ' + err));
    }
    </script>
    
    <!-- 数据清理 -->
    <div class="card" style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 1.5rem;">
        <h3 style="margin-bottom: 1rem; color: #333;">🗑️ 数据清理</h3>
        
        <!-- V3.6: 数据库大小统计 -->
        <div style="margin-bottom: 1rem; padding: 0.75rem; background: #e7f3ff; border-radius: 8px; display: inline-block;">
            <span style="color: #0066cc; font-weight: 500;">💾 数据库占用: <?php echo $dbSize['total']; ?> MB</span>
        </div>
        
        <div style="margin-bottom: 1.5rem;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr style="background: #f8f9fa;">
                    <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid #ddd;">数据类型</th>
                    <th style="padding: 0.75rem; text-align: center; border-bottom: 1px solid #ddd;">当前记录数</th>
                    <th style="padding: 0.75rem; text-align: center; border-bottom: 1px solid #ddd;">建议保留</th>
                </tr>
                <tr>
                    <td style="padding: 0.75rem; border-bottom: 1px solid #eee;">📋 监控日志</td>
                    <td style="padding: 0.75rem; text-align: center; border-bottom: 1px solid #eee;"><?php echo $stats['monitor_logs']; ?> 条</td>
                    <td style="padding: 0.75rem; text-align: center; border-bottom: 1px solid #eee;"><?php echo $settings['history_retention_days'] ?? 30; ?>天</td>
                </tr>
                <tr>
                    <td style="padding: 0.75rem; border-bottom: 1px solid #eee;">📧 邮件日志</td>
                    <td style="padding: 0.75rem; text-align: center; border-bottom: 1px solid #eee;"><?php echo $stats['email_logs']; ?> 条</td>
                    <td style="padding: 0.75rem; text-align: center; border-bottom: 1px solid #eee;"><?php echo $settings['history_retention_days'] ?? 30; ?>天</td>
                </tr>
                <tr>
                    <td style="padding: 0.75rem; border-bottom: 1px solid #eee;">🔔 告警日志</td>
                    <td style="padding: 0.75rem; text-align: center; border-bottom: 1px solid #eee;"><?php echo $stats['alert_logs']; ?> 条</td>
                    <td style="padding: 0.75rem; text-align: center; border-bottom: 1px solid #eee;"><?php echo $settings['history_retention_days'] ?? 30; ?>天</td>
                </tr>
            </table>
        </div>
        
        <form method="POST" onsubmit="return confirm('确定要清理数据吗？此操作不可恢复！');">
            <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 1rem;">
                <label style="font-weight: 500;">清理</label>
                <input type="number" name="clean_days" value="1" min="1" max="365" style="width: 60px; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px; text-align: center;">
                <label style="font-weight: 500;">天前的数据</label>
            </div>
            
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <button type="submit" name="clean_action" value="monitor_logs" class="btn" style="background: #17a2b8;">📋 清理监控日志</button>
                <button type="submit" name="clean_action" value="email_logs" class="btn" style="background: #17a2b8;">📧 清理邮件日志</button>
                <button type="submit" name="clean_action" value="alert_logs" class="btn" style="background: #17a2b8;">🔔 清理告警日志</button>
                <button type="submit" name="clean_action" value="all" class="btn" style="background: #dc3545;">🗑️ 清理全部</button>
            </div>
        </form>
    </div>
    
    <!-- 管理员管理 -->
    <div class="card" style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <h3 style="margin-bottom: 0.5rem; color: #333;">👥 管理员管理</h3>
        <p style="color: #666; margin-bottom: 1rem;">管理系统的管理员账号，包括添加新管理员、修改密码等。</p>
        <a href="admin_manage.php" class="btn btn-primary">进入管理员管理</a>
    </div>
    
    <div class="card" style="background: #f8f9fa; border-radius: 12px; padding: 1.5rem; margin-top: 1.5rem;">
        <h3 style="margin-bottom: 0.5rem; color: #666;">💡 提示</h3>
        <p style="color: #888; font-size: 0.875rem;">
            HTTP监控、SSL证书、邮件通知等设置已移至
            <a href="?page=alert_settings" style="color: #1a73e8;">🔔 告警设置</a>
            页面
        </p>
    </div>
</div>
