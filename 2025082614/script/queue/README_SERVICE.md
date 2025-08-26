# DoyeCMS AI队列处理器服务部署指南

本指南将帮助您在Windows系统上将DoyeCMS AI队列处理器部署为系统服务，确保长期稳定运行。

## 文件说明

- `queue_processor.php` - AI队列处理器主程序
- `queue_processor.conf` - Supervisor配置文件（Linux系统使用）
- `install_service.bat` - Windows服务安装脚本
- `manage_service.bat` - Windows服务管理脚本
- `README_SERVICE.md` - 本说明文件

## Windows系统部署

### 前置要求

1. **安装NSSM工具**
   - 下载地址：https://nssm.cc/download
   - 下载后解压，将`nssm.exe`添加到系统PATH环境变量中
   - 或者将`nssm.exe`复制到`C:\Windows\System32`目录

2. **验证PHP环境**
   - 确保PHP可以正常运行
   - 检查Redis扩展是否已安装
   - 确保相关依赖文件存在

### Windows部署步骤

### 方法一：使用NSSM (推荐)

1. **下载NSSM工具**
   ```bash
   # 下载地址：https://nssm.cc/download
   # 解压到任意目录，建议放在 C:\nssm
   ```

2. **运行安装脚本**
   ```bash
   # 以管理员身份运行
   cd /d "D:\phpstudy_pro\WWW\DoyeCMS\script\queue"
   install_service.bat
   ```

3. **使用管理脚本**
   ```bash
   # 运行服务管理脚本
   manage_service.bat
   ```

### 安装步骤

1. **以管理员身份运行**
   ```batch
   # 右键点击 install_service.bat，选择"以管理员身份运行"
   ```

2. **或者使用管理脚本**
   ```batch
   # 右键点击 manage_service.bat，选择"以管理员身份运行"
   # 然后选择选项1进行安装
   ```

### 服务管理

使用`manage_service.bat`脚本可以方便地管理服务：

- **启动服务**: 选项2
- **停止服务**: 选项3
- **重启服务**: 选项4
- **查看状态**: 选项5
- **查看日志**: 选项6
- **删除服务**: 选项7

### 手动管理命令

如果您熟悉命令行，也可以直接使用NSSM命令：

```batch
# 启动服务
nssm start "DoyeCMS_Queue_Processor"

# 停止服务
nssm stop "DoyeCMS_Queue_Processor"

# 重启服务
nssm restart "DoyeCMS_Queue_Processor"

# 查看状态
nssm status "DoyeCMS_Queue_Processor"

# 删除服务
nssm remove "DoyeCMS_Queue_Processor" confirm
```

## Linux系统部署

### 方法一：使用服务管理脚本 (推荐)

1. **设置脚本权限**
   ```bash
   cd /path/to/DoyeCMS/script/queue
   chmod +x *.sh
   ```

2. **安装服务**
   ```bash
   # 安装systemd或Supervisor服务
   sudo ./manage_service.sh install
   ```

3. **启动服务**
   ```bash
   sudo ./manage_service.sh start
   ```

4. **查看服务状态**
   ```bash
   ./manage_service.sh status
   ```

### 方法二：使用Supervisor

1. **安装Supervisor**
   ```bash
   # Ubuntu/Debian
   sudo apt-get update
   sudo apt-get install supervisor
   
   # CentOS/RHEL
   sudo yum install supervisor
   # 或者 (CentOS 8+)
   sudo dnf install supervisor
   ```

2. **复制配置文件**
   ```bash
   sudo cp queue_processor.conf /etc/supervisor/conf.d/
   ```

3. **重新加载配置**
   ```bash
   sudo supervisorctl reread
   sudo supervisorctl update
   ```

4. **启动服务**
   ```bash
   sudo supervisorctl start doye_queue_processor
   ```

### 方法三：使用systemd

1. **创建服务文件**
   ```bash
   # 使用管理脚本自动创建
   sudo ./manage_service.sh install
   
   # 或手动创建
   sudo cp queue_processor.service /etc/systemd/system/doyecms-queue.service
   ```

2. **启用并启动服务**
   ```bash
   sudo systemctl daemon-reload
   sudo systemctl enable doyecms-queue
   sudo systemctl start doyecms-queue
   ```

### Linux服务管理

#### 使用管理脚本

```bash
# 查看服务状态
./manage_service.sh status

# 启动服务
sudo ./manage_service.sh start

# 停止服务
sudo ./manage_service.sh stop

# 重启服务
sudo ./manage_service.sh restart

# 查看日志
./manage_service.sh logs

# 卸载服务
sudo ./manage_service.sh uninstall
```

