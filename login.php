<?php
/**
 * 登录页面 - 支持主题切换
 */

session_start();

// 检查是否已安装
if (!file_exists(__DIR__ . '/install.lock')) {
    header('Location: install.php');
    exit;
}

// 加载配置和数据库类
require_once __DIR__ . '/config/Config.php';
require_once __DIR__ . '/Database.php';

// 获取当前主题
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $stmt = $conn->query("SELECT current_theme FROM system_settings ORDER BY id DESC LIMIT 1");
    $currentTheme = $stmt->fetchColumn() ?: 'apple';
} catch (Exception $e) {
    $currentTheme = 'apple';
}

$error = '';

// 处理登录请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = '用户名和密码不能为空';
    } else {
        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            // 查询用户
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['logged_in'] = true;
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                header('Location: admin.php');
                exit;
            } else {
                $error = '用户名或密码错误';
            }
        } catch (Exception $e) {
            $error = '登录失败: ' . $e->getMessage();
        }
    }
}

// 检查主题是否有login.php模板
$themeLogin = __DIR__ . '/themes/' . $currentTheme . '/login.php';
if (file_exists($themeLogin)) {
    // 使用主题登录模板
    include $themeLogin;
    exit;
}

// 否则使用默认登录页面（带主题CSS）
$themeCss = __DIR__ . '/themes/' . $currentTheme . '/style.css';
$cssUrl = file_exists($themeCss) 
    ? 'themes/' . htmlspecialchars($currentTheme) . '/style.css?v=' . filemtime($themeCss)
    : 'assets/css/admin.css';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>网站监控系统 - 登录</title>
    <link rel="stylesheet" href="<?php echo $cssUrl; ?>">
    <style>
        .login-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-box { background: #FFFFFF; border-radius: 20px; padding: 40px; width: 100%; max-width: 400px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
        .login-header { text-align: center; margin-bottom: 32px; }
        .login-header h1 { font-size: 1.5rem; color: #1D1D1F; margin-bottom: 8px; font-weight: 600; }
        .login-header p { color: #86868B; }
        .error { background: rgba(255,59,48,0.12); color: #FF3B30; padding: 12px 16px; border-radius: 12px; margin-bottom: 16px; text-align: center; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #1D1D1F; }
        .form-group input { width: 100%; padding: 14px 16px; border: 1px solid #E5E5EA; border-radius: 12px; font-size: 1rem; background: #F5F5F7; }
        .form-group input:focus { outline: none; border-color: #007AFF; background: #FFFFFF; box-shadow: 0 0 0 3px rgba(0,122,255,0.12); }
        .btn { display: block; width: 100%; padding: 14px; background: #007AFF; color: white; border: none; border-radius: 12px; font-size: 1rem; font-weight: 500; cursor: pointer; }
        .btn:hover { background: #0056CC; }
        .login-footer { margin-top: 24px; text-align: center; color: #86868B; font-size: 0.875rem; }
    </style>
</head>
<body class="login-page">
    <div class="login-box">
        <div class="login-header">
            <h1>🟢 网站监控系统</h1>
            <p>管理后台登录</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>用户名</label>
                <input type="text" name="username" placeholder="admin" required>
            </div>
            
            <div class="form-group">
                <label>密码</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            
            <button type="submit" class="btn">登录</button>
        </form>
        
        <div class="login-footer">
            <p>WebMonitor v3.0</p>
        </div>
    </div>
</body>
</html>