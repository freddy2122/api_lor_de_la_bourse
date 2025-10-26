# Dockerfile (exemple pour Laravel 10/11)
FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git curl zip unzip libpng-dev libonig-dev libxml2-dev libzip-dev \
    nginx supervisor

# PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy app
COPY . /var/www/html

# Install composer deps
RUN composer install --optimize-autoloader --no-dev --no-interaction

# Build frontend if needed (optional)
# RUN npm ci && npm run build

# Permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Copy nginx conf
COPY .docker/nginx.conf /etc/nginx/sites-enabled/default

# Expose port used by nginx
EXPOSE 80

# Entrypoint: run php-fpm + nginx via supervisor
COPY .docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
CMD ["/usr/bin/supervisord", "-n"]
