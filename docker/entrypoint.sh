#!/bin/sh
set -e

# 启动 PHP-FPM
php-fpm -D

# 启动 Nginx
nginx -g "daemon off;"
