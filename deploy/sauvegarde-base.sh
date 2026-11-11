#!/bin/sh
# Sauvegarde quotidienne de la base Naneu Shop (production, VPS).
#
# Exporte la base PostgreSQL dans /var/backups/naneu-shop, garde 14 jours.
# Lancé chaque nuit à 00:30 par /etc/cron.d/naneu-shop, avant la sauvegarde
# automatique du disque par OVH (vers 01:40) : celle-ci emporte donc chaque
# nuit une copie cohérente de la base, hors du VPS. Les photos et documents
# (volume Docker storage-app) sont, eux, déjà dans la sauvegarde du disque.
#
# Installation (une fois, en root) :
#   install -m 700 sauvegarde-base.sh /usr/local/sbin/naneu-sauvegarde-base
#   echo '30 0 * * * root /usr/local/sbin/naneu-sauvegarde-base' > /etc/cron.d/naneu-shop
#
# Suivi :      journalctl -t naneu-sauvegarde
# Restaurer :  voir docs/EXPLOITATION.md (« Restaurer la base »).

set -eu

DOSSIER_APP="${NANEU_DOSSIER_APP:-/home/felikoadk/naneu-shop}"
DESTINATION="${NANEU_DESTINATION:-/var/backups/naneu-shop}"
JOURS_A_GARDER="${NANEU_JOURS:-14}"

umask 077
mkdir -p "$DESTINATION"

fichier="$DESTINATION/naneu_shop_$(date +%Y-%m-%d_%H%M).dump"
partiel="$fichier.partiel"

journal() { logger -t naneu-sauvegarde "$1"; echo "$1"; }

# En cas d'échec, ne jamais laisser un fichier incomplet passer pour bon.
trap 'rm -f "$partiel"; journal "ÉCHEC de la sauvegarde de la base"' EXIT

cd "$DOSSIER_APP"
compose() { docker compose -f docker-compose.prod.yml "$@"; }

# Format « custom » : compressé, restaurable table par table avec pg_restore.
compose exec -T db sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --format=custom' > "$partiel"

# Vérifie que l'export est lisible avant de le considérer comme valide.
compose exec -T db pg_restore --list < "$partiel" > /dev/null

mv "$partiel" "$fichier"
trap - EXIT

# Rotation : supprime les exports de plus de JOURS_A_GARDER jours.
find "$DESTINATION" -maxdepth 1 -name 'naneu_shop_*.dump' -mtime +"$JOURS_A_GARDER" -delete

journal "OK $(basename "$fichier") ($(du -h "$fichier" | cut -f1)), $(find "$DESTINATION" -maxdepth 1 -name 'naneu_shop_*.dump' | wc -l) exports conservés"
