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
    
    // 获取数据库大小
    $dbSize = [];
    $stmt = $conn->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size FROM information_schema.tables WHERE table_schema = 'yunlian'");
    $dbSize['total'] = $stmt->fetch()['size'] ?? 0;
    ?>
    
    <?php if ($cleanMessage): ?>
    <div class="message <?php echo $cleanMessageType === 'success' ? 'success' : 'error'; ?>">
        <?php echo $cleanMessage; ?>
    </div>
    <?php endif; ?>
    
    <!-- 历史记录设置 -->
    <div style="background: #FFFFFF; border-radius: 16px; padding: 24px; border: 1px solid #F2F2F7; margin-bottom: 24px;">
        <h3 style="margin-bottom: 16px; color: #1D1D1F; font-size: 1rem; font-weight: 600;">📊 历史记录设置</h3>
        
        <form method="POST">
            <input type="hidden" name="action" value="update_settings">
            
            <div class="form-group">
                <label>历史记录保留天数</label>
                <input type="number" name="history_retention_days" value="<?php echo htmlspecialchars($settings['history_retention_days'] ?? 30); ?>" min="1" max="365" style="max-width: 200px;">
                <p style="color: #86868B; font-size: 0.875rem; margin-top: 4px;">监控历史记录保留的天数</p>
            </div>
            
            <button type="submit" class="btn btn-success">💾 保存设置</button>
        </form>
    </div>
    
    <!-- 日志查看 -->
    <div style="background: #FFFFFF; border-radius: 16px; padding: 24px; border: 1px solid #F2F2F7; margin-bottom: 24px;">
        <h3 style="margin-bottom: 16px; color: #1D1D1F; font-size: 1rem; font-weight: 600;">📜 日志查看 <small style="color: #86868B; font-size: 0.75rem;">最新50条记录</small></h3>
        
        <div class="filter-tabs" style="margin-bottom: 16px;">
            <button type="button" onclick="switchLogTab('monitor')" id="btn-log-monitor" class="filter-tab active">📋 监控日志</button>
            <button type="button" onclick="switchLogTab('email')" id="btn-log-email" class="filter-tab">📧 邮件日志</button>
            <button type="button" onclick="switchLogTab('alert')" id="btn-log-alert" class="filter-tab">🔔 告警日志</button>
        </div>
        
        <!-- 监控日志 -->
        <div id="log-panel-monitor">
            <?php
            $logNodeFilter = isset($_GET['log_node']) ? $_GET['log_node'] : '';
            $allNodesForLog = $conn->query("SELECT id, name, type FROM nodes ORDER BY id")->fetchAll();
            
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
            <div style="display: flex; gap: 8px; margin-bottom: 12px; align-items: center;">
                <span style="color: #86868B; font-size: 0.875rem;">筛选：</span>
                <select onchange="window.location.href='?page=settings&log_node='+this.value">
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
                <table class="table">
                    <thead>
                        <tr>
                            <th>时间</th>
                            <th>网站</th>
                            <th style="text-align: center;">节点</th>
                            <th style="text-align: center;">HTTP</th>
                            <th style="text-align: center;">SSL(天)</th>
                            <th style="text-align: center;">响应</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monitorLogs as $log): ?>
                        <tr>
                            <td style="white-space: nowrap; color: #86868B;"><?php echo date('m-d H:i', strtotime($log['checked_at'])); ?></td>
                            <td><?php echo htmlspecialchars($log['website_name'] ?? 'ID:'.$log['website_id']); ?></td>
                            <td style="text-align: center;">
                                <?php if ($log['node_id'] == 0 || $log['node_id'] === null): ?>
                                    <span class="type-label website">主控</span>
                                <?php else: ?>
                                    <span class="type-label domain"><?php echo htmlspecialchars($log['node_name'] ?? '节点'.$log['node_id']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($log['http_status'] === 'up'): ?>
                                    <span style="color: #34C759;">✓ <?php echo $log['http_code']; ?></span>
                                <?php elseif ($log['http_status'] === 'down'): ?>
                                    <span style="color: #FF3B30;">✗ <?php echo $log['http_code'] ?: '超时'; ?></span>
                                <?php else: ?>
                                    <span style="color: #86868B;">-</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($log['ssl_days'] !== null): ?>
                                    <?php if ($log['ssl_days'] > 30): ?>
                                        <span style="color: #34C759;"><?php echo $log['ssl_days']; ?></span>
                                    <?php elseif ($log['ssl_days'] > 7): ?>
                                        <span style="color: #FF9500;"><?php echo $log['ssl_days']; ?></span>
                                    <?php else: ?>
                                        <span style="color: #FF3B30;"><?php echo $log['ssl_days']; ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #86868B;">-</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($log['response_time']): ?>
                                    <?php echo $log['response_time']; ?>ms
                                <?php else: ?>
                                    <span style="color: #86868B;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (empty($monitorLogs)): ?>
                    <p style="text-align: center; color: #86868B; padding: 32px;">暂无监控日志</p>
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
                <table class="table">
                    <thead>
                        <tr>
                            <th>时间</th>
                            <th>网站</th>
                            <th>类型</th>
                            <th>收件人</th>
                            <th style="text-align: center;">状态</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($emailLogs as $log): ?>
                        <tr>
                            <td style="white-space: nowrap; color: #86868B;"><?php echo date('m-d H:i', strtotime($log['sent_at'])); ?></td>
                            <td><?php echo htmlspecialchars($log['website_name'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($log['alert_type']); ?></td>
                            <td style="font-size: 0.8125rem; color: #86868B;"><?php echo htmlspecialchars($log['recipients']); ?></td>
                            <td style="text-align: center;">
                                <?php if ($log['status'] === 'success'): ?>
                                    <span style="color: #34C759;">✓ 成功</span>
                                <?php else: ?>
                                    <span style="color: #FF3B30;">✗ 失败</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (empty($emailLogs)): ?>
                    <p style="text-align: center; color: #86868B; padding: 32px;">暂无邮件日志</p>
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
                <table class="table">
                    <thead>
                        <tr>
                            <th>时间</th>
                            <th>网站</th>
                            <th>类型</th>
                            <th>消息</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alertLogs as $log): ?>
                        <tr>
                            <td style="white-space: nowrap; color: #86868B;"><?php echo date('m-d H:i', strtotime($log['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($log['website_name'] ?? '-'); ?></td>
                            <td>
                                <?php 
                                $typeColors = [
                                    'http_down' => '#FF3B30',
                                    'http_recovery' => '#34C759',
                                    'ssl_warning' => '#FF9500',
                                    'ssl_expired' => '#FF3B30',
                                    'ssl_recovery' => '#34C759'
                                ];
                                $typeLabels = [
                                    'http_down' => 'HTTP异常',
                                    'http_recovery' => 'HTTP恢复',
                                    'ssl_warning' => 'SSL警告',
                                    'ssl_expired' => 'SSL过期',
                                    'ssl_recovery' => 'SSL恢复'
                                ];
                                $color = $typeColors[$log['alert_type']] ?? '#86868B';
                                $label = $typeLabels[$log['alert_type']] ?? $log['alert_type'];
                                ?>
                                <span style="color: <?php echo $color; ?>;"><?php echo $label; ?></span>
                            </td>
                            <td style="font-size: 0.8125rem; color: #86868B;"><?php echo htmlspecialchars(mb_substr($log['alert_message'], 0, 50)); ?><?php echo strlen($log['alert_message']) > 50 ? '...' : ''; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (empty($alertLogs)): ?>
                    <p style="text-align: center; color: #86868B; padding: 32px;">暂无告警日志</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
    function switchLogTab(type) {
        document.getElementById('log-panel-monitor').style.display = 'none';
        document.getElementById('log-panel-email').style.display = 'none';
        document.getElementById('log-panel-alert').style.display = 'none';
        
        document.querySelectorAll('.filter-tab').forEach(btn => btn.classList.remove('active'));
        
        document.getElementById('log-panel-' + type).style.display = 'block';
        document.getElementById('btn-log-' + type).classList.add('active');
    }
    </script>
    
    <!-- 数据清理 -->
    <div style="background: #FFFFFF; border-radius: 16px; padding: 24px; border: 1px solid #F2F2F7; margin-bottom: 24px;">
        <h3 style="margin-bottom: 16px; color: #1D1D1F; font-size: 1rem; font-weight: 600;">🗑️ 数据清理</h3>
        
        <div style="margin-bottom: 16px; padding: 12px 16px; background: rgba(0,122,255,0.08); border-radius: 12px; display: inline-block;">
            <span style="color: #007AFF; font-weight: 500;">💾 数据库占用: <?php echo $dbSize['total']; ?> MB</span>
        </div>
        
        <table class="table" style="margin-bottom: 20px;">
            <thead>
                <tr>
                    <th>数据类型</th>
                    <th style="text-align: center;">当前记录数</th>
                    <th style="text-align: center;">建议保留</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>📋 监控日志</td>
                    <td style="text-align: center; font-weight: 500;"><?php echo $stats['monitor_logs']; ?> 条</td>
                    <td style="text-align: center;"><?php echo $settings['history_retention_days'] ?? 30; ?>天</td>
                </tr>
                <tr>
                    <td>📧 邮件日志</td>
                    <td style="text-align: center; font-weight: 500;"><?php echo $stats['email_logs']; ?> 条</td>
                    <td style="text-align: center;"><?php echo $settings['history_retention_days'] ?? 30; ?>天</td>
                </tr>
                <tr>
                    <td>🔔 告警日志</td>
                    <td style="text-align: center; font-weight: 500;"><?php echo $stats['alert_logs']; ?> 条</td>
                    <td style="text-align: center;"><?php echo $settings['history_retention_days'] ?? 30; ?>天</td>
                </tr>
            </tbody>
        </table>
        
        <form method="POST" onsubmit="return confirm('确定要清理数据吗？此操作不可恢复！');">
            <div style="display: flex; gap: 12px; align-items: center; margin-bottom: 16px;">
                <label style="font-weight: 500;">清理</label>
                <input type="number" name="clean_days" value="1" min="1" max="365" style="width: 60px; text-align: center;">
                <label style="font-weight: 500;">天前的数据</label>
            </div>
            
            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                <button type="submit" name="clean_action" value="monitor_logs" class="btn" style="background: #5AC8FA;">📋 清理监控日志</button>
                <button type="submit" name="clean_action" value="email_logs" class="btn" style="background: #5AC8FA;">📧 清理邮件日志</button>
                <button type="submit" name="clean_action" value="alert_logs" class="btn" style="background: #5AC8FA;">🔔 清理告警日志</button>
                <button type="submit" name="clean_action" value="all" class="btn btn-danger">🗑️ 清理全部</button>
            </div>
        </form>
    </div>
    
    <!-- 管理员管理 -->
    <div style="background: #FFFFFF; border-radius: 16px; padding: 24px; border: 1px solid #F2F2F7;">
        <h3 style="margin-bottom: 8px; color: #1D1D1F; font-size: 1rem; font-weight: 600;">👥 管理员管理</h3>
        <p style="color: #86868B; margin-bottom: 16px;">管理系统的管理员账号，包括添加新管理员、修改密码等。</p>
        <a href="admin_manage.php" class="btn">进入管理员管理</a>
    </div>
    
    <div style="background: #F9F9FB; border-radius: 16px; padding: 20px; margin-top: 24px; border: 1px solid #F2F2F7;">
        <h3 style="margin-bottom: 8px; color: #1D1D1F; font-size: 1rem; font-weight: 600;">💡 提示</h3>
        <p style="color: #86868B; font-size: 0.875rem; margin: 0;">
            HTTP监控、SSL证书、邮件通知等设置已移至
            <a href="?page=alert_settings" style="color: #007AFF;">🔔 告警设置</a>
            页面
        </p>
    </div>
</div>