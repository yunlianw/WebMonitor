# WebMonitor v3.0 - 分布式网站监控系统

> 轻量级 PHP 网站监控系统，支持 HTTP/SSL/域名到期监控，多节点分布式检测，邮件+Telegram 告警。

## ✨ 功能特性

| 功能 | 说明 |
|------|------|
| HTTP 监控 | 网站可用性检测，支持自定义重试次数和超时时间 |
| SSL 监控 | 证书有效期检测，到期前自动告警（默认提前24天） |
| 域名到期监控 | WHOIS 查询，域名到期提前提醒 |
| 多节点支持 | 内置节点 + 外部节点，国内/海外分布式检测 |
| 告警通知 | 邮件（SMTP）+ Telegram 双通道 |
| 告警模板 | 自定义通知标题和内容，支持变量替换 |
| 主题系统 | 苹果风格 + 默认风格，登录页跟随主题 |
| 监控日志 | 完整的检测记录和历史查询 |
| 数据看板 | 网站状态总览、检测统计、告警统计 |

## 📦 安装方式

### 方式一：Docker 部署（推荐）

适合有 Docker 经验的用户，3 条命令搞定。

**前置条件：**
- Docker 20.10+
- Docker Compose 2.0+

**安装步骤：**

```bash
# 1. 克隆仓库
git clone https://github.com/yunlianw/WebMonitor.git
cd WebMonitor

# 2. 启动服务（自动构建镜像 + 启动 MySQL）
docker-compose up -d

# 3. 查看运行状态
docker-compose ps
```

**访问系统：**
- 地址：`http://服务器IP:8080`
- 首次访问会进入安装向导，设置管理员账号

**默认配置：**

| 配置项 | 默认值 | 说明 |
|--------|--------|------|
| Web 端口 | 8080 | 可在 docker-compose.yml 中修改 |
| 数据库 | MySQL 8.0 | 自动创建，数据持久化 |
| 数据库名 | webmonitor | |
| 数据库用户 | webmonitor | |
| 数据库密码 | WebMonitor2026! | 建议修改 |

**修改配置：**
编辑 `docker-compose.yml`，修改 `environment` 中的数据库密码等参数，然后重新启动：
```bash
docker-compose down
docker-compose up -d
```

**数据持久化：**
以下目录通过 volume 映射，容器删除后数据不丢失：
- `./storage/` — 存储文件
- `./logs/` — 日志文件
- `./backups/` — 备份文件
- `mysql-data` — MySQL 数据（Docker volume）

**停止/重启：**
```bash
docker-compose stop    # 停止
docker-compose start   # 启动
docker-compose restart # 重启
docker-compose down    # 停止并删除容器
docker-compose logs -f # 查看日志
```

---

### 方式二：传统部署

适合宝塔面板、LNMP 环境用户。

**前置条件：**

| 组件 | 最低版本 | 说明 |
|------|----------|------|
| PHP | 7.4 | 推荐 8.0+ |
| MySQL | 5.7 | 推荐 8.0+ |
| PDO | — | PHP 扩展 |
| cURL | — | PHP 扩展 |
| OpenSSL | — | PHP 扩展 |
| GD | — | PHP 扩展（可选） |

**安装步骤：**

**1) 下载程序**

```bash
# 方式A：从 GitHub 下载
cd /www/wwwroot/your-domain.com
wget https://github.com/yunlianw/WebMonitor/archive/refs/heads/main.zip
unzip main.zip
mv WebMonitor-main/* .
rm -rf WebMonitor-main main.zip

# 方式B：直接上传压缩包到网站目录后解压
```

**2) 创建数据库**

在宝塔面板或 phpMyAdmin 中创建数据库：
- 数据库名：`webmonitor`（可自定义）
- 字符集：`utf8mb4`

**3) 设置目录权限**

```bash
chmod 755 storage logs data backups
chmod 644 config/Config.php
```

**4) 运行安装向导**

浏览器访问 `https://你的域名/install.php`，按向导完成：

| 步骤 | 内容 |
|------|------|
| 步骤 1 | 环境检查（PHP版本、扩展、目录权限） |
| 步骤 2 | 数据库配置（主机、端口、库名、用户名、密码） |
| 步骤 3 | 初始化数据库（自动创建17张表 + 默认配置） |
| 步骤 4 | 创建管理员（用户名、密码、邮箱） |
| 步骤 5 | 安装完成 |

