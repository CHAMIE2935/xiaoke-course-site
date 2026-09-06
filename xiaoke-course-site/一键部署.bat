@echo off
chcp 65001 >nul
title 小课学堂 - 一键部署启动

echo ============================================
echo   小课学堂 - 知识付费网课平台 一键部署
echo ============================================
echo.

REM ---- 优先使用 Docker ----
where docker >nul 2>nul
if %errorlevel%==0 (
    echo [1/3] 检测到 Docker，是否使用 Docker 部署？(Y=使用Docker / N=使用本机PHP)
    set /p use_docker="请选择 [Y/N]: "
    if /i "%use_docker%"=="Y" goto docker_deploy
    goto php_deploy
) else (
    echo [1/3] 未检测到 Docker，尝试使用本机 PHP...
    goto php_deploy
)

:docker_deploy
echo.
echo [2/3] 正在启动容器（首次会自动构建镜像，MySQL 数据保存在 Docker 卷中）...
docker compose up -d --build
if errorlevel 1 (
    echo Docker 启动失败，请检查 Docker 是否正在运行。
    pause
    exit /b 1
)
echo.
echo [3/3] 启动完成！
echo   前台:   http://localhost:8080
echo   安装向导: http://localhost:8080/install.php   （首次部署请先访问）
echo   后台:   http://localhost:8080/admin/login.php
echo.
echo 常用命令：
echo   停止: docker compose down
echo   查看日志: docker compose logs -f
pause
exit /b 0

:php_deploy
echo.
set PHP_BIN=
where php >nul 2>nul
if %errorlevel%==0 (
    set PHP_BIN=php
) else (
    if exist "C:\php\php.exe" set PHP_BIN=C:\php\php.exe
)

if "%PHP_BIN%"=="" (
    echo [!] 未找到 PHP。请先安装 PHP 8（https://windows.php.net/download/）
    echo     并开启 php.ini 中的 pdo_mysql、openssl、mbstring 扩展，
    echo     然后重新运行本脚本；或安装 Docker 后选择 Docker 方式部署。
    pause
    exit /b 1
)

echo [2/3] 检测到 PHP：
%PHP_BIN% -v | findstr /R "^PHP"
echo.

echo [3/3] 启动开发服务器：http://localhost:8000
echo   安装向导: http://localhost:8000/install.php  （首次部署请先访问）
echo   提示: 生产环境建议使用 Nginx/Apache + php-fpm，本脚本用于快速体验
echo   按 Ctrl+C 停止服务
echo.
%PHP_BIN% -S 0.0.0.0:8000
pause
