@echo off
REM DoyeCMS AI队列处理器 - Windows统一管理脚本
REM 版本: 2.0
REM 更新日期: 2025-01-16
REM 功能: 整合所有Windows环境下的队列处理器管理功能

setlocal enabledelayedexpansion

REM ========================================
REM 配置参数
REM ========================================
set SCRIPT_DIR=%~dp0
set SCRIPT_DIR=%SCRIPT_DIR:~0,-1%
set SERVICE_NAME=DoyeCMS_Queue_Processor
set PHP_PATH=d:\phpstudy_pro\Extensions\php\php8.0.2nts\php.exe
set QUEUE_SCRIPT=%SCRIPT_DIR%\queue_processor.php
set MONITOR_SCRIPT=%SCRIPT_DIR%\monitor.php
set LOG_DIR=%SCRIPT_DIR%\logs
set CHECK_INTERVAL=300
set MAX_RESTART_ATTEMPTS=3
set RESTART_COOLDOWN=600

REM ========================================
REM 颜色定义
REM ========================================
set "COLOR_RESET=[0m"
set "COLOR_RED=[91m"
set "COLOR_GREEN=[92m"
set "COLOR_YELLOW=[93m"
set "COLOR_BLUE=[94m"
set "COLOR_CYAN=[96m"
set "COLOR_WHITE=[97m"

REM ========================================
REM 工具函数
REM ========================================

:log_message
set timestamp=%date% %time:~0,8%
echo %COLOR_CYAN%[%timestamp%]%COLOR_RESET% %~1
if not "%~2"=="" (
    echo [%timestamp%] %~1 >> "%~2"
)
goto :eof

:print_success
echo %COLOR_GREEN%✓ %~1%COLOR_RESET%
goto :eof

:print_error
echo %COLOR_RED%✗ %~1%COLOR_RESET%
goto :eof

:print_warning
echo %COLOR_YELLOW%⚠ %~1%COLOR_RESET%
goto :eof

:print_info
echo %COLOR_BLUE%ℹ %~1%COLOR_RESET%
goto :eof

:check_nssm
where nssm >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    call :print_error "未找到NSSM工具"
    echo 请先下载并安装NSSM: https://nssm.cc/download
    echo 并将nssm.exe添加到系统PATH中
    exit /b 1
)
goto :eof

:check_php
if not exist "%PHP_PATH%" (
    call :print_error "PHP路径不存在: %PHP_PATH%"
    echo 请检查PHP安装路径并修改脚本中的PHP_PATH变量
    exit /b 1
)
goto :eof

:create_log_dir
if not exist "%LOG_DIR%" (
    mkdir "%LOG_DIR%"
    call :print_info "创建日志目录: %LOG_DIR%"
)
goto :eof

:get_service_status
for /f "tokens=*" %%i in ('nssm status "%SERVICE_NAME%" 2^>nul') do set SERVICE_STATUS=%%i
if "%SERVICE_STATUS%"=="" set SERVICE_STATUS=未安装
goto :eof

REM ========================================
REM 服务管理功能
REM ========================================

:install_service
call :print_info "正在安装DoyeCMS AI队列处理器服务..."
call :check_nssm
if %ERRORLEVEL% NEQ 0 exit /b 1

call :check_php
if %ERRORLEVEL% NEQ 0 exit /b 1

call :create_log_dir

REM 停止并删除现有服务（如果存在）
nssm stop "%SERVICE_NAME%" 2>nul
nssm remove "%SERVICE_NAME%" confirm 2>nul

REM 安装新服务
call :print_info "创建服务: %SERVICE_NAME%"
nssm install "%SERVICE_NAME%" "%PHP_PATH%" "%QUEUE_SCRIPT%"

REM 配置服务参数
nssm set "%SERVICE_NAME%" AppDirectory "%SCRIPT_DIR%"
nssm set "%SERVICE_NAME%" DisplayName "DoyeCMS AI队列处理器"
nssm set "%SERVICE_NAME%" Description "DoyeCMS AI队列处理器 - 处理AI相关任务队列"

