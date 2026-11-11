#!/bin/sh
# Point d'entrée de l'image de production.
#
# La configuration dépend du .env fourni au conteneur : elle ne peut donc être
# mise en cache qu'au démarrage, pas à la construction de l'image.
set -e

php artisan optimize --no-interaction

# Passe la main au point d'entrée de FrankenPHP (serveur web ou commande artisan).
exec docker-php-entrypoint "$@"
