# Prise en main — Naneu Shop

Ce guide te permet d'installer l'application sur ton poste et de la tester, profil par profil,
en une heure environ. Aucune connaissance de Laravel n'est nécessaire.

> **Sommaire** · [1. Prérequis](#1-prérequis) · [2. Installation](#2-installation) · [3. Première connexion](#3-première-connexion)
> · [4. Parcours de test](#4-parcours-de-test) · [5. Signaler un problème](#5-signaler-un-problème) · [6. Dépannage](#6-dépannage)

---

## 1. Prérequis

| Outil | Version | Vérification |
|---|---|---|
| [Docker Desktop](https://www.docker.com/products/docker-desktop/) | récent, **démarré** | `docker compose version` |
| [Git](https://git-scm.com/downloads) | 2.x | `git --version` |
| [Node.js](https://nodejs.org/) | 20 ou plus | `node -v` |

Prévois environ 3 Go d'espace disque pour les images Docker. Les ports **8088, 8080, 5050, 5433, 9000 et 9001**
doivent être libres sur ton poste.

---

## 2. Installation

### 2.1 Récupérer le projet

```bash
git clone https://github.com/GoldenDev74/naneu-shop.git
cd naneu-shop
```

### 2.2 Créer le fichier de configuration

```bash
cp .env.example .env
```

Ouvre `.env` et **remplis les lignes vides** ci-dessous. Les valeurs sont libres : ce sont les mots de passe
des services de ton installation locale.

```dotenv
DB_PASSWORD=choisis-un-mot-de-passe
PGADMIN_PASSWORD=choisis-un-mot-de-passe

REVERB_APP_ID=123456
REVERB_APP_KEY=        # 32 caractères aléatoires
REVERB_APP_SECRET=     # 32 caractères aléatoires

MINIO_ACCESS_KEY_ID=naneu
MINIO_SECRET_ACCESS_KEY=   # 8 caractères minimum
```

Pour générer des valeurs aléatoires de 32 caractères :

```bash
# macOS / Linux / Git Bash
openssl rand -hex 16
```

```powershell
# Windows PowerShell
-join ((48..57) + (97..102) | Get-Random -Count 32 | ForEach-Object { [char]$_ })
```

Laisse `APP_KEY` vide : elle est générée à l'étape suivante.

### 2.3 Démarrer les services

```bash
docker compose up -d --build
```

Le premier démarrage télécharge et construit les images (5 à 10 minutes). Vérifie que tout tourne :

```bash
docker compose ps
```

Les services `app`, `queue`, `scheduler`, `reverb`, `db`, `pgadmin` et `minio` doivent être à l'état *running*
(`minio-init` s'arrête normalement après avoir créé le compartiment de fichiers).

### 2.4 Initialiser l'application

```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed --seeder=DemoSeeder
```

`DemoSeeder` crée les paramètres, les unités, les **4 comptes de démonstration** et un **catalogue
d'une quarantaine de produits** (catégories, fournisseurs togolais et gabonais). Le stock est vide au départ.

### 2.5 Compiler l'interface

```bash
npm install
npm run build
```

### 2.6 Ouvrir l'application

👉 **http://localhost:8088**

---

## 3. Première connexion

On se connecte avec **l'e-mail ou le nom d'utilisateur**. Mot de passe commun : `NaneuShop2026!`

| Identifiant | Profil | À quoi il sert |
|---|---|---|
| `admin` | Administrateur | Tout, y compris les comptes administrateurs |
| `gerant` | Gérant | Tout : stock, prix, rapports, comptabilité, comptes des employés |
| `magasinier` | Gestion de stock | Entrées, sorties, inventaires… sans rapports ni comptabilité |
| `caisse` | Vente (caisse) | La caisse ; le reste en lecture seule |

> 💡 Ouvre une **fenêtre de navigation privée** pour un second profil : tu peux ainsi être connecté en
> gérant et en caisse en même temps et voir les notifications arriver en direct.

La langue (FR / EN) se change en haut à droite, le thème clair / sombre aussi.

---

## 4. Parcours de test

Coche chaque étape. Si un résultat ne correspond pas à ce qui est attendu, note-le (voir [section 5](#5-signaler-un-problème)).

### A. Approvisionner le stock — profil `gerant`

- [ ] **Entrées → Nouvelle entrée**. Choisis un fournisseur, scanne ou cherche 3 produits, saisis quantités et
      prix d'achat, puis enregistre.
      *Attendu :* le bon reçoit un numéro `ENT-2026-xxxx`, chaque ligne crée un lot.
- [ ] Refais une entrée **du même produit à un autre prix d'achat** (utile pour le test FIFO).
- [ ] Essaie un **prix d'achat à 0** ou une **date de péremption antérieure à la réception**.
      *Attendu :* refus avec un message clair.
- [ ] **Lots** : les deux lots du produit apparaissent, dans l'ordre où ils seront consommés.

### B. Vendre — profil `caisse`

- [ ] Connecte-toi en `caisse`. *Attendu :* arrivée directe sur la **Caisse**, menu réduit.
- [ ] Ajoute des produits (clic sur une vignette ou recherche), saisis le montant remis, **Encaisser**.
      *Attendu :* monnaie à rendre correcte, ticket imprimable et PDF.
- [ ] Ouvre **Produits**, **Catégories**, **Fournisseurs**, **Entrées**, **Sorties**.
      *Attendu :* consultation possible, **aucun bouton** de création, modification ou suppression.
- [ ] Tape directement `http://localhost:8088/produits/nouveau` dans la barre d'adresse.
      *Attendu :* page **403 « Accès en lecture seule »**.

### C. Le FIFO et la marge — profil `gerant`

- [ ] **Sorties** : ouvre la vente que tu viens de faire. *Attendu :* le coût vient du **lot le plus ancien**
      d'abord ; la marge = prix encaissé − coût réel des lots.
- [ ] **Sorties → Nouvelle sortie**, motif *Vente*, modifie un prix de vente à la main.
      *Attendu :* le prix catalogue s'affiche, un **motif** est exigé dans la note.
- [ ] Même chose en `magasinier` avec une remise de 30 %. *Attendu :* refus (au-delà du plafond de 10 %).

### D. Péremptions et alertes — profil `gerant`

- [ ] Fais une entrée d'un produit périssable avec une **date de péremption proche** (dans 3 jours).
      *Attendu :* une alerte apparaît (badge **Alertes** dans le menu, cloche).
- [ ] **Péremptions** : le tableau propose une décision (rythme de vente, invendus prévus).
- [ ] **Alertes → Marquer traité** : le badge du menu diminue (il ne compte que les alertes non traitées).

### E. Inventaire — profil `magasinier` puis `gerant`

- [ ] **Inventaires → Lancer un inventaire** (partiel, sur une catégorie), saisis des quantités comptées
      différentes du stock théorique.
- [ ] En `gerant`, **valide** l'inventaire. *Attendu :* le stock est ajusté, l'écart est valorisé au coût des lots.

### F. Comptes et droits — profil `gerant`

- [ ] **Utilisateurs et accès → Nouveau compte**, profil *Vente*. Laisse le nom d'utilisateur vide.
      *Attendu :* un identifiant est proposé, le mot de passe provisoire s'affiche **une seule fois**.
- [ ] Dans la grille **Menus et droits**, passe *Produits* en **Complet** et *Sorties* en **Aucun**, enregistre.
      Connecte-toi avec ce compte (navigation privée). *Attendu :* changement du mot de passe imposé, puis
      les menus suivent exactement la grille.
- [ ] Tape 5 fois un mauvais mot de passe sur un compte. *Attendu :* compte **verrouillé** 15 minutes,
      notification au gérant, bouton **Déverrouiller** dans Utilisateurs.

### G. Tableaux et confort

- [ ] Dans n'importe quelle liste, clique sur l'**entonnoir** d'un en-tête de colonne : texte (`creme` trouve
      « Crème »), liste, date, nombre (`>2000`, `100-500`).
- [ ] **Paramètres** : change la devise, le plafond de remise, le délai de déconnexion après inactivité.
- [ ] Bascule la langue en **EN** : toute l'interface passe en anglais.
- [ ] Va sur une adresse inexistante (`/nimporte-quoi`). *Attendu :* page 404 aux couleurs de la boutique.

---

## 5. Signaler un problème

Ouvre une *issue* sur le dépôt GitHub (onglet **Issues → New issue**) avec :

1. **Profil utilisé** (gerant, caisse…) et **écran** concerné ;
2. **Étapes** pour reproduire ;
3. **Résultat attendu** et **résultat obtenu** ;
4. une **capture d'écran** si possible.

En cas d'erreur 500, joins la fin du journal :

```bash
docker compose exec app tail -n 50 storage/logs/laravel.log
```

---

## 6. Dépannage

| Symptôme | Solution |
|---|---|
| `port is already allocated` au démarrage | Un autre programme utilise ce port. Arrête-le, ou change le port de gauche dans `docker-compose.yml` (ex. `"8089:80"`). |
| Page blanche ou sans mise en forme | `npm run build` n'a pas été lancé, ou a échoué. Relance-le. |
| *No application encryption key* | `docker compose exec app php artisan key:generate` |
| Erreur de connexion à la base | Vérifie `DB_PASSWORD` dans `.env`, puis `docker compose restart`. Si tu as changé le mot de passe après le premier démarrage : `docker compose down -v` (⚠️ efface les données) puis reprends l'étape 2.3. |
| Photos de produits absentes | Vérifie `MINIO_ACCESS_KEY_ID` / `MINIO_SECRET_ACCESS_KEY` ; **Paramètres → Tester la connexion** MinIO. |
| La cloche ne bouge pas en direct | Vérifie que `reverb` et `queue` tournent (`docker compose ps`). La cloche se rafraîchit aussi toute seule chaque minute. |
| Déconnecté tout seul | Normal après 30 minutes d'inactivité (réglable dans Paramètres). |
| Repartir de zéro | `docker compose exec app php artisan migrate:fresh --seed --seeder=DemoSeeder` (⚠️ efface les données). |

Arrêter l'application : `docker compose stop` · la relancer : `docker compose start`.

---

Bon test ! 🛒
