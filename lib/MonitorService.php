<?php
/**
 * 网站监控服务类 V2.1
 * 
 * 核心修复：分离HTTP和SSL检测
 * - HTTP检测：跟随301/302跳转，获取最终页面状态
 * - SSL检测：不跟随跳转，获取原始域名的证书（解决301跳转后证书错误问题）
 * 
 * 核心原则：
 * 1. 本地检测逻辑优先级最高，保持独立可用
 * 2. 在没有外部节点时，表现与单机版完全一致
 * 3. 支持多点分配，但核心检测逻辑不变
 * 
 * 使用方式：
 * - 本地检测：MonitorService::parallelCheck($sites)
 * - 探针检测：同样调用此方法
 */

class MonitorService {
    
    /**
     * 并发检测多个网站
     * 
     * @param array $sites 网站列表 [{id, url, check_http, check_ssl}, ...]
     * @param int $timeout 超时时间(秒)
     * @param int $maxRetry 最大重试次数
     * @return array 检测结果 [{site_id, http_status, http_code, ssl_days, ssl_error, response_time, retries}, ...]
     */
    public static function parallelCheck(array $sites, int $timeout = 10, int $maxRetry = 3): array {
        if (empty($sites)) return [];
        
        $results = [];
        
        // 第一轮并发检测
        $firstRound = self::doParallelCheck($sites, $timeout);
        
        // 收集失败的网站（V4.2：http_success=null 表示未启用HTTP检测，不参与重试）
        $failedSites = [];
        foreach ($firstRound as $siteId => $result) {
            $results[$siteId] = $result;
            if ($result['http_success'] === false) {
                // 找到对应的site信息用于重试
                foreach ($sites as $site) {
                    if ($site['id'] == $siteId) {
                        $failedSites[] = $site;
                        break;
                    }
                }
            }
        }
        
        // 并行重试
        $retryCount = 0;
        while (!empty($failedSites) && $retryCount < $maxRetry) {
            $retryCount++;
            $retryResults = self::doParallelCheck($failedSites, $timeout);
            
            $stillFailed = [];
            foreach ($retryResults as $siteId => $result) {
                $results[$siteId] = $result;
                $results[$siteId]['retries'] = $retryCount;
                
                if ($result['http_success'] === false) {
                    foreach ($failedSites as $site) {
                        if ($site['id'] == $siteId) {
                            $stillFailed[] = $site;
                            break;
                        }
                    }
                }
            }
            
            $failedSites = $stillFailed;
            
            if (!empty($failedSites) && $retryCount < $maxRetry) {
                usleep(500000); // 0.5秒
            }
        }
        
        return $results;
    }
    
    /**
     * 执行一轮并发检测
     * 修复：分离HTTP和SSL检测，HTTP跟随跳转，SSL不跟随跳转（解决301问题）
     */
    private static function doParallelCheck(array $sites, int $timeout): array {
        if (empty($sites)) return [];
        
        $results = [];
        $httpResults = [];
        $sslResults = [];
        
        // ========== 第一轮：HTTP检测（跟随跳转，获取最终页面状态） ==========
        $mh = curl_multi_init();
        $handles = [];
        $redirectChains = []; // 每个站点的 Location 跳转链 [site_id => [url1, url2, ...]]
        
        foreach ($sites as $site) {
            // V4.2修复：尊重 check_http 字段，未启用HTTP检测的网站直接跳过（不检测、不告警）
            $checkHttp = isset($site['check_http']) ? (int)$site['check_http'] : 1;
            if (!$checkHttp) {
                $httpResults[$site['id']] = [
                    'http_success' => null,
                    'http_code' => null,
                    'http_error' => null,
                    'http_message' => null,
                    'response_time' => null,
                    'skipped' => true
                ];
                continue;
            }
            $ch = curl_init($site['url']);
            $redirectChains[$site['id']] = [];
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_FOLLOWLOCATION => true,      // HTTP跟随跳转
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_HEADERFUNCTION => function($curl, $header) use (&$redirectChains, $site) {
                    $len = strlen($header);
                    $parts = explode(':', $header, 2);
                    if (count($parts) === 2 && strcasecmp(trim($parts[0]), 'Location') === 0) {
                        $redirectChains[$site['id']][] = trim($parts[1]);
                    }
                    return $len;
                }
            ]);
            
