FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libzip-dev \
    zlib1g-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libonig-dev \
    pkg-config \
    libssl-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        intl \
        zip \
        gd \
        dom \
        xml \
        curl \
        mbstring \
        pdo \
        pdo_mysql

# Enable Apache modules
RUN a2enmod rewrite ssl headers

# Set working directory
WORKDIR /var/www/html

# Copy SnappyMail files
COPY . /var/www/html/

# Copy entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Create data directory and set proper permissions
RUN mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/data

# Create include.php from _include.php for custom configuration
RUN cp /var/www/html/_include.php /var/www/html/include.php

# Configure PHP
RUN echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/snappymail.ini \
    && echo "upload_max_filesize = 25M" >> /usr/local/etc/php/conf.d/snappymail.ini \
    && echo "post_max_size = 25M" >> /usr/local/etc/php/conf.d/snappymail.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/snappymail.ini

# Expose port
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
  CMD curl -f http://localhost/ || exit 1

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]