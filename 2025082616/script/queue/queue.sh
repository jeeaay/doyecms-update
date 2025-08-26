#!/bin/bash

# DoyeCMS AI队列处理器统一管理脚本 (Linux版本)
# 功能：统一管理队列处理器的安装、启动、停止、监控等操作
# 作者：DoyeCMS Team
# 版本：2.1
# 日期：2025-01-16

# 获取脚本目录
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# 加载.env配置文件
if [ -f "$SCRIPT_DIR/.env" ]; then
    # 读取.env文件并导出变量
    export $(grep -v '^#' "$SCRIPT_DIR/.env" | grep -v '^$' | xargs)
    echo "已加载配置文件: $SCRIPT_DIR/.env"
else
    echo "警告: 未找到配置文件 $SCRIPT_DIR/.env，使用默认配置"
fi

# 设置默认配置（如果.env文件中没有定义）
PHP_PATH="${PHP_PATH:-/usr/bin/php}"
QUEUE_PROCESSOR_PATH="${QUEUE_PROCESSOR_PATH:-queue_processor.php}"
MONITOR_SCRIPT_PATH="${MONITOR_SCRIPT_PATH:-monitor.php}"
LOG_DIR_NAME="${LOG_DIR:-logs}"
SERVICE_NAME="${SERVICE_NAME:-doyecms-queue}"
MAX_MEMORY_LIMIT="${MAX_MEMORY_LIMIT:-512}"
LOG_LEVEL="${LOG_LEVEL:-info}"

# 构建完整路径
QUEUE_SCRIPT="$SCRIPT_DIR/$QUEUE_PROCESSOR_PATH"
MONITOR_SCRIPT="$SCRIPT_DIR/$MONITOR_SCRIPT_PATH"
LOG_DIR="$SCRIPT_DIR/$LOG_DIR_NAME"
SERVICE_FILE="/etc/systemd/system/${SERVICE_NAME}.service"
SUPERVISOR_CONF="/etc/supervisor/conf.d/${SERVICE_NAME}.conf"
PID_FILE="$SCRIPT_DIR/monitor.pid"
STOP_FILE="$SCRIPT_DIR/.stop_monitor"
INSTALL_FLAG="$SCRIPT_DIR/.installed"

# 验证PHP路径
if [ ! -x "$PHP_PATH" ] && ! command -v "$PHP_PATH" &> /dev/null; then
    echo "错误: PHP路径无效: $PHP_PATH"
    echo "请检查.env文件中的PHP_PATH配置或安装PHP"
    exit 1
fi

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# 日志函数
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

log_blue() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_purple() {
    echo -e "${PURPLE}[STEP]${NC} $1"
}

log_cyan() {
    echo -e "${CYAN}[SUCCESS]${NC} $1"
}

# 显示横幅
show_banner() {
    echo -e "${CYAN}"
    echo "================================================================"
    echo "    DoyeCMS AI队列处理器统一管理脚本 v2.0"
    echo "================================================================"
    echo -e "${NC}"
    echo "功能：队列处理器安装、服务管理、监控管理"
    echo ""
}

# 检查是否为root用户
check_root() {
    if [ "$EUID" -ne 0 ]; then
        log_error "此操作需要root权限，请使用sudo运行"
        return 1
    fi
    return 0
}

# 检查系统环境
check_system_environment() {
    log_purple "检查系统环境"
    
    # 检查操作系统
    if [ ! -f /etc/os-release ]; then
        log_error "无法识别操作系统"
        return 1
    fi
    
    local os_info=$(cat /etc/os-release | grep PRETTY_NAME | cut -d'"' -f2)
    log_info "操作系统: $os_info"
    
    # 检查必要的命令
    local required_commands=("php" "systemctl" "chmod" "mkdir")
    for cmd in "${required_commands[@]}"; do
        if command -v "$cmd" >/dev/null 2>&1; then
            log_info "✓ $cmd 命令可用"
        else
            log_error "✗ $cmd 命令不可用"
            return 1
        fi
    done
    
    return 0
}

