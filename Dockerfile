FROM php:8.3-apache

# Production PHP settings + opcache
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && docker-php-ext-install opcache \
 && { \
      echo 'expose_php=Off'; \
      echo 'opcache.enable=1'; \
      echo 'opcache.validate_timestamps=0'; \
    } > "$PHP_INI_DIR/conf.d/app.ini" \
 && echo 'ServerTokens Prod\nServerSignature Off' > /etc/apache2/conf-enabled/hardening.conf

# Render/Fly/Railway inject $PORT; default to 80
ENV PORT=80
RUN sed -ri 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
 && sed -ri 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf

COPY --chown=www-data:www-data src/ /var/www/html/

HEALTHCHECK --interval=30s --timeout=3s CMD curl -fsS "http://localhost:${PORT}/health.php" || exit 1
