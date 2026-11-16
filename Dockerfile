FROM php:8.2-apache

# PHP extension used by the app + production php.ini (errors logged, not shown)
RUN docker-php-ext-install pdo_mysql \
 && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Serve only public/ so .env, src/ and schema.sql are never reachable over HTTP
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
      /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY . /var/www/html
RUN sed -i 's/\r$//' /var/www/html/docker/entrypoint.sh \
 && chmod +x /var/www/html/docker/entrypoint.sh \
 && chown -R www-data:www-data /var/www/html

EXPOSE 8080
ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
