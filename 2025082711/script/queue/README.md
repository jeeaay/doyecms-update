# DoyeCMS AI队列处理器

这个目录包含了DoyeCMS AI队列处理器的所有相关文件，用于处理AI任务队列的长期稳定运行。

## quick start

1. 确定已安装
  - Redis
  - php-redis扩展
2. 确定./queue.sh已有执行权限（chmod +x queue.sh）
3. 复制.env.sample文件为.env
4. 尤其需要注意.env中PHP_PATH路径
  - 默认使用系统环境的/usr/bin/php
  - 宝塔面板的路径为/www/server/php/[84]/bin/php,[84]是版本号 需要替换为已安装php-redis扩展的php版本

```bash
# 安装:  
./queue.sh install
# 启动： 
./queue.sh start
# 查看状态： 
./queue.sh status
# 查看帮助
./queue.sh help
# 停止： 
./queue.sh stop
# 重启： 
./queue.sh restart

# 监控服务管理
sudo ./queue.sh monitor start   # 启动监控服务
sudo ./queue.sh monitor stop    # 停止监控服务
sudo ./queue.sh monitor restart # 重启监控服务
sudo ./queue.sh monitor status  # 查看监控状态

# 日志查看
./queue.sh logs           # 查看队列处理器日志
./queue.sh logs -f        # 实时跟踪日志
./queue.sh logs monitor   # 查看监控日志
./queue.sh logs system    # 查看系统服务日志
./queue.sh logs error     # 查看错误日志

```

## 📁 目录结构

```
queue/
├── README.md                    # 本文件，目录说明
├── queue_processor.php          # 队列处理器核心程序
├── monitor.php                  # 进程监控和健康检查程序
├── queue_processor.conf         # Supervisor配置文件（Linux）
├── queue.sh                     # Linux环境统一管理脚本
├── queue.bat                    # Windows环境统一管理脚本
│
└── logs/                        # 日志文件目录
```

## 开始使用

### Windows环境

```batch
# 进入队列目录
cd ./script/queue

# 交互式菜单模式（推荐）
queue.bat

# 或者直接使用命令行模式
# 安装Windows服务
queue.bat install

# 启动服务
queue.bat start

# 查看状态
queue.bat status
```

### Linux环境

```bash
# 进入队列目录
cd ./script/queue

# 设置执行权限
chmod +x queue.sh

# 首次安装（自动检测环境并安装服务）
sudo ./queue.sh install

# 启动服务
sudo ./queue.sh start

# 查看状态
./queue.sh status

# 查看帮助
./queue.sh help
```

## 📋 核心功能

### 1. 队列处理器 (`queue_processor.php`)
- **功能**: AI任务队列的核心处理程序
- **特性**: 
  - 内存管理和泄漏防护
  - 进程锁防止重复运行
  - 完整的日志记录
  - 健康状态检查
  - 优雅关闭机制
  - 异常处理和恢复
  - AI模型随机选择支持

### 2. 监控系统 (`monitor.php`)
- **功能**: 进程状态监控和健康检查
- **特性**:
  - 实时进程状态检测
  - 性能指标收集
  - 自动重启机制
  - 报警通知功能

### 3. Linux统一管理脚本 (`queue.sh`)
- **功能**: Linux环境下的一站式管理工具
- **特性**:
  - 自动环境检测和依赖安装
  - 支持systemd和Supervisor两种服务管理方式
  - 智能服务安装、启动、停止、重启
  - 实时状态监控和健康检查
  - 自动监控服务（异常重启、冷却时间、重启次数限制）
  - 多类型日志查看和实时跟踪
  - 彩色输出和友好的用户界面
  - 完整的帮助系统和版本信息

### 4. Windows统一管理脚本 (`queue.bat`)
- **功能**: Windows环境下的一站式管理工具
- **特性**:
  - 基于NSSM的Windows服务管理
  - 服务安装、启动、停止、重启、状态查看、卸载
  - 自动监控系统（异常重启、冷却时间、重启次数限制）
  - 多类型日志查看和实时跟踪
  - 交互式菜单和命令行模式
  - 彩色输出和友好的用户界面
  - 警报通知系统
  - 开机自启、崩溃重启、日志轮转

## 📊 日志文件

所有日志文件默认存储在 `./logs/` 目录下：

- `queue_processor.log` - 队列处理器运行日志
- `monitor.log` - 监控系统日志
- `service.log` - 服务管理日志
- `error.log` - 错误日志

## 🔧 配置说明

### 队列处理器配置
在 `queue_processor.php` 中可以配置：
- 数据库连接参数
- 日志级别和路径
- 内存限制
- 处理间隔时间
- 健康检查参数

### 监控配置
在 `monitor.php` 中可以配置：
- 检查间隔时间
- 重启策略
- 报警阈值
- 通知方式

## 🛠️ 故障排除

### 常见问题

