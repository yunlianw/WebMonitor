<?php
/**
 * 网站监控系统 - 安装向导
 * 全新安装，简单高效
 */

// 防止重复安装
if (file_exists(__DIR__ . '/install.lock')) {
    die('安装已锁定，如需重新安装请删除 install.lock 文件');
}

$step = $_GET['step'] ?? 1;
$error = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 步骤2：测试数据库连接
    if (isset($_POST['action']) && $_POST['action'] === 'test_db') {
        $host = $_POST['host'] ?? '127.0.0.1';
        $port = $_POST['port'] ?? '3306';
        $dbname = $_POST['dbname'] ?? '';
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        try {
            // 尝试创建数据库连接
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // 尝试创建数据库
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` DEFAULT CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // 保存配置到临时文件
            $config = [
                'host' => $host,
                'port' => $port,
                'dbname' => $dbname,
                'username' => $username,
                'password' => $password
            ];
            file_put_contents(__DIR__ . '/data/db_temp.json', json_encode($config));
            
            $step = 3;
        } catch (PDOException $e) {
            $error = '数据库连接失败: ' . $e->getMessage();
            $step = 2;
        }
    }
    
    // 步骤4：创建管理员
    if (isset($_POST['action']) && $_POST['action'] === 'create_admin') {
        $admin_user = $_POST['admin_user'] ?? '';
        $admin_pass = $_POST['admin_pass'] ?? '';
        
        if (empty($admin_user) || empty($admin_pass)) {
            $error = '用户名和密码不能为空';
            $step = 4;
        } else {
            // 读取数据库配置
            $config_file = __DIR__ . '/data/db_temp.json';
            if (!file_exists($config_file)) {
                $error = '配置已过期，请重新填写数据库信息';
                $step = 2;
            } else {
                $config = json_decode(file_get_contents($config_file), true);
                
                try {
                    // 连接数据库
                    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset=utf8mb4";
                    $pdo = new PDO($dsn, $config['username'], $config['password']);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    // 加密密码
                    $password_hash = password_hash($admin_pass, PASSWORD_DEFAULT);
                    
                    // 检查用户表是否存在
                    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                    
                    if (in_array('users', $tables)) {
                        // 插入管理员
                        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'admin')");
                        $stmt->execute([$admin_user, $password_hash]);
                    }
                    
                    // 创建安装锁文件
                    file_put_contents(__DIR__ . '/install.lock', date('Y-m-d H:i:s'));
                    
                    // 清理临时文件
                    @unlink(__DIR__ . '/data/db_temp.json');
                    
                    $step = 5;
                } catch (PDOException $e) {
                    $error = '创建管理员失败: ' . $e->getMessage();
                    $step = 4;
                }
            }
        }
    }
}

// 获取PHP扩展状态
function getExtensions() {
    return [
        'php' => version_compare(PHP_VERSION, '7.4', '>='),
        'pdo_mysql' => extension_loaded('pdo_mysql'),
        'mysqli' => extension_loaded('mysqli'),
        'curl' => extension_loaded('curl'),
        'openssl' => extension_loaded('openssl'),
    ];
}

// 获取目录权限状态
function getDirs() {
    $dirs = [
        __DIR__ => '',
        __DIR__ . '/config' => '',
        __DIR__ . '/logs' => '',
    ];
    
    foreach ($dirs as $dir => &$status) {
        if (!is_dir($dir)) {
            if (@mkdir($dir, 0755, true)) {
                $status = is_writable($dir) ? 'ok' : 'fail';
            } else {
                $status = 'fail';
            }
        } else {
            $status = is_writable($dir) ? 'ok' : 'fail';
        }
    }
    
    return $dirs;
}

$extensions = getExtensions();
$dirs = getDirs();
$ext_ok = !in_array(false, $extensions);
$dir_ok = !in_array('fail', $dirs);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>网站监控系统 - 安装向导</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; min-height: 100vh; padding: 40px 20px; }
        .container { max-width: 700px; margin: 0 auto; background: white; border-radius: 12px; box-shadow: 0 2px 20px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px; text-align: center; }
        .header h1 { font-size: 28px; margin-bottom: 8px; }
        .header p { opacity: 0.9; }
        
        .step-bar { display: flex; background: #f8f9fa; padding: 20px 40px; border-bottom: 1px solid #eee; }
        .step-item { flex: 1; text-align: center; position: relative; }
        .step-item::after { content: ''; position: absolute; top: 12px; left: 50%; width: 100%; height: 2px; background: #ddd; z-index: 0; }
        .step-item:last-child::after { display: none; }
        .step-num { width: 28px; height: 28px; border-radius: 50%; background: #ddd; color: #999; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; position: relative; z-index: 1; }
        .step-item.active .step-num { background: #667eea; color: white; }
        .step-item.completed .step-num { background: #4caf50; color: white; }
        .step-label { font-size: 12px; color: #999; margin-top: 8px; display: block; }
        
        .content { padding: 40px; }
        
        .error-box { background: #fee; border: 1px solid #fcc; color: #c33; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .success-box { background: #efe; border: 1px solid #cfc; color: #3c3; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        
        .check-list { margin: 20px 0; }
        .check-item { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
        .check-item:last-child { border-bottom: none; }
        .check-item .name { font-weight: 500; }
        .check-item .status { font-weight: bold; }
        .check-item .status.ok { color: #4caf50; }
        .check-item .status.fail { color: #f44336; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #333; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 15px; }
        .form-group input:focus { outline: none; border-color: #667eea; }
        
        .btn { display: inline-block; padding: 14px 32px; background: #667eea; color: white; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; width: 100%; }
        .btn:hover { background: #5a6fd6; }
        .btn:disabled { background: #ccc; cursor: not-allowed; }
        .btn-group { display: flex; gap: 12px; }
        .btn-group .btn { flex: 1; }
        
        .info { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; line-height: 1.8; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🐮 网站监控系统</h1>
            <p>全新安装向导</p>
        </div>
        
        <!-- 步骤条 -->
        <div class="step-bar">
            <div class="step-item <?php echo $step >= 1 ? ($step > 1 ? 'completed' : 'active') : ''; ?>">
                <div class="step-num"><?php echo $step > 1 ? '✓' : '1'; ?></div>
                <span class="step-label">环境检查</span>
            </div>
            <div class="step-item <?php echo $step >= 2 ? ($step > 2 ? 'completed' : 'active') : ''; ?>">
                <div class="step-num"><?php echo $step > 2 ? '✓' : '2'; ?></div>
                <span class="step-label">数据库配置</span>
            </div>
            <div class="step-item <?php echo $step >= 3 ? ($step > 3 ? 'completed' : 'active') : ''; ?>">
                <div class="step-num"><?php echo $step > 3 ? '✓' : '3'; ?></div>
                <span class="step-label">初始化数据库</span>
            </div>
            <div class="step-item <?php echo $step >= 4 ? ($step > 4 ? 'completed' : 'active') : ''; ?>">
                <div class="step-num"><?php echo $step > 4 ? '✓' : '4'; ?></div>
                <span class="step-label">创建管理员</span>
            </div>
            <div class="step-item <?php echo $step >= 5 ? 'active' : ''; ?>">
                <div class="step-num">5</div>
                <span class="step-label">完成</span>
            </div>
        </div>
        
        <div class="content">
            <!-- 步骤1：环境检查 -->
            <?php if ($step == 1): ?>
                <?php if (!$ext_ok || !$dir_ok): ?>
                    <div class="error-box">
                        ⚠️ 环境检查未通过，请修复以下问题后重新安装
                    </div>
                <?php else: ?>
                    <div class="success-box">
                        ✅ 环境检查通过
                    </div>
                <?php endif; ?>
                
                <div class="check-list">
                    <h3>PHP 环境</h3>
                    <div class="check-item">
                        <span class="name">PHP 版本 (>=7.4)</span>
                        <span class="status <?php echo $extensions['php'] ? 'ok' : 'fail'; ?>">
                            <?php echo $extensions['php'] ? '✓ ' . PHP_VERSION : '✗ 需要 PHP 7.4+'; ?>
                        </span>
                    </div>
                    <div class="check-item">
                        <span class="name">pdo_mysql 扩展</span>
                        <span class="status <?php echo $extensions['pdo_mysql'] ? 'ok' : 'fail'; ?>">
                            <?php echo $extensions['pdo_mysql'] ? '✓ 已开启' : '✗ 未开启'; ?>
                        </span>
                    </div>
                    <div class="check-item">
                        <span class="name">curl 扩展</span>
                        <span class="status <?php echo $extensions['curl'] ? 'ok' : 'fail'; ?>">
                            <?php echo $extensions['curl'] ? '✓ 已开启' : '✗ 未开启'; ?>
                        </span>
                    </div>
                    <div class="check-item">
                        <span class="name">openssl 扩展</span>
                        <span class="status <?php echo $extensions['openssl'] ? 'ok' : 'fail'; ?>">
                            <?php echo $extensions['openssl'] ? '✓ 已开启' : '✗ 未开启'; ?>
                        </span>
                    </div>
                </div>
                
                <div class="check-list">
                    <h3>目录权限</h3>
                    <?php foreach ($dirs as $dir => $status): ?>
                    <div class="check-item">
                        <span class="name"><?php echo basename($dir); ?> 目录</span>
                        <span class="status <?php echo $status; ?>">
                            <?php echo $status === 'ok' ? '✓ 可写' : '✗ 不可写'; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($ext_ok && $dir_ok): ?>
                <form method="get">
                    <input type="hidden" name="step" value="2">
                    <button type="submit" class="btn">下一步 →</button>
                </form>
                <?php else: ?>
                <button class="btn" disabled>请修复环境问题</button>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- 步骤2：数据库配置 -->
            <?php if ($step == 2): ?>
                <?php if ($error): ?>
                    <div class="error-box"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="post">
                    <input type="hidden" name="action" value="test_db">
                    
                    <div class="form-group">
                        <label>数据库地址</label>
                        <input type="text" name="host" value="127.0.0.1" required>
                    </div>
                    
                    <div class="form-group">
                        <label>数据库端口</label>
                        <input type="text" name="port" value="3306" required>
                    </div>
                    
                    <div class="form-group">
                        <label>数据库名称</label>
                        <input type="text" name="dbname" placeholder="请输入数据库名" required>
                    </div>
                    
                    <div class="form-group">
                        <label>数据库用户名</label>
                        <input type="text" name="username" placeholder="请输入用户名" required>
                    </div>
                    
                    <div class="form-group">
                        <label>数据库密码</label>
                        <input type="password" name="password" placeholder="请输入密码">
                    </div>
                    
                    <div class="btn-group">
                        <a href="?step=1" class="btn" style="background:#999;text-decoration:none;text-align:center;">返回</a>
                        <button type="submit" class="btn">测试并连接 →</button>
                    </div>
                </form>
            <?php endif; ?>
            
            <!-- 步骤3：初始化数据库 -->
            <?php if ($step == 3): ?>
                <?php 
                // 自动执行SQL初始化
                $config = json_decode(file_get_contents(__DIR__ . '/data/db_temp.json'), true);
                $init_error = '';
                
                try {
                    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset=utf8mb4";
                    $pdo = new PDO($dsn, $config['username'], $config['password']);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    // 读取SQL文件
                    $sql_file = __DIR__ . '/install.sql';
                    if (!file_exists($sql_file)) {
                        throw new Exception('install.sql 文件不存在');
                    }
                    
                    $sql = file_get_contents($sql_file);
                    
                    // 分割SQL语句
                    $statements = [];
                    $current = '';
                    $lines = explode("\n", $sql);
                    
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (empty($line) || strpos($line, '--') === 0) continue;
                        $current .= ' ' . $line;
                        if (substr($line, -1) === ';') {
                            $statements[] = trim($current);
                            $current = '';
                        }
                    }
                    
                    // 执行SQL
                    foreach ($statements as $statement) {
                        if (!empty($statement)) {
                            try {
                                // DROP TABLE 如果存在
                                if (preg_match('/CREATE TABLE.*`?(\w+)`?/i', $statement, $match)) {
                                    $table = $match[1];
                                    $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
                                }
                                $pdo->exec($statement);
                            } catch (PDOException $e) {
                                // 忽略已存在等错误
                            }
                        }
                    }
                    
                } catch (Exception $e) {
                    $init_error = $e->getMessage();
                }
                ?>
                
                <?php if ($init_error): ?>
                    <div class="error-box">初始化失败: <?php echo $init_error; ?></div>
                    <a href="?step=2" class="btn" style="background:#999;text-decoration:none;display:block;text-align:center;">返回重试</a>
                <?php else: ?>
                    <div class="success-box">✅ 数据库初始化成功</div>
                    <form method="get">
                        <input type="hidden" name="step" value="4">
                        <button type="submit" class="btn">下一步 →</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- 步骤4：创建管理员 -->
            <?php if ($step == 4): ?>
                <?php if ($error): ?>
                    <div class="error-box"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="post">
                    <input type="hidden" name="action" value="create_admin">
                    
                    <div class="info">
                        请设置后台管理员账户，安装完成后使用此账户登录管理后台。
                    </div>
                    
                    <div class="form-group">
                        <label>管理员用户名</label>
                        <input type="text" name="admin_user" placeholder="请输入管理员用户名" required>
                    </div>
                    
                    <div class="form-group">
                        <label>管理员密码</label>
                        <input type="password" name="admin_pass" placeholder="请输入管理员密码" required>
                    </div>
                    
                    <div class="btn-group">
                        <a href="?step=3" class="btn" style="background:#999;text-decoration:none;text-align:center;">返回</a>
                        <button type="submit" class="btn">创建管理员 →</button>
                    </div>
                </form>
            <?php endif; ?>
            
            <!-- 步骤5：完成 -->
            <?php if ($step == 5): ?>
                <div style="text-align:center;padding:40px 0;">
                    <div style="font-size:64px;">🎉</div>
                    <h2 style="color:#4caf50;margin:20px 0;">安装成功！</h2>
                    <p style="color:#666;margin-bottom:30px;">网站监控系统已安装完成</p>
                    
                    <div class="info">
                        <p><strong>管理后台：</strong> admin.php</p>
                        <p><strong>首页：</strong> index.php</p>
                    </div>
                    
                    <a href="admin.php" class="btn">进入管理后台</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
