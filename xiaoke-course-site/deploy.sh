#!/usr/bin/env bash
# ============================================================
#  小课学堂 - 一键部署脚本 (Linux / macOS)
#  用法: chmod +x deploy.sh && ./deploy.sh
# ============================================================
set -e
cd "$(dirname "$0")"

echo "============================================"
echo "  小课学堂 - 知识付费网课平台 一键部署"
echo "============================================"

# ---- Docker 优先 ----
if command -v docker &>/dev/null && docker info &>/dev/null; then
    read -p "检测到 Docker，是否使用 Docker 部署？[Y/n]: " yn
    if [[ ! "$yn" =~ ^[Nn] ]]; then
        echo "[2/3] 正在启动容器（MySQL 数据保存在 Docker 卷中）..."
        docker compose up -d --build
        echo "[3/3] 启动完成！"
        echo "  前台:     http://localhost:8080"
        echo "  安装向导: http://localhost:8080/install.php （首次部署请先访问）"
        echo "  后台:     http://localhost:8080/admin/login.php"
        echo "  停止: docker compose down"
        exit 0
    fi
fi

# ---- 本机 PHP ----
PHP_BIN=""
if command -v php &>/dev/null; then
    PHP_BIN=php
elif [ -x /usr/local/php/bin/php ]; then
    PHP_BIN=/usr/local/php/bin/php
fi

if [ -z "$PHP_BIN" ]; then
    echo "[!] 未找到 PHP。请先安装 PHP 7.4+ 及 pdo_mysql/openssl/mbstring 扩展后重试，"
    echo "    或安装 Docker 后重新运行本脚本。"
    exit 1
fi

echo "[2/3] 检测到 PHP："
$PHP_BIN -v | head -1
echo
echo "[3/3] 启动开发服务器: http://localhost:8000"
echo "  安装向导: http://localhost:8000/install.php （首次部署请先访问）"
echo "  提示: 生产环境建议 Nginx/Apache + php-fpm，本方式用于快速体验"
echo "  Ctrl+C 停止服务"
echo
exec $PHP_BIN -S 0.0.0.0:8000
