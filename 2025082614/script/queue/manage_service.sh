#!/bin/bash

# DoyeCMS AI队列处理器服务管理脚本 (Linux版本)
# 功能：管理队列处理器服务的安装、启动、停止等操作
# 作者：DoyeCMS Team
# 版本：1.0
# 日期：2025-01-16

# 配置参数
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SERVICE_NAME="doyecms-queue"
SERVICE_DESCRIPTION="DoyeCMS AI队列处理器服务"
PHP_PATH="/usr/bin/php"  # 根据实际环境调整
QUEUE_SCRIPT="$SCRIPT_DIR/queue_processor.php"
MONITOR_SCRIPT="$SCRIPT_DIR/monitor.php"
SERVICE_FILE="/etc/systemd/system/${SERVICE_NAME}.service"
SUPERVISOR_CONF="/etc/supervisor/conf.d/${SERVICE_NAME}.conf"
LOG_DIR="$SCRIPT_DIR/logs"

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
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

# 检查是否为root用户
check_root() {
    if [ "$EUID" -ne 0 ]; then
        log_error "此操作需要root权限，请使用sudo运行"
        return 1
    fi
    return 0
}

# 检查PHP环境
check_php_environment() {
    if [ ! -f "$PHP_PATH" ]; then
        log_error "PHP可执行文件不存在: $PHP_PATH"
        log_info "请安装PHP或修改脚本中的PHP_PATH变量"
        return 1
    fi
    
    if [ ! -f "$QUEUE_SCRIPT" ]; then
        log_error "队列处理器脚本不存在: $QUEUE_SCRIPT"
        return 1
    fi
    
    # 检查PHP扩展
    local required_extensions=("mysqli" "json" "pcntl")
    for ext in "${required_extensions[@]}"; do
        if ! $PHP_PATH -m | grep -q "^$ext$"; then
            log_warn "PHP扩展 $ext 未安装，可能影响功能"
        fi
    done
    
    return 0
}

# 创建systemd服务文件
create_systemd_service() {
    log_info "创建systemd服务文件..."
    
    # 创建日志目录
    mkdir -p "$LOG_DIR"
    chown www:www "$LOG_DIR" 2>/dev/null || true
    
    cat > "$SERVICE_FILE" << EOF
[Unit]
Description=$SERVICE_DESCRIPTION
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
    
    # 创建日志目录
    mkdir -p "$LOG_DIR"
    chown www:www "$LOG_DIR" 2>/dev/null || true
    
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
    log_info "开始安装DoyeCMS AI队列处理器服务..."
    
    if ! check_root; then
        return 1
    fi
    
    if ! check_php_environment; then
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
            log_info "systemd服务安装完成"
            ;;
        2)
            create_supervisor_config
            log_info "Supervisor配置安装完成"
            ;;
        3)
            create_systemd_service
            create_supervisor_config
            systemctl enable "$SERVICE_NAME"
            log_info "systemd和Supervisor配置都已安装"
            ;;
        *)
            log_error "无效选择"
            return 1
            ;;
    esac
    
    log_info "服务安装完成！"
    log_info "使用 '$0 start' 启动服务"
}

# 启动服务
start_service() {
    log_info "启动DoyeCMS AI队列处理器服务..."
    
    # 尝试systemd
    if systemctl is-enabled "$SERVICE_NAME" >/dev/null 2>&1; then
        systemctl start "$SERVICE_NAME"
        if systemctl is-active "$SERVICE_NAME" >/dev/null 2>&1; then
            log_info "systemd服务启动成功"
            return 0
        fi
    fi
    
    # 尝试Supervisor
    if [ -f "$SUPERVISOR_CONF" ]; then
        supervisorctl start "$SERVICE_NAME"
        if supervisorctl status "$SERVICE_NAME" | grep -q "RUNNING"; then
            log_info "Supervisor服务启动成功"
            return 0
        fi
    fi
    
    log_error "服务启动失败，请检查配置"
    return 1
}

# 停止服务
stop_service() {
    log_info "停止DoyeCMS AI队列处理器服务..."
    
    local stopped=false
    
    # 尝试systemd
    if systemctl is-active "$SERVICE_NAME" >/dev/null 2>&1; then
        systemctl stop "$SERVICE_NAME"
        log_info "systemd服务已停止"
        stopped=true
    fi
    
    # 尝试Supervisor
    if supervisorctl status "$SERVICE_NAME" 2>/dev/null | grep -q "RUNNING"; then
        supervisorctl stop "$SERVICE_NAME"
        log_info "Supervisor服务已停止"
        stopped=true
    fi
    
    if [ "$stopped" = false ]; then
        log_warn "未找到运行中的服务"
    fi
}