REM 配置日志
nssm set "%SERVICE_NAME%" AppStdout "%LOG_DIR%\queue_service_stdout.log"
nssm set "%SERVICE_NAME%" AppStderr "%LOG_DIR%\queue_service_stderr.log"
nssm set "%SERVICE_NAME%" AppRotateFiles 1
nssm set "%SERVICE_NAME%" AppRotateOnline 1
nssm set "%SERVICE_NAME%" AppRotateSeconds 86400
nssm set "%SERVICE_NAME%" AppRotateBytes 10485760

REM 配置重启策略
nssm set "%SERVICE_NAME%" AppExit Default Restart
nssm set "%SERVICE_NAME%" AppRestartDelay 5000
nssm set "%SERVICE_NAME%" AppThrottle 1500

REM 配置启动类型
nssm set "%SERVICE_NAME%" Start SERVICE_AUTO_START

REM 启动服务
call :print_info "启动服务..."
nssm start "%SERVICE_NAME%"

if %ERRORLEVEL% EQU 0 (
    call :print_success "服务安装成功！"
    echo.
    call :print_info "服务名称: %SERVICE_NAME%"
    call :print_info "日志文件位置:"
    echo   标准输出: %LOG_DIR%\queue_service_stdout.log
    echo   错误输出: %LOG_DIR%\queue_service_stderr.log
) else (
    call :print_error "服务安装失败！"
)
goto :eof

:start_service
call :print_info "正在启动服务..."
nssm start "%SERVICE_NAME%"
if %ERRORLEVEL% EQU 0 (
    call :print_success "服务启动成功"
) else (
    call :print_error "服务启动失败"
)
goto :eof

:stop_service
call :print_info "正在停止服务..."
nssm stop "%SERVICE_NAME%"
if %ERRORLEVEL% EQU 0 (
    call :print_success "服务停止成功"
) else (
    call :print_error "服务停止失败"
)
goto :eof

:restart_service
call :print_info "正在重启服务..."
nssm restart "%SERVICE_NAME%"
if %ERRORLEVEL% EQU 0 (
    call :print_success "服务重启成功"
) else (
    call :print_error "服务重启失败"
)
goto :eof

:show_service_status
call :get_service_status
echo.
echo %COLOR_CYAN%========================================%COLOR_RESET%
echo %COLOR_CYAN%   服务状态信息%COLOR_RESET%
echo %COLOR_CYAN%========================================%COLOR_RESET%
echo 服务名称: %SERVICE_NAME%
echo 当前状态: %COLOR_YELLOW%[%SERVICE_STATUS%]%COLOR_RESET%
echo.
if not "%SERVICE_STATUS%"=="未安装" (
    echo 详细信息:
    sc query "%SERVICE_NAME%" 2>nul
    if %ERRORLEVEL% NEQ 0 (
        call :print_warning "无法获取详细服务信息"
    )
)
goto :eof

:uninstall_service
echo.
call :print_warning "警告: 这将删除服务！"
set /p confirm=确定要删除服务吗？(y/N): 
if /i "%confirm%"=="y" (
    call :print_info "正在删除服务..."
    nssm stop "%SERVICE_NAME%" 2>nul
    nssm remove "%SERVICE_NAME%" confirm
    if %ERRORLEVEL% EQU 0 (
        call :print_success "服务删除成功"
    ) else (
        call :print_error "服务删除失败"
    )
) else (
    call :print_info "操作已取消"
)
goto :eof

REM ========================================
REM 监控管理功能
REM ========================================

:start_monitor
echo.
call :print_info "正在启动自动监控服务..."

REM 检查是否已经在运行
for /f "tokens=2" %%i in ('tasklist /fi "windowtitle eq DoyeCMS队列监控" /fo csv 2^>nul ^| find "cmd.exe"') do (
    call :print_warning "监控服务已在运行中"
    echo 如需停止，请运行: %~nx0 monitor stop
    goto :eof
)

REM 删除可能存在的停止文件
set STOP_FILE=%SCRIPT_DIR%\stop_monitor
if exist "%STOP_FILE%" del "%STOP_FILE%" 2>nul

echo.
call :print_info "监控功能:"
echo - 每%CHECK_INTERVAL%秒检查队列处理器健康状态
echo - 异常时自动重启（最多%MAX_RESTART_ATTEMPTS%次）
echo - 记录详细的监控日志
echo - 支持警报通知
echo.
call :print_info "日志文件位置:"
echo   监控日志: %LOG_DIR%\auto_monitor_*.log
echo   警报日志: %LOG_DIR%\alerts.log
echo.