# 检查PHP环境
check_php_environment() {
    log_purple "检查PHP环境"
    
    # 检查PHP版本
    if [ -f "$PHP_PATH" ]; then
        local php_version=$($PHP_PATH -v | head -n 1)
        log_info "PHP版本: $php_version"
    else
        log_error "PHP可执行文件不存在: $PHP_PATH"
        log_info "请安装PHP或修改脚本中的PHP_PATH变量"
        return 1
    fi
    
    # 检查PHP扩展
    local required_extensions=("mysqli" "json" "pcntl" "posix")
    local missing_extensions=()
    
    for ext in "${required_extensions[@]}"; do
        if $PHP_PATH -m | grep -q "^$ext$"; then
            log_info "✓ PHP扩展 $ext 已安装"
        else
            log_warn "✗ PHP扩展 $ext 未安装"
            missing_extensions+=("$ext")
        fi
    done
    
    if [ ${#missing_extensions[@]} -gt 0 ]; then
        log_warn "缺少PHP扩展: ${missing_extensions[*]}"
        log_info "安装命令示例:"
        log_info "  Ubuntu/Debian: sudo apt-get install php-mysqli php-json php-pcntl"
        log_info "  CentOS/RHEL: sudo yum install php-mysqli php-json php-process"
    fi
    
    # 检查队列处理器脚本
    if [ -f "$QUEUE_SCRIPT" ]; then
        log_info "✓ 队列处理器脚本存在: $QUEUE_SCRIPT"
    else
        log_error "✗ 队列处理器脚本不存在: $QUEUE_SCRIPT"
        return 1
    fi
    
    return 0
}

# 设置文件权限
setup_permissions() {
    log_purple "设置文件权限"
    
    # 设置Shell脚本执行权限
    chmod +x "$0"
    
    # 创建日志目录
    if [ ! -d "$LOG_DIR" ]; then
        mkdir -p "$LOG_DIR"
        log_info "✓ 创建日志目录: $LOG_DIR"
    else
        log_info "✓ 日志目录已存在: $LOG_DIR"
    fi
    
    # 设置日志目录权限
    if command -v www >/dev/null 2>&1; then
        chown -R www:www "$LOG_DIR" 2>/dev/null || true
        log_info "✓ 设置日志目录所有者为 www"
    fi
    
    return 0
}

# 创建systemd服务文件
create_systemd_service() {
    log_info "创建systemd服务文件..."
    
    cat > "$SERVICE_FILE" << EOF
[Unit]
Description=DoyeCMS AI队列处理器服务
After=network.target mysql.service
Wants=network.target

[Service]
Type=simple
User=www
Group=www
WorkingDirectory=$SCRIPT_DIR
ExecStart=$PHP_PATH $QUEUE_SCRIPT
ExecReload=/bin/kill -USR1 \$MAINPID
Restart=always
RestartSec=10
StartLimitInterval=60
StartLimitBurst=3

# 环境变量
Environment=PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
Environment=TZ=Asia/Shanghai

# 资源限制
LimitNOFILE=65536
LimitNPROC=4096

# 日志配置
StandardOutput=append:$LOG_DIR/service.log
StandardError=append:$LOG_DIR/service_error.log

# 安全配置
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=strict
ReadWritePaths=$LOG_DIR $SCRIPT_DIR

[Install]
WantedBy=multi-user.target
EOF
    
    # 重新加载systemd配置
    systemctl daemon-reload
    log_info "systemd服务文件创建完成: $SERVICE_FILE"
}

# 创建Supervisor配置文件
create_supervisor_config() {
    log_info "创建Supervisor配置文件..."
    
    # 检查Supervisor是否安装
    if ! command -v supervisorctl >/dev/null 2>&1; then
        log_warn "Supervisor未安装，跳过配置文件创建"
        log_info "安装命令: sudo apt-get install supervisor (Ubuntu/Debian)"
        log_info "安装命令: sudo yum install supervisor (CentOS/RHEL)"
        return 1
    fi
    
    cat > "$SUPERVISOR_CONF" << EOF
[program:$SERVICE_NAME]
command=$PHP_PATH $QUEUE_SCRIPT
directory=$SCRIPT_DIR
user=www
group=www
autostart=true
autorestart=true
startsecs=10
startretries=3
redirect_stderr=true
stdout_logfile=$LOG_DIR/supervisor.log
stdout_logfile_maxbytes=50MB
stdout_logfile_backups=5
environment=TZ=Asia/Shanghai,PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
priority=999
EOF
    
    # 重新加载Supervisor配置
    supervisorctl reread
    supervisorctl update
    log_info "Supervisor配置文件创建完成: $SUPERVISOR_CONF"
}

# 安装服务
install_service() {
    log_purple "开始安装DoyeCMS AI队列处理器服务"
    
    if ! check_root; then
        return 1
    fi
    
    if ! check_system_environment; then
        return 1
    fi
    
    if ! check_php_environment; then
        return 1
    fi
    
    if ! setup_permissions; then
        return 1
    fi
    
    echo "请选择服务管理方式:"
    echo "1) systemd (推荐，适用于大多数现代Linux发行版)"
    echo "2) Supervisor (适用于需要更精细控制的场景)"
    echo "3) 两者都安装"
    read -p "请输入选择 [1-3]: " choice
    
    case $choice in
        1)
            create_systemd_service
            systemctl enable "$SERVICE_NAME"
            log_cyan "systemd服务安装完成"
            ;;
        2)
            create_supervisor_config
            log_cyan "Supervisor配置安装完成"
            ;;
        3)
            create_systemd_service
            create_supervisor_config
            systemctl enable "$SERVICE_NAME"
            log_cyan "systemd和Supervisor配置都已安装"
            ;;
        *)
            log_error "无效选择"
            return 1
            ;;
    esac
    
    # 创建安装标记文件
    touch "$INSTALL_FLAG"
    
    log_cyan "安装完成！"
    echo ""
    echo "下一步操作:"
    echo "  启动服务: $0 start"
    echo "  查看状态: $0 status"
    echo "  启动监控: $0 monitor start"
    
    return 0
}

