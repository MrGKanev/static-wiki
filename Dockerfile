FROM dunglas/frankenphp:1-php8.3-alpine

# Install system dependencies
RUN apk add --no-cache \
  git \
  freetype-dev \
  libjpeg-turbo-dev \
  libzip-dev \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j$(nproc) gd zip \
  && docker-php-ext-install pdo pdo_mysql mysqli mbstring exif pcntl bcmath

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy application files
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Create directories and set permissions
RUN mkdir -p cache content/uploads \
  && chown -R www-data:www-data /app \
  && chmod -R 755 /app \
  && chmod -R 777 cache content/uploads

# Create Caddyfile for FrankenPHP
RUN echo ':80 {
root * /app
encode gzip

# Handle clean URLs
try_files {path} {path}/ /index.php

# PHP files
php_fastcgi unix//var/run/php/php-fpm.sock

# Security headers
header {
X-Content-Type-Options nosniff
X-Frame-Options DENY
X-XSS-Protection "1; mode=block"
Referrer-Policy "strict-origin-when-cross-origin"
}

# Cache static files
@static path *.css *.js *.png *.jpg *.jpeg *.gif *.ico *.svg
handle @static {
header Cache-Control "public, max-age=31536000, immutable"
}

# Protect sensitive directories
@protected path /cache/* /classes/* /templates/*
respond @protected 403
}' > /etc/caddy/Caddyfile

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
  CMD wget --no-verbose --tries=1 --spider http://localhost/ || exit 1

CMD ["frankenphp", "run"]