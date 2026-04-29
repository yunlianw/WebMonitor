# 更新日志

所有重要的变更都会记录在此文件中。

格式基于 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.0.0/)，
版本号遵循 [语义化版本](https://semver.org/lang/zh-CN/)。

---

## [1.1.0] - 2026-04-29

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

## [1.0.0] - 2026-04-28

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

*最后更新: 2026-04-29*