            curl_multi_add_handle($mh, $ch);
            $handles[$site['id']] = [
                'ch' => $ch,
                'site' => $site
            ];
        }
        
        // 并发执行HTTP检测
        $running = null;
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh);
        } while ($running > 0);
        
        // 收集HTTP结果
        foreach ($handles as $siteId => $handle) {
            $ch = $handle['ch'];
            $site = $handle['site'];
            
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $redirectCount = curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
            $error = curl_error($ch);
            $responseTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000;
            
            // ===== 方案A 修复（2026-08-16）=====
            // 原逻辑：$httpSuccess = ($httpCode >= 200 && $httpCode < 400);
            // 问题：跨域跳转后被目标站拦截（如 558sm.com→u754.com→403），最终 403 误判源站故障
            // 新逻辑：通过 CURLOPT_HEADERFUNCTION 捕获 Location 头，识别跨域跳转；
            //         跨域跳转场景单独探测源站状态，源站 2xx/3xx 即视为正常
            // v2 修复（2026-08-16 龙虾二号审查反馈）：
            //   1. 遍历全部 Location 链，相对路径按源站 URL 解析，直任一跨域即判跨域
            //   2. 探测源站改用 GET 语义（CURLOPT_RANGE 0-0）而非 HEAD，避免 405 误判
            $srcHost = parse_url($site['url'], PHP_URL_HOST) ?: '';
            $srcPort = parse_url($site['url'], PHP_URL_PORT);
            $srcPort = $srcPort ?: (parse_url($site['url'], PHP_URL_SCHEME) === 'https' ? 443 : 80);
            $srcScheme = parse_url($site['url'], PHP_URL_SCHEME) ?: 'http';
            $srcAuthority = strtolower($srcHost) . ':' . $srcPort; // 包含端口的唯一标识
            $crossDomainRedirect = false;
            $dstHost = '';
            
            if ($redirectCount > 0 && !empty($redirectChains[$siteId])) {
                // 遍历所有 Location 链接，相对路径按源站 URL 解析
                foreach ($redirectChains[$siteId] as $location) {
                    $resolved = $location;
                    // 协议相对 "//host/path" → 补 scheme
                    if (strpos($resolved, '//') === 0) {
                        $resolved = $srcScheme . ':' . $resolved;
                    }
                    // 相对路径 "/path" → 按源站解析
                    if (strpos($resolved, '/') === 0 && parse_url($resolved, PHP_URL_HOST) === null) {
                        $resolved = $srcScheme . '://' . $srcHost . $resolved;
                    }
                    $resolvedHost = parse_url($resolved, PHP_URL_HOST) ?: '';
                    $resolvedPort = parse_url($resolved, PHP_URL_PORT);
                    $resolvedPort = $resolvedPort ?: (parse_url($resolved, PHP_URL_SCHEME) === 'https' ? 443 : 80);
                    $resolvedAuthority = strtolower($resolvedHost) . ':' . $resolvedPort;
                    if ($resolvedHost && strcasecmp($srcAuthority, $resolvedAuthority) !== 0) {
                        $crossDomainRedirect = true;
                        $dstHost = $resolvedHost . ($resolvedPort != ($resolvedHost && parse_url($resolved, PHP_URL_SCHEME) === 'https' ? 443 : 80) ? ':' . $resolvedPort : '');
                        break; // 找到一个跨域跳转即可
                    }
                }
            }
            
            if ($crossDomainRedirect) {
                // 不跟随重定向单独探测源站真实状态（GET 语义，避免 HEAD 405）
                $srcCh = curl_init($site['url']);
                curl_setopt_array($srcCh, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_TIMEOUT => 5,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_CONNECTTIMEOUT => 3,
                    CURLOPT_RANGE => '0-0'  // GET 语义，只取 1 字节
                ]);
                curl_exec($srcCh);
                $srcCode = (int)curl_getinfo($srcCh, CURLINFO_HTTP_CODE);
                curl_close($srcCh);
                
                $httpSuccess = ($srcCode >= 200 && $srcCode < 400);
                $httpMessage = sprintf(
                    '源站%d→跨域跳转→%s→最终%d',
                    $srcCode, $dstHost, $httpCode ?: 0
                );
            } else {
                $httpSuccess = ($httpCode >= 200 && $httpCode < 400);
                $httpMessage = null;
            }
            
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            
            $httpResults[$siteId] = [
                'http_success' => $httpSuccess,
                'http_code' => $httpCode ?: 0,
                'http_error' => $error ?: null,
                'http_message' => $httpMessage,
                'response_time' => round($responseTime)
            ];
        }
        
        curl_multi_close($mh);
        
        // ========== 第二轮：SSL检测（不跟随跳转，获取原始域名证书） ==========
        // 只对HTTPS网站发起SSL检测请求
        $mh2 = curl_multi_init();
        $sslHandles = [];
        
        foreach ($sites as $site) {
            // V4.2修复：尊重 check_ssl 字段，未启用SSL检测的网站直接跳过（不检测、不告警）
            $checkSsl = isset($site['check_ssl']) ? (int)$site['check_ssl'] : 1;
            if (!$checkSsl) {
                $sslResults[$site['id']] = [
                    'ssl_days' => null,
                    'ssl_error' => null,
                    'skipped' => true
                ];
                continue;
            }
            // 从URL提取host和port
            $url = $site['url'];
            $parsed = parse_url($url);
            $host = $parsed['host'] ?? $url;
            $port = isset($parsed['port']) ? (int)$parsed['port'] : 443;
            
            // 只检测HTTPS网站
            if (!isset($parsed['scheme']) || $parsed['scheme'] !== 'https') {
                $sslResults[$site['id']] = [
                    'ssl_days' => null,
                    'ssl_error' => '非HTTPS网站'
                ];
                continue;
            }
            
            // 发起SSL检测请求（不跟随跳转）
            // V4.3.1: 修复自定义端口（port=4433 等）的 SSL 检测
            $sslUrl = "https://{$host}:{$port}";
            $ch = curl_init($sslUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_FOLLOWLOCATION => false,     // SSL不跟随跳转！这是关键修复
                CURLOPT_CERTINFO => true,            // 获取证书信息
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_NOBODY => true               // 只获取头信息
            ]);
            
            curl_multi_add_handle($mh2, $ch);
            $sslHandles[$site['id']] = [
                'ch' => $ch,
                'site' => $site
            ];
        }
        
        // 并发执行SSL检测
        if (count($sslHandles) > 0) {
            $running = null;
            do {
                curl_multi_exec($mh2, $running);
                curl_multi_select($mh2);
            } while ($running > 0);
            
            // 收集SSL结果
            foreach ($sslHandles as $siteId => $handle) {
                $ch = $handle['ch'];
                $site = $handle['site'];
                
                $certInfo = curl_getinfo($ch, CURLINFO_CERTINFO);
                $error = curl_error($ch);
                
                curl_multi_remove_handle($mh2, $ch);
                curl_close($ch);
                
                // 解析SSL证书
                $sslDays = null;
                $sslError = null;
                if (!empty($certInfo)) {
                    $sslResult = self::parseCertInfo($certInfo);
                    $sslDays = $sslResult['days'];
                    $sslError = $sslResult['error'];
                } elseif ($error && strpos($error, 'SSL') !== false) {
                    $sslError = self::parseSSLError($error);
                }
                
                $sslResults[$siteId] = [
                    'ssl_days' => $sslDays,
                    'ssl_error' => $sslError
                ];
            }
        }
        
        curl_multi_close($mh2);
        
        // ========== 合并HTTP和SSL结果 ==========
        foreach ($sites as $site) {
            $siteId = $site['id'];
            $http = $httpResults[$siteId] ?? [
                'http_success' => false,
                'http_code' => 0,
                'http_error' => '检测失败',
                'response_time' => 0
            ];
            $ssl = $sslResults[$siteId] ?? [
                'ssl_days' => null,
                'ssl_error' => '检测失败'
            ];
            
            $httpSkipped = !empty($http['skipped']);
            $sslSkipped = !empty($ssl['skipped']);
            
            $results[$siteId] = [
                'site_id' => $siteId,
                'url' => $site['url'],
                'http_success' => $http['http_success'],
                'http_status' => $httpSkipped ? 'skipped' : ($http['http_success'] ? 'up' : 'down'),
                'http_skipped' => $httpSkipped,
                'ssl_skipped' => $sslSkipped,
                'http_code' => $http['http_code'],
                'http_error' => $http['http_error'],
                'http_message' => $http['http_message'] ?? null,
                'ssl_days' => $ssl['ssl_days'],
                'ssl_error' => $ssl['ssl_error'],
                'response_time' => $http['response_time'],
                'retries' => 0,
                'checked_at' => date('Y-m-d H:i:s')
            ];
        }
        
        return $results;
    }
    
    /**
     * 解析SSL证书信息
     */
    private static function parseCertInfo(array $certInfo): array {
        $minDays = PHP_INT_MAX;
        $error = null;
        
        foreach ($certInfo as $cert) {
            if (isset($cert['Expire date'])) {
                try {
                    $expireTime = strtotime($cert['Expire date']);
                    if ($expireTime === false) continue;
                    
                    $days = floor(($expireTime - time()) / 86400);
                    
                    if ($days <= 0) {
                        $error = '证书已过期';
                    } elseif ($days <= 7) {
                        $error = "证书即将过期(剩余{$days}天)";
                    }
                    
                    // V1.1: 过期时也要更新 $minDays（可能是负数），保证 last_ssl_days 真实反映状态
                    if ($days < $minDays) {
                        $minDays = $days;
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
        }
        
        return [
            'days' => $minDays < PHP_INT_MAX ? $minDays : null,
            'error' => $error
        ];
    }
    
    /**
     * 解析SSL错误信息
     */
    private static function parseSSLError(string $error): string {
        $errorMap = [
            'certificate has expired' => '证书已过期',
            'certificate is not yet valid' => '证书尚未生效',
            'unable to get local issuer certificate' => '无法验证证书链',
            'certificate verify failed' => '证书验证失败',
            'subject alt name' => '域名不匹配',
            'self signed certificate' => '自签名证书',
            'SSL certificate problem' => 'SSL证书问题'
        ];
        
        foreach ($errorMap as $key => $msg) {
            if (stripos($error, $key) !== false) {
                return $msg;
            }
        }
        
        return 'SSL握手失败: ' . $error;
    }
    
    /**
     * 检测单个网站（用于测试或特殊场景）
     */
    public static function checkSingle(array $site, int $timeout = 10): array {
        $results = self::parallelCheck([$site], $timeout, 0);
        return $results[$site['id']] ?? null;
    }
    
    /**
     * 格式化结果为JSON（用于探针上报）
     */
    public static function formatForReport(array $results): string {
        $data = [];
        foreach ($results as $siteId => $result) {
            $data[] = [
                'site_id' => $siteId,
                'http_status' => $result['http_status'],
                'http_code' => $result['http_code'],
                'http_error' => $result['http_error'],
                'ssl_days' => $result['ssl_days'],
                'ssl_error' => $result['ssl_error'],
                'response_time' => $result['response_time'],
                'retries' => $result['retries'],
                'checked_at' => $result['checked_at']
            ];
        }
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * 本地快速检测（单机模式专用）
     * 直接调用，无需任何分布式逻辑
     * 
     * @param array $siteIds 要检测的网站ID列表，为空则检测所有启用的网站
     * @param PDO $conn 数据库连接
     * @return array 检测结果
     */
    public static function localCheck(array $siteIds = [], $conn = null): array {
        // 如果没有传入数据库连接，尝试获取
        if ($conn === null) {
            require_once __DIR__ . "/../Database.php";
            $db = Database::getInstance();
            $conn = $db->getConnection();
        }
        
        // 获取配置
        $stmt = $conn->query("SELECT setting_name, setting_value FROM alert_settings");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_name']] = $row['setting_value'];
        }
        
        $timeout = (int)($settings['http_timeout_seconds'] ?? 10);
        $maxRetry = (int)($settings['max_retry_count'] ?? 3);
        
        // 构建查询
        $sql = "SELECT id, name, url, check_http, check_ssl FROM websites WHERE enabled = 1";
        if (!empty($siteIds)) {
            $placeholders = implode(',', array_fill(0, count($siteIds), '?'));
            $sql .= " AND id IN ($placeholders)";
            $stmt = $conn->prepare($sql);
            $stmt->execute($siteIds);
        } else {
            $stmt = $conn->query($sql);
        }
        
        $sites = $stmt->fetchAll();
        
        if (empty($sites)) {
            return ['checked' => 0, 'results' => []];
        }
        
        $checkSites = array_map(function($site) {
            return [
                'id' => $site['id'],
                'url' => $site['url'],
                'check_http' => $site['check_http'] ?? true,
                'check_ssl' => $site['check_ssl'] ?? true
            ];
        }, $sites);
        
        // 执行检测
        $results = self::parallelCheck($checkSites, $timeout, $maxRetry);
        
        return [
            'checked' => count($sites),
            'results' => $results,
            'mode' => 'local',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}