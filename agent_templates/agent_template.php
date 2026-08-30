<?php
/**
 * 探针端脚本 V2.0 - 零配置部署版
 * 
 * 部署方式：
 * 1. 从主控后台下载此文件（已内置配置）
 * 2. 上传到节点服务器
 * 3. 宝塔添加计划任务：每分钟访问一次
 * 4. 完成！
 * 
 * 支持模式：
 * - Pull模式：主控主动请求此探针
 * - Push模式：此探针主动向主控领任务
 */

// ==================== 配置区域（主控自动填充） ====================
$config = [
    'master_url' => '{{MASTER_URL}}',
    'node_id' => '{{NODE_ID}}',
    'api_key' => '{{API_KEY}}'
];
// ================================================================

// 安全检查：如果配置未填充，直接404
if (strpos($config['api_key'], '{{') !== false || strpos($config['node_id'], '{{') !== false) {
    http_response_code(404);
    exit('404 Not Found');
}

// 密钥验证（支持URL参数传入或配置内置）
$inputKey = $_GET['key'] ?? $_POST['key'] ?? '';
$validKey = $config['api_key'] ?? '';

if (!empty($validKey) && $inputKey !== $validKey) {
    http_response_code(404);
    exit('404 Not Found');
}

header("Content-Type: application/json; charset=utf-8");

$action = $_GET['action'] ?? $_POST['action'] ?? 'push';

switch ($action) {
    case 'check':
        // Pull模式：接收主控发来的检测任务
        handlePullCheck();
        break;
    case 'push':
        // Push模式：主动向主控领取任务并上报
        handlePushMode();
        break;
    case 'status':
        // 状态检查
        handleStatus();
        break;
    case 'test':
        // 测试模式
        handleTest();
        break;
    default:
        jsonResponse(false, "未知操作: $action");
}

/**
 * Pull模式 - 接收主控请求执行检测
 */