# 启动服务
start_service() {
    log_info "启动DoyeCMS AI队列处理器服务..."
    
    # 检查systemd服务
    if [ -f "$SERVICE_FILE" ]; then
        if ! check_root; then
            log_error "启动systemd服务需要root权限"
            return 1
        fi
        
        systemctl start "$SERVICE_NAME"
        if [ $? -eq 0 ]; then
            log_cyan "systemd服务启动成功"
        else
            log_error "systemd服务启动失败"
            return 1
        fi
    else
        log_warn "systemd服务未安装，尝试直接启动队列处理器"
        
        # 直接启动队列处理器
        nohup $PHP_PATH "$QUEUE_SCRIPT" > "$LOG_DIR/queue_processor.log" 2>&1 &
        local pid=$!
        
        sleep 2
        if kill -0 "$pid" 2>/dev/null; then
            log_cyan "队列处理器启动成功 (PID: $pid)"
            echo $pid > "$SCRIPT_DIR/queue.pid"
        else
            log_error "队列处理器启动失败"
            return 1
        fi
    fi
    
    return 0
}

# 停止服务
stop_service() {
    log_info "停止DoyeCMS AI队列处理器服务..."
    
    # 检查systemd服务
    if [ -f "$SERVICE_FILE" ]; then
        if ! check_root; then
            log_error "停止systemd服务需要root权限"
            return 1
        fi
        
        systemctl stop "$SERVICE_NAME"
        if [ $? -eq 0 ]; then
            log_cyan "systemd服务停止成功"
        else
            log_error "systemd服务停止失败"
            return 1
        fi
    else
        # 直接停止队列处理器
        local pid_file="$SCRIPT_DIR/queue.pid"
        if [ -f "$pid_file" ]; then
            local pid=$(cat "$pid_file")
            if kill -0 "$pid" 2>/dev/null; then
                kill -TERM "$pid"
                sleep 2
                if kill -0 "$pid" 2>/dev/null; then
                    kill -KILL "$pid"
                fi
                rm -f "$pid_file"
                log_cyan "队列处理器停止成功"
            else
                log_warn "队列处理器进程不存在"
                rm -f "$pid_file"
            fi
        else
            log_warn "未找到队列处理器PID文件"
        fi
    fi
    
    return 0
}

