FROM php:8.2-apache

RUN apt-get update && apt-get install -y git unzip libzip-dev libpng-dev libonig-dev libxml2-dev libpq-dev \
    && docker-php-ext-install pdo_mysql pdo_pgsql mbstring zip gd bcmath opcache

# --- Velocidad ---
# PHP precompilado en memoria (opcache): el código no cambia dentro del contenedor, así que no revisa archivos en cada petición
RUN { \
      echo 'opcache.enable=1'; \
      echo 'opcache.memory_consumption=128'; \
      echo 'opcache.max_accelerated_files=10000'; \
      echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/opcache-sica.ini

# Apache: comprime las respuestas (página, JSON, JS) y deja que el navegador guarde librerías e imágenes un día
RUN a2enmod rewrite deflate headers \
    && { \
      echo 'AddOutputFilterByType DEFLATE application/json application/manifest+json image/svg+xml'; \
      echo '<LocationMatch "^/(vendor|img|plantillas)/">'; \
      echo '    Header set Cache-Control "public, max-age=86400"'; \
      echo '</LocationMatch>'; \
    } > /etc/apache2/conf-available/sica-velocidad.conf \
    && a2enconf sica-velocidad

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --optimize-autoloader \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80
# Al arrancar: guarda la configuración y las vistas ya compiladas (más rápido), migra, crea cuentas y enciende Apache
CMD ["sh", "-c", "php artisan config:cache && php artisan view:cache && php artisan migrate --force && php artisan sica:crear-usuarios && chown -R www-data:www-data storage bootstrap/cache && apache2-foreground"]
