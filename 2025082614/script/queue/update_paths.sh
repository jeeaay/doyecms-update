#!/bin/bash

# DoyeCMS AI队列处理器路径更新脚本
# 功能：更新所有脚本中的路径引用，适应新的目录结构
# 作者：DoyeCMS Team
# 版本：1.0
# 日期：2025-01-16

# 获取脚本目录
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

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

# 更新脚本路径引用
update_script_paths() {
    log_info "开始更新脚本路径引用..."
    
    # 需要更新的脚本文件列表
    local scripts=(
        "deploy_linux.sh"
        "manage_service.sh"
        "auto_monitor.sh"
        "start_monitor.sh"
        "stop_monitor.sh"
    )
    
    # 需要更新的.bat文件列表
    local bat_scripts=(
        "install_service.bat"
        "manage_service.bat"
        "auto_monitor.bat"
        "start_monitor.bat"
        "stop_monitor.bat"
    )
    
    # 更新Shell脚本
    for script in "${scripts[@]}"; do
        local script_path="$SCRIPT_DIR/$script"
        if [ -f "$script_path" ]; then
            log_info "更新脚本: $script"
            
            # 备份原文件
            cp "$script_path" "${script_path}.backup"
            
            # 更新路径引用
            # 将 ../script/ 替换为当前目录
            sed -i 's|\.\.[\/]script[\/]|./|g' "$script_path"
            
            # 将 ./script/ 替换为当前目录
            sed -i 's|\.\/script[\/]|./|g' "$script_path"
            
            # 更新日志目录路径（确保在当前目录下）
            sed -i 's|LOG_DIR="\$SCRIPT_DIR[\/]logs"|LOG_DIR="$SCRIPT_DIR/logs"|g' "$script_path"
            
            # 更新队列脚本路径
            sed -i 's|QUEUE_SCRIPT="\$SCRIPT_DIR[\/]queue_processor\.php"|QUEUE_SCRIPT="$SCRIPT_DIR/queue_processor.php"|g' "$script_path"
            
            log_info "✓ 已更新: $script"
        else
            log_warn "✗ 脚本不存在: $script"
        fi
    done
    
    # 更新Windows批处理脚本
    for script in "${bat_scripts[@]}"; do
        local script_path="$SCRIPT_DIR/$script"
        if [ -f "$script_path" ]; then
            log_info "更新批处理脚本: $script"
            
            # 备份原文件
            cp "$script_path" "${script_path}.backup"
            
            # 更新Windows路径引用
            # 将 ..\script\ 替换为当前目录
            sed -i 's|\.\.\\script\\|.\\|g' "$script_path"
            
            # 将 .\script\ 替换为当前目录
            sed -i 's|\.\\script\\|.\\|g' "$script_path"
            
            # 更新日志目录路径
            sed -i 's|set LOG_DIR=%SCRIPT_DIR%\\logs|set LOG_DIR=%SCRIPT_DIR%\\logs|g' "$script_path"
            
            # 更新队列脚本路径
            sed -i 's|set QUEUE_SCRIPT=%SCRIPT_DIR%\\queue_processor\.php|set QUEUE_SCRIPT=%SCRIPT_DIR%\\queue_processor.php|g' "$script_path"
            
            log_info "✓ 已更新: $script"
        else
            log_warn "✗ 批处理脚本不存在: $script"
        fi
    done
}

# 更新Supervisor配置文件
update_supervisor_config() {
    log_info "更新Supervisor配置文件..."
    
    local config_file="$SCRIPT_DIR/queue_processor.conf"
    if [ -f "$config_file" ]; then
        # 备份原文件
        cp "$config_file" "${config_file}.backup"
        
        # 更新配置文件中的路径
        sed -i "s|command=php /path/to/script/queue_processor.php|command=php $SCRIPT_DIR/queue_processor.php|g" "$config_file"
        sed -i "s|directory=/path/to/script|directory=$SCRIPT_DIR|g" "$config_file"
        sed -i "s|stdout_logfile=/path/to/script/logs/queue_processor.log|stdout_logfile=$SCRIPT_DIR/logs/queue_processor.log|g" "$config_file"
        
        log_info "✓ 已更新Supervisor配置文件"
    else
        log_warn "✗ Supervisor配置文件不存在"
    fi
}

