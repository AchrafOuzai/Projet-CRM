FROM php:8.1-fpm

RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev \
    libxml2-dev zip unzip libcurl4-openssl-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd curl

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/symfony

COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --optimize
RUN chown -R www-data:www-data /var/www/symfony
RUN mkdir -p /var/www/symfony/var && chmod -R 755 /var/www/symfony/var

EXPOSE 9000
CMD ["php-fpm"]