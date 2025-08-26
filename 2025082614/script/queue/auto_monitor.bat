@echo off
REM DoyeCMS AI队列处理器自动监控脚本
REM 定期检查队列处理器状态，异常时自动重启

setlocal enabledelayedexpansion

REM 配置参数
set PHP_PATH=d:\phpstudy_pro\Extensions\php\php8.0.2nts\php.exe
set SCRIPT_DIR=d:\phpstudy_pro\WWW\DoyeCMS\script
set MONITOR_SCRIPT=%SCRIPT_DIR%\monitor.php
set LOG_DIR=d:\phpstudy_pro\WWW\DoyeCMS\logs
set MONITOR_LOG=%LOG_DIR%\auto_monitor_%date:~0,4%-%date:~5,2%-%date:~8,2%.log
set CHECK_INTERVAL=300
set MAX_RESTART_ATTEMPTS=3
set RESTART_COOLDOWN=600

REM 创建日志目录
if not exist "%LOG_DIR%" mkdir "%LOG_DIR%"

REM 记录日志函数
:log
set timestamp=%date% %time:~0,8%
echo [%timestamp%] %~1 >> "%MONITOR_LOG%"
echo [%timestamp%] %~1
goto :eof

REM 主监控循环
call :log "自动监控启动，检查间隔: %CHECK_INTERVAL%秒"

set restart_count=0
set last_restart_time=0

:monitor_loop
    REM 执行健康检查
    "%PHP_PATH%" "%MONITOR_SCRIPT%" --check > nul 2>&1
    set health_status=!errorlevel!
    
    if !health_status! neq 0 (
        call :log "健康检查失败，准备重启队列处理器"
        
        REM 检查重启冷却时间
        set current_time=!time:~0,2!!time:~3,2!!time:~6,2!
        set /a time_diff=!current_time! - !last_restart_time!
        
        if !time_diff! gtr !RESTART_COOLDOWN! (
            set restart_count=0
        )
        
        if !restart_count! lss !MAX_RESTART_ATTEMPTS! (
            call :log "执行重启 (第!restart_count!/!MAX_RESTART_ATTEMPTS!次)"
            
            "%PHP_PATH%" "%MONITOR_SCRIPT%" --restart
            set restart_result=!errorlevel!
            
            if !restart_result! equ 0 (
                call :log "重启成功"
                set /a restart_count+=1
                set last_restart_time=!current_time!
            ) else (
                call :log "重启失败"
                set /a restart_count+=1
            )
        ) else (
            call :log "重启次数已达上限，停止自动重启"
            call :log "请手动检查系统状态"
            
            REM 发送警报（可以扩展为邮件或其他通知方式）
            call :send_alert "队列处理器重启失败，需要人工干预"
        )
    ) else (
        REM 健康检查通过，重置重启计数
        if !restart_count! gtr 0 (
            call :log "系统恢复正常，重置重启计数"
            set restart_count=0
        )
    )
    
    REM 等待下次检查
    timeout /t %CHECK_INTERVAL% /nobreak > nul
    
    REM 检查是否需要退出（可以通过创建stop文件来停止监控）
    if exist "%SCRIPT_DIR%\stop_monitor" (
        call :log "检测到停止信号，退出监控"
        del "%SCRIPT_DIR%\stop_monitor" 2>nul
        goto :end
    )
    
goto monitor_loop

:send_alert
    REM 发送警报通知
    call :log "警报: %~1"
    
    REM 这里可以添加邮件发送、短信通知等功能
    REM 例如：
    REM powershell -Command "Send-MailMessage -To 'admin@example.com' -Subject 'DoyeCMS队列处理器警报' -Body '%~1' -SmtpServer 'smtp.example.com'"
    
    REM 或者写入特殊的警报日志文件
    echo [%date% %time:~0,8%] ALERT: %~1 >> "%LOG_DIR%\alerts.log"
goto :eof

:end
call :log "自动监控结束"
endlocal
exit /b 0