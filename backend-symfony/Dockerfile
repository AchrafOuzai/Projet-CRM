FROM php:8.1-fpm

RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev \
    libxml2-dev zip unzip

RUN docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/symfony

COPY composer.json composer.lock ./

# ← changed: removed --no-dev to include fixtures bundle
RUN composer install --no-scripts --no-autoloader --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --optimize
RUN chown -R www-data:www-data /var/www/symfony
RUN chmod -R 755 /var/www/symfony/var

EXPOSE 9000
CMD ["php-fpm"]