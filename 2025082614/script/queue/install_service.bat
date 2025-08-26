@echo off
REM DoyeCMS AI队列处理器 - Windows服务安装脚本
REM 使用NSSM (Non-Sucking Service Manager) 创建Windows服务

echo 正在安装DoyeCMS AI队列处理器服务...

REM 检查NSSM是否存在
where nssm >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo 错误: 未找到NSSM工具
    echo 请先下载并安装NSSM: https://nssm.cc/download
    echo 并将nssm.exe添加到系统PATH中
    pause
    exit /b 1
)

REM 获取脚本目录
set SCRIPT_DIR=%~dp0
set SCRIPT_DIR=%SCRIPT_DIR:~0,-1%

REM 设置变量
set SERVICE_NAME=DoyeCMS_Queue_Processor
set PHP_PATH=d:\phpstudy_pro\Extensions\php\php8.0.2nts\php.exe
set SCRIPT_PATH=%SCRIPT_DIR%\queue_processor.php
set WORK_DIR=%SCRIPT_DIR%
set LOG_DIR=%SCRIPT_DIR%\logs

REM 创建日志目录
if not exist "%LOG_DIR%" mkdir "%LOG_DIR%"

REM 停止并删除现有服务（如果存在）
nssm stop "%SERVICE_NAME%" 2>nul
nssm remove "%SERVICE_NAME%" confirm 2>nul

REM 安装新服务
echo 创建服务: %SERVICE_NAME%
nssm install "%SERVICE_NAME%" "%PHP_PATH%" "%SCRIPT_PATH%"

REM 配置服务参数
nssm set "%SERVICE_NAME%" AppDirectory "%WORK_DIR%"
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
echo 启动服务...
nssm start "%SERVICE_NAME%"

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ✓ 服务安装成功！
    echo 服务名称: %SERVICE_NAME%
    echo 可以使用以下命令管理服务:
    echo   启动: nssm start "%SERVICE_NAME%"
    echo   停止: nssm stop "%SERVICE_NAME%"
    echo   重启: nssm restart "%SERVICE_NAME%"
    echo   状态: nssm status "%SERVICE_NAME%"
    echo   删除: nssm remove "%SERVICE_NAME%" confirm
    echo.
    echo 日志文件位置:
    echo   标准输出: %LOG_DIR%\queue_service_stdout.log
    echo   错误输出: %LOG_DIR%\queue_service_stderr.log
) else (
    echo ✗ 服务安装失败！
)

echo.
echo 按任意键退出...
pause >nul