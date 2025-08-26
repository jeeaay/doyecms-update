#!/bin/bash

# DoyeCMS AI队列处理器监控停止脚本 (Linux版本)
# 功能：停止队列处理器自动监控服务
# 作者：DoyeCMS Team
# 版本：1.0
# 日期：2025-01-16

# 获取脚本目录
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MONITOR_SCRIPT="$SCRIPT_DIR/auto_monitor.sh"
LOG_DIR="$SCRIPT_DIR/logs"
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
    echo "    DoyeCMS AI队列处理器监控停止服务"
    echo "================================================"
    echo ""
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

# 优雅停止监控服务
graceful_stop() {
    local pid=$1
    
    log_info "发送停止信号给监控服务 (PID: $pid)..."
    
    # 创建停止信号文件
    touch "$STOP_FILE"
    
    # 等待服务优雅退出
    local count=0
    local max_wait=30
    
    log_info "等待监控服务优雅退出 (最多等待 ${max_wait} 秒)..."
    
    while [ $count -lt $max_wait ]; do
        if ! kill -0 "$pid" 2>/dev/null; then
            log_info "监控服务已优雅退出"
            rm -f "$PID_FILE" "$STOP_FILE"
            return 0
        fi
        
        # 显示进度
        if [ $((count % 5)) -eq 0 ]; then
            echo -n "."
        fi
        
        sleep 1
        count=$((count + 1))
    done
    
    echo ""  # 换行
    log_warn "优雅退出超时，将强制终止进程"
    return 1
}

# 强制停止监控服务
force_stop() {
    local pid=$1
    
    log_warn "强制终止监控进程 (PID: $pid)..."
    
    # 发送TERM信号
    kill -TERM "$pid" 2>/dev/null
    sleep 2
    
    # 检查是否已终止
    if ! kill -0 "$pid" 2>/dev/null; then
        log_info "进程已终止"
        rm -f "$PID_FILE" "$STOP_FILE"
        return 0
    fi
    
    # 发送KILL信号
    log_warn "发送KILL信号强制终止进程..."
    kill -KILL "$pid" 2>/dev/null
    sleep 1
    
    # 最终检查
    if ! kill -0 "$pid" 2>/dev/null; then
        log_info "进程已强制终止"
        rm -f "$PID_FILE" "$STOP_FILE"
        return 0
    else
        log_error "无法终止进程，可能需要系统管理员权限"
        return 1
    fi
}

# 清理相关进程
cleanup_related_processes() {
    log_info "检查并清理相关进程..."
    
    # 查找可能的孤儿进程
    local orphan_pids=$(pgrep -f "queue_processor.php" 2>/dev/null)
    
    if [ -n "$orphan_pids" ]; then
        log_warn "发现可能的孤儿进程:"
        ps -p $orphan_pids -o pid,ppid,cmd 2>/dev/null || true
        
        read -p "是否终止这些进程? [y/N]: " kill_orphans
        if [ "$kill_orphans" = "y" ] || [ "$kill_orphans" = "Y" ]; then
            for pid in $orphan_pids; do
                log_info "终止进程 $pid"
                kill -TERM "$pid" 2>/dev/null || true
            done
            
            sleep 2
            
            # 强制终止仍在运行的进程
            for pid in $orphan_pids; do
                if kill -0 "$pid" 2>/dev/null; then
                    log_warn "强制终止进程 $pid"
                    kill -KILL "$pid" 2>/dev/null || true
                fi
            done
        fi
    else
        log_info "未发现相关孤儿进程"
    fi
}

# 显示停止后的信息
show_stop_info() {
    log_blue "=== 监控服务已停止 ==="
    echo ""
    
    # 显示日志文件信息
    if [ -d "$LOG_DIR" ]; then
        echo "日志文件位置: $LOG_DIR"
        echo "查看最近日志: tail -n 50 $LOG_DIR/monitor.log"
    fi
    
    echo ""
    echo "重新启动监控服务:"
    echo "  $SCRIPT_DIR/start_monitor.sh"
    echo ""
    echo "管理系统服务:"
    echo "  sudo $SCRIPT_DIR/manage_service.sh start    # 启动系统服务"
    echo "  sudo $SCRIPT_DIR/manage_service.sh status   # 查看服务状态"
    echo ""
}

