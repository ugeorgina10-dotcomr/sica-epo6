FROM php:8.5-apache

# Instala los programas que necesita el sistema para funcionar
RUN apt-get update && apt-get install -y git unzip libzip-dev libpng-dev libonig-dev libxml2-dev libpq-dev libicu-dev \
    && docker-php-ext-install pdo_mysql pdo_pgsql mbstring zip gd bcmath intl

# --- Que el sistema responda más rápido ---
# Guarda el código ya preparado para no leerlo de nuevo en cada visita
RUN { \
      echo 'opcache.enable=1'; \
      echo 'opcache.memory_consumption=128'; \
      echo 'opcache.max_accelerated_files=10000'; \
      echo 'opcache.interned_strings_buffer=16'; \
      echo 'opcache.enable_cli=1'; \
      echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/opcache-sica.ini

# --- Permitir subir archivos grandes ---
# Sin esto solo se podían subir archivos de 2 MB, y las listas o plantillas grandes fallaban
RUN { \
      echo 'upload_max_filesize=25M'; \
      echo 'post_max_size=30M'; \
      echo 'max_file_uploads=50'; \
      echo 'memory_limit=256M'; \
      echo 'max_execution_time=120'; \
      echo 'realpath_cache_size=4096K'; \
      echo 'realpath_cache_ttl=600'; \
    } > /usr/local/etc/php/conf.d/sica-subidas.ini

# Comprime las páginas para que carguen más rápido y hace que el navegador
# guarde las imágenes y librerías 30 días (así no las descarga cada vez)
RUN a2enmod rewrite deflate headers \
    && { \
      echo 'AddOutputFilterByType DEFLATE text/html text/css text/plain text/javascript application/javascript application/json application/manifest+json image/svg+xml'; \
      echo '<LocationMatch "^/(vendor|img)/">'; \
      echo '    Header set Cache-Control "public, max-age=2592000, immutable"'; \
      echo '</LocationMatch>'; \
      echo '<LocationMatch "^/plantillas/">'; \
      echo '    Header set Cache-Control "public, max-age=86400"'; \
      echo '</LocationMatch>'; \
    } > /etc/apache2/conf-available/sica-velocidad.conf \
    && a2enconf sica-velocidad

# --- Que aguante muchos escaneos a la vez ---
# Apache reparte el trabajo entre un número fijo de procesos. Muchos procesos gastan toda la memoria del servidor y lo tumban;
# pocos y reutilizados (KeepAlive) atienden más gente al mismo tiempo porque cada petición del sistema dura milésimas de segundo.
# Si subes a un plan con más memoria, aumenta MaxRequestWorkers (aprox. 1 por cada 40 MB libres).
RUN { \
      echo 'ServerName localhost'; \
      echo 'Timeout 60'; \
      echo 'KeepAlive On'; \
      echo 'KeepAliveTimeout 5'; \
      echo 'MaxKeepAliveRequests 200'; \
      echo '<IfModule mpm_prefork_module>'; \
      echo '    StartServers 5'; \
      echo '    MinSpareServers 5'; \
      echo '    MaxSpareServers 10'; \
      echo '    ServerLimit 20'; \
      echo '    MaxRequestWorkers 20'; \
      echo '    MaxConnectionsPerChild 2000'; \
      echo '</IfModule>'; \
    } > /etc/apache2/conf-available/sica-rendimiento.conf \
    && a2enconf sica-rendimiento

# Indica que el sitio se muestra desde la carpeta "public"
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Copia el proyecto e instala lo que necesita
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --optimize-autoloader \
    && (php artisan view:cache || true) \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80
# Al encender el servidor: prepara todo, crea las tablas y las cuentas que falten, y abre el sistema
# (las vistas ya se prepararon al construir; aquí solo lo que depende de las variables de Render)
CMD ["sh", "-c", "php artisan config:cache && php artisan migrate --force && php artisan sica:crear-usuarios && chown -R www-data:www-data storage bootstrap/cache && exec apache2-foreground"]
