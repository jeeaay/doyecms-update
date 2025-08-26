#!/bin/bash

# DoyeCMS AI队列处理器 Linux环境快速部署脚本
# 功能：一键部署队列处理器服务和监控系统
# 作者：DoyeCMS Team
# 版本：1.0
# 日期：2025-01-16

# 获取脚本目录
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP_PATH="/usr/bin/php"
QUEUE_SCRIPT="$SCRIPT_DIR/queue_processor.php"
MONITOR_SCRIPT="$SCRIPT_DIR/monitor.php"
LOG_DIR="$SCRIPT_DIR/logs"

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
    echo "    DoyeCMS AI队列处理器 Linux环境快速部署脚本"
    echo "================================================================"
    echo -e "${NC}"
    echo "本脚本将帮助您快速部署DoyeCMS AI队列处理器服务"
    echo "包括：队列处理器、系统服务、监控系统"
    echo ""
}

# 检查系统环境
check_system_environment() {
    log_purple "步骤 1: 检查系统环境"
    
    # 检查操作系统
    if [ ! -f /etc/os-release ]; then
        log_error "无法识别操作系统"
        return 1
    fi
    
    local os_info=$(cat /etc/os-release | grep PRETTY_NAME | cut -d'"' -f2)
    log_info "操作系统: $os_info"
    
    # 检查是否为root用户
    if [ "$EUID" -eq 0 ]; then
        log_info "当前用户: root (具有管理员权限)"
    else
        log_warn "当前用户: $(whoami) (建议使用sudo运行此脚本)"
    fi
    
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
    log_purple "步骤 2: 检查PHP环境"
    
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
    log_purple "步骤 3: 设置文件权限"
    
    # 设置Shell脚本执行权限
    local shell_scripts=("manage_service.sh" "auto_monitor.sh" "start_monitor.sh" "stop_monitor.sh")
    
    for script in "${shell_scripts[@]}"; do
        local script_path="$SCRIPT_DIR/$script"
        if [ -f "$script_path" ]; then
            chmod +x "$script_path"
            log_info "✓ 设置执行权限: $script"
        else
            log_warn "✗ 脚本不存在: $script"
        fi
    done
    
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

# 安装系统服务
install_system_service() {
    log_purple "步骤 4: 安装系统服务"
    
    if [ "$EUID" -ne 0 ]; then
        log_warn "需要root权限安装系统服务，跳过此步骤"
        log_info "手动安装命令: sudo $SCRIPT_DIR/manage_service.sh install"
        return 0
    fi
    
    # 检查服务管理脚本
    local manage_script="$SCRIPT_DIR/manage_service.sh"
    if [ ! -f "$manage_script" ]; then
        log_error "服务管理脚本不存在: $manage_script"
        return 1
    fi
    
    # 安装服务
    log_info "正在安装系统服务..."
    if "$manage_script" install; then
        log_cyan "✓ 系统服务安装成功"
        return 0
    else
        log_error "✗ 系统服务安装失败"
        return 1
    fi
}

# 启动服务
start_services() {
    log_purple "步骤 5: 启动服务"
    
    local manage_script="$SCRIPT_DIR/manage_service.sh"
    
    if [ "$EUID" -eq 0 ]; then
        # 启动系统服务
        log_info "启动系统服务..."
        if "$manage_script" start; then
            log_cyan "✓ 系统服务启动成功"
        else
            log_error "✗ 系统服务启动失败"
            return 1
        fi
    else
        log_warn "需要root权限启动系统服务"
        log_info "手动启动命令: sudo $manage_script start"
    fi
    
    return 0
}

# 设置监控系统
setup_monitoring() {
    log_purple "步骤 6: 设置监控系统"
    
    local monitor_script="$SCRIPT_DIR/start_monitor.sh"
    
    if [ ! -f "$monitor_script" ]; then
        log_error "监控启动脚本不存在: $monitor_script"
        return 1
    fi
    
    log_info "监控系统已准备就绪"
    log_info "启动监控命令: $monitor_script"
    
    # 询问是否启动监控
    read -p "是否现在启动监控系统? [y/N]: " start_monitor
    if [ "$start_monitor" = "y" ] || [ "$start_monitor" = "Y" ]; then
        log_info "启动监控系统..."
        "$monitor_script" &
        log_cyan "✓ 监控系统已在后台启动"
    else
        log_info "监控系统未启动，可稍后手动启动"
    fi
    
    return 0
}

# 显示部署结果
show_deployment_result() {
    log_purple "部署完成"
    
    echo ""
    echo -e "${CYAN}================================================================${NC}"
    echo -e "${CYAN}                    部署结果总结${NC}"
    echo -e "${CYAN}================================================================${NC}"
    echo ""
    
    # 检查服务状态
    local manage_script="$SCRIPT_DIR/manage_service.sh"
    if [ -f "$manage_script" ]; then
        echo -e "${BLUE}系统服务状态:${NC}"
        "$manage_script" status 2>/dev/null || echo "服务状态检查失败"
        echo ""
    fi
    
    # 显示管理命令
    echo -e "${BLUE}常用管理命令:${NC}"
    echo "  查看服务状态: $manage_script status"
    echo "  启动服务:     sudo $manage_script start"
    echo "  停止服务:     sudo $manage_script stop"
    echo "  重启服务:     sudo $manage_script restart"
    echo "  查看日志:     $manage_script logs"
    echo ""
    
    echo -e "${BLUE}监控系统命令:${NC}"
    echo "  启动监控:     $SCRIPT_DIR/start_monitor.sh"
    echo "  停止监控:     $SCRIPT_DIR/stop_monitor.sh"
    echo "  监控状态:     $SCRIPT_DIR/stop_monitor.sh status"
    echo ""
    
    echo -e "${BLUE}日志文件位置:${NC}"
    echo "  应用日志:     $LOG_DIR/queue_processor.log"
    echo "  监控日志:     $LOG_DIR/monitor.log"
    echo "  服务日志:     $LOG_DIR/service.log"
    echo "  实时日志:     tail -f $LOG_DIR/queue_processor.log"
    echo ""
    
    echo -e "${GREEN}部署完成！DoyeCMS AI队列处理器已准备就绪。${NC}"
    echo ""
}

# 显示帮助信息
show_help() {
    echo "DoyeCMS AI队列处理器 Linux环境快速部署脚本"
    echo "用法: $0 [选项]"
    echo ""
    echo "选项:"
    echo "  deploy      执行完整部署 (默认)"
    echo "  check       仅检查环境"
    echo "  permissions 仅设置权限"
    echo "  --help      显示此帮助信息"
    echo ""
    echo "部署步骤:"
    echo "  1. 检查系统环境"
    echo "  2. 检查PHP环境"
    echo "  3. 设置文件权限"
    echo "  4. 安装系统服务"
    echo "  5. 启动服务"
    echo "  6. 设置监控系统"
    echo ""
}

# 仅检查环境
check_only() {
    show_banner
    
    if check_system_environment && check_php_environment; then
        log_cyan "✓ 环境检查通过，可以进行部署"
        return 0
    else
        log_error "✗ 环境检查失败，请解决问题后重试"
        return 1
    fi
}

# 仅设置权限
permissions_only() {
    show_banner
    
    if setup_permissions; then
        log_cyan "✓ 文件权限设置完成"
        return 0
    else
        log_error "✗ 文件权限设置失败"
        return 1
    fi
}

# 完整部署
full_deploy() {
    show_banner
    
    # 执行所有部署步骤
    if ! check_system_environment; then
        log_error "系统环境检查失败，部署终止"
        exit 1
    fi
    
    if ! check_php_environment; then
        log_error "PHP环境检查失败，部署终止"
        exit 1
    fi
    
    if ! setup_permissions; then
        log_error "文件权限设置失败，部署终止"
        exit 1
    fi
    
    # 安装系统服务（可选）
    install_system_service
    
    # 启动服务（可选）
    start_services
    
    # 设置监控系统
    setup_monitoring
    
    # 显示部署结果
    show_deployment_result
}

# 主程序入口
case "${1:-deploy}" in
    deploy)
        full_deploy
        ;;
    check)
        check_only
        ;;
    permissions)
        permissions_only
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