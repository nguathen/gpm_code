# Sử dụng image PHP với Apache và Composer
FROM php:8.2-apache

# Thông tin gói Docker
LABEL maintainer="admin@giaiphapmmo.vn"
LABEL version="1.0"
LABEL description="Docker image for GPM Login Private Server"

# Cài đặt các extensions PHP cần thiết
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libzip-dev \
    unzip \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring zip exif pcntl gd

# Cài đặt Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# COPY ./.env.docker.example /var/www/html/.env

# Thiết lập thư mục làm việc
WORKDIR /var/www/html

# Copy code Laravel vào container
COPY . .

COPY php_large_file_upload.ini /usr/local/etc/php/conf.d
# COPY env.docker.example /var/www/html/.env
# COPY env.docker.example /var/www/html/abc
# COPY env.docker.example /usr/local/etc/php/conf.d/ngon
# COPY env.docker.example /usr/local/etc/php/conf.d/.env

RUN chmod -R 755 /var/www/html

RUN rm -rf public/storage/
RUN php artisan storage:link

# Thiết lập quyền cho thư mục storage và bootstrap/cache
# RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
RUN chown -R www-data:www-data /var/www/html
RUN find /var/www/html -type d -exec chmod 755 {} \;
RUN find /var/www/html -type f -exec chmod 644 {} \;

# Cấu hình Apache
RUN a2enmod rewrite

# Copy file .env.example thành .env và tạo key Laravel
# RUN cp .env.example .env && composer install && php artisan key:generate
# Copy file .env và cài đặt Composer
# RUN cp .env.example .env
# RUN composer update
# RUN composer install
# RUN php artisan key:generate


# RUN cp env.docker.example .env \
#     && cp env.docker.example test_ok \
#     && composer update \
#     && composer install \
#     && php artisan key:generate

RUN composer update \
    && composer install \
    && php artisan key:generate


# # Chạy lệnh entrypoint khi container khởi động
# ENTRYPOINT ["docker-php-entrypoint"]
# CMD ["apache2-foreground"]

# Copy script entrypoint.sh
# COPY entrypoint.sh /usr/local/bin/

# # Thiết lập quyền thực thi cho script
# RUN chmod +x /usr/local/bin/entrypoint.sh

# # Cấu hình để chạy script trước khi khởi động Apache
# ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]