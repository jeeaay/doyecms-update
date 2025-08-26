#!/bin/bash

# DoyeCMS AI队列处理器自动监控脚本 (Linux版本)
# 功能：定期检查队列处理器状态，异常时自动重启
# 作者：DoyeCMS Team
# 版本：1.0
# 日期：2025-01-16

# 配置参数
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP_PATH="/usr/bin/php"  # 根据实际环境调整
QUEUE_SCRIPT="$SCRIPT_DIR/queue_processor.php"
MONITOR_SCRIPT="$SCRIPT_DIR/monitor.php"
LOG_DIR="$SCRIPT_DIR/logs"
MONITOR_LOG="$LOG_DIR/monitor.log"
STOP_FILE="$SCRIPT_DIR/.stop_monitor"
PID_FILE="$SCRIPT_DIR/monitor.pid"

# 监控配置
CHECK_INTERVAL=30        # 检查间隔（秒）
MAX_RESTART_COUNT=5      # 最大重启次数
RESTART_COOLDOWN=300     # 重启冷却时间（秒）
ALERT_EMAIL=""           # 告警邮箱（可选）

# 全局变量
RESTART_COUNT=0
LAST_RESTART_TIME=0

# 创建必要的目录
mkdir -p "$LOG_DIR"

# 日志记录函数
log_message() {
    local level="$1"
    local message="$2"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$timestamp] [$level] $message" | tee -a "$MONITOR_LOG"
}

# 检查PHP环境
check_php_environment() {
    if [ ! -f "$PHP_PATH" ]; then
        log_message "ERROR" "PHP可执行文件不存在: $PHP_PATH"
        return 1
    fi
    
    if [ ! -f "$QUEUE_SCRIPT" ]; then
        log_message "ERROR" "队列处理器脚本不存在: $QUEUE_SCRIPT"
        return 1
    fi
    
    if [ ! -f "$MONITOR_SCRIPT" ]; then
        log_message "ERROR" "监控脚本不存在: $MONITOR_SCRIPT"
        return 1
    fi
    
    return 0
}

# 检查队列处理器健康状态
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

