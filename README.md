# WebMonitor v3.0 - 分布式网站监控系统

轻量级网站监控系统，支持HTTP/SSL/域名到期监控，邮件+Telegram告警，多节点分布式检测。

## 功能特性

- **HTTP监控** - 网站可用性检测，支持重试
- **SSL监控** - 证书有效期检测，提前告警
- **域名到期监控** - WHOIS查询，域名到期提醒
- **多节点支持** - 分布式检测，国内/海外节点
- **告警通知** - 邮件 + Telegram 双通道
- **主题系统** - 苹果风格UI，支持自定义主题
- **告警模板** - 可自定义通知内容

## 安装步骤

1. 上传文件到网站目录
2. 访问 `https://你的域名/install.php`
3. 按向导完成安装：
   - 环境检查
   - 数据库配置
   - 创建管理员
4. 删除 `install.php` 文件
5. 登录后台配置监控任务

## 环境要求

- PHP 7.4+
- MySQL 5.7+
- PDO / cURL / OpenSSL 扩展

## 目录结构

```
├── config/           # 配置文件
├── lib/              # 核心类库
├── notifications/    # 通知服务
├── pages/            # 后台页面
├── themes/           # 主题模板
│   ├── apple/        # 苹果风格主题
│   └── default/      # 默认主题
├── assets/           # 静态资源
├── storage/          # 存储目录
├── logs/             # 日志目录
├── admin.php         # 后台入口
├── login.php         # 登录页面
├── api_refactored.php # 监控API
└── install.php       # 安装程序
```

## 定时任务

添加以下cron任务：

```bash
# 每5分钟检测一次
*/5 * * * * curl "https://你的域名/api_refactored.php?action=check&key=你的密钥"
```

## 默认账号

- 后台: `https://域名/admin.php`
- 用户名: 安装时设置
- 密码: 安装时设置

## 监控节点

支持两种节点类型：
- **内置节点** - 主服务器直接检测
- **外部节点** - 部署在海外/其他网络环境

节点API：
```
GET /node_api.php?action=check&key=节点密钥
```

## 告警配置

### 邮件配置
后台 → 邮件配置 → 填写SMTP信息

### Telegram配置
1. 创建Bot（@BotFather）
2. 获取Bot Token
3. 获取Chat ID
4. 后台 → Telegram → 填写配置

## 安全建议

1. 修改数据库默认密码
2. 配置HTTPS
3. 设置 `storage/` `logs/` 目录权限为 755
4. 定期备份数据库

## 版本历史

### v3.0 (2026-04-29)
- 主题系统（苹果风格UI）
- 告警模板可编辑
- WHOIS域名监控
- 多节点分布式架构

## License

MIT License
