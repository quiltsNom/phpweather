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

# Listen on $PORT (Render/Fly inject it; Hugging Face Spaces expects 7860).
# Unprivileged port so Apache can run as non-root (required by HF Spaces).
ENV PORT=7860
RUN sed -ri 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
 && sed -ri 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf \
 && chown -R www-data:www-data /var/run/apache2 /var/lock/apache2 /var/log/apache2

COPY --chown=www-data:www-data src/ /var/www/html/

USER www-data
EXPOSE 7860

HEALTHCHECK --interval=30s --timeout=3s CMD curl -fsS "http://localhost:${PORT}/health.php" || exit 1