# 重启队列处理器
restart_queue_processor() {
    local current_time=$(date +%s)
    
    # 检查冷却时间
    if [ $((current_time - LAST_RESTART_TIME)) -lt $RESTART_COOLDOWN ]; then
        log_message "WARNING" "重启冷却时间未到，跳过重启"
        return 1
    fi
    
    # 检查重启次数限制
    if [ $RESTART_COUNT -ge $MAX_RESTART_COUNT ]; then
        log_message "ERROR" "达到最大重启次数限制($MAX_RESTART_COUNT)，停止自动重启"
        send_alert "队列处理器达到最大重启次数，需要人工干预"
        return 1
    fi
    
    log_message "INFO" "开始重启队列处理器..."
    
    # 尝试优雅停止
    $PHP_PATH "$MONITOR_SCRIPT" --restart 2>&1 | while read line; do
        log_message "INFO" "重启输出: $line"
    done
    
    # 等待重启完成
    sleep 10
    
    # 验证重启是否成功
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

# 发送告警通知
send_alert() {
    local message="$1"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    
    log_message "ALERT" "$message"
    
    # 如果配置了邮箱，发送邮件告警
    if [ -n "$ALERT_EMAIL" ]; then
        echo "[$timestamp] DoyeCMS队列处理器告警: $message" | \
        mail -s "DoyeCMS队列处理器告警" "$ALERT_EMAIL" 2>/dev/null || \
        log_message "WARNING" "邮件告警发送失败"
    fi
}

# 清理函数
cleanup() {
    log_message "INFO" "监控服务正在停止..."
    rm -f "$PID_FILE"
    exit 0
}

# 信号处理
trap cleanup SIGTERM SIGINT

# 主监控循环
main_monitor_loop() {
    log_message "INFO" "DoyeCMS AI队列处理器监控服务启动"
    log_message "INFO" "监控配置: 检查间隔=${CHECK_INTERVAL}s, 最大重启次数=${MAX_RESTART_COUNT}"
    
    # 记录PID
    echo $$ > "$PID_FILE"
    
    while true; do
        # 检查停止信号
        if [ -f "$STOP_FILE" ]; then
            log_message "INFO" "检测到停止信号文件，监控服务即将停止"
            rm -f "$STOP_FILE"
            break
        fi
        
        # 执行健康检查
        if ! check_queue_health; then
            log_message "WARNING" "队列处理器健康检查失败，尝试重启"
            
            if restart_queue_processor; then
                log_message "INFO" "队列处理器重启成功"
                # 重启成功后重置部分计数器
                if [ $RESTART_COUNT -gt 0 ] && [ $(($(date +%s) - LAST_RESTART_TIME)) -gt $((RESTART_COOLDOWN * 2)) ]; then
                    RESTART_COUNT=0
                    log_message "INFO" "重启计数器已重置"
                fi
            else
                log_message "ERROR" "队列处理器重启失败"
                send_alert "队列处理器重启失败，需要检查系统状态"
            fi
        fi
        
        # 等待下次检查
        sleep $CHECK_INTERVAL
    done
    
    cleanup
}

# 显示使用帮助
show_help() {
    echo "DoyeCMS AI队列处理器自动监控脚本"
    echo "用法: $0 [选项]"
    echo ""
    echo "选项:"
    echo "  start     启动监控服务"
    echo "  stop      停止监控服务"
    echo "  status    查看监控状态"
    echo "  restart   重启监控服务"
    echo "  --help    显示此帮助信息"
    echo ""
    echo "配置文件位置: $0"
    echo "日志文件位置: $MONITOR_LOG"
}

# 检查监控服务状态
check_monitor_status() {
    if [ -f "$PID_FILE" ]; then
        local pid=$(cat "$PID_FILE")
        if kill -0 "$pid" 2>/dev/null; then
            echo "监控服务正在运行 (PID: $pid)"
            return 0
        else
            echo "监控服务未运行 (PID文件存在但进程不存在)"
            rm -f "$PID_FILE"
            return 1
        fi
    else
        echo "监控服务未运行"
        return 1
    fi
}

# 停止监控服务
stop_monitor() {
    if [ -f "$PID_FILE" ]; then
        local pid=$(cat "$PID_FILE")
        if kill -0 "$pid" 2>/dev/null; then
            echo "正在停止监控服务..."
            touch "$STOP_FILE"
            
            # 等待进程优雅退出
            local count=0
            while [ $count -lt 30 ] && kill -0 "$pid" 2>/dev/null; do
                sleep 1
                count=$((count + 1))
            done
            
            # 如果进程仍在运行，强制终止
            if kill -0 "$pid" 2>/dev/null; then
                echo "强制终止监控进程..."
                kill -TERM "$pid"
                sleep 2
                if kill -0 "$pid" 2>/dev/null; then
                    kill -KILL "$pid"
                fi
            fi
            
            rm -f "$PID_FILE" "$STOP_FILE"
            echo "监控服务已停止"
        else
            echo "监控服务未运行"
            rm -f "$PID_FILE"
        fi
    else
        echo "监控服务未运行"
    fi
}

# 主程序入口
case "${1:-start}" in
    start)
        if check_monitor_status >/dev/null 2>&1; then
            echo "监控服务已在运行中"
            exit 1
        fi
        
        if ! check_php_environment; then
            echo "PHP环境检查失败，请检查配置"
            exit 1
        fi
        
        echo "启动DoyeCMS AI队列处理器监控服务..."
        main_monitor_loop
        ;;
    stop)
        stop_monitor
        ;;
    status)
        check_monitor_status
        ;;
    restart)
        stop_monitor
        sleep 2
        if ! check_php_environment; then
            echo "PHP环境检查失败，请检查配置"
            exit 1
        fi
        echo "重启DoyeCMS AI队列处理器监控服务..."
        main_monitor_loop
        ;;
    --help|-h)
        show_help
        ;;
    *)
        echo "未知选项: $1"
        show_help
        exit 1
        ;;
esac