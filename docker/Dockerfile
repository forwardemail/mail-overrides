FROM php:8.2-apache

# Local Development Dockerfile
# For production deployment, use Ansible from the main forwardemail monorepo

LABEL maintainer="Forward Email LLC"
LABEL description="Forward Email SnappyMail - Local Development"

# Install PHP extensions required by SnappyMail
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libxml2-dev \
    libzip-dev \
    libssl-dev \
    curl \
    && docker-php-ext-install \
    intl \
    opcache \
    pdo_mysql \
    zip \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite headers

# Set working directory
WORKDIR /var/www/html

# Configure PHP for development
RUN { \
    echo 'memory_limit = 256M'; \
    echo 'upload_max_filesize = 25M'; \
    echo 'post_max_size = 25M'; \
    echo 'max_execution_time = 300'; \
    echo 'display_errors = On'; \
    echo 'error_reporting = E_ALL'; \
    echo 'opcache.enable = 1'; \
    echo 'opcache.revalidate_freq = 0'; \
} > /usr/local/etc/php/conf.d/snappymail-dev.ini

# Configure Apache
RUN { \
    echo '<Directory /var/www/html>'; \
    echo '    Options -Indexes +FollowSymLinks'; \
    echo '    AllowOverride All'; \
    echo '    Require all granted'; \
    echo '</Directory>'; \
} > /etc/apache2/conf-available/snappymail.conf && \
    a2enconf snappymail

EXPOSE 80

CMD ["apache2-foreground"]
