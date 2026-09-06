FROM php:8.2-apache

# 安装 PHP 扩展（pdo_mysql 已内置，需要 openssl/mbstring）
RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite

# 复制站点代码
COPY . /var/www/html/

# Apache 指向根目录 & 权限
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/storage

EXPOSE 80
