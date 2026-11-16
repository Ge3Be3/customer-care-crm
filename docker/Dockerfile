# PHP 8.2 + Apache image for running the CRM in Docker / GitHub Codespaces.
FROM php:8.2-apache
RUN docker-php-ext-install pdo_mysql \
 && a2enmod headers rewrite \
 && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
 && echo "date.timezone=Asia/Ho_Chi_Minh" > /usr/local/etc/php/conf.d/tz.ini \
 && apt-get update && apt-get install -y --no-install-recommends git \
 && rm -rf /var/lib/apt/lists/*
