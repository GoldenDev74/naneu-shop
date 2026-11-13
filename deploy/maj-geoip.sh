#!/bin/sh
# Met à jour la base de géolocalisation (pays d'une adresse IP) utilisée par
# Naneu Shop (accès réservé au Togo) et par Grafana Alloy (carte des visiteurs).
#
# Source : DB-IP « IP to Country Lite » — gratuite, sans compte, format .mmdb,
# publiée chaque mois. Licence CC BY 4.0 : « IP Geolocation by DB-IP »
# (https://db-ip.com), mention reprise dans le Journal de sécurité.
#
# Installation (une fois, en root) :
#   install -m 755 maj-geoip.sh /usr/local/sbin/naneu-maj-geoip
#   /usr/local/sbin/naneu-maj-geoip
#   echo '15 4 3 * * root /usr/local/sbin/naneu-maj-geoip' > /etc/cron.d/naneu-geoip
#
# Suivi : journalctl -t naneu-geoip

set -eu

DOSSIER="${NANEU_GEOIP_DOSSIER:-/var/lib/naneu-geoip}"
FICHIER="$DOSSIER/pays.mmdb"

journal() { logger -t naneu-geoip "$1"; echo "$1"; }

mkdir -p "$DOSSIER"
temporaire="$(mktemp "$DOSSIER/.telechargement.XXXXXX")"
trap 'rm -f "$temporaire" "$temporaire.gz"' EXIT

# La base du mois n'est publiée qu'en début de mois : on essaie le mois
# courant, puis le précédent.
for mois in "$(date +%Y-%m)" "$(date -d "$(date +%Y-%m-01) -1 day" +%Y-%m)"; do
    url="https://download.db-ip.com/free/dbip-country-lite-$mois.mmdb.gz"
    if curl -fsSL --retry 3 --max-time 120 -o "$temporaire.gz" "$url"; then
        gunzip -c "$temporaire.gz" > "$temporaire"
        # Un fichier .mmdb valide se termine par les métadonnées MaxMind.
        if [ "$(stat -c %s "$temporaire")" -gt 1000000 ] && grep -aq 'MaxMind.com' "$temporaire"; then
            chmod 644 "$temporaire"
            mv -f "$temporaire" "$FICHIER"
            journal "Base de géolocalisation à jour ($mois) : $FICHIER"
            exit 0
        fi
        journal "Fichier téléchargé invalide pour $mois"
    fi
done

journal "ÉCHEC : impossible de télécharger la base de géolocalisation (l'ancienne reste en place)"
exit 1