# 重启服务
restart_service() {
    log_info "重启DoyeCMS AI队列处理器服务..."
    
    stop_service
    sleep 2
    start_service
    
    return $?
}

# 查看服务状态
show_service_status() {
    log_blue "=== DoyeCMS AI队列处理器服务状态 ==="
    
    # 检查systemd服务状态
    if [ -f "$SERVICE_FILE" ]; then
        echo "systemd服务状态:"
        systemctl status "$SERVICE_NAME" --no-pager -l
        echo ""
    fi
    
    # 检查Supervisor服务状态
    if [ -f "$SUPERVISOR_CONF" ] && command -v supervisorctl >/dev/null 2>&1; then
        echo "Supervisor服务状态:"
        supervisorctl status "$SERVICE_NAME"
        echo ""
    fi
    
    # 检查进程状态
    echo "进程状态:"
    local queue_pids=$(pgrep -f "queue_processor.php" 2>/dev/null)
    if [ -n "$queue_pids" ]; then
        echo "队列处理器进程:"
        ps -p $queue_pids -o pid,ppid,cmd
    else
        echo "未发现队列处理器进程"
    fi
    
    echo ""
    
    # 检查监控状态
    show_monitor_status
}

# 检查监控服务状态
check_monitor_status() {
    if [ -f "$PID_FILE" ]; then
        local pid=$(cat "$PID_FILE")
        if kill -0 "$pid" 2>/dev/null; then
            echo "$pid"  # 返回PID
            return 0     # 运行中
        else
            rm -f "$PID_FILE"  # 清理无效PID文件
            return 1           # 未运行
        fi
    else
        return 1  # 未运行
    fi
}

# 显示监控状态
show_monitor_status() {
    echo "监控服务状态:"
    local pid
    pid=$(check_monitor_status)
    local status=$?
    
    if [ $status -eq 0 ]; then
        echo "  监控服务正在运行 (PID: $pid)"
        echo "  PID文件: $PID_FILE"
    else
        echo "  监控服务未运行"
    fi
    
    echo ""
}