**5) 安全收尾**

```bash
# 安装完成后必须删除安装文件
rm install.php

# 确保目录不可写
chmod 644 config/Config.php
```

**6) 设置定时任务**

```bash
crontab -e
```

添加以下内容（每5分钟检测一次）：
```cron
*/5 * * * * curl -s "https://你的域名/api_refactored.php?action=check&key=你的监控密钥" > /dev/null
```

> 监控密钥在后台「系统设置」中查看。

---

## 📁 目录结构

```
WebMonitor/
├── config/              # 配置文件
│   └── Config.php       # 数据库配置（安装时自动写入）
├── lib/                 # 核心业务类
│   ├── MonitorService.php         # HTTP/SSL 监控服务
│   ├── NodeScheduler.php          # 节点调度
│   ├── WhoisChecker.php           # WHOIS 查询
│   └── WhoisMonitorService.php    # 域名到期监控
├── notifications/       # 告警通知
│   ├── NotificationManager.php    # 通知管理器
│   ├── EmailNotification.php      # 邮件通知
│   ├── TelegramNotification.php   # Telegram 通知
│   └── NotificationInterface.php  # 通知接口
├── pages/               # 后台页面（默认主题）
├── themes/              # 主题模板
│   ├── apple/           # 🍎 苹果风格主题
│   │   ├── style.css    # 苹果风格 CSS（743行）
│   │   ├── header.php   # 顶部导航
│   │   ├── dashboard.php # 仪表盘
│   │   └── ...          # 其他页面模板
│   └── default/         # 默认主题
├── assets/              # 静态资源
│   └── css/admin.css    # 管理后台样式
├── docker/              # Docker 配置
│   ├── nginx.conf       # Nginx 配置
│   └── entrypoint.sh    # 容器启动脚本
├── agent_templates/     # 节点代理模板
├── phpmailer/           # 邮件发送库（PHPMailer 6.x）
├── storage/             # 存储目录（运行时生成）
├── logs/                # 日志目录（运行时生成）
├── admin.php            # 后台入口
├── login.php            # 登录页（跟随当前主题）
├── api_refactored.php   # 监控 API（定时任务调用）
├── install.php          # 安装向导（安装后删除）
├── install.sql          # 数据库表结构（17张表）
├── Database.php         # 数据库操作类
├── Dockerfile           # Docker 镜像构建
├── docker-compose.yml   # Docker Compose 配置
└── README.md            # 本文档
```

## 🗄️ 数据库表

共 17 张数据表：

| 表名 | 说明 |
|------|------|
| `users` | 管理员账号 |
| `websites` | 监控网站列表 |
| `monitor_logs` | 监控检测日志 |
| `nodes` | 检测节点 |
| `monitor_nodes` | 节点与网站关联 |
| `node_check_times` | 节点检测时间记录 |
| `node_reports` | 节点上报数据 |
| `email_config` | 邮件 SMTP 配置 |
| `email_logs` | 邮件发送日志 |
| `telegram_config` | Telegram 配置 |
| `alert_settings` | 告警设置 |
| `alert_logs` | 告警发送记录 |
| `alert_templates` | 告警模板 |
| `notification_channels` | 通知渠道配置 |
| `system_settings` | 系统设置（主题、密钥等） |
| `gold_price_config` | 金价配置 |
| `gold_prices` | 金价数据 |

## ⚙️ 配置说明

### 数据库配置

安装完成后，数据库配置保存在 `config/Config.php`：

```php
'database' => [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'webmonitor',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
]
```

### 环境变量覆盖

支持通过环境变量覆盖配置（适用于 Docker）：

| 环境变量 | 对应配置 |
|----------|----------|
| `DB_HOST` | 数据库主机 |
| `DB_PORT` | 数据库端口 |
| `DB_DATABASE` | 数据库名 |
| `DB_USERNAME` | 数据库用户名 |
| `DB_PASSWORD` | 数据库密码 |

## 🔔 告警配置

### 邮件告警

1. 后台 → 邮件配置
2. 填写 SMTP 信息：
   - SMTP 主机（如 `smtp.163.com`）
   - SMTP 端口（如 `465`）
   - 加密方式（SSL/TLS）
   - 发件邮箱 + 授权码
   - 收件邮箱（多个用逗号分隔）
3. 点击「测试发送」验证

