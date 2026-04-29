<?php
/**
 * 域名WHOIS检测类 V1.0
 * 支持多源接口查询，按优先级轮询
 */

class WhoisChecker {
    
    /**
     * 获取域名到期信息
     * @param string $domain 域名
     * @return array ['days' => 剩余天数, 'expire_date' => 到期日期, 'source' => 数据来源]
     */
    public static function check($domain) {
        // 清理域名（去除协议、路径等）
        $domain = self::cleanDomain($domain);
        
        if (empty($domain)) {
            return self::errorResult('域名无效');
        }
        
        // 按优先级尝试各接口
        $result = self::checkWhois4cn($domain);
        if ($result['status'] === 'success') {
            return $result;
        }
        
        $result = self::checkTencentWhois($domain);
        if ($result['status'] === 'success') {
            return $result;
        }
        
        $result = self::checkRdap($domain);
        if ($result['status'] === 'success') {
            return $result;
        }
        
        return self::errorResult('所有WHOIS接口查询失败');
    }
    
    /**
     * 清理域名
     */
    private static function cleanDomain($domain) {
        // 去除协议
        $domain = preg_replace('/^https?:\/\//', '', $domain);
        // 去除路径、查询参数
        $domain = preg_replace('/\/.*$/', '', $domain);
        // 去除端口
        $domain = preg_replace('/:\d+$/', '', $domain);
        // 去除www
        $domain = preg_replace('/^www\./', '', $domain);
        
        return trim($domain);
    }
    
    /**
     * 主接口：whois.4.cn
     */
    private static function checkWhois4cn($domain) {
        $apiUrl = "http://whois.4.cn/api/main?domain=" . $domain;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error || $httpCode !== 200) {
            return self::errorResult('whois.4.cn 请求失败');
        }
        
        $data = json_decode($response, true);
        
        // 检查retcode和数据结构
        if (isset($data['retcode']) && $data['retcode'] === 0 && isset($data['data'])) {
            $dataContent = $data['data'];
            
            // 检查expire_date字段
            if (isset($dataContent['expire_date'])) {
                return self::parseDate($dataContent['expire_date'], 'whois.4.cn');
            }
            
            // 备选字段
            if (isset($dataContent['expireDate'])) {
                return self::parseDate($dataContent['expireDate'], 'whois.4.cn');
            }
        }
        
        return self::errorResult('whois.4.cn 数据解析失败');
    }
    
    /**
     * 备用一：腾讯云WHOIS
     */
    private static function checkTencentWhois($domain) {
        $url = "https://whois.cloud.tencent.com/cgi-bin/whoisv2?domain=" . $domain;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Referer: https://whois.cloud.tencent.com/'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error || $httpCode !== 200) {
            return self::errorResult('腾讯云WHOIS请求失败');
        }
        
        // 正则匹配到期时间
        // 匹配格式：到期时间: 2026-10-27 00:00:00 或 到期时间: 2026-10-27
        if (preg_match('/到期时间[：:]\s*(\d{4}-\d{1,2}-\d{1,2})/', $response, $matches)) {
            return self::parseDate($matches[1], '腾讯云WHOIS');
        }
        
        // 匹配另一种格式
        if (preg_match('/Registration\s*Expiration\s*Date[：:]\s*(\d{4}-\d{1,2}-\d{1,2})/i', $response, $matches)) {
            return self::parseDate($matches[1], '腾讯云WHOIS');
        }
        
        return self::errorResult('腾讯云WHOIS数据解析失败');
    }
    
    /**
     * 备用二：RDAP协议
     */
    private static function checkRdap($domain) {
        // 尝试常见的TLD RDAP服务
        $tlds = ['.com', '.net', '.org', '.cn', '.io'];
        
        foreach ($tlds as $tld) {
            if (strpos($domain, $tld) !== false) {
                $rdapUrl = "https://rdap.org/domain/" . $domain;
                break;
            }
        }
        
        if (!isset($rdapUrl)) {
            // 默认使用com/net
            $rdapUrl = "https://rdap.org/domain/" . $domain;
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $rdapUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: Mozilla/5.0 (compatible; WhoisChecker/1.0)'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error || $httpCode !== 200) {
            return self::errorResult('RDAP请求失败');
        }
        
        $data = json_decode($response, true);
        
        // 查找expiresAt字段
        if (isset($data['expiresAt'])) {
            return self::parseDate($data['expiresAt'], 'RDAP');
        }
        
        // 查找events中的expiration日期
        if (isset($data['events'])) {
            foreach ($data['events'] as $event) {
                if (isset($event['eventAction']) && $event['eventAction'] === 'expiration' && isset($event['eventDate'])) {
                    return self::parseDate($event['eventDate'], 'RDAP');
                }
            }
        }
        
        return self::errorResult('RDAP数据解析失败');
    }
    
    /**
     * 解析日期，统一格式
     */
    private static function parseDate($dateStr, $source) {
        $timestamp = self::formatDate($dateStr);
        
        if (!$timestamp) {
            return self::errorResult('日期格式无法解析: ' . $dateStr);
        }
        
        $expireDate = date('Y-m-d', $timestamp);
        $daysLeft = floor(($timestamp - time()) / 86400);
        
        return [
            'status' => 'success',
            'days' => $daysLeft,
            'expire_date' => $expireDate,
            'source' => $source,
            'raw_date' => $dateStr
        ];
    }
    
    /**
     * 统一日期格式转换
     * 处理各种乱七八糟的日期格式
     */
    public static function formatDate($dateStr) {
        if (empty($dateStr)) {
            return null;
        }
        
        // 去除空格
        $dateStr = trim($dateStr);
        
        // 尝试直接解析
        $timestamp = strtotime($dateStr);
        if ($timestamp !== false && $timestamp > 0) {
            return $timestamp;
        }
        
        // 常见格式替换
        $patterns = [
            '/(\d{4})[年\-\/](\d{1,2})[月\-\/](\d{1,2})/' => '$1-$2-$3', // 2026年10月27日
            '/(\d{4})(\d{2})(\d{2})/' => '$1-$2-$3', // 20261027
            '/(\d{4})\.(\d{2})\.(\d{2})/' => '$1-$2-$3', // 2026.10.27
        ];
        
        foreach ($patterns as $pattern => $replacement) {
            $dateStr = preg_replace($pattern, $replacement, $dateStr);
        }
        
        $timestamp = strtotime($dateStr);
        if ($timestamp !== false && $timestamp > 0) {
            return $timestamp;
        }
        
        return null;
    }
    
    /**
     * 错误结果
     */
    private static function errorResult($message) {
        return [
            'status' => 'error',
            'message' => $message,
            'days' => null,
            'expire_date' => null,
            'source' => null
        ];
    }
}
