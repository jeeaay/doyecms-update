#!/bin/bash

# DoyeCMS AI队列处理器监控启动脚本 (Linux版本)
# 功能：启动队列处理器自动监控服务
# 作者：DoyeCMS Team
# 版本：1.0
# 日期：2025-01-16

# 获取脚本目录
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MONITOR_SCRIPT="$SCRIPT_DIR/auto_monitor.sh"
LOG_DIR="$SCRIPT_DIR/logs"
MONITOR_LOG="$LOG_DIR/monitor.log"
STOP_FILE="$SCRIPT_DIR/.stop_monitor"
PID_FILE="$SCRIPT_DIR/monitor.pid"

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

# 显示标题
show_banner() {
    echo "================================================"
    echo "    DoyeCMS AI队列处理器自动监控服务"
    echo "================================================"
    echo ""
}

# 检查环境
check_environment() {
    log_info "检查运行环境..."
    
    # 检查监控脚本是否存在
    if [ ! -f "$MONITOR_SCRIPT" ]; then
        log_error "监控脚本不存在: $MONITOR_SCRIPT"
        return 1
    fi
    
    # 检查脚本权限
    if [ ! -x "$MONITOR_SCRIPT" ]; then
        log_warn "监控脚本没有执行权限，正在设置..."
        chmod +x "$MONITOR_SCRIPT"
        if [ $? -eq 0 ]; then
            log_info "执行权限设置成功"
        else
            log_error "无法设置执行权限"
            return 1
        fi
    fi
    
    # 创建日志目录
    if [ ! -d "$LOG_DIR" ]; then
        mkdir -p "$LOG_DIR"
        log_info "创建日志目录: $LOG_DIR"
    fi
    
    return 0
}

# 检查监控服务状态
check_monitor_status() {
    if [ -f "$PID_FILE" ]; then
        local pid=$(cat "$PID_FILE")
        if kill -0 "$pid" 2>/dev/null; then
            return 0  # 运行中
        else
            rm -f "$PID_FILE"  # 清理无效PID文件
            return 1  # 未运行
        fi
    else
        return 1  # 未运行
    fi
}

# 停止现有监控服务
stop_existing_monitor() {
    if check_monitor_status; then
        log_warn "检测到监控服务正在运行，正在停止..."
        
        # 创建停止信号文件
        touch "$STOP_FILE"
        
        # 等待服务停止
        local count=0
        while [ $count -lt 30 ] && check_monitor_status; do
            sleep 1
            count=$((count + 1))
        done
        
        # 如果仍在运行，强制停止
        if check_monitor_status; then
            local pid=$(cat "$PID_FILE")
            log_warn "强制停止监控进程 (PID: $pid)"
            kill -TERM "$pid" 2>/dev/null
            sleep 2
            if kill -0 "$pid" 2>/dev/null; then
                kill -KILL "$pid" 2>/dev/null
            fi
        fi
        
        # 清理文件
        rm -f "$PID_FILE" "$STOP_FILE"
        log_info "现有监控服务已停止"
    fi
}

# 启动监控服务
start_monitor_service() {
    log_info "启动DoyeCMS AI队列处理器监控服务..."
    
    # 后台启动监控脚本
    nohup "$MONITOR_SCRIPT" start > /dev/null 2>&1 &
    local monitor_pid=$!
    
    # 等待一下确保启动
    sleep 2
    
    # 检查是否启动成功
    if kill -0 "$monitor_pid" 2>/dev/null; then
        log_info "监控服务启动成功 (PID: $monitor_pid)"
        return 0
    else
        log_error "监控服务启动失败"
        return 1
    fi
}