# 启动监控服务
start_monitor() {
    log_info "启动DoyeCMS AI队列处理器监控服务..."
    
    # 检查是否已在运行
    local pid
    pid=$(check_monitor_status)
    local status=$?
    
    if [ $status -eq 0 ]; then
        log_warn "监控服务已在运行 (PID: $pid)"
        return 0
    fi
    
    # 检查监控脚本
    if [ ! -f "$MONITOR_SCRIPT" ]; then
        log_error "监控脚本不存在: $MONITOR_SCRIPT"
        return 1
    fi
    
    # 启动监控服务
    nohup bash -c '
        SCRIPT_DIR="'"$SCRIPT_DIR"'"
        PHP_PATH="'"$PHP_PATH"'"
        QUEUE_SCRIPT="'"$QUEUE_SCRIPT"'"
        MONITOR_SCRIPT="'"$MONITOR_SCRIPT"'"
        LOG_DIR="'"$LOG_DIR"'"
        MONITOR_LOG="'"$LOG_DIR"'/monitor.log"
        STOP_FILE="'"$STOP_FILE"'"
        PID_FILE="'"$PID_FILE"'"
        
        CHECK_INTERVAL=30
        MAX_RESTART_COUNT=5
        RESTART_COOLDOWN=300
        
        RESTART_COUNT=0
        LAST_RESTART_TIME=0
        
        log_message() {
            local level="$1"
            local message="$2"
            local timestamp=$(date "+%Y-%m-%d %H:%M:%S")
            echo "[$timestamp] [$level] $message" | tee -a "$MONITOR_LOG"
        }
        
        check_queue_health() {
            local result
            result=$($PHP_PATH "$MONITOR_SCRIPT" --check 2>&1)
            local exit_code=$?
            
            if [ $exit_code -eq 0 ]; then
                log_message "INFO" "队列处理器健康检查通过"
                return 0
            else
                log_message "WARNING" "队列处理器健康检查失败: $result"
                return 1
            fi
        }
        
        restart_queue_processor() {
            local current_time=$(date +%s)
            
            if [ $((current_time - LAST_RESTART_TIME)) -lt $RESTART_COOLDOWN ]; then
                log_message "WARNING" "重启冷却时间未到，跳过重启"
                return 1
            fi
            
            if [ $RESTART_COUNT -ge $MAX_RESTART_COUNT ]; then
                log_message "ERROR" "达到最大重启次数限制($MAX_RESTART_COUNT)，停止自动重启"
                return 1
            fi
            
            log_message "INFO" "开始重启队列处理器..."
            
            $PHP_PATH "$MONITOR_SCRIPT" --restart 2>&1 | while read line; do
                log_message "INFO" "重启输出: $line"
            done
            
            sleep 10
            
            if check_queue_health; then
                RESTART_COUNT=$((RESTART_COUNT + 1))
                LAST_RESTART_TIME=$current_time
                log_message "INFO" "队列处理器重启成功 (第${RESTART_COUNT}次)"
                return 0
            else
                log_message "ERROR" "队列处理器重启失败"
                return 1
            fi
        }
        
        cleanup() {
            log_message "INFO" "监控服务正在停止..."
            rm -f "$PID_FILE"
            exit 0
        }
        
        trap cleanup SIGTERM SIGINT
        
        log_message "INFO" "DoyeCMS AI队列处理器监控服务启动"
        log_message "INFO" "监控配置: 检查间隔=${CHECK_INTERVAL}s, 最大重启次数=${MAX_RESTART_COUNT}"
        
        echo $$ > "$PID_FILE"
        
        while true; do
            if [ -f "$STOP_FILE" ]; then
                log_message "INFO" "检测到停止信号文件，监控服务即将停止"
                rm -f "$STOP_FILE"
                break
            fi
            
            if ! check_queue_health; then
                log_message "WARNING" "队列处理器健康检查失败，尝试重启"
                
                if restart_queue_processor; then
                    log_message "INFO" "队列处理器重启成功"
                    if [ $RESTART_COUNT -gt 0 ] && [ $(($(date +%s) - LAST_RESTART_TIME)) -gt $((RESTART_COOLDOWN * 2)) ]; then
                        RESTART_COUNT=0
                        log_message "INFO" "重启计数器已重置"
                    fi
                else
                    log_message "ERROR" "队列处理器重启失败"
                fi
            fi
            
            sleep $CHECK_INTERVAL
        done
        
        cleanup
    ' > /dev/null 2>&1 &
    
    local monitor_pid=$!
    
    # 等待一下确保启动
    sleep 2
    
    # 检查是否启动成功
    if kill -0 "$monitor_pid" 2>/dev/null; then
        log_cyan "监控服务启动成功 (PID: $monitor_pid)"
        return 0
    else
        log_error "监控服务启动失败"
        return 1
    fi
}

