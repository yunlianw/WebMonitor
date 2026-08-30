# 更新日志

所有重要的变更都会记录在此文件中。

**说明**：本 CHANGELOG 按**版本号倒序**排列（最新版本在最上方），方便查看最新动态。

格式基于 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.0.0/)，
版本号遵循 [语义化版本](https://semver.org/lang/zh-CN/)。

---

## [4.3.2] - 2026-08-31

### 修复

- 🐛 **自定义端口 SSL 检测失败**（龙虾二号第 2 轮审查发现）
  - `lib/MonitorService.php`：`parse_url` 后取 host 丢 port，拼成 `https://host` 默认走 443
  - 修复：补 `port = isset($parsed['port']) ? (int)$parsed['port'] : 443;` + `https://{$host}:{$port}`
- 🐛 **admin.php normalizeWebsiteUrl() 重建 URL 时丢端口**
  - 后台添加/编辑网站后，URL 中端口被剥掉（如 `wajd.55661.cn:4433` → `https://wajd.55661.cn`）
  - 修复：重建时补上 `:port`
- 🐛 **探针端 agent.php + agent_template.php 同款 bug**
  - 探针做 SSL 检测时也丢端口
  - 修复：两处同步加上 port 提取

### 验证

老季实测：添加 `wajd.55661.cn` SSL 检查（实际端口 4433）：
- 修复前：last_ssl_days = -848（连错服务的过期证书）
- 修复后：last_ssl_days = **198**（真实证书 2027-03-17 到期）

### 审查

- 龙虾二号（deepseek/deepseek-v4-flash）两轮审查 PASS

---

## [4.3.1] - 2026-08-31

### 修复

- 🐛 **last_ssl_status 字段从未被写入**
  - `lib/NodeScheduler.php`：所有 4 个 UPDATE 分支都漏写 `last_ssl_status`
  - 新增 `sslStatusFor($sslDays)` 私有方法，统一词表（unknown / expired / warning / valid）
  - 词表与项目 logResult / node_api.php 统一
  - 边界修正：`≤ 0 天 → expired`（之前 `0 天 → warning`，违反直觉）
- 🐛 **wajd.55661.cn 立即检查后状态显示**
  - 之前只有 HTTP=0+SSL=1 时，写 last_ssl_days 不写 last_ssl_status
  - 修复后立即看到 `ssl_st=expired, days=-848`

### 验证

- php -l 通过
- 实测 5 个 enabled=1 的 SSL 检测网站，状态字段全部正确填入
- 词表与 logResult / node_api.php 统一（unknown/expired/warning/valid）

### 审查

- 龙虾二号（deepseek/deepseek-v4-flash）两轮审查 PASS

---

## [4.3.0] - 2026-08-31

### 修复（龙虾二号两轮审查 18 项单元测试 PASS）

- 🐛 **HTTP/SSL/WHOIS 告警无视 check_xxx 字段**
  - `lib/MonitorService.php`：doParallelCheck() 函数不再无条件检测，根据 `check_http`/`check_ssl` 字段跳过，跳过的项标记为 `skipped`，不触发告警
  - `lib/WhoisMonitorService.php`：非 force 模式的 WHOIS 查询补 `AND check_whois = 1` 过滤
  - `agent.php`：同样的复制代码 bug 一并修复
  - `node_api.php handleReport`：加 `WHERE id = ? AND enabled = 1` 守卫，已禁用网站不会被迟到的探针上报触发告警
- 🐛 **重复添加同一域名报"已存在"无法重新启用**
  - `admin.php add_website`：改用 `ON DUPLICATE KEY UPDATE`，重复添加时自动重新启用 + 更新检测配置
- 🐛 **仪表盘不显示已禁用网站**
  - admin.php 仪表盘查询改为全量展示（`ORDER BY w.enabled DESC`）
  - 三个主题（apple/default/pages）名称旁加 **"⏸ 已暂停"** 红色徽标
- 🐛 **过期证书不告警**
  - `agent.php parseCertInfo` + `node_api.php` SSL 告警：`$days < $minDays`（不再 `> 0 && <`）让过期证书计入
- 🐛 **null 重试条件 bug**
  - 修复 `!$http_success` → `=== false`（null 不再触发重试）

### 数据清理

- 🗑️ 删除测试数据 `wajd.55661.cn`（id=86）及其 8 条误报 alert_logs

### 项目清理

- 🧹 删除 5 个旧备份文件（4 月、6 月、8 月 16 日），仅保留本次修复的备份
- 📦 加 .gitignore 排除 `*.backup.*` / `*.bak` / `*.old` / 测试文件 / 敏感配置

### 审查

- 龙虾二号（deepseek/deepseek-v4-flash）两轮审查，18 项单元测试全 PASS

---

## [4.2.0] - 2026-04-29

### 新增

- 🎨 **主题系统** - 支持 WordPress 风格一键切换主题
  - 新增 `themes/` 目录存放主题文件
  - 新增 `pages/theme.php` 主题管理页面
  - 数据库 `system_settings` 表添加 `current_theme` 字段
- 🍏 **Apple 风格主题** - 全后台果系化设计
  - 呼吸感：圆角 12-16px，内边距 20px+
  - 层级感：背景 #F5F5F7，白色卡片，柔和阴影
  - 色彩克制：Apple 绿/橙/红/蓝
  - Dashboard 卡片化布局
- 📄 **README.md** - 开源风格项目说明文档

### 修复

- 🔧 `login.php` 引用不存在的 `check_installed.php` 文件错误
- 🔧 主题切换无效（`system_settings` 表 id 不匹配问题）
- 🔧 `admin_manage.php` 文件缺失问题

### 优化

- ⚡ 删除 `local_heartbeat.php` 套娃调用，简化监控架构
  - 原架构：`外部宝塔 → api_refactored.php` + `本机 crontab → local_heartbeat.php → api_refactored.php`
  - 新架构：`外部宝塔 → api_refactored.php`（唯一入口）
  - `api_refactored.php` 已内置 `updateBuiltinNodeSync()` 自动更新心跳

### 变更

- 📁 重构后台文件结构
  - `admin.php` 支持主题加载
  - 样式文件迁移到 `themes/apple/style.css`
  - 页面模板迁移到 `themes/apple/*.php`

---

## [4.0.0] - 2026-04-28

### 新增

- 🚀 初始版本发布
- 🔗 **HTTP 访问检测** - 实时检测网站可用性
- 🔐 **SSL 证书监控** - 自动检测证书有效期
- 🌐 **域名到期监控** - WHOIS 检测域名注册信息
- 🖥️ **分布式节点** - 支持 Pull/Push 模式
  - 内置节点（本机监控）
  - Pull 模式（主控主动请求）
  - Push 模式（探针主动上报）
  - 零代码部署探针
- 📧 **邮件通知** - SMTP 发送告警
- 📱 **Telegram 通知** - Bot 推送告警
- 📋 **批量管理** - 批量添加/删除网站
- 🔍 **日志系统** - 监控日志、邮件日志、告警日志
- 🗑️ **数据清理** - 按时间范围清理历史数据
- 👥 **多管理员** - 支持多个管理员账号

---

## 版本说明

- **主版本号（Major）**：不兼容的 API 变更
- **次版本号（Minor）**：向后兼容的功能新增
- **修订号（Patch）**：向后兼容的问题修复

---

## 维护说明

- **更新顺序**：每次发布新版本时，将段落插入到 `[Unreleased]` 之上
- **日期格式**：YYYY-MM-DD
- **分段标签**：新增 / 修复 / 优化 / 变更 / 弃用 / 移除 / 安全

---

*最后更新: 2026-08-31*
