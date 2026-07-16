# 1. 进入项目根目录
cd /www/domain/p.inetpub.cn

# 2. 创建 .user.ini
cat > .user.ini << 'EOF'
; ======================================================
; Lighttp v1.0.5 - PHP 安全配置
; 适用环境：宝塔面板 / PHP 7.4+
; ======================================================

disable_functions = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source,phpinfo,dl,pfsockopen,fsockopen,pcntl_exec
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /www/domain/p.inetpub.cn/var/logs/php_errors.log
upload_max_filesize = 20M
post_max_size = 20M
max_file_uploads = 20
memory_limit = 256M
max_execution_time = 120
max_input_time = 120
date.timezone = Asia/Shanghai
opcache.enable = 1
opcache.enable_cli = 0
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 4000
opcache.revalidate_freq = 60
opcache.fast_shutdown = 1
open_basedir = /www/domain/p.inetpub.cn/:/tmp/:/proc/:/var/tmp/
expose_php = Off
allow_url_fopen = Off
allow_url_include = Off
session.cookie_httponly = 1
session.use_only_cookies = 1
session.cookie_secure = 0
session.gc_maxlifetime = 1440
EOF

# 3. 创建根目录 .htaccess
cat > .htaccess << 'EOF'
# ======================================================
# Lighttp v1.0.5 - 根目录 Apache 安全配置
# ======================================================

Options -Indexes

<FilesMatch "^(config|database|install|migrate-passwords)\.php$">
    Order allow,deny
    Deny from all
</FilesMatch>

<FilesMatch "^\.env">
    Order allow,deny
    Deny from all
</FilesMatch>

<Files ".user.ini">
    Order allow,deny
    Deny from all
</FilesMatch>

<Files ".htaccess">
    Order allow,deny
    Deny from all
</FilesMatch>

RewriteRule ^var/ - [F,L]
RewriteRule ^app/ - [F,L]
RedirectMatch 403 /\..*$

AddDefaultCharset UTF-8

<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/css text/plain text/xml text/javascript application/javascript application/json
</IfModule>

<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 30 days"
    ExpiresByType image/jpeg "access plus 30 days"
    ExpiresByType image/png "access plus 30 days"
    ExpiresByType image/gif "access plus 30 days"
    ExpiresByType image/webp "access plus 30 days"
    ExpiresByType text/css "access plus 30 days"
    ExpiresByType application/javascript "access plus 30 days"
    ExpiresByType text/javascript "access plus 30 days"
    ExpiresByType application/json "access plus 1 day"
</IfModule>

<IfModule mod_headers.c>
    Header set X-XSS-Protection "1; mode=block"
    Header set X-Content-Type-Options "nosniff"
    Header set Referrer-Policy "no-referrer-when-downgrade"
    Header set X-Frame-Options "SAMEORIGIN"
</IfModule>
EOF

# 4. 创建 public/.htaccess
cat > public/.htaccess << 'EOF'
# ======================================================
# Lighttp v1.0.5 - Public 目录 Apache 配置
# ======================================================

<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php [QSA,L]
</IfModule>

Options -Indexes

<Files ".htaccess">
    Order allow,deny
    Deny from all
</Files>

<FilesMatch "^(config|database|install|migrate-passwords)\.php$">
    Order allow,deny
    Deny from all
</FilesMatch>

<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/css text/plain text/xml text/javascript application/javascript application/json
</IfModule>

<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 30 days"
    ExpiresByType image/jpeg "access plus 30 days"
    ExpiresByType image/png "access plus 30 days"
    ExpiresByType image/gif "access plus 30 days"
    ExpiresByType image/webp "access plus 30 days"
    ExpiresByType text/css "access plus 30 days"
    ExpiresByType application/javascript "access plus 30 days"
    ExpiresByType text/javascript "access plus 30 days"
</IfModule>

<IfModule mod_headers.c>
    Header set X-XSS-Protection "1; mode=block"
    Header set X-Content-Type-Options "nosniff"
    Header set Referrer-Policy "no-referrer-when-downgrade"
    Header set X-Frame-Options "SAMEORIGIN"
</IfModule>

DirectoryIndex index.php index.html
EOF

# 5. 设置权限
chmod 644 .user.ini .htaccess public/.htaccess

echo "✅ 安全文件部署完成！"