# 停止监控服务
stop_monitor() {
    log_info "停止DoyeCMS AI队列处理器监控服务..."
    
    local pid
    pid=$(check_monitor_status)
    local status=$?
    
    if [ $status -ne 0 ]; then
        log_warn "监控服务未运行"
        rm -f "$PID_FILE" "$STOP_FILE"
        return 0
    fi
    
    log_info "发送停止信号给监控服务 (PID: $pid)..."
    
    # 创建停止信号文件
    touch "$STOP_FILE"
    
    # 等待服务优雅退出
    local count=0
    local max_wait=30
    
    log_info "等待监控服务优雅退出 (最多等待 ${max_wait} 秒)..."
    
    while [ $count -lt $max_wait ]; do
        if ! kill -0 "$pid" 2>/dev/null; then
            log_cyan "监控服务已优雅退出"
            rm -f "$PID_FILE" "$STOP_FILE"
            return 0
        fi
        
        sleep 1
        count=$((count + 1))
    done
    
    log_warn "优雅退出超时，将强制终止进程"
    
    # 强制停止
    kill -TERM "$pid" 2>/dev/null
    sleep 2
    
    if ! kill -0 "$pid" 2>/dev/null; then
        log_cyan "监控服务已终止"
        rm -f "$PID_FILE" "$STOP_FILE"
        return 0
    fi
    
    kill -KILL "$pid" 2>/dev/null
    sleep 1
    
    if ! kill -0 "$pid" 2>/dev/null; then
        log_cyan "监控服务已强制终止"
        rm -f "$PID_FILE" "$STOP_FILE"
        return 0
    else
        log_error "无法终止监控进程"
        return 1
    fi
}

# 重启监控服务
restart_monitor() {
    log_info "重启DoyeCMS AI队列处理器监控服务..."
    
    stop_monitor
    sleep 2
    start_monitor
    
    return $?
}

# 查看日志
show_logs() {
    local log_type="$1"
    local lines="${2:-50}"
    
    if [ ! -d "$LOG_DIR" ]; then
        log_error "日志目录不存在: $LOG_DIR"
        return 1
    fi
    
    case "$log_type" in
        "queue"|"processor")
            local log_file="$LOG_DIR/queue_processor.log"
            ;;
        "monitor")
            local log_file="$LOG_DIR/monitor.log"
            ;;
        "service")
            local log_file="$LOG_DIR/service.log"
            ;;
        "error")
            local log_file="$LOG_DIR/service_error.log"
            ;;
        "supervisor")
            local log_file="$LOG_DIR/supervisor.log"
            ;;
        *)
            log_error "未知的日志类型: $log_type"
            echo "可用的日志类型: queue, monitor, service, error, supervisor"
            return 1
            ;;
    esac
    
    if [ -f "$log_file" ]; then
        log_blue "=== $log_type 日志 (最近 $lines 行) ==="
        tail -n "$lines" "$log_file"
    else
        log_warn "日志文件不存在: $log_file"
    fi
}

# 实时查看日志
follow_logs() {
    local log_type="$1"
    
    if [ ! -d "$LOG_DIR" ]; then
        log_error "日志目录不存在: $LOG_DIR"
        return 1
    fi
    
    case "$log_type" in
        "queue"|"processor")
            local log_file="$LOG_DIR/queue_processor.log"
            ;;
        "monitor")
            local log_file="$LOG_DIR/monitor.log"
            ;;
        "service")
            local log_file="$LOG_DIR/service.log"
            ;;
        "error")
            local log_file="$LOG_DIR/service_error.log"
            ;;
        "supervisor")
            local log_file="$LOG_DIR/supervisor.log"
            ;;
        *)
            log_error "未知的日志类型: $log_type"
            echo "可用的日志类型: queue, monitor, service, error, supervisor"
            return 1
            ;;
    esac
    
    if [ -f "$log_file" ]; then
        log_blue "=== 实时查看 $log_type 日志 (按 Ctrl+C 退出) ==="
        tail -f "$log_file"
    else
        log_warn "日志文件不存在: $log_file"
        log_info "创建空日志文件..."
        touch "$log_file"
        tail -f "$log_file"
    fi
}

