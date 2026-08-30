<?php
/**
 * 告警模板辅助类
 * 用于获取和渲染告警模板
 */
class AlertTemplateHelper {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * 获取模板
     * @param string $type email|telegram
     * @return array|null
     */
    public function getTemplate(string $type): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM alert_templates WHERE template_type = ? AND enabled = 1 LIMIT 1");
        $stmt->execute([$type]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * 渲染模板
     * @param string $template 模板内容
     * @param array $vars 变量 ['node' => '美国', 'time' => '2026-04-29', 'alerts' => '...']
     * @return string
     */
    public function render(string $template, array $vars): string {
        $result = $template;
        foreach ($vars as $key => $value) {
            $result = str_replace('{' . $key . '}', $value, $result);
        }
        return $result;
    }
    
    /**
     * 构建告警详情列表
     * @param array $alerts ['http_down' => [...], 'http_up' => [...], 'ssl_warning' => [...], 'whois_warning' => [...]]
     * @param string $format html|text
     * @return string
     */
    public function buildAlertsList(array $alerts, string $format = 'html'): string {
        $lines = [];
        
        if (!empty($alerts['http_down'])) {
            $lines[] = $format === 'html' 
                ? "<h2>HTTP异常 (" . count($alerts['http_down']) . "个)</h2>"
                : "HTTP异常 (" . count($alerts['http_down']) . "个)";
            foreach ($alerts['http_down'] as $a) {
                $lines[] = $format === 'html'
                    ? "<p><strong>{$a['name']}</strong> - {$a['error']}</p>"
                    : "{$a['name']} - {$a['error']}";
            }
            if ($format === 'text') $lines[] = "";
        }
        
        if (!empty($alerts['http_up'])) {
            $lines[] = $format === 'html'
                ? "<h2>HTTP恢复 (" . count($alerts['http_up']) . "个)</h2>"
                : "HTTP恢复 (" . count($alerts['http_up']) . "个)";
            foreach ($alerts['http_up'] as $a) {
                $lines[] = $format === 'html'
                    ? "<p><strong>{$a['name']}</strong> - 已恢复</p>"
                    : "{$a['name']} - 已恢复";
            }
            if ($format === 'text') $lines[] = "";
        }
        
        if (!empty($alerts['ssl_warning'])) {
            $lines[] = $format === 'html'
                ? "<h2>SSL证书提醒 (" . count($alerts['ssl_warning']) . "个)</h2>"
                : "SSL证书提醒 (" . count($alerts['ssl_warning']) . "个)";
            foreach ($alerts['ssl_warning'] as $a) {
                $lines[] = $format === 'html'
                    ? "<p><strong>{$a['name']}</strong> - SSL剩余 {$a['days']} 天</p>"
                    : "{$a['name']} - SSL剩余 {$a['days']} 天";
            }
        }
        
        if (!empty($alerts['whois_warning'])) {
            $lines[] = $format === 'html'
                ? "<h2>域名到期提醒 (" . count($alerts['whois_warning']) . "个)</h2>"
                : "域名到期提醒 (" . count($alerts['whois_warning']) . "个)";
            foreach ($alerts['whois_warning'] as $a) {
                $expireDate = $a['expire_date'] ?? '未知';
                $lines[] = $format === 'html'
                    ? "<p><strong>{$a['name']}</strong> - 剩余 {$a['days']} 天 (到期: {$expireDate})</p>"
                    : "{$a['name']} - 剩余 {$a['days']} 天 (到期: {$expireDate})";
            }
        }
        
        return implode("\n", $lines);
    }
}
