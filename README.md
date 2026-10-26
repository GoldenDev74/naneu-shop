<div align="center">

<img src="public/images/logo-naneu.png" alt="Naneu Shop" width="260">

# Naneu Shop — Gestion de stock et caisse

Gestion de stock **FIFO par lots**, caisse, péremptions, comptabilité et droits par menu,
pour une alimentation générale de **Lomé (Togo)** spécialisée dans les produits importés du Gabon.

![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)
![Laravel 12](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)
![Livewire 3](https://img.shields.io/badge/Livewire-3-FB70A9?logo=livewire&logoColor=white)
![PostgreSQL 16](https://img.shields.io/badge/PostgreSQL-16-4169E1?logo=postgresql&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&logoColor=white)
![Tests](https://img.shields.io/badge/tests-155%20verts-55BF81)

**[Prise en main (testeurs)](docs/PRISE-EN-MAIN.md)** · [Fonctionnalités](#fonctionnalités) · [Démarrage rapide](#démarrage-rapide) · [Architecture](#architecture) · [Sécurité](#sécurité)

</div>

---

## Fonctionnalités

| Domaine | Ce que fait l'application |
|---|---|
| **Caisse** | Vente au comptoir en espèces, rendu de monnaie, ticket 80 mm (impression et PDF), vignettes rapides pour les produits sans code-barres, scan douchette. |
| **Catalogue** | Produits, catégories arborescentes, unités, fournisseurs locaux et importés, photos (MinIO), code-barres EAN ou code interne. |
| **Entrées** | Bon de réception multi-lignes : chaque ligne crée un **lot** (prix d'achat, date limite). Frais de transport rattachés en charge. |
| **Sorties** | Vente, perte, péremption, casse, ajustement, retour fournisseur, consommation interne. Aperçu FIFO lot par lot avant validation. |
| **Péremptions** | Lots périmés **bloqués à la vente**, tableau d'aide à la décision (rythme de vente, invendus prévus), durée de conservation pour les produits frais sans date. |
| **Inventaire** | Comptage par rayon, écarts valorisés au **coût réel des lots**, ventes pendant le comptage prises en compte. |
| **Comptabilité** | Charges (transport, vignettes, énergie…), marge brute, démarque, bénéfice net. |
| **Rapports** | CA, marge, valorisation, meilleures ventes, réapprovisionnement, exports CSV. |
| **Alertes** | Stock bas, rupture, péremption proche, vente bloquée : ouverture et fermeture automatiques, cloche en temps réel, courriel pour les critiques. |
| **Comptes et droits** | Profils (administrateur, gérant, gestion de stock, vente) et **droits par menu** réglables compte par compte : aucun, lecture seule, complet. |
| **Sécurité** | Connexion par e-mail ou nom d'utilisateur, verrouillage après échecs répétés, déconnexion après inactivité, journal de sécurité. |
| **Tableaux** | Filtre par colonne (texte, liste, date, nombre `>10`, `10-20`), tri, recherche insensible aux accents. |
| **International** | Interface **français / anglais**, devise d'affichage réglable (XOF par défaut). |

### Règles de gestion clés

- **Prix TTC, boutique non assujettie à la TVA** (réglable) : marge = prix encaissé − coût FIFO des lots.
- **Grand livre immuable** : une erreur se corrige par un mouvement inverse, jamais par une modification.
- **Chronologie respectée** : une sortie datée du jour J ne consomme que des lots reçus au plus tard le jour J.
- **Remise plafonnée** : un prix de vente modifié exige un motif ; au-delà du plafond (Paramètres), seule la gérance valide.
- **Numérotation sûre** : `ENT-2026-0001`, `SOR-…`, `INV-…`, `CHG-…` attribués à l'enregistrement, jamais réutilisés.

---

## Démarrage rapide

> Guide pas à pas pour un testeur : **[docs/PRISE-EN-MAIN.md](docs/PRISE-EN-MAIN.md)**.

**Prérequis :** Docker Desktop, Git, Node.js 20+.

```bash
git clone https://github.com/GoldenDev74/naneu-shop.git
cd naneu-shop
cp .env.example .env          # puis renseigner les mots de passe et clés (voir le guide)

docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed --seeder=DemoSeeder

npm install
npm run build
```

Application : **http://localhost:8088**

| Service | Adresse |
|---|---|
| Application | http://localhost:8088 |
| pgAdmin | http://localhost:5050 |
| Console MinIO | http://localhost:9001 (API sur 9000) |
| PostgreSQL | `localhost:5433` |
| WebSocket (Reverb) | `localhost:8080` |

### Comptes de démonstration

Créés par le seeder **hors production uniquement**. Mot de passe commun : `NaneuShop2026!`

| Identifiant | Profil |
|---|---|
| `admin` | Administrateur |
| `gerant` | Gérant |
| `magasinier` | Gestion de stock |
| `caisse` | Vente (caisse) |

> ⚠️ Ces comptes et ce mot de passe sont publics. Ne les utilisez jamais sur une installation accessible depuis Internet :
> en production, créez l'administrateur avec `php artisan naneu:creer-admin`.

---

## Architecture

```
app/
├── Services/StockService.php      Moteur de stock : réceptions, sorties FIFO/FEFO, ajustements, annulations
├── Services/AlertService.php      Cycle de vie des alertes (état, pas évènement)
├── Services/AccountingService.php Marge, démarque, charges, bénéfice net
├── Services/ExpiryAdvisor.php     Aide à la décision sur les lots proches de la péremption
├── Livewire/                      Écrans (un composant par écran)
├── Enums/UserRole.php             Profils et droits par défaut
├── Models/User.php                accessLevel() : source unique des droits par menu
└── Support/                       Money (formatage, devise), ShopSettings, LooseSearch
```

| Couche | Choix |
|---|---|
| Back-end | PHP 8.4, Laravel 12 |
| Interface | Livewire 3, Volt, Alpine.js, Tailwind CSS 3 (thème clair / sombre) |
| Temps réel | Laravel Reverb (WebSocket), files d'attente Laravel |
| Données | PostgreSQL 16 (production) — SQLite en mémoire pour les tests |
| Fichiers | MinIO (compatible S3), liens signés temporaires |
| Serveur | FrankenPHP dans Docker ; services `app`, `queue`, `scheduler`, `reverb`, `db`, `pgadmin`, `minio` |

**Principe directeur :** aucun code n'écrit directement dans les lots, les mouvements ou les quantités en stock.
Tout passe par `StockService`, dans une transaction, ce qui garde le grand livre cohérent avec les lots.

### Droits d'accès

Un seul point de décision, `User::accessLevel($menu)`, alimente le menu latéral, le middleware des routes
(`section:produits,ecriture`), les politiques et l'affichage des boutons. Un utilisateur en **lecture seule**
ne voit pas les boutons de création, modification et suppression, et le serveur refuse ces actions.

| Profil | Par défaut |
|---|---|
| Administrateur, Gérant | Tous les menus, y compris rapports, comptabilité, utilisateurs, paramètres, journal |
| Gestion de stock | Tous les menus d'exploitation en accès complet ; chaque modification est notifiée à la gérance |
| Vente (caisse) | Caisse ; produits, catégories, fournisseurs, entrées et sorties en lecture seule |

---

## Sécurité

- Mots de passe **bcrypt (12 tours)**, 10 caractères minimum, casse mixte, chiffres, rejet des mots de passe divulgués.
- **Pas d'inscription publique** : les comptes sont créés par la gérance.
- **Verrouillage** du compte après des échecs répétés (réglable), gérance notifiée, déverrouillage manuel possible.
- **Déconnexion après inactivité** (réglable), avertissement une minute avant.
- Sessions coupées sur tous les appareils après un changement de mot de passe ou une suspension.
- Mot de passe provisoire : l'application reste verrouillée jusqu'à son remplacement.
- **Journal de sécurité** en ajout seul ; secrets filtrés à l'écriture.
- Sessions chiffrées, en-têtes de durcissement, HTTPS forcé en production, canaux WebSocket privés.
- Pages d'erreur personnalisées (403, 404, 419, 500, 503) qui ne divulguent aucun détail technique.

---

## Tâches planifiées

Assurées par le service `scheduler` (`php artisan schedule:work`).

| Heure | Tâche |
|---|---|
| 07 h 00 | `naneu:verifier-peremptions` |
| 07 h 05 et 13 h 00 | `naneu:verifier-stock` |
| 19 h 00 | `naneu:resume-quotidien` (CA, marge, pertes, alertes) |
| Lundi 06 h 00 | `naneu:recalculer-stock` (contrôle d'intégrité) |

## Commandes utiles

```bash
docker compose exec app php artisan naneu:creer-admin           # Créer un administrateur
docker compose exec app php artisan naneu:sortir-perimes        # Retirer les lots périmés
docker compose exec app php artisan naneu:renotifier-alertes    # Rattraper des notifications
docker compose exec app php artisan naneu:recalculer-stock --fix
```

---

## Tests

```bash
docker compose exec app php artisan test                       # SQLite en mémoire

# Sur PostgreSQL (base à créer une seule fois)
docker compose exec db createdb -U naneu naneu_shop_test
docker compose exec -e DB_CONNECTION=pgsql -e DB_DATABASE=naneu_shop_test app php artisan test
```

**155 tests** couvrent le moteur FIFO (multi-lots, FEFO, chronologie, annulations, immuabilité), les péremptions,
la caisse, la comptabilité, les droits par menu, la sécurité de connexion, les filtres de colonnes,
la traduction et le rendu de tous les écrans.

---

## Mise en production

- `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`
- `APP_URL`, `MINIO_PUBLIC_ENDPOINT` et `REVERB_*` sur l'adresse publique en HTTPS, puis `npm run build`
- `php artisan config:cache route:cache view:cache`
- Remplacer les comptes de démonstration ; n'exposer ni pgAdmin, ni PostgreSQL, ni la console MinIO
- Exposition possible sans ouvrir de port via un tunnel Cloudflare (service `cloudflared`)

---

<div align="center">
<sub>Naneu Shop · <em>Se faire plaisir est un plaisir</em></sub>
</div>
