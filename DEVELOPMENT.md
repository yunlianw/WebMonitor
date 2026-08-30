# WebMonitor 开发指南

## Git 工作流程

### 首次克隆仓库

```bash
git clone https://github.com/yunlianw/WebMonitor.git
cd WebMonitor
```

### 日常开发流程

```bash
# 1. 拉取最新代码
git pull origin main

# 2. 创建功能分支（可选）
git checkout -b feature/新功能名称

# 3. 修改代码...

# 4. 查看修改
git status
git diff

# 5. 添加修改到暂存区
git add .                    # 添加所有文件
git add 文件名               # 添加单个文件

# 6. 提交修改
git commit -m "描述本次修改的内容"

# 7. 推送到远程
git push origin main         # 推送到main分支
git push origin feature/xxx  # 推送到功能分支
```

### 常用命令

| 命令 | 说明 |
|------|------|
| `git status` | 查看当前状态 |
| `git log --oneline` | 查看提交历史 |
| `git diff` | 查看未暂存的修改 |
| `git diff --staged` | 查看已暂存的修改 |
| `git branch` | 查看本地分支 |
| `git branch -a` | 查看所有分支（含远程） |
| `git checkout 分支名` | 切换分支 |
| `git merge 分支名` | 合并分支 |
| `git stash` | 暂存当前修改 |
| `git stash pop` | 恢复暂存的修改 |

### 版本回退

```bash
# 查看提交历史
git log --oneline

# 回退到指定版本（保留修改）
git reset --soft 提交ID

# 回退到指定版本（丢弃修改）
git reset --hard 提交ID

# 强制推送到远程（谨慎使用）
git push -f origin main
```

## 开发规范

### 代码规范

1. **单文件不超过400行** - 超过必须拆分
2. **模块分离** - config / business logic / view 完全分离
3. **视图和PHP代码物理分离** - 不要混写
4. **单一职责** - 每个文件只做一件事

### 提交规范

```
feat: 新功能
fix: 修复bug
docs: 文档更新
style: 代码格式（不影响功能）
refactor: 重构
perf: 性能优化
test: 测试
chore: 构建/工具
```

示例：
```bash
git commit -m "feat: 添加域名WHOIS监控功能"
git commit -m "fix: 修复SSL证书检测时间计算错误"
git commit -m "docs: 更新安装文档"
```

### 发布流程

1. **开发环境测试通过**
2. **牛马二号审查代码**
   - 语法检查
   - 安全审查
   - 敏感数据检查
3. **提交到Git**
4. **打版本标签**
   ```bash
   git tag -a v3.1.0 -m "版本描述"
   git push origin v3.1.0
   ```

## 敏感数据处理

### 禁止提交的内容

- ❌ 数据库密码
- ❌ API密钥
- ❌ 邮箱密码
- ❌ Telegram Bot Token
- ❌ 用户真实数据

### .gitignore 配置

```gitignore
# 敏感文件
data/config.json
install.lock
logs/*
storage/*
backups/*

# 开发文件
*.backup*
*.bak
*.log

# 系统文件
.DS_Store
Thumbs.db
```

### 检查敏感数据

```bash
# 检查是否包含敏感词
grep -rn "password\|secret\|token\|key.*=" . --include="*.php" | grep -v "function\|const\|placeholder"

# 检查硬编码的数据库名
grep -rn "database.*=.*'.*'" . --include="*.php"
```

## Token 认证

GitHub 已禁用密码认证，需要使用 Personal Access Token：

### 创建 Token

1. GitHub → Settings → Developer settings → Personal access tokens → Tokens (classic)
2. Generate new token (classic)
3. 勾选 `repo` 权限
4. 生成并保存 token

### 使用 Token 推送

```bash
# 方式一：每次输入
git push origin main
# Username: 你的用户名
# Password: 你的token（不是GitHub密码）

# 方式二：URL嵌入（不推荐）
git remote set-url origin https://用户名:TOKEN@github.com/yunlianw/WebMonitor.git

# 方式三：SSH密钥（推荐）
ssh-keygen -t ed25519 -C "你的邮箱"
cat ~/.ssh/id_ed25519.pub  # 添加到 GitHub SSH Keys
git remote set-url origin git@github.com:yunlianw/WebMonitor.git
```

## 常见问题

### Q: 推送被拒绝（non-fast-forward）

```bash
# 先拉取远程更新
git pull origin main --rebase
# 再推送
git push origin main
```

### Q: 合并冲突

```bash
# 查看冲突文件
git status

# 手动编辑冲突文件，解决后
git add 冲突文件
git commit -m "merge: 解决冲突"
git push origin main
```

### Q: 撤销最后一次提交（未推送）

```bash
git reset --soft HEAD~1
```

### Q: 修改最后一次提交信息

```bash
git commit --amend -m "新的提交信息"
```

---

*最后更新: 2026-04-30*
