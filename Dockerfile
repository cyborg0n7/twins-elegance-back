FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli mbstring exif pcntl bcmath gd zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock* ./

# Install dependencies WITHOUT running scripts (this is the key fix)
RUN composer install --no-scripts --no-autoloader --optimize-autoloader --no-dev || \
    composer install --no-scripts --no-autoloader --no-dev

# Copy all application files
COPY . .

# Create .env from .env.example if it doesn't exist
RUN if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || echo "APP_ENV=prod" > .env; fi

# Generate optimized autoloader
RUN composer dump-autoload --optimize --no-dev

# Create necessary directories and set permissions
RUN mkdir -p var/cache var/log var/sessions && \
    chown -R www-data:www-data var/ public/

# Configure Apache
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf && \
    sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf && \
    a2enmod rewrite

EXPOSE 80

CMD ["apache2-foreground"]