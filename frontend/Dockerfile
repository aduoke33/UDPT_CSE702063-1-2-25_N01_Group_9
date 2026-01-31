# =====================================================
# FRONTEND - Laravel PHP Application
# Movie Booking System
# =====================================================

FROM php:8.2-fpm-alpine

LABEL maintainer="Movie Booking Team"
LABEL description="Laravel Frontend for Movie Booking System"

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    git \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    zip \
    unzip \
    oniguruma-dev \
    libxml2-dev \
    icu-dev

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    gd \
    pdo \
    pdo_mysql \
    mbstring \
    xml \
    bcmath \
    intl \
    opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy all application files
COPY . .

# Create .env file from .env.example
RUN cp .env.example .env

# Create required directories BEFORE composer install
RUN mkdir -p storage/framework/{sessions,views,cache/data} \
    && mkdir -p storage/app/public \
    && mkdir -p storage/logs \
    && mkdir -p bootstrap/cache

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Generate APP_KEY
RUN php artisan key:generate --force

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage \
    && chmod -R 775 bootstrap/cache

# Copy nginx config
COPY nginx-frontend.conf /etc/nginx/http.d/default.conf

# Copy supervisor config
RUN mkdir -p /var/log/supervisor
COPY supervisord.conf /etc/supervisord.conf

# Expose port 80
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# Start supervisor
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisord.conf"]
