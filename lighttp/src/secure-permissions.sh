#!/bin/bash
# ======================================================
# Lighttp v1.0.5 - 文件及目录权限安全设置脚本
# 适用环境：宝塔面板 (CentOS/Ubuntu)
# Web 用户：www
# 运行：bash secure-permissions.sh
# ======================================================

set -e

echo "=========================================="
echo "  Lighttp - 文件及目录权限安全设置"
echo "  适用环境：宝塔面板"
echo "  版本：v1.0.5"
echo "=========================================="
echo ""

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# 宝塔面板 Web 用户
WEB_USER="www"
WEB_GROUP="www"
CURRENT_USER=$(whoami)

# 检查是否在项目根目录
if [ ! -d "public" ] || [ ! -d "app" ]; then
    echo -e "${RED}错误：请在 Lighttp 项目根目录下运行此脚本${NC}"
    echo "当前目录：$(pwd)"
    exit 1
fi

# 检查 www 用户是否存在
if ! id "$WEB_USER" &>/dev/null; then
    echo -e "${YELLOW}警告：用户 $WEB_USER 不存在，请确认 Web 服务器用户${NC}"
    read -p "请输入正确的 Web 用户名（默认 www）: " input_user
    WEB_USER="${input_user:-www}"
    WEB_GROUP="$WEB_USER"
fi

echo -e "${YELLOW}[1/7] 检测环境...${NC}"
echo "  - 当前用户：$CURRENT_USER"
echo "  - Web 用户：$WEB_USER"
echo "  - Web 组：$WEB_GROUP"

echo ""
echo -e "${YELLOW}[2/7] 设置目录权限...${NC}"

# 目录权限：755 (rwx r-x r-x)
find . -type d -exec chmod 755 {} \; 2>/dev/null || true
echo "  - 所有目录：755 ✓"

echo ""
echo -e "${YELLOW}[3/7] 设置文件权限...${NC}"

# 文件权限：644 (rw- r-- r--)
find . -type f -exec chmod 644 {} \; 2>/dev/null || true
echo "  - 所有文件：644 ✓"

echo ""
echo -e "${YELLOW}[4/7] 设置特殊目录权限...${NC}"

# 需要写入权限的目录（Web 服务器需要）
if [ -d "var" ]; then
    chmod -R 755 var/
    echo "  - var/：755 ✓"
fi

if [ -d "var/cache" ]; then
    chmod -R 755 var/cache/
    echo "  - var/cache/：755 (可写) ✓"
fi

if [ -d "var/logs" ]; then
    chmod -R 755 var/logs/
    echo "  - var/logs/：755 (可写) ✓"
fi

if [ -d "var/sessions" ]; then
    chmod -R 755 var/sessions/
    echo "  - var/sessions/：755 (可写) ✓"
fi

if [ -d "public/uploads" ]; then
    chmod -R 755 public/uploads/
    echo "  - public/uploads/：755 (可写) ✓"
fi

echo ""
echo -e "${YELLOW}[5/7] 设置敏感文件权限...${NC}"

if [ -f "app/config/config.php" ]; then
    chmod 644 app/config/config.php
    echo "  - app/config/config.php：644 ✓"
fi

find . -name ".htaccess" -exec chmod 644 {} \; 2>/dev/null || true
echo "  - .htaccess 文件：644 ✓"

find . -name ".user.ini" -exec chmod 644 {} \; 2>/dev/null || true
echo "  - .user.ini 文件：644 ✓"

echo ""
echo -e "${YELLOW}[6/7] 设置所有者和组...${NC}"

# 设置所有文件和目录的所有者为当前用户，组为 www
chown -R "$CURRENT_USER":"$WEB_GROUP" . 2>/dev/null || true
echo "  - 所有者：$CURRENT_USER:$WEB_GROUP ✓"

# 特殊目录：确保 www 用户有写入权限
if [ -d "var/cache" ]; then
    chown -R "$CURRENT_USER":"$WEB_GROUP" var/cache/ 2>/dev/null || true
    chmod -R 775 var/cache/
fi

if [ -d "var/logs" ]; then
    chown -R "$CURRENT_USER":"$WEB_GROUP" var/logs/ 2>/dev/null || true
    chmod -R 775 var/logs/
fi

if [ -d "var/sessions" ]; then
    chown -R "$CURRENT_USER":"$WEB_GROUP" var/sessions/ 2>/dev/null || true
    chmod -R 775 var/sessions/
fi

if [ -d "public/uploads" ]; then
    chown -R "$CURRENT_USER":"$WEB_GROUP" public/uploads/ 2>/dev/null || true
    chmod -R 775 public/uploads/
fi

echo "  - 可写目录已设置 ✓"

echo ""
echo -e "${YELLOW}[7/7] 安全检查...${NC}"

# 检查是否有可写的 PHP 文件
echo "  - 检查可写 PHP 文件..."
WRITABLE_PHP=$(find . -type f -name "*.php" -perm -o+w 2>/dev/null | head -20)
if [ -n "$WRITABLE_PHP" ]; then
    echo -e "${YELLOW}    警告：以下 PHP 文件对其他人可写：${NC}"
    echo "$WRITABLE_PHP" | head -10
    echo "    建议执行：find . -type f -name '*.php' -exec chmod o-w {} \;"
else
    echo "    没有可写的 PHP 文件 ✓"
fi

# 检查敏感文件
echo "  - 检查敏感文件..."
SENSITIVE_FILES=""
if [ -f "install.php" ]; then
    SENSITIVE_FILES="$SENSITIVE_FILES install.php"
fi
if [ -f "install.sql" ]; then
    SENSITIVE_FILES="$SENSITIVE_FILES install.sql"
fi
if [ -f "migrate-passwords.php" ]; then
    SENSITIVE_FILES="$SENSITIVE_FILES migrate-passwords.php"
fi
if [ -n "$SENSITIVE_FILES" ]; then
    echo -e "${YELLOW}    提醒：以下安装/工具文件建议删除：${NC}"
    echo "    $SENSITIVE_FILES"
    echo "    执行命令：rm -f $SENSITIVE_FILES"
else
    echo "    没有敏感文件残留 ✓"
fi

echo ""
echo "=========================================="
echo -e "${GREEN}  ✅ 权限安全设置完成！${NC}"
echo "=========================================="
echo ""
echo "📊 权限设置总结："
echo "  - 目录权限：755 (drwxr-xr-x)"
echo "  - 文件权限：644 (-rw-r--r--)"
echo "  - 所有者：$CURRENT_USER:$WEB_GROUP"
echo ""
echo "📁 可写目录（Web 服务器需要）："
echo "  - var/cache/ (775)"
echo "  - var/logs/ (775)"
echo "  - var/sessions/ (775)"
echo "  - public/uploads/ (775) 如有"
echo ""
echo "⚠️  安全提醒："
echo "  1. 建议删除 install.php 和 install.sql"
echo "  2. 生产环境请将 config.php 中的 debug 设为 false"
echo "  3. 定期检查 var/logs/ 目录的日志文件"
echo "  4. 定期备份数据库和文件"
echo ""