# 显示帮助信息
show_help() {
    show_banner
    
    echo "用法: $0 [命令] [选项]"
    echo ""
    echo "服务管理命令:"
    echo "  install                 安装队列处理器服务 (需要root权限)"
    echo "  start                   启动队列处理器服务"
    echo "  stop                    停止队列处理器服务"
    echo "  restart                 重启队列处理器服务"
    echo "  status                  查看服务状态"
    echo ""
    echo "监控管理命令:"
    echo "  monitor start           启动监控服务"
    echo "  monitor stop            停止监控服务"
    echo "  monitor restart         重启监控服务"
    echo "  monitor status          查看监控状态"
    echo ""
    echo "日志管理命令:"
    echo "  logs <type> [lines]     查看指定类型的日志 (默认50行)"
    echo "  follow <type>           实时查看指定类型的日志"
    echo ""
    echo "日志类型:"
    echo "  queue, processor        队列处理器日志"
    echo "  monitor                 监控服务日志"
    echo "  service                 系统服务日志"
    echo "  error                   错误日志"
    echo "  supervisor              Supervisor日志"
    echo ""
    echo "其他命令:"
    echo "  --help, -h              显示此帮助信息"
    echo "  --version, -v           显示版本信息"
    echo ""
    echo "示例:"
    echo "  $0 install              # 安装服务"
    echo "  $0 start                # 启动服务"
    echo "  $0 monitor start        # 启动监控"
    echo "  $0 logs queue 100       # 查看队列日志最近100行"
    echo "  $0 follow monitor       # 实时查看监控日志"
    echo ""
    echo "配置文件位置:"
    echo "  脚本目录: $SCRIPT_DIR"
    echo "  日志目录: $LOG_DIR"
    echo "  systemd服务: $SERVICE_FILE"
    echo "  Supervisor配置: $SUPERVISOR_CONF"
}

# 显示版本信息
show_version() {
    echo "DoyeCMS AI队列处理器统一管理脚本 v2.0"
    echo "作者: DoyeCMS Team"
    echo "日期: 2025-01-16"
}

# 主程序入口
main() {
    # 检查是否为首次运行
    if [ ! -f "$INSTALL_FLAG" ] && [ "$1" != "install" ] && [ "$1" != "--help" ] && [ "$1" != "-h" ] && [ "$1" != "--version" ] && [ "$1" != "-v" ]; then
        show_banner
        log_warn "检测到首次运行，请先执行安装:"
        echo "  sudo $0 install"
        echo ""
        echo "或查看帮助信息:"
        echo "  $0 --help"
        return 1
    fi
    
    case "$1" in
        "install")
            install_service
            ;;
        "start")
            start_service
            ;;
        "stop")
            stop_service
            ;;
        "restart")
            restart_service
            ;;
        "status")
            show_service_status
            ;;
        "monitor")
            case "$2" in
                "start")
                    start_monitor
                    ;;
                "stop")
                    stop_monitor
                    ;;
                "restart")
                    restart_monitor
                    ;;
                "status")
                    show_monitor_status
                    ;;
                *)
                    log_error "未知的监控命令: $2"
                    echo "可用命令: start, stop, restart, status"
                    return 1
                    ;;
            esac
            ;;
        "logs")
            if [ -z "$2" ]; then
                log_error "请指定日志类型"
                echo "可用类型: queue, monitor, service, error, supervisor"
                return 1
            fi
            show_logs "$2" "$3"
            ;;
        "follow")
            if [ -z "$2" ]; then
                log_error "请指定日志类型"
                echo "可用类型: queue, monitor, service, error, supervisor"
                return 1
            fi
            follow_logs "$2"
            ;;
        "--help"|"-h")
            show_help
            ;;
        "--version"|"-v")
            show_version
            ;;
        "")
            show_help
            ;;
        *)
            log_error "未知命令: $1"
            echo "使用 '$0 --help' 查看帮助信息"
            return 1
            ;;
    esac
}

# 执行主程序
main "$@"
exit $?