function handlePullCheck() {
    global $config;
    
    // 验证密钥
    $key = $_GET['key'] ?? $_POST['key'] ?? '';
    if (empty($key)) {
        jsonResponse(false, "缺少密钥");
    }
    
    // 获取任务数据
    $tasksJson = $_POST['tasks'] ?? file_get_contents('php://input');
    $tasks = json_decode($tasksJson, true);
    
    if (!$tasks || !is_array($tasks)) {
        jsonResponse(false, "任务数据格式错误");
    }
    
    // 获取配置
    $timeout = intval($_GET['timeout'] ?? $_POST['timeout'] ?? 10);
    $maxRetry = intval($_GET['max_retry'] ?? $_POST['max_retry'] ?? 3);
    
    // 执行检测
    $results = parallelCheck($tasks, $timeout, $maxRetry);
    
    // V3.6: 为每个结果添加 node_id
    foreach ($results as &$result) {
        $result['node_id'] = $config['node_id'];
    }
    
    jsonResponse(true, "检测完成", [
        'results' => array_values($results),
        'total' => count($results),
        'node_id' => $config['node_id'],  // V3.6: 返回节点ID
        'agent_version' => '2.1.0',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Push模式 - 主动向主控领取任务并上报
 */
function handlePushMode() {
    global $config;
    
    if (empty($config['master_url']) || empty($config['node_id']) || empty($config['api_key'])) {
        jsonResponse(false, "配置不完整，请重新从主控下载探针");
    }
    
    // 1. 向主控领取任务
    $taskUrl = rtrim($config['master_url'], '/') . "/node_api.php?action=get_tasks&node_id={$config['node_id']}&key={$config['api_key']}";
    $taskResponse = httpGet($taskUrl);
    
    if (!$taskResponse['success']) {
        jsonResponse(false, "领取任务失败: " . $taskResponse['message']);
    }
    
    $taskData = json_decode($taskResponse['body'], true);
    if (!$taskData || !$taskData['success']) {
        jsonResponse(false, "主控返回错误: " . ($taskData['message'] ?? '未知错误'));
    }
    
    $sites = $taskData['data']['sites'] ?? [];
    $checkConfig = $taskData['data']['config'] ?? [];
    
    if (empty($sites)) {
        jsonResponse(true, "无任务需要执行", ['total' => 0, 'node' => $taskData['data']['node'] ?? []]);
    }
    
    // 2. 执行检测
    $timeout = $checkConfig['timeout'] ?? 10;
    $maxRetry = $checkConfig['max_retry'] ?? 3;
    
    $results = parallelCheck($sites, $timeout, $maxRetry);
    
    // 3. 上报结果
    $reportData = array_values($results);
    $reportUrl = rtrim($config['master_url'], '/') . "/node_api.php?action=report&node_id={$config['node_id']}&key={$config['api_key']}";
    $reportResponse = httpPost($reportUrl, $reportData);
    
    if (!$reportResponse['success']) {
        jsonResponse(false, "上报结果失败: " . $reportResponse['message']);
    }
    
    jsonResponse(true, "Push模式执行成功", [
        'checked' => count($sites),
        'reported' => count($results),
        'node' => $taskData['data']['node'] ?? []
    ]);
}

/**
 * 状态检查
 */
function handleStatus() {
    global $config;
    
    jsonResponse(true, "探针运行正常", [
        'version' => '2.0.0',
        'php_version' => PHP_VERSION,
        'server_time' => date('Y-m-d H:i:s'),
        'config_status' => [
            'master_url' => !empty($config['master_url']) ? '已配置' : '未配置',
            'node_id' => !empty($config['node_id']) ? $config['node_id'] : '未配置',
            'api_key' => !empty($config['api_key']) ? '已配置' : '未配置'
        ],
        'ready' => !empty($config['master_url']) && !empty($config['node_id']) && !empty($config['api_key'])
    ]);
}

/**
 * 测试模式 - 检测主控连通性
 */
function handleTest() {
    global $config;
    
    if (empty($config['master_url'])) {
        jsonResponse(false, "主控地址未配置");
    }
    
    $testUrl = rtrim($config['master_url'], '/') . "/node_api.php?action=ip_location&ip=" . $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
    $response = httpGet($testUrl);
    
    jsonResponse(true, "测试完成", [
        'master_url' => $config['master_url'],
        'master_reachable' => $response['success'],
        'http_code' => $response['http_code'],
        'server_ip' => $_SERVER['SERVER_ADDR'] ?? 'unknown',
        'php_version' => PHP_VERSION
    ]);
}

/**
 * 并发检测多个网站
 */
function parallelCheck(array $sites, int $timeout = 10, int $maxRetry = 3): array {
    if (empty($sites)) return [];
    
    $results = [];
    
    // 第一轮并发检测
    $firstRound = doParallelCheck($sites, $timeout);
    
    // 收集失败的网站
    $failedSites = [];
    foreach ($firstRound as $siteId => $result) {
        $results[$siteId] = $result;
        if (!$result['http_success']) {
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
        $retryResults = doParallelCheck($failedSites, $timeout);
        
        $stillFailed = [];
        foreach ($retryResults as $siteId => $result) {
            $results[$siteId] = $result;
            $results[$siteId]['retries'] = $retryCount;
            
            if (!$result['http_success']) {
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
            usleep(500000);
        }
    }
    
    return $results;
}

/**
 * 执行一轮并发检测
 * 修复：分离HTTP和SSL检测，HTTP跟随跳转，SSL不跟随跳转（解决301问题）
 */
function doParallelCheck(array $sites, int $timeout): array {
    if (empty($sites)) return [];
    
    $results = [];
    $httpResults = [];
    $sslResults = [];
    
    // ========== 第一轮：HTTP检测（跟随跳转，获取最终页面状态） ==========
    $mh = curl_multi_init();
    $handles = [];
    
    foreach ($sites as $site) {
        $ch = curl_init($site['url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => true,      // HTTP跟随跳转
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 5
        ]);
        
        curl_multi_add_handle($mh, $ch);
        $handles[$site['id']] = ['ch' => $ch, 'site' => $site];
    }
    
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
        $error = curl_error($ch);
        $responseTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000;
        $httpSuccess = ($httpCode >= 200 && $httpCode < 400);
        
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
        
        $httpResults[$siteId] = [
            'http_success' => $httpSuccess,
            'http_code' => $httpCode ?: 0,
            'http_error' => $error ?: null,
            'response_time' => round($responseTime)
        ];
    }
    
    curl_multi_close($mh);
    
    // ========== 第二轮：SSL检测（不跟随跳转，获取原始域名证书） ==========
    $mh2 = curl_multi_init();
    $sslHandles = [];
    
    foreach ($sites as $site) {
        // 从URL提取host
        $url = $site['url'];
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? $url;
        
        // 只检测HTTPS网站
        if (!isset($parsed['scheme']) || $parsed['scheme'] !== 'https') {
            $sslResults[$site['id']] = [
                'ssl_days' => null,
                'ssl_error' => '非HTTPS网站'
            ];
            continue;
        }
        
        // 发起SSL检测请求（不跟随跳转）
        $sslUrl = "https://{$host}";
        $ch = curl_init($sslUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => false,     // SSL不跟随跳转！关键修复
            CURLOPT_CERTINFO => true,            // 获取证书信息
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_NOBODY => true               // 只获取头信息
        ]);
        
        curl_multi_add_handle($mh2, $ch);
        $sslHandles[$site['id']] = ['ch' => $ch, 'site' => $site];
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
                $sslResult = parseCertInfo($certInfo);
                $sslDays = $sslResult['days'];
                $sslError = $sslResult['error'];
            } elseif ($error && strpos($error, 'SSL') !== false) {
                $sslError = parseSSLError($error);
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
        
        $results[$siteId] = [
            'site_id' => $siteId,
            'url' => $site['url'],
            'http_success' => $http['http_success'],
            'http_status' => $http['http_success'] ? 'up' : 'down',
            'http_code' => $http['http_code'],
            'http_error' => $http['http_error'],
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
function parseCertInfo(array $certInfo): array {
    $minDays = PHP_INT_MAX;
    $error = null;
    
    foreach ($certInfo as $cert) {
        if (isset($cert['Expire date'])) {
            $expireTime = strtotime($cert['Expire date']);
            if ($expireTime === false) continue;
            
            $days = floor(($expireTime - time()) / 86400);
            
            if ($days <= 0) {
                $error = '证书已过期';
            } elseif ($days <= 7) {
                $error = "证书即将过期(剩余{$days}天)";
            }
            
            if ($days > 0 && $days < $minDays) {
                $minDays = $days;
            }
        }
    }
    
    return ['days' => $minDays < PHP_INT_MAX ? $minDays : null, 'error' => $error];
}

/**
 * 解析SSL错误信息
 */
function parseSSLError(string $error): string {
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
 * HTTP GET请求
 */
function httpGet($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    
    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $errorNo = curl_errno($ch);
    curl_close($ch);
    
    // CURL层面错误
    if ($errorNo) {
        $errorMap = [
            CURLE_COULDNT_CONNECT => '无法连接到主控服务器（检查网络或主控地址）',
            CURLE_OPERATION_TIMEDOUT => '连接超时（主控无响应或网络不通）',
            CURLE_COULDNT_RESOLVE_HOST => '无法解析主控域名（检查DNS配置）',
            CURLE_SSL_CONNECT_ERROR => 'SSL连接错误',
            28 => '请求超时（主控响应过慢）'
        ];
        $errorDetail = $errorMap[$errorNo] ?? "CURL错误 #{$errorNo}: $error";
        return [
            'success' => false,
            'body' => $body,
            'http_code' => $httpCode,
            'message' => $errorDetail
        ];
    }
    
    // 解析API返回的JSON（无论HTTP状态码是什么）
    $json = json_decode($body, true);
    if ($json && isset($json['success'])) {
        if (!$json['success']) {
            return [
                'success' => false,
                'body' => $body,
                'http_code' => $httpCode,
                'message' => $json['message'] ?? 'API返回失败'
            ];
        }
        return [
            'success' => true,
            'body' => $body,
            'http_code' => $httpCode,
            'message' => 'OK'
        ];
    }
    
    // 无法解析JSON，返回HTTP状态
    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'body' => $body,
        'http_code' => $httpCode,
        'message' => "HTTP {$httpCode}"
    ];
}

/**
 * HTTP POST请求
 */
function httpPost($url, $data) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    
    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $errorNo = curl_errno($ch);
    curl_close($ch);
    
    // 详细错误信息
    if ($errorNo) {
        $errorMap = [
            CURLE_COULDNT_CONNECT => '无法连接到主控服务器',
            CURLE_OPERATION_TIMEDOUT => '连接超时',
            CURLE_COULDNT_RESOLVE_HOST => '无法解析主控域名',
            28 => '请求超时'
        ];
        $errorDetail = $errorMap[$errorNo] ?? "CURL错误 #{$errorNo}: $error";
    } else {
        $errorDetail = "HTTP {$httpCode}";
    }
    
    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'body' => $body,
        'http_code' => $httpCode,
        'message' => $errorDetail
    ];
}

/**
 * JSON响应
 */
function jsonResponse($success, $message, $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
