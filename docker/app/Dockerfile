# Image de l'application : PHP 8.4 servi par FrankenPHP (Caddy intégré).
# Contrairement à `php artisan serve`, il traite plusieurs requêtes à la fois.
FROM dunglas/frankenphp:1-php8.4

RUN install-php-extensions pdo_pgsql pgsql gd zip intl bcmath pcntl exif opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/app/php.ini $PHP_INI_DIR/conf.d/naneu.ini

# HTTP simple sur le port 80 du conteneur (pas de certificat en local).
ENV SERVER_NAME=:80

WORKDIR /app

# Dépendances installées dans l'image : le volume « vendor » en hérite au
# premier démarrage, ce qui évite de les lire depuis le disque Windows.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --prefer-dist \
    && rm -rf /root/.composer/cache \
    && mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions storage/framework/testing
