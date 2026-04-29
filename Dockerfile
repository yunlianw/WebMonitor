FROM php:8.2-fpm-alpine

# 安装系统依赖
RUN apk add --no-cache \
    nginx \
    mysql-client \
    curl \
    libpng-dev \
    libzip-dev \
    icu-dev \
    && docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    mysqli \
    gd \
    zip \
    intl

# 创建目录
RUN mkdir -p /var/www/html /run/nginx

# 复制程序文件
COPY . /var/www/html/

# 复制nginx配置
COPY docker/nginx.conf /etc/nginx/http.d/default.conf

# 设置权限
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && mkdir -p /var/www/html/storage /var/www/html/logs \
    && chmod -R 777 /var/www/html/storage /var/www/html/logs

# 暴露端口
EXPOSE 80

# 启动脚本
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

CMD ["/entrypoint.sh"]