REM 在新窗口中启动监控
start "DoyeCMS队列监控" /min cmd /c "call :auto_monitor_loop"

call :print_success "监控服务已启动"
call :print_info "监控窗口已最小化到任务栏"
goto :eof

:stop_monitor
echo.
call :print_info "正在停止监控服务..."

set STOP_FILE=%SCRIPT_DIR%\stop_monitor
echo stop > "%STOP_FILE%"

REM 等待监控服务检测到停止信号
set /a count=0
:wait_stop_loop
    if not exist "%STOP_FILE%" (
        call :print_success "监控服务已停止"
        goto :eof
    )
    
    set /a count+=1
    if %count% gtr 30 (
        call :print_warning "监控服务可能未正常响应停止信号"
        call :print_info "尝试强制终止监控进程..."
        
        REM 查找并终止监控进程
        for /f "tokens=2" %%i in ('tasklist /fi "windowtitle eq DoyeCMS队列监控" /fo csv 2^>nul ^| find "cmd.exe"') do (
            echo 终止进程: %%i
            taskkill /pid %%i /f >nul 2>&1
        )
        
        REM 删除停止文件
        if exist "%STOP_FILE%" del "%STOP_FILE%" 2>nul
        
        call :print_success "监控服务已强制停止"
        goto :eof
    )
    
    echo 等待监控服务响应... (%count%/30)
    timeout /t 2 /nobreak >nul
goto wait_stop_loop

:show_monitor_status
echo.
echo %COLOR_CYAN%========================================%COLOR_RESET%
echo %COLOR_CYAN%   监控服务状态%COLOR_RESET%
echo %COLOR_CYAN%========================================%COLOR_RESET%

REM 检查监控进程是否在运行
for /f "tokens=2" %%i in ('tasklist /fi "windowtitle eq DoyeCMS队列监控" /fo csv 2^>nul ^| find "cmd.exe"') do (
    echo 监控状态: %COLOR_GREEN%[运行中]%COLOR_RESET%
    echo 进程ID: %%i
    goto :monitor_status_end
)

echo 监控状态: %COLOR_RED%[未运行]%COLOR_RESET%

:monitor_status_end
echo.
REM 显示队列处理器健康状态
call :print_info "队列处理器健康检查:"
"%PHP_PATH%" "%MONITOR_SCRIPT%" --check
if %ERRORLEVEL% EQU 0 (
    call :print_success "队列处理器运行正常"
) else (
    call :print_error "队列处理器状态异常"
)
goto :eof

:auto_monitor_loop
REM 自动监控主循环
set MONITOR_LOG=%LOG_DIR%\auto_monitor_%date:~0,4%-%date:~5,2%-%date:~8,2%.log
call :create_log_dir

call :log_message "自动监控启动，检查间隔: %CHECK_INTERVAL%秒" "%MONITOR_LOG%"

set restart_count=0
set last_restart_time=0

:monitor_main_loop
    REM 执行健康检查
    "%PHP_PATH%" "%MONITOR_SCRIPT%" --check > nul 2>&1
    set health_status=!errorlevel!
    
    if !health_status! neq 0 (
        call :log_message "健康检查失败，准备重启队列处理器" "%MONITOR_LOG%"
        
        REM 检查重启冷却时间
        set current_time=!time:~0,2!!time:~3,2!!time:~6,2!
        set /a time_diff=!current_time! - !last_restart_time!
        
        if !time_diff! gtr !RESTART_COOLDOWN! (
            set restart_count=0
        )
        
        if !restart_count! lss !MAX_RESTART_ATTEMPTS! (
            call :log_message "执行重启 (第!restart_count!/!MAX_RESTART_ATTEMPTS!次)" "%MONITOR_LOG%"
            
            "%PHP_PATH%" "%MONITOR_SCRIPT%" --restart
            set restart_result=!errorlevel!
            
            if !restart_result! equ 0 (
                call :log_message "重启成功" "%MONITOR_LOG%"
                set /a restart_count+=1
                set last_restart_time=!current_time!
            ) else (
                call :log_message "重启失败" "%MONITOR_LOG%"
                set /a restart_count+=1
            )
        ) else (
            call :log_message "重启次数已达上限，停止自动重启" "%MONITOR_LOG%"
            call :log_message "请手动检查系统状态" "%MONITOR_LOG%"
            
            REM 发送警报
            call :send_alert "队列处理器重启失败，需要人工干预"
        )
    ) else (
        REM 健康检查通过，重置重启计数
        if !restart_count! gtr 0 (
            call :log_message "系统恢复正常，重置重启计数" "%MONITOR_LOG%"
            set restart_count=0
        )
    )
    
    REM 等待下次检查
    timeout /t %CHECK_INTERVAL% /nobreak > nul
    
    REM 检查是否需要退出
    if exist "%SCRIPT_DIR%\stop_monitor" (
        call :log_message "检测到停止信号，退出监控" "%MONITOR_LOG%"
        del "%SCRIPT_DIR%\stop_monitor" 2>nul
        goto :monitor_end
    )
    
