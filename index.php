<?php
/**
 * 网站监控系统 - 首页
 */

// 检查是否已安装（通过install.lock判断）
if (!file_exists(__DIR__ . '/install.lock')) {
    header('Location: install.php');
    exit;
}

// 跳转到管理后台
header('Location: admin.php');
exit;
