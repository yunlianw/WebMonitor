<?php
/**
 * 告警模板管理页面
 * 支持编辑邮件和Telegram通知模板
 */

// 处理模板保存
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_template') {
        $templateId = intval($_POST['template_id'] ?? 0);
        $subjectTemplate = $_POST['subject_template'] ?? '';
        $bodyTemplate = $_POST['body_template'] ?? '';
        
        if ($templateId > 0) {
            $stmt = $conn->prepare("UPDATE alert_templates SET subject_template = ?, body_template = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$subjectTemplate, $bodyTemplate, $templateId]);
            $message = "✅ 模板已保存";
            $messageType = "success";
        }
    }
    
    if ($_POST['action'] === 'reset_template') {
        $templateId = intval($_POST['template_id'] ?? 0);
        $templateType = $_POST['template_type'] ?? '';
        
        // 重置为默认模板
        if ($templateType === 'email') {
            $defaultSubject = '网站状态通知 [{node}] - {time}';
            $defaultBody = "<h1>网站状态通知</h1>\n<p>节点: {node}</p>\n<p>时间: {time}</p>\n\n{alerts}";
        } else {
            $defaultSubject = '';
            $defaultBody = "网站状态通知\n节点: {node}\n时间: {time}\n\n{alerts}";
        }
        
        $stmt = $conn->prepare("UPDATE alert_templates SET subject_template = ?, body_template = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$defaultSubject, $defaultBody, $templateId]);
        $message = "✅ 模板已重置为默认";
        $messageType = "success";
    }
}

// 获取所有模板
$stmt = $conn->query("SELECT * FROM alert_templates ORDER BY template_type, id");
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="section">
    <h2>📝 告警模板配置</h2>
    
    <?php if (isset($message)): ?>
    <div class="message <?php echo $messageType ?? 'info'; ?>">
        <?php echo $message; ?>
    </div>
    <?php endif; ?>
    
    <div style="background: #FFF9F0; border-radius: 12px; padding: 16px; margin-bottom: 20px; border: 1px solid #FFE0B2;">
        <p style="color: #FF9500; font-size: 0.875rem; margin: 0;">
            <strong>可用变量：</strong>
            <code style="background: #E5E5EA; padding: 2px 6px; border-radius: 4px;">{node}</code> 节点名称 · 
            <code style="background: #E5E5EA; padding: 2px 6px; border-radius: 4px;">{time}</code> 告警时间 · 
            <code style="background: #E5E5EA; padding: 2px 6px; border-radius: 4px;">{alerts}</code> 告警详情列表
        </p>
    </div>
    
    <?php foreach ($templates as $tpl): ?>
    <div style="background: #FFFFFF; border-radius: 16px; padding: 24px; margin-bottom: 16px; border: 1px solid #F2F2F7;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="margin: 0; color: #1D1D1F; font-size: 1rem;">
                <?php echo $tpl['template_type'] === 'email' ? '📧 邮件模板' : '📱 Telegram模板'; ?>
            </h3>
            <span style="background: <?php echo $tpl['template_type'] === 'email' ? 'rgba(0,122,255,0.12)' : 'rgba(52,199,89,0.12)'; ?>; color: <?php echo $tpl['template_type'] === 'email' ? '#007AFF' : '#34C759'; ?>; padding: 4px 12px; border-radius: 8px; font-size: 0.75rem;">
                <?php echo $tpl['template_type']; ?>
            </span>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="save_template">
            <input type="hidden" name="template_id" value="<?php echo $tpl['id']; ?>">
            
            <?php if ($tpl['template_type'] === 'email'): ?>
            <div class="form-group">
                <label>邮件标题模板</label>
                <input type="text" name="subject_template" value="<?php echo htmlspecialchars($tpl['subject_template'] ?? ''); ?>" placeholder="网站状态通知 [{node}] - {time}">
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label>内容模板 <?php echo $tpl['template_type'] === 'email' ? '(支持HTML)' : '(纯文本)'; ?></label>
                <textarea name="body_template" rows="8" style="font-family: 'SF Mono', Menlo, monospace; font-size: 0.875rem;"><?php echo htmlspecialchars($tpl['body_template'] ?? ''); ?></textarea>
            </div>
            
            <div style="display: flex; gap: 12px;">
                <button type="submit" class="btn btn-success">保存模板</button>
                <button type="submit" name="action" value="reset_template" class="btn btn-secondary" onclick="return confirm('确定要重置为默认模板吗？');">重置默认</button>
            </div>
        </form>
    </div>
    <?php endforeach; ?>
</div>

<div class="section">
    <h2>📋 模板说明</h2>
    
    <div style="background: #F9F9FB; border-radius: 12px; padding: 20px; border: 1px solid #F2F2F7;">
        <h3 style="color: #1D1D1F; font-size: 1rem; margin-bottom: 12px;">告警类型说明</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>类型</th>
                    <th>触发条件</th>
                    <th>模板变量</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>HTTP异常</td>
                    <td>网站无法访问或返回错误状态码</td>
                    <td>网站名称、HTTP状态码、错误信息</td>
                </tr>
                <tr>
                    <td>HTTP恢复</td>
                    <td>网站从异常恢复为正常</td>
                    <td>网站名称</td>
                </tr>
                <tr>
                    <td>SSL证书提醒</td>
                    <td>SSL证书剩余天数少于阈值</td>
                    <td>网站名称、剩余天数</td>
                </tr>
                <tr>
                    <td>域名到期提醒</td>
                    <td>域名注册剩余天数少于阈值</td>
                    <td>网站名称、剩余天数、到期日期</td>
                </tr>
            </tbody>
        </table>
        
        <h3 style="color: #1D1D1F; font-size: 1rem; margin: 20px 0 12px 0;">邮件示例</h3>
        <pre style="background: #FFFFFF; padding: 16px; border-radius: 8px; font-size: 0.8125rem; overflow-x: auto; border: 1px solid #F2F2F7;">网站状态通知
节点: 美国节点
时间: 2026-04-29 13:30:00

HTTP异常 (2个)
我的网站 - HTTP 444 (重试3次)
测试站点 - 连接超时 (10s)

HTTP恢复 (1个)
我的网站 - 已恢复

SSL证书提醒 (1个)
我的网站 - SSL剩余 15 天

域名到期提醒 (1个)
测试域名 - 剩余 30 天 (到期: 2026-05-29)</pre>
    </div>
</div>