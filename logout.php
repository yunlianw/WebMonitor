<?php
/**
 * 登出页面
 */

session_start();
session_destroy();

// 跳转到登录页面
header('Location: login.php');
exit;