# 更新README文档
update_readme_docs() {
    log_info "更新README文档..."
    
    local readme_files=("README.md" "README_SERVICE.md")
    
    for readme in "${readme_files[@]}"; do
        local readme_path="$SCRIPT_DIR/$readme"
        if [ -f "$readme_path" ]; then
            # 备份原文件
            cp "$readme_path" "${readme_path}.backup"
            
            # 更新文档中的路径引用
            sed -i 's|\.\/script\/|\.\/script\/queue\/|g' "$readme_path"
            sed -i 's|cd \.\/script|cd \.\/script\/queue|g' "$readme_path"
            
            log_info "✓ 已更新: $readme"
        else
            log_warn "✗ 文档不存在: $readme"
        fi
    done
}

# 创建日志目录
create_log_directory() {
    log_info "创建日志目录..."
    
    local log_dir="$SCRIPT_DIR/logs"
    if [ ! -d "$log_dir" ]; then
        mkdir -p "$log_dir"
        log_info "✓ 创建日志目录: $log_dir"
    else
        log_info "✓ 日志目录已存在: $log_dir"
    fi
    
    # 设置权限
    chmod 755 "$log_dir"
    
    # 创建日志文件占位符
    touch "$log_dir/queue_processor.log"
    touch "$log_dir/monitor.log"
    touch "$log_dir/service.log"
    
    log_info "✓ 日志目录设置完成"
}

# 设置脚本执行权限
set_script_permissions() {
    log_info "设置脚本执行权限..."
    
    local scripts=("*.sh")
    
    for script_pattern in "${scripts[@]}"; do
        for script_file in $SCRIPT_DIR/$script_pattern; do
            if [ -f "$script_file" ]; then
                chmod +x "$script_file"
                local script_name=$(basename "$script_file")
                log_info "✓ 设置执行权限: $script_name"
            fi
        done
    done
}

# 验证更新结果
verify_updates() {
    log_info "验证更新结果..."
    
    local errors=0
    
    # 检查关键文件是否存在
    local key_files=(
        "queue_processor.php"
        "monitor.php"
        "manage_service.sh"
        "deploy_linux.sh"
    )
    
    for file in "${key_files[@]}"; do
        if [ -f "$SCRIPT_DIR/$file" ]; then
            log_info "✓ 关键文件存在: $file"
        else
            log_error "✗ 关键文件缺失: $file"
            ((errors++))
        fi
    done
    
    # 检查日志目录
    if [ -d "$SCRIPT_DIR/logs" ]; then
        log_info "✓ 日志目录存在"
    else
        log_error "✗ 日志目录缺失"
        ((errors++))
    fi
    
    if [ $errors -eq 0 ]; then
        log_info "✓ 所有验证通过"
        return 0
    else
        log_error "✗ 发现 $errors 个错误"
        return 1
    fi
}

# 清理备份文件
cleanup_backups() {
    read -p "是否删除备份文件? [y/N]: " cleanup
    if [ "$cleanup" = "y" ] || [ "$cleanup" = "Y" ]; then
        log_info "清理备份文件..."
        rm -f "$SCRIPT_DIR"/*.backup
        log_info "✓ 备份文件已清理"
    else
        log_info "保留备份文件，可手动删除 *.backup 文件"
    fi
}

# 显示帮助信息
show_help() {
    echo "DoyeCMS AI队列处理器路径更新脚本"
    echo "用法: $0 [选项]"
    echo ""
    echo "选项:"
    echo "  update      执行完整更新 (默认)"
    echo "  verify      仅验证文件"
    echo "  cleanup     清理备份文件"
    echo "  --help      显示此帮助信息"
    echo ""
}

# 主程序入口
main() {
    echo "================================================================"
    echo "    DoyeCMS AI队列处理器路径更新脚本"
    echo "================================================================"
    echo ""
    
    case "${1:-update}" in
        update)
            update_script_paths
            update_supervisor_config
            update_readme_docs
            create_log_directory
            set_script_permissions
            verify_updates
            cleanup_backups
            
            echo ""
            log_info "路径更新完成！"
            log_info "现在可以使用以下命令管理队列处理器："
            echo "  部署服务: sudo ./deploy_linux.sh"
            echo "  管理服务: sudo ./manage_service.sh"
            echo "  启动监控: ./start_monitor.sh"
            ;;
        verify)
            verify_updates
            ;;
        cleanup)
            cleanup_backups
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

# 运行主程序
main "$@"