# 主停止函数
stop_monitor_service() {
    log_info "正在停止DoyeCMS AI队列处理器监控服务..."
    
    # 检查服务状态
    local pid
    pid=$(check_monitor_status)
    local status=$?
    
    if [ $status -ne 0 ]; then
        log_warn "监控服务未运行"
        
        # 清理可能存在的文件
        rm -f "$PID_FILE" "$STOP_FILE"
        
        # 检查是否有相关进程
        cleanup_related_processes
        
        return 0
    fi
    
    log_info "发现运行中的监控服务 (PID: $pid)"
    
    # 尝试优雅停止
    if graceful_stop "$pid"; then
        log_info "监控服务已成功停止"
    else
        # 强制停止
        if force_stop "$pid"; then
            log_info "监控服务已强制停止"
        else
            log_error "无法停止监控服务"
            return 1
        fi
    fi
    
    # 清理相关进程
    cleanup_related_processes
    
    return 0
}

# 显示服务状态
show_status() {
    log_blue "=== 监控服务状态 ==="
    
    local pid
    pid=$(check_monitor_status)
    local status=$?
    
    if [ $status -eq 0 ]; then
        echo "监控服务状态: 运行中 (PID: $pid)"
        
        # 显示进程信息
        echo ""
        echo "进程信息:"
        ps -p "$pid" -o pid,ppid,etime,cmd 2>/dev/null || echo "无法获取进程信息"
    else
        echo "监控服务状态: 未运行"
    fi
    
    # 显示相关进程
    echo ""
    echo "相关进程:"
    local related_pids=$(pgrep -f "queue_processor" 2>/dev/null)
    if [ -n "$related_pids" ]; then
        ps -p $related_pids -o pid,ppid,etime,cmd 2>/dev/null || true
    else
        echo "未发现相关进程"
    fi
    
    # 显示文件状态
    echo ""
    echo "文件状态:"
    echo "PID文件: $([ -f "$PID_FILE" ] && echo "存在" || echo "不存在")"
    echo "停止信号文件: $([ -f "$STOP_FILE" ] && echo "存在" || echo "不存在")"
    
    # 显示最近日志
    if [ -f "$LOG_DIR/monitor.log" ]; then
        echo ""
        echo "最近日志 (最后10行):"
        echo "----------------------------------------"
        tail -n 10 "$LOG_DIR/monitor.log" 2>/dev/null || echo "无法读取日志文件"
    fi
}

# 显示帮助信息
show_help() {
    echo "DoyeCMS AI队列处理器监控停止脚本"
    echo "用法: $0 [选项]"
    echo ""
    echo "选项:"
    echo "  stop        停止监控服务 (默认)"
    echo "  status      查看服务状态"
    echo "  force       强制停止所有相关进程"
    echo "  --help      显示此帮助信息"
    echo ""
    echo "相关脚本:"
    echo "  $SCRIPT_DIR/start_monitor.sh   # 启动监控服务"
    echo "  $SCRIPT_DIR/auto_monitor.sh    # 监控服务主脚本"
    echo "  $SCRIPT_DIR/manage_service.sh  # 系统服务管理"
    echo ""
}

# 强制停止所有相关进程
force_stop_all() {
    log_warn "强制停止所有相关进程..."
    
    # 停止监控服务
    local pid
    pid=$(check_monitor_status)
    if [ $? -eq 0 ]; then
        force_stop "$pid"
    fi
    
    # 强制清理所有相关进程
    local all_pids=$(pgrep -f "queue_processor\|auto_monitor" 2>/dev/null)
    if [ -n "$all_pids" ]; then
        log_info "终止所有相关进程: $all_pids"
        for pid in $all_pids; do
            kill -KILL "$pid" 2>/dev/null || true
        done
    fi
    
    # 清理文件
    rm -f "$PID_FILE" "$STOP_FILE"
    
    log_info "所有相关进程已强制停止"
}

# 主程序
main() {
    show_banner
    
    case "${1:-stop}" in
        stop)
            if stop_monitor_service; then
                echo ""
                show_stop_info
            else
                log_error "停止监控服务失败"
                exit 1
            fi
            ;;
        status)
            show_status
            ;;
        force)
            force_stop_all
            echo ""
            show_stop_info
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
}

# 程序入口
main "$@"