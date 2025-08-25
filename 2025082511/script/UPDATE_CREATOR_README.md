# DoyeCMS 更新包创建器使用说明

## 概述

`create_update.py` 是一个完整的 DoyeCMS 更新包创建工具，可以自动化完成以下任务：

1. 检测 git 暂存区文件
2. 复制文件到更新目录
3. 生成 `file_list.txt` 文件
4. 更新版本列表
5. 推送到远程仓库

## 环境要求

- Python 3.12+
- uv 包管理器
- Git
- 项目必须在 git 仓库中

## 安装依赖

```bash
# 在 script 目录下执行
cd script
uv sync
```

## 使用方法

### 1. 标准工作流程

```bash
# 1. 修改代码文件
# 2. 添加文件到 git 暂存区
git add apps/admin/controller/content/AiController.php
git add apps/admin/view/default/common/foot.html

# 3. 运行更新脚本
cd script
uv run create_update.py
```

### 2. 简化参数（推荐）

```bash
# 直接指定版本号创建或更新
uv run create_update.py 2025082510

# 如果版本目录已存在，会重新生成file_list并推送
uv run create_update.py 2025082209

# 如果版本目录不存在，会创建新的更新包
uv run create_update.py 2025082511
```

### 3. 跳过 git 检查

```bash
# 如果版本目录已存在，可以跳过 git 暂存区检查
uv run create_update.py --skip-git
```

### 4. 指定项目根目录

```bash
# 如果不在标准位置运行
uv run create_update.py --project-root /path/to/DoyeCMS
```

## 命令行参数

- `version`: 版本号（格式：YYYYMMDDHH），可选位置参数
  - 如果目录存在：重新生成file_list并推送
  - 如果目录不存在：创建新的更新包
  - 如果不提供：使用当前北京时间作为版本号
- `--skip-git`: 跳过 git 暂存区检查
- `--project-root PROJECT_ROOT`: 指定项目根目录路径
- `--help`: 显示帮助信息

## 简化使用方式

支持直接传入版本号作为位置参数：

```bash
# 智能处理版本号
uv run create_update.py 2025082209
```

**智能逻辑：**
- 如果版本目录已存在：重新生成file_list.txt并执行git push
- 如果版本目录不存在：使用该版本号创建新的更新包

## 工作流程详解

### 1. 检测暂存区文件

脚本会执行 `git diff --cached --name-only` 来获取暂存区的文件列表。

### 2. 复制文件

将暂存区的文件复制到 `update/[版本号]/` 目录下，保持原有的目录结构。

### 3. 生成文件列表

扫描版本目录下的所有文件，生成 `file_list.txt`，格式为每行一个文件路径。

### 4. 更新版本列表

更新 `update/update_list.txt` 文件，添加新版本号并排序。

### 5. 推送到远程

在 `update` 目录下执行：
```bash
git add .
git commit -m "Add update package [版本号]"
git push
```

## 目录结构

```
DoyeCMS/
├── script/
│   ├── create_update.py      # 更新脚本
│   ├── pyproject.toml        # 项目配置
│   └── uv.lock              # 依赖锁定文件
├── update/
│   ├── 2025082209/          # 版本目录
│   │   ├── apps/
│   │   └── file_list.txt
│   ├── 2025082218/
│   │   ├── apps/
│   │   └── file_list.txt
│   └── update_list.txt      # 版本列表
└── apps/                    # 应用代码
```

## 注意事项

1. **时区**: 版本号使用北京时间（UTC+8）
2. **Git 状态**: 确保项目在 git 仓库中，且有正确的远程仓库配置
3. **权限**: 确保对 `update` 目录有写权限
4. **暂存区**: 使用前请确保相关文件已添加到 git 暂存区
5. **路径分隔符**: 文件路径统一使用正斜杠（/）

## 错误处理

- **暂存区为空**: 脚本会提示并退出
- **文件不存在**: 跳过不存在的文件并继续
- **Git 操作失败**: 显示详细错误信息
- **权限问题**: 检查目录权限设置

## 示例输出

```
项目根目录: D:\phpstudy_pro\WWW\DoyeCMS
更新目录: D:\phpstudy_pro\WWW\DoyeCMS\update

=== 开始创建更新包 2025082509 ===
检测到 2 个暂存区文件:
  - apps/admin/controller/content/AiController.php
  - apps/admin/view/default/common/foot.html
已复制: apps/admin/controller/content/AiController.php
已复制: apps/admin/view/default/common/foot.html
成功复制 2 个文件到 D:\phpstudy_pro\WWW\DoyeCMS\update\2025082509
正在生成文件列表：2025082509
成功生成文件列表：D:\phpstudy_pro\WWW\DoyeCMS\update\2025082509\file_list.txt
共包含 2 个文件：
  - apps/admin/controller/content/AiController.php
  - apps/admin/view/default/common/foot.html
已更新版本列表：D:\phpstudy_pro\WWW\DoyeCMS\update\update_list.txt
当前版本列表：['2025082209', '2025082218', '2025082509']
成功推送更新到远程仓库

=== 更新包 2025082509 创建完成 ===
✅ 更新包创建成功！
```

## 故障排除

### 1. Git 命令未找到

确保 Git 已安装并在 PATH 中。

### 2. uv 命令未找到

安装 uv 包管理器：
```bash
curl -LsSf https://astral.sh/uv/install.sh | sh
```

### 3. 权限错误

确保对项目目录有读写权限。

### 4. 远程推送失败

检查 Git 远程仓库配置和认证信息。