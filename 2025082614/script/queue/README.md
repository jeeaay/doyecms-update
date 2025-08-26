# DoyeCMS AI队列处理器

这个目录包含了DoyeCMS AI队列处理器的所有相关文件，用于处理AI任务队列的长期稳定运行。

## 📁 目录结构

```
queue/
├── README.md                    # 本文件，目录说明
├── README_SERVICE.md            # 详细的部署和使用文档
├── queue_processor.php          # 队列处理器核心程序
├── monitor.php                  # 进程监控和健康检查程序
├── queue_processor.conf         # Supervisor配置文件（Linux）
├── deploy_linux.sh              # Linux环境一键部署脚本
│
├── Windows脚本/
│   ├── install_service.bat      # Windows服务安装脚本
│   ├── manage_service.bat       # Windows服务管理脚本
│   ├── auto_monitor.bat         # Windows自动监控脚本
│   ├── start_monitor.bat        # Windows监控启动脚本
│   └── stop_monitor.bat         # Windows监控停止脚本
│
└── Linux脚本/
    ├── manage_service.sh        # Linux服务管理脚本
    ├── auto_monitor.sh          # Linux自动监控脚本
    ├── start_monitor.sh         # Linux监控启动脚本
    └── stop_monitor.sh          # Linux监控停止脚本
```

## 快速开始

### Windows环境

```batch
# 进入队列目录
cd ./script/queue

# 安装Windows服务
install_service.bat

# 启动服务
manage_service.bat
```

### Linux环境

```bash
# 进入队列目录
cd ./script/queue

# 设置执行权限
chmod +x *.sh

# 快速部署
sudo ./deploy_linux.sh

# 或手动管理服务
sudo ./manage_service.sh
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

### 2. 监控系统 (`monitor.php`)
- **功能**: 进程状态监控和健康检查
- **特性**:
  - 实时进程状态检测
  - 性能指标收集
  - 自动重启机制
  - 报警通知功能

### 3. 服务管理
- **Windows**: 基于NSSM的Windows服务
- **Linux**: 支持systemd和Supervisor两种方式
- **功能**: 开机自启、崩溃重启、日志轮转

### 4. 自动监控
- **功能**: 定期检查队列处理器状态
- **特性**: 异常自动重启、冷却时间、重启次数限制

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

```bash
# 查看服务状态
./manage_service.sh status

# 查看实时日志
tail -f ./logs/queue_processor.log

# 手动运行队列处理器（调试模式）
php queue_processor.php --debug

# 检查监控状态
php monitor.php --check
```

## 📚 更多信息

详细的部署指南和使用说明请参考 [README_SERVICE.md](README_SERVICE.md)

## 🔒 安全建议

1. **文件权限**: 确保脚本文件具有适当的执行权限
2. **日志轮转**: 定期清理或轮转日志文件
3. **监控报警**: 配置适当的监控和报警机制
4. **备份策略**: 定期备份配置文件和重要数据

---

**版本**: 1.0  
**更新日期**: 2025-01-16  
**维护团队**: DoyeCMS Team