### Telegram 告警

1. 创建 Telegram Bot：
   - 找 @BotFather → `/newbot` → 获取 Bot Token
2. 获取 Chat ID：
   - 给你的 Bot 发一条消息
   - 访问 `https://api.telegram.org/bot<TOKEN>/getUpdates`
   - 找到 `chat.id` 字段
3. 后台 → Telegram 配置 → 填写 Token 和 Chat ID

### 告警模板

后台 → 告警模板 → 可自定义邮件/Telegram 的通知内容，支持变量：

| 变量 | 说明 |
|------|------|
| `{site_name}` | 网站名称 |
| `{site_url}` | 网站地址 |
| `{status}` | 状态（正常/异常） |
| `{message}` | 详细信息 |
| `{time}` | 检测时间 |

## 🌐 多节点部署

### 节点类型

| 类型 | 说明 | 适用场景 |
|------|------|----------|
| 内置节点 | 主服务器直接检测 | 小规模监控 |
| 外部节点 | 独立服务器/云函数 | 国内+海外多地域检测 |

### 添加外部节点

1. 后台 → 节点管理 → 添加节点
2. 填写节点名称、URL、位置
3. 系统自动生成 API 密钥
4. 在节点服务器部署 `agent_templates/agent_template.php`
5. 配置定时任务调用节点脚本

### 节点 API

```
GET {主站}/node_api.php?action=get_tasks&node_id={ID}&key={密钥}
POST {主站}/node_api.php?action=report&node_id={ID}&key={密钥}
```

## 🎨 主题系统

### 切换主题

后台 → 主题管理 → 选择主题 → 保存

### 当前主题

| 主题 | 风格 | 说明 |
|------|------|------|
| 苹果（apple） | 果系简约 | #F5F5F7 背景、#007AFF 主色、圆角卡片 |
| 默认（default） | 标准后台 | #1a73e8 蓝色主题 |

### 自定义主题

1. 复制 `themes/default/` 为 `themes/your-theme/`
2. 修改 `theme.json`（主题名称、描述）
3. 修改 `style.css`（样式）
4. 修改各页面模板（可选，不覆盖则使用 `pages/` 默认）

## 🔒 安全建议

### 必做

1. **删除安装文件** — 安装后必须删除 `install.php`
2. **修改默认密码** — 管理员密码 + 数据库密码
3. **配置 HTTPS** — 通过宝塔或 Nginx 配置 SSL 证书

### 建议

4. 设置 `config/` 目录权限为 644（不可写）
5. 设置 `storage/` `logs/` 权限为 755
6. 定期备份数据库
7. 监控密钥使用强随机字符串

### Docker 特别注意

8. 修改 `docker-compose.yml` 中的默认数据库密码
9. 不要将容器端口直接暴露到公网（建议用 Nginx 反向代理）

## 🆘 常见问题

### Q: 安装向导提示目录权限不足？
```bash
chmod 755 storage logs data backups
chmod 644 config/Config.php
```

### Q: 定时任务不执行？
- 检查 `api_refactored.php` 是否可访问
- 检查监控密钥是否正确
- 检查服务器 curl 是否可用

### Q: Docker 启动失败？
```bash
# 查看日志
docker-compose logs -f

# 重新构建
docker-compose build --no-cache
docker-compose up -d
```

### Q: 邮件发送失败？
- 确认使用的是「授权码」而不是邮箱密码
- 163/邮箱需要开启 SMTP 服务
- 检查 SMTP 端口是否被防火墙拦截

### Q: Telegram 告警收不到？
- Bot 需要先给用户发过消息，用户才能收到
- 检查 Bot Token 和 Chat ID 是否正确
- 如果是群组，需要 `/start` 机器人

## 📝 更新日志

### v3.0 (2026-04-30)
- ✨ 主题系统（苹果风格 + 默认风格）
- ✨ 登录页跟随主题
- ✨ 告警模板可自定义
- ✨ WHOIS 域名到期监控
- ✨ 多节点分布式检测
- ✨ Docker 一键部署
- ✨ 告警模板编辑器
- 🐛 修复 CSS 缓存问题（版本号）

## 📄 License

MIT License - 可自由使用、修改和分发。

## 🔗 相关链接

- 仓库：https://github.com/yunlianw/WebMonitor
- 问题反馈：https://github.com/yunlianw/WebMonitor/issues
