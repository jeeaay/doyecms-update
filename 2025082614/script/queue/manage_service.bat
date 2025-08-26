@echo off
setlocal enabledelayedexpansion

REM 获取脚本目录
set SCRIPT_DIR=%~dp0
set SCRIPT_DIR=%SCRIPT_DIR:~0,-1%

REM 配置参数
set SERVICE_NAME=DoyeCMS-Queue
set QUEUE_SCRIPT=%SCRIPT_DIR%\queue_processor.php
set LOG_DIR=%SCRIPT_DIR%\logs

REM DoyeCMS AI队列处理器 - Windows服务管理脚本

:menu
cls
echo ========================================
echo   DoyeCMS AI队列处理器 - 服务管理
echo ========================================
echo.
echo 当前服务状态:
for /f "tokens=*" %%i in ('nssm status "%SERVICE_NAME%" 2^>nul') do set SERVICE_STATUS=%%i
if "%SERVICE_STATUS%"=="" (
    echo [未安装] 服务尚未安装
) else (
    echo [%SERVICE_STATUS%] %SERVICE_NAME%
)
echo.
echo 请选择操作:
echo 1. 安装服务
echo 2. 启动服务
echo 3. 停止服务
echo 4. 重启服务
echo 5. 查看服务状态
echo 6. 查看日志
echo 7. 删除服务
echo 8. 退出
echo.
set /p choice=请输入选项 (1-8): 

if "%choice%"=="1" goto install
if "%choice%"=="2" goto start
if "%choice%"=="3" goto stop
if "%choice%"=="4" goto restart
if "%choice%"=="5" goto status
if "%choice%"=="6" goto logs
if "%choice%"=="7" goto remove
if "%choice%"=="8" goto exit

echo 无效选项，请重新选择
pause
goto menu

:install
echo 正在安装服务...
call install_service.bat
pause
goto menu

:start
echo 正在启动服务...
nssm start "%SERVICE_NAME%"
if %ERRORLEVEL% EQU 0 (
    echo ✓ 服务启动成功
) else (
    echo ✗ 服务启动失败
)
pause
goto menu

:stop
echo 正在停止服务...
nssm stop "%SERVICE_NAME%"
if %ERRORLEVEL% EQU 0 (
    echo ✓ 服务停止成功
) else (
    echo ✗ 服务停止失败
)
pause
goto menu

:restart
echo 正在重启服务...
nssm restart "%SERVICE_NAME%"
if %ERRORLEVEL% EQU 0 (
    echo ✓ 服务重启成功
) else (
    echo ✗ 服务重启失败
)
pause
goto menu

:status
echo 服务状态信息:
echo ==================
nssm status "%SERVICE_NAME%"
echo.
echo 详细信息:
sc query "%SERVICE_NAME%" 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo 服务未安装或无法访问
)
pause
goto menu

:logs
echo 日志文件位置:
echo ==================
set LOG_DIR=d:\phpstudy_pro\WWW\DoyeCMS\logs
echo 标准输出日志: %LOG_DIR%\queue_service_stdout.log
echo 错误输出日志: %LOG_DIR%\queue_service_stderr.log
echo 应用程序日志: %LOG_DIR%\queue_processor_*.log
echo.
echo 选择要查看的日志:
echo 1. 标准输出日志 (最后50行)
echo 2. 错误输出日志 (最后50行)
echo 3. 应用程序日志 (最新文件的最后50行)
echo 4. 返回主菜单
echo.
set /p log_choice=请选择 (1-4): 

if "%log_choice%"=="1" (
    if exist "%LOG_DIR%\queue_service_stdout.log" (
        echo.
        echo === 标准输出日志 (最后50行) ===
        powershell "Get-Content '%LOG_DIR%\queue_service_stdout.log' -Tail 50"
    ) else (
        echo 日志文件不存在
    )
)
if "%log_choice%"=="2" (
    if exist "%LOG_DIR%\queue_service_stderr.log" (
        echo.
        echo === 错误输出日志 (最后50行) ===
        powershell "Get-Content '%LOG_DIR%\queue_service_stderr.log' -Tail 50"
    ) else (
        echo 日志文件不存在
    )
)
if "%log_choice%"=="3" (
    for /f "delims=" %%f in ('dir /b /od "%LOG_DIR%\queue_processor_*.log" 2^>nul ^| findstr /r ".*"') do set LATEST_LOG=%%f
    if defined LATEST_LOG (
        echo.
        echo === 应用程序日志 (最后50行) ===
        powershell "Get-Content '%LOG_DIR%\!LATEST_LOG!' -Tail 50"
    ) else (
        echo 应用程序日志文件不存在
    )
)
if "%log_choice%"=="4" goto menu

pause
goto menu

:remove
echo 警告: 这将删除服务！
set /p confirm=确定要删除服务吗？(y/N): 
if /i "%confirm%"=="y" (
    echo 正在删除服务...
    nssm stop "%SERVICE_NAME%" 2>nul
    nssm remove "%SERVICE_NAME%" confirm
    if %ERRORLEVEL% EQU 0 (
        echo ✓ 服务删除成功
    ) else (
        echo ✗ 服务删除失败
    )
) else (
    echo 操作已取消
)
pause
goto menu

:exit
echo 再见！
exit /b 0