#### Supervisor管理命令

```bash
# 查看状态
sudo supervisorctl status doye_queue_processor

# 启动服务
sudo supervisorctl start doye_queue_processor

# 停止服务
sudo supervisorctl stop doye_queue_processor

# 重启服务
sudo supervisorctl restart doye_queue_processor

# 查看日志
sudo supervisorctl tail doye_queue_processor
```

#### systemd管理命令

```bash
# 查看状态
sudo systemctl status doyecms-queue

# 启动服务
sudo systemctl start doyecms-queue

# 停止服务
sudo systemctl stop doyecms-queue

# 重启服务
sudo systemctl restart doyecms-queue

# 查看日志
sudo journalctl -u doyecms-queue -f

# 开机自启
sudo systemctl enable doyecms-queue
```

## 日志文件位置

### Windows环境
- 应用程序日志：`./logs/queue_processor.log`
- 服务日志：`./logs/service.log`
- 错误日志：`./logs/error.log`
- 监控日志：`./logs/monitor.log`

### Linux环境
- 应用程序日志：`./logs/queue_processor.log`
- 监控日志：`./logs/monitor.log`
- systemd服务日志：`./logs/service.log`
- systemd错误日志：`./logs/service_error.log`
- Supervisor日志：`./logs/supervisor.log`
- 系统日志：`journalctl -u doyecms-queue`

### 日志查看命令

#### Windows
```bash
# 查看实时日志
tail -f logs\queue_processor.log
tail -f logs\monitor.log
```

#### Linux
```bash
# 查看实时日志
tail -f logs/queue_processor.log
tail -f logs/monitor.log
tail -f logs/service.log

# 查看systemd日志
sudo journalctl -u doyecms-queue -f

# 查看Supervisor日志
sudo tail -f /var/log/supervisor/doyecms-queue.log
```

## 监控和维护

### Windows监控脚本

```bash
# 启动自动监控
start_monitor.bat

# 停止监控
stop_monitor.bat

# 查看监控状态
monitor.php --check
```

### Linux监控脚本

```bash
# 启动自动监控
./start_monitor.sh

# 停止监控
./stop_monitor.sh

# 查看监控状态
./stop_monitor.sh status

# 强制停止所有相关进程
./stop_monitor.sh force

# 直接使用监控脚本
./auto_monitor.sh start    # 启动监控
./auto_monitor.sh stop     # 停止监控
./auto_monitor.sh status   # 查看状态
./auto_monitor.sh restart  # 重启监控
```

### 性能监控

队列处理器内置了以下监控功能：

- **内存监控**: 每分钟检查内存使用情况
- **健康检查**: 每5分钟执行健康检查
- **错误统计**: 记录处理错误次数
- **任务统计**: 记录处理任务数量

### 自动重启机制

- 内存使用过高时自动重启
- 错误次数过多时自动重启
- 严重错误时立即停止
- 服务管理器会自动重启停止的进程

### 日志轮转

- 应用程序日志按天轮转
- 服务日志自动轮转（10MB/文件，保留10个文件）
- 可通过管理脚本查看最新日志

## 故障排除

### 常见问题

1. **服务无法启动**
   - 检查PHP路径是否正确
   - 检查脚本文件是否存在
   - 检查权限设置
   - 查看错误日志

2. **Redis连接失败**
   - 检查Redis服务是否运行
   - 检查Redis配置
   - 检查网络连接

3. **内存使用过高**
   - 调整内存限制设置
   - 检查是否有内存泄漏
   - 增加服务器内存

### 调试模式

可以直接运行PHP脚本进行调试：

```batch
# Windows
cd /d d:\phpstudy_pro\WWW\DoyeCMS
php script\queue_processor.php

# Linux
cd /path/to/DoyeCMS
php script/queue/queue_processor.php
```

## 配置优化

### 内存设置

在`queue_processor.php`中可以调整以下参数：

```php
// 最大内存使用量（默认128MB）
$this->maxMemoryUsage = 128 * 1024 * 1024;

// 最大执行时间（默认1小时）
$this->maxExecutionTime = 3600;
```

### 服务配置

在Windows服务安装脚本中可以调整：

- 重启延迟时间
- 日志轮转设置
- 启动类型

## 安全建议

1. **权限控制**
   - 使用专用用户运行服务
   - 限制文件访问权限
   - 定期更新系统

2. **日志管理**
   - 定期清理旧日志
   - 监控日志大小
   - 备份重要日志

3. **网络安全**
   - 限制Redis访问
   - 使用防火墙规则
   - 监控网络连接

---

如有问题，请查看日志文件或联系技术支持。