# WebMonitor - 网站监控系统

> 一个轻量级的 PHP 网站监控平台，支持 HTTP/SSL/域名到期监控、多节点分布式检测、邮件/Telegram 告警。

## ✨ 功能特性

### 监控能力
- 🔗 **HTTP 访问检测** - 实时检测网站可用性，支持自定义频率
- 🔐 **SSL 证书监控** - 自动检测证书有效期，到期预警
- 🌐 **域名到期监控** - WHOIS 检测域名注册信息，提前预警
- 📊 **响应时间追踪** - 记录每次检测的响应时间
- 🔄 **自动恢复通知** - 网站恢复时自动发送通知

### 分布式架构
- 🖥️ **内置节点** - 本机自动检测
- 📡 **Pull 模式** - 主控主动请求远程探针
- 📤 **Push 模式** - 探针主动上报检测结果
- 🔑 **零代码部署** - 下载探针文件上传即可，无需编写代码

### 告警通知
- 📧 **邮件通知** - SMTP 发送，支持自定义收件人
- 📱 **Telegram 通知** - Bot 推送告警消息
- ⚙️ **灵活告警策略** - 自定义告警冷却、阈值、间隔

### 管理后台
- 🎨 **主题系统** - 支持多套主题，一键切换
- 📋 **批量管理** - 批量添加、批量删除网站
- 🔍 **日志查看** - 监控日志、邮件日志、告警日志
- 🗑️ **数据清理** - 按时间范围清理历史数据
- 👥 **多管理员** - 支持多个管理员账号

## 📁 项目结构

```
wwwroot/
└── api.5276.net/
    ├── admin.php            # 管理后台主入口
    ├── login.php            # 登录页面
    ├── logout.php           # 退出登录
    ├── api_refactored.php   # 核心 API（定时调用）
    ├── node_api.php         # 节点通信 API
    ├── Database.php         # 数据库类
    ├── install.php          # 安装向导
    ├── install.sql          # 数据库结构
    ├── config/
    │   └── Config.php       # 数据库配置
    ├── lib/
    │   ├── NodeScheduler.php       # 节点调度器
    │   ├── WhoisChecker.php        # WHOIS 检测
    │   └── WhoisMonitorService.php # 域名监控服务
    ├── notifications/
    │   └── TelegramNotification.php # Telegram 通知
    ├── themes/              # 🎨 主题目录
    │   ├── apple/           # Apple 风格主题
    │   │   ├── theme.json   # 主题描述
    │   │   ├── style.css    # 样式文件
    │   │   ├── header.php   # 顶部导航
    │   │   ├── dashboard.php
    │   │   ├── websites.php
    │   │   ├── nodes.php
    │   │   ├── email.php
    │   │   ├── telegram.php
    │   │   ├── alert_settings.php
    │   │   ├── settings.php
    │   │   └── monitor.php
    │   └── default/         # 默认主题
    │       └── ...
    ├── pages/               # 主题管理页面
    │   └── theme.php
    └── phpmailer/           # PHPMailer 邮件库
```

## 🚀 安装

### 环境要求
- PHP 7.4+ / 8.x
- MySQL 5.7+ / 8.0+
- 需要开启 curl、openssl、pdo_mysql 扩展

### 安装步骤
1. 将项目文件上传到 Web 服务器
2. 访问 `http://你的域名/install.php` 进入安装向导
3. 按照提示填写数据库信息
4. 创建管理员账号
5. 安装完成后删除 `install.php`（自动生成 `install.lock`）
6. 登录后台 `http://你的域名/admin.php`

### 定时任务配置
在宝塔面板或 crontab 中添加定时任务：

```bash
# 每分钟执行一次监控检查
curl -s "https://你的域名/api_refactored.php?action=check&key=你的监控密钥"
```

监控密钥在后台「🔑 监控密钥」页面获取。

## 🎨 主题系统

WebMonitor 支持 WordPress 风格的主题切换。

### 使用方法
1. 登录后台，点击侧边栏「🎨 主题管理」
2. 选择想要的主题，点击「切换主题」
3. 页面自动刷新，新主题立即生效

### 开发自定义主题

```
1. 复制现有主题作为基础
   cp -r themes/apple themes/my-theme

2. 编辑主题描述
   vim themes/my-theme/theme.json

3. 修改样式
   vim themes/my-theme/style.css

4. 修改页面模板（可选）
   vim themes/my-theme/header.php
   vim themes/my-theme/dashboard.php
   ...

5. 回到主题管理页面即可看到新主题
```

### 主题文件说明

| 文件 | 说明 | 必须 |
|------|------|------|
| `theme.json` | 主题描述（名称、版本、作者） | ✅ |
| `style.css` | 主题样式文件 | ✅ |
| `header.php` | 顶部导航 + 侧边栏 | ✅ |
| `dashboard.php` | 仪表盘页面 | ✅ |
| `websites.php` | 网站管理页面 | 推荐 |
| `nodes.php` | 节点管理页面 | 推荐 |
| `email.php` | 邮件配置页面 | 推荐 |
| `telegram.php` | Telegram 配置页面 | 推荐 |
| `alert_settings.php` | 告警设置页面 | 推荐 |
| `settings.php` | 系统设置页面 | 推荐 |
| `monitor.php` | 监控密钥页面 | 推荐 |

**注意**: 如果主题缺少某个页面模板文件，系统会自动回退到 `pages/` 目录下的对应文件。

### theme.json 格式

```json
{
    "name": "Apple",
    "version": "1.0",
    "author": "WebMonitor",
    "description": "Apple风格简约设计，呼吸感、层级感、色彩克制"
}
```

## 🖥️ 分布式节点部署

### 添加 Pull 节点
1. 后台「节点管理」→「添加新节点」→ 选择 Pull 模式
2. 点击「📥 下载探针」获取探针文件
3. 上传到目标服务器
4. 在目标服务器添加定时任务访问探针 URL

### 添加 Push 节点
1. 后台「节点管理」→「添加新节点」→ 选择 Push 模式
2. 点击「📥 下载探针」获取探针文件
3. 上传到目标服务器
4. 在目标服务器添加定时任务：
   ```
   curl -s "http://目标服务器/agent.php?action=push&node_id=节点ID&key=密钥"
   ```

## 📡 API 接口

### 触发监控检查
```
GET /api_refactored.php?action=check&key={monitor_key}
```

### 手动触发 WHOIS 检测
```
GET /api_refactored.php?action=whois&key={monitor_key}
```

### 强制检查（忽略时间间隔）
```
GET /api_refactored.php?action=check&key={monitor_key}&force=1
```

## 🗄️ 数据库

### 核心表结构
- `users` - 用户表
- `websites` - 网站监控表
- `monitor_logs` - 监控日志表
- `nodes` - 监控节点表
- `node_check_times` - 节点检测时间表
- `email_config` - 邮件配置表
- `email_logs` - 邮件日志表
- `telegram_config` - Telegram 配置表
- `system_settings` - 系统设置表（含主题配置）
- `alert_settings` - 告警设置表
- `alert_logs` - 告警日志表

## 📄 开源协议

MIT License

## 🤝 贡献

欢迎提交 Issue 和 Pull Request！

---

**WebMonitor** - 让网站监控变得简单。
