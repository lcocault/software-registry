FROM php:8.2-apache

# Install PostgreSQL client library and PHP PDO extension for PostgreSQL
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

# Copy application source into the Apache document root
COPY . /var/www/html/

# Ensure Apache can read all files
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