1. **服务无法启动**
   - 检查PHP路径是否正确
   - 确认文件权限设置
   - 查看错误日志

2. **队列处理缓慢**
   - 检查数据库连接
   - 监控内存使用情况
   - 调整处理间隔时间

3. **监控系统异常**
   - 检查进程锁文件
   - 确认日志目录权限
   - 重启监控服务

### 调试命令

#### Linux环境
```bash
# 查看服务状态
./queue.sh status

# 查看实时日志
./queue.sh logs -f

# 手动运行队列处理器（调试模式）
php queue_processor.php --debug

# 检查监控状态
php monitor.php --check

# 检查系统环境
./queue.sh check
```

#### Windows环境
```batch
# 查看服务状态
queue.bat status

# 手动启动队列处理器
php queue_processor.php

# 查看实时日志
queue.bat logs -f

# 检查监控状态
queue.bat monitor status

# 系统环境检查
queue.bat check
```

## 🚀 queue.sh 详细使用说明

### 基本命令

```bash
# 安装服务（首次使用）
sudo ./queue.sh install

# 启动服务
sudo ./queue.sh start

# 停止服务
sudo ./queue.sh stop

# 重启服务
sudo ./queue.sh restart

# 查看服务状态
./queue.sh status

# 卸载服务
sudo ./queue.sh uninstall
```

### 监控管理

```bash
# 启动监控服务
sudo ./queue.sh monitor start
# 停止监控服务
sudo ./queue.sh monitor stop
# 重启监控服务
sudo ./queue.sh monitor restart
# 查看监控状态
./queue.sh monitor status
```

### 日志管理

```bash
# 查看队列处理器日志
./queue.sh logs
# 实时跟踪日志
./queue.sh logs -f
# 查看监控日志
./queue.sh logs monitor
# 查看系统服务日志
./queue.sh logs system
# 查看错误日志
./queue.sh logs error
```

### 系统信息

```bash
# 显示帮助信息
./queue.sh help
# 显示版本信息
./queue.sh version
# 检查系统环境
./queue.sh check
```

### 高级功能

- **自动环境检测**: 脚本会自动检测系统类型、PHP环境、权限等
- **智能服务选择**: 优先使用systemd，不可用时自动切换到Supervisor
- **健康检查**: 内置健康检查机制，确保服务正常运行
- **自动重启**: 监控服务可在队列处理器异常时自动重启
- **日志轮转**: 支持日志文件的自动管理和轮转
- **彩色输出**: 友好的彩色终端输出，提升用户体验

## 🚀 queue.bat 详细使用说明

### 基本命令

```batch
# 服务管理
queue.bat install      # 安装Windows服务
queue.bat start        # 启动服务
queue.bat stop         # 停止服务
queue.bat restart      # 重启服务
queue.bat status       # 查看服务状态
queue.bat uninstall    # 卸载服务
```

### 监控管理

```batch
# 监控服务管理
queue.bat monitor start    # 启动监控服务
queue.bat monitor stop     # 停止监控服务
queue.bat monitor status   # 查看监控状态
queue.bat monitor restart  # 重启监控服务
```

### 日志管理

```batch
# 日志查看
queue.bat logs              # 查看队列处理器日志
queue.bat logs -f           # 实时跟踪日志
queue.bat logs monitor      # 查看监控日志
queue.bat logs service      # 查看服务日志
queue.bat logs error        # 查看错误日志
```

### 系统信息

```batch
# 系统信息
queue.bat help         # 显示帮助信息
queue.bat version      # 显示版本信息
queue.bat check        # 检查系统环境
```

### 高级功能

- **NSSM服务管理**: 基于NSSM工具的专业Windows服务管理
- **交互式菜单**: 直接运行`queue.bat`进入友好的菜单界面
- **健康检查**: 内置健康检查机制，确保服务正常运行
- **多类型日志**: 支持查看不同类型的日志文件
- **实时日志跟踪**: 支持实时跟踪日志文件变化
- **彩色输出**: 友好的彩色命令行输出，提升用户体验
- **警报系统**: 监控异常时可发送警报通知

## 📚 更多信息

本文档已包含完整的部署指南和使用说明。如需更多技术支持，请联系维护团队。

## 🔒 安全建议

1. **文件权限**: 确保脚本文件具有适当的执行权限
2. **日志轮转**: 定期清理或轮转日志文件
3. **监控报警**: 配置适当的监控和报警机制
4. **备份策略**: 定期备份配置文件和重要数据

---

**版本**: 2.2  
**更新日期**: 2025-01-16  
**维护团队**: DoyeCMS Team  
**更新内容**: 
- 统一Windows脚本管理，新增queue.bat一站式管理工具
- 简化目录结构，移除冗余的BAT脚本文件
- 完善文档结构，整合部署和使用指南
- 提升用户体验，统一Linux和Windows管理接口