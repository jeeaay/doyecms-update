@echo off
REM DoyeCMS AI队列处理器监控启动脚本

echo ========================================
echo   DoyeCMS AI队列处理器监控启动
echo ========================================
echo.

set SCRIPT_DIR=%~dp0
set AUTO_MONITOR=%SCRIPT_DIR%auto_monitor.bat
set STOP_FILE=%SCRIPT_DIR%stop_monitor

REM 检查是否已经在运行
for /f "tokens=2" %%i in ('tasklist /fi "imagename eq cmd.exe" /fo csv ^| find "auto_monitor.bat"') do (
    echo 监控服务已在运行中
    echo 如需停止，请运行 stop_monitor.bat
    pause
    exit /b 1
)

REM 删除可能存在的停止文件
if exist "%STOP_FILE%" del "%STOP_FILE%" 2>nul

echo 正在启动自动监控服务...
echo.
echo 监控功能:
echo - 每5分钟检查队列处理器健康状态
echo - 异常时自动重启（最多3次）
echo - 记录详细的监控日志
echo - 支持警报通知
echo.
echo 日志文件位置:
echo   监控日志: logs\auto_monitor_YYYY-MM-DD.log
echo   警报日志: logs\alerts.log
echo.
echo 要停止监控，请运行 stop_monitor.bat
echo.
echo 按任意键开始监控，或按 Ctrl+C 取消...
pause >nul

REM 在新窗口中启动监控
start "DoyeCMS队列监控" /min cmd /c ""%AUTO_MONITOR%""

echo.
echo ✓ 监控服务已启动
echo 监控窗口已最小化到任务栏
echo.
echo 管理命令:
echo   查看状态: php monitor.php --status
echo   健康检查: php monitor.php --check
echo   手动重启: php monitor.php --restart
echo   停止监控: stop_monitor.bat
echo.
echo 按任意键退出...
pause >nul