goto monitor_main_loop

:monitor_end
call :log_message "自动监控结束" "%MONITOR_LOG%"
exit /b 0

:send_alert
REM 发送警报通知
set ALERT_LOG=%LOG_DIR%\alerts.log
call :log_message "警报: %~1" "%ALERT_LOG%"

REM 这里可以添加邮件发送、短信通知等功能
REM 例如：
REM powershell -Command "Send-MailMessage -To 'admin@example.com' -Subject 'DoyeCMS队列处理器警报' -Body '%~1' -SmtpServer 'smtp.example.com'"
goto :eof

REM ========================================
REM 日志管理功能
REM ========================================

:show_logs
if "%~1"=="" (
    call :show_queue_logs
) else if /i "%~1"=="monitor" (
    call :show_monitor_logs
) else if /i "%~1"=="system" (
    call :show_system_logs
) else if /i "%~1"=="error" (
    call :show_error_logs
) else if /i "%~1"=="alert" (
    call :show_alert_logs
) else (
    call :print_error "未知的日志类型: %~1"
    echo 可用的日志类型: queue, monitor, system, error, alert
)
goto :eof

:show_queue_logs
echo.
echo %COLOR_CYAN%========================================%COLOR_RESET%
echo %COLOR_CYAN%   队列处理器日志%COLOR_RESET%
echo %COLOR_CYAN%========================================%COLOR_RESET%

if "%~1"=="-f" (
    REM 实时跟踪日志
    for /f "delims=" %%f in ('dir /b /od "%LOG_DIR%\queue_processor_*.log" 2^>nul ^| findstr /r ".*"') do set LATEST_LOG=%%f
    if defined LATEST_LOG (
        call :print_info "实时跟踪日志: %LOG_DIR%\!LATEST_LOG!"
        call :print_info "按 Ctrl+C 停止跟踪"
        powershell -Command "Get-Content '%LOG_DIR%\!LATEST_LOG!' -Wait -Tail 10"
    ) else (
        call :print_error "队列处理器日志文件不存在"
    )
) else (
    REM 显示最新日志的最后50行
    for /f "delims=" %%f in ('dir /b /od "%LOG_DIR%\queue_processor_*.log" 2^>nul ^| findstr /r ".*"') do set LATEST_LOG=%%f
    if defined LATEST_LOG (
        call :print_info "显示最新日志的最后50行: %LOG_DIR%\!LATEST_LOG!"
        echo.
        powershell "Get-Content '%LOG_DIR%\!LATEST_LOG!' -Tail 50"
    ) else (
        call :print_error "队列处理器日志文件不存在"
    )
)
goto :eof

:show_monitor_logs
echo.
echo %COLOR_CYAN%========================================%COLOR_RESET%
echo %COLOR_CYAN%   监控服务日志%COLOR_RESET%
echo %COLOR_CYAN%========================================%COLOR_RESET%

for /f "delims=" %%f in ('dir /b /od "%LOG_DIR%\auto_monitor_*.log" 2^>nul ^| findstr /r ".*"') do set LATEST_MONITOR_LOG=%%f
if defined LATEST_MONITOR_LOG (
    call :print_info "显示最新监控日志的最后50行: %LOG_DIR%\!LATEST_MONITOR_LOG!"
    echo.
    powershell "Get-Content '%LOG_DIR%\!LATEST_MONITOR_LOG!' -Tail 50"
) else (
    call :print_error "监控日志文件不存在"
)
goto :eof