# 显示服务信息
show_service_info() {
    log_blue "=== 服务信息 ==="
    echo "监控脚本: $MONITOR_SCRIPT"
    echo "日志目录: $LOG_DIR"
    echo "主日志文件: $MONITOR_LOG"
    echo "PID文件: $PID_FILE"
    echo ""
    
    log_blue "=== 管理命令 ==="
    echo "查看监控状态: $MONITOR_SCRIPT status"
    echo "停止监控服务: $MONITOR_SCRIPT stop"
    echo "重启监控服务: $MONITOR_SCRIPT restart"
    echo "查看实时日志: tail -f $MONITOR_LOG"
    echo ""
    
    log_blue "=== 服务管理 ==="
    echo "安装系统服务: sudo $SCRIPT_DIR/manage_service.sh install"
    echo "启动系统服务: sudo $SCRIPT_DIR/manage_service.sh start"
    echo "查看服务状态: $SCRIPT_DIR/manage_service.sh status"
    echo ""
}

# 显示日志文件位置
show_log_info() {
    log_blue "=== 日志文件位置 ==="
    
    if [ -d "$LOG_DIR" ]; then
        echo "日志目录: $LOG_DIR"
        echo ""
        echo "可用的日志文件:"
        
        for log_file in "$LOG_DIR"/*.log; do
            if [ -f "$log_file" ]; then
                local file_size=$(du -h "$log_file" | cut -f1)
                local file_time=$(stat -c %y "$log_file" 2>/dev/null || stat -f %Sm "$log_file" 2>/dev/null || echo "未知")
                echo "  $(basename "$log_file") (大小: $file_size, 修改时间: ${file_time%.*})"
            fi
        done
        
        echo ""
        echo "查看实时日志命令:"
        echo "  tail -f $LOG_DIR/monitor.log          # 监控日志"
        echo "  tail -f $LOG_DIR/queue_processor.log  # 队列处理器日志"
        echo "  tail -f $LOG_DIR/service.log          # 系统服务日志"
    else
        log_warn "日志目录不存在: $LOG_DIR"
    fi
    
    echo ""
}

# 主程序
main() {
    show_banner
    
    # 检查环境
    if ! check_environment; then
        log_error "环境检查失败，无法启动监控服务"
        exit 1
    fi
    
    # 停止现有服务
    stop_existing_monitor
    
    # 启动监控服务
    if start_monitor_service; then
        echo ""
        log_info "DoyeCMS AI队列处理器监控服务已启动！"
        echo ""
        
        # 显示服务信息
        show_service_info
        
        # 显示日志信息
        show_log_info
        
        log_info "监控服务正在后台运行，可以安全关闭此窗口"
        
        # 询问是否查看实时日志
        echo ""
        read -p "是否查看实时监控日志? [y/N]: " view_logs
        if [ "$view_logs" = "y" ] || [ "$view_logs" = "Y" ]; then
            echo ""
            log_info "显示实时监控日志 (按 Ctrl+C 退出):"
            echo "================================================"
            tail -f "$MONITOR_LOG" 2>/dev/null || {
                log_warn "日志文件尚未创建，等待监控服务生成日志..."
                sleep 3
                tail -f "$MONITOR_LOG" 2>/dev/null || log_error "无法读取日志文件"
            }
        fi
    else
        log_error "监控服务启动失败！"
        echo ""
        log_info "请检查以下内容:"
        echo "1. PHP环境是否正确安装"
        echo "2. queue_processor.php文件是否存在"
        echo "3. 文件权限是否正确"
        echo "4. 系统资源是否充足"
        echo ""
        log_info "查看详细错误信息: tail -f $LOG_DIR/monitor.log"
        exit 1
    fi
}

# 显示帮助信息
show_help() {
    echo "DoyeCMS AI队列处理器监控启动脚本"
    echo "用法: $0 [选项]"
    echo ""
    echo "选项:"
    echo "  start       启动监控服务 (默认)"
    echo "  --help      显示此帮助信息"
    echo ""
    echo "相关脚本:"
    echo "  $MONITOR_SCRIPT     # 监控服务主脚本"
    echo "  $SCRIPT_DIR/manage_service.sh  # 系统服务管理脚本"
    echo ""
}

# 程序入口
case "${1:-start}" in
    start)
        main
        ;;
    --help|-h)
        show_help
        ;;
    *)
        log_error "未知选项: $1"
        show_help
        exit 1
        ;;
esac