# 重启服务
restart_service() {
    log_info "重启DoyeCMS AI队列处理器服务..."
    stop_service
    sleep 2
    start_service
}

# 查看服务状态
status_service() {
    log_blue "=== DoyeCMS AI队列处理器服务状态 ==="
    
    # systemd状态
    if [ -f "$SERVICE_FILE" ]; then
        echo -e "\n${BLUE}systemd服务状态:${NC}"
        systemctl status "$SERVICE_NAME" --no-pager -l
    fi
    
    # Supervisor状态
    if [ -f "$SUPERVISOR_CONF" ]; then
        echo -e "\n${BLUE}Supervisor服务状态:${NC}"
        supervisorctl status "$SERVICE_NAME" 2>/dev/null || echo "Supervisor服务未运行"
    fi
    
    # 进程状态
    echo -e "\n${BLUE}相关进程:${NC}"
    ps aux | grep -E "(queue_processor|php.*queue)" | grep -v grep || echo "未找到相关进程"
    
    # 日志文件
    echo -e "\n${BLUE}日志文件:${NC}"
    if [ -d "$LOG_DIR" ]; then
        ls -la "$LOG_DIR"/
    else
        echo "日志目录不存在: $LOG_DIR"
    fi
}

# 查看日志
view_logs() {
    echo "请选择要查看的日志:"
    echo "1) systemd服务日志"
    echo "2) Supervisor日志"
    echo "3) 应用程序日志"
    echo "4) 错误日志"
    read -p "请输入选择 [1-4]: " choice
    
    case $choice in
        1)
            journalctl -u "$SERVICE_NAME" -f
            ;;
        2)
            if [ -f "$LOG_DIR/supervisor.log" ]; then
                tail -f "$LOG_DIR/supervisor.log"
            else
                log_error "Supervisor日志文件不存在"
            fi
            ;;
        3)
            if [ -f "$LOG_DIR/queue_processor.log" ]; then
                tail -f "$LOG_DIR/queue_processor.log"
            else
                log_error "应用程序日志文件不存在"
            fi
            ;;
        4)
            if [ -f "$LOG_DIR/service_error.log" ]; then
                tail -f "$LOG_DIR/service_error.log"
            else
                log_error "错误日志文件不存在"
            fi
            ;;
        *)
            log_error "无效选择"
            ;;
    esac
}

# 卸载服务
uninstall_service() {
    log_warn "即将卸载DoyeCMS AI队列处理器服务"
    read -p "确认卸载? [y/N]: " confirm
    
    if [ "$confirm" != "y" ] && [ "$confirm" != "Y" ]; then
        log_info "取消卸载"
        return 0
    fi
    
    if ! check_root; then
        return 1
    fi
    
    # 停止服务
    stop_service
    
    # 删除systemd配置
    if [ -f "$SERVICE_FILE" ]; then
        systemctl disable "$SERVICE_NAME"
        rm -f "$SERVICE_FILE"
        systemctl daemon-reload
        log_info "systemd配置已删除"
    fi
    
    # 删除Supervisor配置
    if [ -f "$SUPERVISOR_CONF" ]; then
        supervisorctl remove "$SERVICE_NAME" 2>/dev/null || true
        rm -f "$SUPERVISOR_CONF"
        supervisorctl reread
        supervisorctl update
        log_info "Supervisor配置已删除"
    fi
    
    log_info "服务卸载完成"
}

# 显示帮助信息
show_help() {
    echo "DoyeCMS AI队列处理器服务管理脚本"
    echo "用法: $0 [选项]"
    echo ""
    echo "选项:"
    echo "  install     安装服务"
    echo "  start       启动服务"
    echo "  stop        停止服务"
    echo "  restart     重启服务"
    echo "  status      查看服务状态"
    echo "  logs        查看日志"
    echo "  uninstall   卸载服务"
    echo "  --help      显示此帮助信息"
    echo ""
    echo "配置文件:"
    echo "  systemd: $SERVICE_FILE"
    echo "  Supervisor: $SUPERVISOR_CONF"
    echo "  日志目录: $LOG_DIR"
}

# 主程序入口
case "${1:-help}" in
    install)
        install_service
        ;;
    start)
        start_service
        ;;
    stop)
        stop_service
        ;;
    restart)
        restart_service
        ;;
    status)
        status_service
        ;;
    logs)
        view_logs
        ;;
    uninstall)
        uninstall_service
        ;;
    --help|-h|help)
        show_help
        ;;
    *)
        log_error "未知选项: $1"
        show_help
        exit 1
        ;;
esac