:show_system_logs
echo.
echo %COLOR_CYAN%========================================%COLOR_RESET%
echo %COLOR_CYAN%   系统服务日志%COLOR_RESET%
echo %COLOR_CYAN%========================================%COLOR_RESET%

if exist "%LOG_DIR%\queue_service_stdout.log" (
    call :print_info "标准输出日志 (最后50行):"
    echo.
    powershell "Get-Content '%LOG_DIR%\queue_service_stdout.log' -Tail 50"
    echo.
)

if exist "%LOG_DIR%\queue_service_stderr.log" (
    call :print_info "错误输出日志 (最后50行):"
    echo.
    powershell "Get-Content '%LOG_DIR%\queue_service_stderr.log' -Tail 50"
) else (
    call :print_error "系统服务日志文件不存在"
)
goto :eof

:show_error_logs
echo.
echo %COLOR_CYAN%========================================%COLOR_RESET%
echo %COLOR_CYAN%   错误日志%COLOR_RESET%
echo %COLOR_CYAN%========================================%COLOR_RESET%

if exist "%LOG_DIR%\queue_service_stderr.log" (
    call :print_info "服务错误日志 (最后50行):"
    echo.
    powershell "Get-Content '%LOG_DIR%\queue_service_stderr.log' -Tail 50"
    echo.
else (
    call :print_error "错误日志文件不存在"
)
goto :eof

:show_alert_logs
echo.
echo %COLOR_CYAN%========================================%COLOR_RESET%
echo %COLOR_CYAN%   警报日志%COLOR_RESET%
echo %COLOR_CYAN%========================================%COLOR_RESET%

if exist "%LOG_DIR%\alerts.log" (
    call :print_info "警报日志 (最后50行):"
    echo.
    powershell "Get-Content '%LOG_DIR%\alerts.log' -Tail 50"
else (
    call :print_error "警报日志文件不存在"
)
goto :eof

REM ========================================
REM 系统信息功能
REM ========================================

:show_help
echo.
echo %COLOR_CYAN%========================================%COLOR_RESET%
echo %COLOR_CYAN%   DoyeCMS AI队列处理器 - 帮助信息%COLOR_RESET%
echo %COLOR_CYAN%========================================%COLOR_RESET%
echo.
echo %COLOR_YELLOW%基本命令:%COLOR_RESET%
echo   %~nx0 install          - 安装服务（首次使用）
echo   %~nx0 start            - 启动服务
echo   %~nx0 stop             - 停止服务
echo   %~nx0 restart          - 重启服务
echo   %~nx0 status           - 查看服务状态
echo   %~nx0 uninstall        - 卸载服务
echo.
echo %COLOR_YELLOW%监控管理:%COLOR_RESET%
echo   %~nx0 monitor start    - 启动监控服务
echo   %~nx0 monitor stop     - 停止监控服务
echo   %~nx0 monitor status   - 查看监控状态
echo.
echo %COLOR_YELLOW%日志管理:%COLOR_RESET%
echo   %~nx0 logs             - 查看队列处理器日志
echo   %~nx0 logs -f          - 实时跟踪日志
echo   %~nx0 logs monitor     - 查看监控日志
echo   %~nx0 logs system      - 查看系统服务日志
echo   %~nx0 logs error       - 查看错误日志
echo   %~nx0 logs alert       - 查看警报日志
echo.
echo %COLOR_YELLOW%系统信息:%COLOR_RESET%
echo   %~nx0 help             - 显示帮助信息
echo   %~nx0 version          - 显示版本信息
echo   %~nx0 check            - 检查系统环境
echo.
echo %COLOR_YELLOW%高级功能:%COLOR_RESET%
echo   - 自动环境检测和依赖检查
echo   - 智能服务管理（基于NSSM）
echo   - 健康检查和自动重启机制
echo   - 多类型日志查看和实时跟踪
echo   - 彩色输出和友好用户界面
echo   - 完整的监控和警报系统
echo.
goto :eof

:show_version
echo.
echo %COLOR_CYAN%========================================%COLOR_RESET%
echo %COLOR_CYAN%   版本信息%COLOR_RESET%
echo %COLOR_CYAN%========================================%COLOR_RESET%
echo.
echo 脚本名称: DoyeCMS AI队列处理器统一管理脚本
echo 版本号: 2.0
echo 更新日期: 2025-01-16
echo 维护团队: DoyeCMS Team
echo 更新内容: 统一Windows脚本管理，整合所有bat脚本功能
echo.
echo 支持功能:
echo - 服务安装和管理
echo - 自动监控和重启
echo - 多类型日志管理
echo - 健康检查和状态监控
echo - 彩色输出和友好界面
echo.
goto :eof

:check_environment
echo.
echo %COLOR_CYAN%========================================%COLOR_RESET%
echo %COLOR_CYAN%   系统环境检查%COLOR_RESET%
echo %COLOR_CYAN%========================================%COLOR_RESET%
echo.

REM 检查NSSM
call :print_info "检查NSSM工具..."
where nssm >nul 2>nul
if %ERRORLEVEL% EQU 0 (
    for /f "tokens=*" %%i in ('nssm version 2^>nul') do (
        call :print_success "NSSM已安装: %%i"
    )
) else (
    call :print_error "NSSM未安装或不在PATH中"
    echo 下载地址: https://nssm.cc/download
)

REM 检查PHP
call :print_info "检查PHP环境..."
if exist "%PHP_PATH%" (
    for /f "tokens=*" %%i in ('"%PHP_PATH%" -v 2^>nul ^| findstr "PHP"') do (
        call :print_success "PHP已安装: %%i"
    )
) else (
    call :print_error "PHP路径不存在: %PHP_PATH%"
)

REM 检查脚本文件
call :print_info "检查脚本文件..."
if exist "%QUEUE_SCRIPT%" (
    call :print_success "队列处理器脚本存在"
) else (
    call :print_error "队列处理器脚本不存在: %QUEUE_SCRIPT%"
)

if exist "%MONITOR_SCRIPT%" (
    call :print_success "监控脚本存在"
) else (
    call :print_error "监控脚本不存在: %MONITOR_SCRIPT%"
)

REM 检查日志目录
call :print_info "检查日志目录..."
if exist "%LOG_DIR%" (
    call :print_success "日志目录存在: %LOG_DIR%"
) else (
    call :print_warning "日志目录不存在，将自动创建"
    call :create_log_dir
)

REM 检查服务状态
call :print_info "检查服务状态..."
call :get_service_status
if not "%SERVICE_STATUS%"=="未安装" (
    call :print_success "服务已安装，状态: %SERVICE_STATUS%"
) else (
    call :print_warning "服务未安装"
)

echo.
call :print_info "环境检查完成"
goto :eof

REM ========================================
REM 主程序入口
REM ========================================

:main
REM 显示横幅
echo.
echo %COLOR_CYAN%========================================%COLOR_RESET%
echo %COLOR_CYAN%   DoyeCMS AI队列处理器统一管理工具%COLOR_RESET%
echo %COLOR_CYAN%   版本: 2.0 ^| 更新: 2025-01-16%COLOR_RESET%
echo %COLOR_CYAN%========================================%COLOR_RESET%

REM 解析命令行参数
if "%~1"=="" goto show_menu
if /i "%~1"=="install" goto install_service
if /i "%~1"=="start" goto start_service
if /i "%~1"=="stop" goto stop_service
if /i "%~1"=="restart" goto restart_service
if /i "%~1"=="status" goto show_service_status
if /i "%~1"=="uninstall" goto uninstall_service
if /i "%~1"=="monitor" (
    if /i "%~2"=="start" goto start_monitor
    if /i "%~2"=="stop" goto stop_monitor
    if /i "%~2"=="status" goto show_monitor_status
    call :print_error "未知的监控命令: %~2"
    echo 可用命令: start, stop, status
    goto end
)
if /i "%~1"=="logs" (
    call :show_logs "%~2" "%~3"
    goto end
)
if /i "%~1"=="help" goto show_help
if /i "%~1"=="version" goto show_version
if /i "%~1"=="check" goto check_environment

call :print_error "未知命令: %~1"
echo 使用 '%~nx0 help' 查看帮助信息
goto end

:show_menu
REM 交互式菜单
call :get_service_status
echo.
echo 当前服务状态: %COLOR_YELLOW%[%SERVICE_STATUS%]%COLOR_RESET%
echo.
echo 请选择操作:
echo %COLOR_YELLOW%1.%COLOR_RESET% 安装服务
echo %COLOR_YELLOW%2.%COLOR_RESET% 启动服务
echo %COLOR_YELLOW%3.%COLOR_RESET% 停止服务
echo %COLOR_YELLOW%4.%COLOR_RESET% 重启服务
echo %COLOR_YELLOW%5.%COLOR_RESET% 查看服务状态
echo %COLOR_YELLOW%6.%COLOR_RESET% 监控管理
echo %COLOR_YELLOW%7.%COLOR_RESET% 日志查看
echo %COLOR_YELLOW%8.%COLOR_RESET% 系统检查
echo %COLOR_YELLOW%9.%COLOR_RESET% 卸载服务
echo %COLOR_YELLOW%0.%COLOR_RESET% 退出
echo.
set /p choice=请输入选项 (0-9): 

if "%choice%"=="1" call :install_service
if "%choice%"=="2" call :start_service
if "%choice%"=="3" call :stop_service
if "%choice%"=="4" call :restart_service
if "%choice%"=="5" call :show_service_status
if "%choice%"=="6" call :monitor_menu
if "%choice%"=="7" call :logs_menu
if "%choice%"=="8" call :check_environment
if "%choice%"=="9" call :uninstall_service
if "%choice%"=="0" goto end

if not "%choice%"=="0" (
    echo.
    pause
    goto show_menu
)
goto end

:monitor_menu
echo.
echo %COLOR_CYAN%========================================%COLOR_RESET%
echo %COLOR_CYAN%   监控管理菜单%COLOR_RESET%
echo %COLOR_CYAN%========================================%COLOR_RESET%
echo.
echo %COLOR_YELLOW%1.%COLOR_RESET% 启动监控服务
echo %COLOR_YELLOW%2.%COLOR_RESET% 停止监控服务
echo %COLOR_YELLOW%3.%COLOR_RESET% 查看监控状态
echo %COLOR_YELLOW%4.%COLOR_RESET% 返回主菜单
echo.
set /p monitor_choice=请输入选项 (1-4): 

if "%monitor_choice%"=="1" call :start_monitor
if "%monitor_choice%"=="2" call :stop_monitor
if "%monitor_choice%"=="3" call :show_monitor_status
if "%monitor_choice%"=="4" goto :eof

if not "%monitor_choice%"=="4" (
    echo.
    pause
    goto monitor_menu
)
goto :eof

:logs_menu
echo.
echo %COLOR_CYAN%========================================%COLOR_RESET%
echo %COLOR_CYAN%   日志查看菜单%COLOR_RESET%
echo %COLOR_CYAN%========================================%COLOR_RESET%
echo.
echo %COLOR_YELLOW%1.%COLOR_RESET% 队列处理器日志
echo %COLOR_YELLOW%2.%COLOR_RESET% 实时跟踪日志
echo %COLOR_YELLOW%3.%COLOR_RESET% 监控服务日志
echo %COLOR_YELLOW%4.%COLOR_RESET% 系统服务日志
echo %COLOR_YELLOW%5.%COLOR_RESET% 错误日志
echo %COLOR_YELLOW%6.%COLOR_RESET% 警报日志
echo %COLOR_YELLOW%7.%COLOR_RESET% 返回主菜单
echo.
set /p logs_choice=请输入选项 (1-7): 

if "%logs_choice%"=="1" call :show_queue_logs
if "%logs_choice%"=="2" call :show_queue_logs "-f"
if "%logs_choice%"=="3" call :show_monitor_logs
if "%logs_choice%"=="4" call :show_system_logs
if "%logs_choice%"=="5" call :show_error_logs
if "%logs_choice%"=="6" call :show_alert_logs
if "%logs_choice%"=="7" goto :eof

if not "%logs_choice%"=="7" (
    echo.
    pause
    goto logs_menu
)
goto :eof

:end
echo.
endlocal
exit /b 0

REM 调用主程序
call :main %*