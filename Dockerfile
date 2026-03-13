FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    mysql-client \
    git \
    curl \
    wget \
    unzip \
    nano \
    vim \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    mysqli \
    pdo_mysql \
    json \
    curl \
    && docker-php-ext-enable \
    mysqli \
    pdo_mysql \
    curl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Enable Apache modules
RUN a2enmod rewrite \
    && a2enmod headers \
    && a2enmod deflate

# Create Apache vhost configuration
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html\n\
    <Directory /var/www/html>\n\
        Options Indexes FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    chmod -R 777 /var/www/html/tmp /var/www/html/logs 2>/dev/null || true

# Create required directories
RUN mkdir -p /var/www/html/logs \
    && mkdir -p /var/www/html/tmp \
    && mkdir -p /var/www/html/backups \
    && chown -R www-data:www-data /var/www/html/logs \
    && chown -R www-data:www-data /var/www/html/tmp \
    && chmod -R 777 /var/www/html/logs \
    && chmod -R 777 /var/www/html/tmp

# Expose port 80
EXPOSE 80

# Enable PHP error logging
RUN echo "error_log = /var/log/apache2/php-error.log" >> /usr/local/etc/php/conf.d/docker-php-ext-json.ini

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Start Apache
CMD ["apache2-foreground"]
