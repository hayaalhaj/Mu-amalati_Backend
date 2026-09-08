FROM php:8.3-apache-bookworm

# ============================================================
# System dependencies + PHP extensions
# ============================================================

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install \
        pdo_pgsql \
        pgsql \
        mbstring \
        bcmath \
        intl \
        zip \
        xml \
    && a2enmod rewrite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ============================================================
# Composer
# ============================================================

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ============================================================
# Laravel
# ============================================================

WORKDIR /var/www/html

COPY . .

RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction

# ============================================================
# Laravel permissions
# ============================================================

RUN chown -R www-data:www-data \
    storage \
    bootstrap/cache

# ============================================================
# Apache - Laravel public directory
# ============================================================

RUN sed -i \
    's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|' \
    /etc/apache2/sites-available/000-default.conf

RUN printf '%s\n' \
    '<Directory /var/www/html/public>' \
    '    AllowOverride All' \
    '    Require all granted' \
    '</Directory>' \
    >> /etc/apache2/apache2.conf

# ============================================================
# Railway PORT
# ============================================================

ENV PORT=10000

RUN sed -i 's/Listen 80/Listen 10000/' \
    /etc/apache2/ports.conf

RUN sed -i \
    's/<VirtualHost \*:80>/<VirtualHost *:10000>/' \
    /etc/apache2/sites-available/000-default.conf

EXPOSE 10000

# ============================================================
# Verify Apache configuration
# ============================================================

RUN apache2ctl configtest

# ============================================================
# Start Apache
# ============================================================

CMD ["apache2-foreground"]