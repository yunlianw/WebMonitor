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

## 安装方式

### 方式一：Docker 部署（推荐）

适合有 Docker 经验的用户，一键部署。

```bash
# 克隆仓库
git clone https://github.com/yunlianw/WebMonitor.git
cd WebMonitor

# 启动服务
docker-compose up -d

# 查看状态
docker-compose ps

# 访问系统
# http://localhost:8080
```

**默认账号：**
- 用户名：`admin`
- 密码：首次访问 install.php 时设置

**数据库配置（.env 或 docker-compose.yml）：**
```yaml
environment:
  - DB_HOST=db
  - DB_DATABASE=webmonitor
  - DB_USERNAME=webmonitor
  - DB_PASSWORD=WebMonitor2026!
```

### 方式二：传统部署

适合宝塔面板、VPS 用户，无需 Docker。

**环境要求：**
- PHP 7.4+
- MySQL 5.7+
- PDO / cURL / OpenSSL 扩展

**安装步骤：**

```bash
# 1. 下载程序到网站目录
cd /www/wwwroot/your-domain.com

# 从 GitHub 下载或上传压缩包
wget https://github.com/yunlianw/WebMonitor/archive/refs/heads/main.zip
unzip main.zip
mv WebMonitor-main/* .
rm -rf WebMonitor-main main.zip

# 2. 创建数据库
# 在宝塔面板或 MySQL 中创建数据库 webmonitor

# 3. 设置目录权限
chmod -R 755 storage logs data backups

# 4. 访问安装向导
# https://你的域名/install.php

# 5. 按向导完成安装
# - 环境检查
# - 数据库配置
# - 创建管理员

# 6. 删除安装文件（重要！）
rm install.php
```

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
├── docker/           # Docker 配置
├── admin.php         # 后台入口
├── login.php         # 登录页面
├── api_refactored.php # 监控API
└── install.php       # 安装程序
```

## 定时任务

**Docker 方式：**
容器内已自动配置 cron。

**传统方式：**
```bash
# 编辑 crontab
crontab -e

# 添加监控任务（每5分钟检测）
*/5 * * * * curl "https://你的域名/api_refactored.php?action=check&key=你的密钥"
```

## 监控节点

支持两种节点类型：
- **内置节点** - 主服务器直接检测
- **外部节点** - 部署在海外/其他网络环境

**添加节点：**
后台 → 节点管理 → 添加节点 → 生成API密钥

**节点部署：**
将生成的 agent.php 放到节点服务器，配置定时任务。

## 告警配置

### 邮件配置
后台 → 邮件配置 → 填写 SMTP 信息

### Telegram 配置
1. 创建 Bot（@BotFather）
2. 获取 Bot Token 和 Chat ID
3. 后台 → Telegram → 填写配置

## 安全建议

1. 修改默认数据库密码（Docker 部署）
2. 配置 HTTPS
3. 设置目录权限：
   - `storage/` `logs/` 777
   - `config/` 644
4. 安装后删除 `install.php`
5. 定期备份数据库

## 更新日志

### v3.0 (2026-04-30)
- 主题系统（苹果风格UI）
- 告警模板可编辑
- WHOIS域名监控
- 多节点分布式架构
- Docker 支持

## License

MIT License

## 支持

- Issues: https://github.com/yunlianw/WebMonitor/issues
- QQ群：待建立
