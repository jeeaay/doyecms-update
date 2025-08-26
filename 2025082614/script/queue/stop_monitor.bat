@echo off
REM DoyeCMS AI队列处理器监控停止脚本

echo ========================================
echo   DoyeCMS AI队列处理器监控停止
echo ========================================
echo.

set SCRIPT_DIR=%~dp0
set STOP_FILE=%SCRIPT_DIR%stop_monitor

REM 创建停止信号文件
echo stop > "%STOP_FILE%"

echo 正在停止监控服务...
echo.

REM 等待监控服务检测到停止信号
set /a count=0
:wait_loop
    if not exist "%STOP_FILE%" (
        echo ✓ 监控服务已停止
        goto :stopped
    )
    
    set /a count+=1
    if %count% gtr 30 (
        echo 警告: 监控服务可能未正常响应停止信号
        echo 尝试强制终止监控进程...
        
        REM 查找并终止监控进程
        for /f "tokens=2" %%i in ('tasklist /fi "windowtitle eq DoyeCMS队列监控" /fo csv 2^>nul ^| find "cmd.exe"') do (
            echo 终止进程: %%i
            taskkill /pid %%i /f >nul 2>&1
        )
        
        REM 删除停止文件
        if exist "%STOP_FILE%" del "%STOP_FILE%" 2>nul
        
        echo ✓ 监控服务已强制停止
        goto :stopped
    )
    
    echo 等待监控服务响应... (%count%/30)
    timeout /t 2 /nobreak >nul
goto wait_loop

:stopped
echo.
echo 监控服务状态: 已停止
echo.
echo 如需重新启动监控，请运行 start_monitor.bat
echo 如需查看队列处理器状态，请运行: php monitor.php --status
echo.
echo 按任意键退出...
pause >nul