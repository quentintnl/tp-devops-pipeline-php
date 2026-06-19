# TP DevOps — Pipeline CI/CD pour une app PHP multi-services 🚀

> **M1 Développeur fullstack — MDS Nantes**
> Vous partez de ce dépôt via **« Use this template »** (bouton vert en haut du repo GitHub). Vous
> obtenez **votre propre copie** de la branche `main` : l'application tourne déjà, **le pipeline est
> à construire — c'est vous qui l'écrivez.**

Ce dépôt contient une petite application PHP (un **livre d'or** avec compteur de vues) qui fonctionne
en local sur **4 services Docker**. Votre mission n'est **pas** de coder l'app : c'est de bâtir la
**chaîne CI/CD** qui la teste, la scanne, la build, la publie et la **déploie automatiquement** sur
**votre** sous-domaine.

> 🎯 **Ce README est un cahier des charges, pas un tutoriel.** Il vous donne des **objectifs**, des
> **critères de réussite** et des **pistes**. Il ne vous donne **pas** la solution toute faite :
> chercher, choisir vos outils et comprendre vos choix fait partie de l'exercice.

---

## 1. Le scénario — votre mission 🎬

Imaginez : vous venez d'être embauché·e dans une équipe. On vous tend une application PHP qui
**marche en local** mais qui se déploie encore « à la main ». On vous confie une tâche claire :

> **Industrialiser le cycle de vie de cette app.** À chaque `git push`, le code doit être testé,
> scanné pour les vulnérabilités, transformé en image Docker, publié sur un registry, puis **déployé
> tout seul** sur le serveur — et l'application doit rester joignable sur son URL publique.

Concrètement, à la fin du TP :

- vous avez écrit un **workflow GitHub Actions** ;
- chaque push déclenche : **tests → scan sécurité (gate) → build → push image → déploiement** ;
- l'app est en ligne sur `https://<votre-sous-domaine>.prenom.nom.mds-nantes.fr`, routée par
  **Nginx Proxy Manager** (déjà installé sur votre VPS) ;
- **aucun secret** n'est écrit en clair dans le dépôt.

---

## 2. Ce qui vous est fourni ✅

Tout ce qui suit est **déjà dans `main`** et **fonctionne** — ne le réécrivez pas, appuyez-vous
dessus :

| Fourni | Quoi |
|---|---|
| **L'application** | Livre d'or PHP 8.3 rendu côté serveur : compteur de vues + formulaire + liste des messages |
| **Les 4 services** | `web` (Nginx), `app` (PHP-FPM), `db` (MariaDB), `cache` (Redis) |
| **Les tests** | Une suite **PHPUnit** verte (logique métier, sans I/O réseau) |
| **Le compose de dev** | `docker-compose.yml` (build local, 2 réseaux isolés) |
| **Le Dockerfile** | Image de l'app `app` (PHP-FPM, multi-stage, non-root, healthcheck) |
| **La config & la doc** | `.env.example`, `db/init.sql`, config Nginx, et `docs/APP.md` (doc technique de l'app) |

> 📖 Vous voulez comprendre **comment l'app est faite** (architecture du code, contrat des
> interfaces) ? Lisez [`docs/APP.md`](docs/APP.md). Pour le TP, vous n'avez pas besoin de modifier
> le PHP.

**Ce qui n'est PAS fourni (c'est votre travail) :** le workflow CI/CD, le compose de production et
le script de déploiement. Le dossier `.github/workflows/` n'existe pas encore — à vous de le créer.

---

## 3. Démarrer en local 🐳

Prérequis : **Docker** + **Docker Compose v2**. (PHP/Composer en local sont **optionnels**, voir
plus bas.)

```bash
# 1. Copiez le modèle d'environnement et adaptez les mots de passe
cp .env.example .env

# 2. Lancez la stack (build + démarrage des 4 services)
docker compose up --build
```

Puis ouvrez **http://localhost:8080** (`APP_PORT=8080` dans `.env`). Vous devez voir :

- un compteur **« Page vue N fois »** qui **s'incrémente à chaque rechargement** (preuve que **Redis**
  fonctionne) ;
- un **formulaire** pour signer le livre d'or ;
- la **liste des messages** (preuve que **MariaDB** fonctionne).

### Vérifier les routes de santé

| Route | Attendu |
|---|---|
| `GET /health` | `200` + `{"status":"ok"}` — *liveness* : répond même sans DB/Redis |
| `GET /health/ready` | `200` si DB **et** Redis répondent, sinon `503` — *readiness* |

```bash
curl -s http://localhost:8080/health
curl -i -s http://localhost:8080/health/ready
```

> 💡 Ces deux routes ne sont pas décoratives : `/health/ready` vous servira à **savoir si le
> déploiement a réussi** côté serveur (l'app est-elle vraiment prête après un `up -d` ?).

### Lancer les tests

Si **PHP + Composer** sont installés sur votre machine :

```bash
composer install
composer test          # exécute PHPUnit
```

Si **PHP n'est pas installé** (cas le plus courant), passez par l'image Docker officielle de
Composer :

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 install --no-interaction --no-progress
docker run --rm -v "$PWD":/app -w /app composer:2 test
```

Les tests **ne nécessitent aucun service** (ni MariaDB, ni Redis) : ils restent verts en isolation.
👉 **Cette propriété est précieuse pour votre CI** : réfléchissez à ce qu'elle implique pour l'étape
de tests de votre pipeline.

### 🔬 Exercice d'observation : prouvez l'isolation réseau

Une bonne partie de la note porte sur la **compréhension du réseau** (section 5). Vérifiez par
vous-même que `db` et `cache` sont **bien isolés** :

```bash
# Depuis `web`, essayez de joindre la base et le cache : ça DOIT échouer.
docker compose exec web sh -c "nc -z db 3306"      # ❌ doit échouer
docker compose exec web sh -c "nc -z cache 6379"   # ❌ doit échouer

# Depuis `app` (le seul pont autorisé), ça DOIT réussir.
docker compose exec app sh -c "nc -z db 3306"      # ✅ doit réussir
docker compose exec app sh -c "nc -z cache 6379"   # ✅ doit réussir

# Aucun port db/cache n'est publié sur l'hôte :
docker compose ps                                   # pas de mapping pour db/cache
nc -z localhost 3306                                 # ❌ doit échouer
```

Si ces résultats vous surprennent, relisez la section suivante : c'est exactement la leçon.

---

## 4. Architecture & la leçon réseau 🕸️

L'application est découpée en **4 services** répartis sur **2 réseaux Docker**. Comprendre cette
topologie est un **objectif noté** — et vous devrez la **reproduire à l'identique** dans votre
compose de production.

```
                       Internet / navigateur
                                │
                                ▼
                  ┌──────────────────────────┐
                  │  Nginx Proxy Manager      │   (déjà sur le VPS,
                  │  *.prenom.nom.mds-...fr    │    HORS docker-compose
                  └──────────────┬────────────┘    du repo)
                                 │  HTTP
        ╔════════════════════════▼═════════════════════════╗
        ║  réseau Docker : frontend                         ║
        ║                                                   ║
        ║   ┌─────────────┐   FastCGI    ┌───────────────┐  ║
        ║   │    web      │─────────────▶│      app      │  ║
        ║   │ nginx :80   │   :9000      │  php-fpm :9000 │  ║
        ║   └─────────────┘              └───────┬───────┘  ║
        ║      (port exposé)                     │          ║
        ╚════════════════════════════════════════│══════════╝
                                                 │ (app aussi
        ╔════════════════════════════════════════│  sur backend)
        ║  réseau Docker : backend                ▼          ║
        ║                            ┌────────┐  ┌────────┐  ║
        ║                            │   db   │  │ cache  │  ║
        ║                            │mariadb │  │ redis  │  ║
        ║                            │ :3306  │  │ :6379  │  ║
        ║                            └────────┘  └────────┘  ║
        ║   (AUCUN port exposé sur l'hôte — injoignables     ║
        ║    depuis web ET depuis l'extérieur)               ║
        ╚═══════════════════════════════════════════════════╝

  Légende :
   - web   : sur frontend uniquement  → ne voit PAS db/cache
   - app   : sur frontend + backend   → seul pont autorisé
   - db    : sur backend uniquement   → injoignable de web et de l'hôte
   - cache : sur backend uniquement   → injoignable de web et de l'hôte
```

**Qui parle à qui :**

- `web` (Nginx) reçoit le HTTP et relaie les requêtes PHP à `app` via **FastCGI** sur le réseau
  `frontend`. C'est le **seul service qui publie un port** vers l'extérieur.
- `app` (PHP-FPM) est le **seul service à cheval sur les deux réseaux** : il reçoit le trafic depuis
  `web` (`frontend`) et parle à `db` et `cache` (`backend`). C'est le **pont unique**.
- `db` et `cache` vivent **uniquement** sur `backend`.

**Pourquoi `db` et `cache` ne doivent JAMAIS être sur `frontend` ni exposés sur l'hôte :**

> 🔒 Une base de données ou un cache exposé = une porte d'entrée directe sur vos données. Le réflexe
> « j'ouvre `3306:3306` juste pour debugger » est un **piège classique** : on oublie de le refermer,
> et la base se retrouve accessible depuis Internet. **L'isolation se décrète à la *définition* du
> compose** (réseau + absence de `ports:`), pas après coup — un override Docker **fusionne** les
> ports, il ne peut pas en retirer un.

👉 **À respecter dans votre compose de prod :** même topologie 2 réseaux, `db`/`cache` jamais
exposés, `web` seul à publier un port.

---

## 5. Objectifs pédagogiques 🎓

À la fin de ce TP, vous saurez :

| # | Compétence visée |
|---|---|
| **OP1** | Lire une **architecture multi-services** et comprendre la **segmentation réseau** (qui parle à qui, ce qui doit rester injoignable) |
| **OP2** | Écrire un **workflow GitHub Actions** déclenché sur `push` / `pull request` |
| **OP3** | Intégrer un **scan de sécurité bloquant** (Trivy) comme **gate** de pipeline |
| **OP4** | Automatiser des **tests** (PHPUnit) en CI et **bloquer** la suite sur échec |
| **OP5** | **Builder + publier** une image Docker sur un **registry** (GHCR) |
| **OP6** | **Déployer** sur un VPS distant via **SSH** et router via **reverse-proxy** |
| **OP7** | Gérer des **secrets** proprement (GitHub Actions secrets, `.env.example`, jamais en clair) |
| **OP8** | Raisonner sur l'**enchaînement des gates** d'un pipeline (ordre, fail-fast, conditions) |

---

## 6. Votre mission : le pipeline CI/CD 🛠️

Voici **ce que votre pipeline doit accomplir**. Pour chaque exigence : l'**objectif**, un critère
**« ça doit échouer si… »** (votre garde-fou), et la **compétence** visée. **À vous de choisir** les
actions, l'ordre des jobs et les conditions.

| # | Objectif (ce qui doit être vrai) | ❌ Doit échouer si… | Compétence |
|---|---|---|---|
| **EX1** | Le pipeline se **déclenche automatiquement** sur `push` (au moins `main`) **et** sur `pull request` | aucun déclencheur configuré | OP2 |
| **EX2** | Les **tests PHPUnit** s'exécutent en CI | un test échoue | OP4 |
| **EX3** | Un **scan Trivy** analyse l'image (ou le système de fichiers) et constitue une **gate bloquante** | une vulnérabilité de sévérité **≥ HIGH** est détectée *(seuil à choisir et justifier)* | OP3 |
| **EX4** | L'**image Docker** de l'app est **buildée** | le build Docker échoue | OP5 |
| **EX5** | L'image est **publiée sur GHCR**, **taguée** (au moins `latest` + le SHA du commit, ou un tag de version) | le push est refusé / le package n'est pas créé | OP5 |
| **EX6** | **Déploiement** sur le VPS via **SSH** (`docker compose pull && up -d`), app joignable sur le sous-domaine | l'app n'est pas joignable après deploy | OP6 |
| **EX7** | **Aucun secret en clair** : tout passe par les **GitHub Actions secrets** ; `.env.example` documente les variables | un secret est committé ou codé en dur | OP7 |

### Les règles du jeu (à respecter, OP8)

> ⚠️ Ce sont les **invariants** de votre pipeline. Le QA les vérifiera.

- **Fail-fast :** les **tests** ET le **scan Trivy** doivent **bloquer** avant tout `push` d'image
  ou `deploy`. Pas question de publier/déployer une image qui n'a pas passé les gates.
- **Déploiement réservé à `main` :** une *pull request* ne doit **jamais** déclencher de déploiement
  (elle teste et scanne, c'est tout).
- **L'ordre, c'est à vous de le trouver.** L'enchaînement logique ressemble à
  `tests → build image → scan Trivy [gate] → push GHCR → deploy SSH`, mais **l'ordre exact
  build/scan dépend de votre stratégie** (scanner l'image construite ? scanner le FS avant build ?).
  👉 **Posez-vous la question :** que scannez-vous, à quel moment, et pourquoi ? Justifiez votre
  choix.

> 🧭 *Indice d'architecture, pas une solution :* GitHub Actions permet d'enchaîner et de
> conditionner des jobs (dépendances entre jobs, conditions sur la branche/l'événement). Cherchez
> comment un job peut **dépendre** d'un autre et comment **restreindre** un job à une branche. Le
> reste, c'est votre conception.

---

## 7. Bonus optionnels 🌟

Pour aller plus loin (points supplémentaires, section barème) :

| Bonus | Apport |
|---|---|
| **Attente de readiness post-deploy** | après `up -d`, le pipeline interroge `/health/ready` et échoue si l'app ne devient pas « ready » |
| **Cache de layers Docker** dans Actions | builds plus rapides, moins de minutes consommées |
| **Matrice de versions PHP** (ex. 8.2 / 8.3) pour les tests | montre la valeur d'une matrice CI |
| **Environnements `staging` vs `prod`** par branche | ex. `develop` → un second sous-domaine |
| **Badge de statut** du workflow en haut de ce README | communication / restitution |
| **`concurrency` / cancel-in-progress** | hygiène de pipeline (pas deux deploys en parallèle) |

---

## 8. Déployer sur votre VPS 🌐

Votre VPS dispose **déjà** de Docker et de **Nginx Proxy Manager (NPM)**. NPM **n'est pas** dans le
`docker-compose` du repo — vous ne le gérez pas dans votre compose, vous le **configurez via son
interface web**.

### 8.1 Router votre sous-domaine (Nginx Proxy Manager)

Dans NPM, créez un **Proxy Host** :

- **Domaine :** `<votre-app>.prenom.nom.mds-nantes.fr`
- **Forward** vers l'hôte + le **port HTTP publié par votre service `web`** (le port que votre
  compose de prod expose sur le VPS).
- NPM gère le **TLS** (certificat Let's Encrypt via le wildcard `*.prenom.nom.mds-nantes.fr`).

> 💡 Faites publier le port de `web` sur l'hôte de préférence en `127.0.0.1:<port>` (NPM tourne en
> local sur le VPS) — et **jamais** les ports de `db`/`cache`.

### 8.2 La forme attendue de votre compose de production

Vous aurez besoin d'un **deuxième** fichier compose, distinct de celui de dev. Il **ressemble** au
compose de dev, avec **une différence clé** (à vous de l'écrire — pas de copier-coller fourni) :

- ✅ votre service `app` n'a **plus** de `build:` mais référence **l'image publiée sur GHCR**
  (`image: ghcr.io/<user>/<repo>:<tag>`) — celle que votre CI a poussée. (`web` reste sur l'image
  `nginx` officielle + votre config ; seul `app` change de mode.)
- ✅ `db` et `cache` restent **identiques** : mêmes images **pinnées**, mêmes réseaux, **toujours
  sans port exposé**, avec un **volume nommé persistant** pour `db`.
- ✅ **Même topologie 2 réseaux** `frontend`/`backend` qu'en dev (section 4).
- ✅ Les variables sensibles viennent d'un fichier `.env` **présent sur le serveur** (jamais
  committé), modelé sur `.env.example`.

### 8.3 Les secrets GitHub à créer

Dans **Settings → Secrets and variables → Actions** de votre repo :

| Secret | Rôle |
|---|---|
| `GITHUB_TOKEN` *(natif, rien à créer)* | push/pull de l'image sur GHCR — **préférez-le**, il est auto-fourni et gratuit (pensez à `permissions: packages: write`) |
| `VPS_SSH_HOST` | hôte / IP du VPS |
| `VPS_SSH_USER` | utilisateur SSH du déploiement |
| `VPS_SSH_KEY` | **clé privée SSH** de déploiement |
| `VPS_SSH_PORT` *(optionnel)* | port SSH si différent de 22 |
| `DEPLOY_PATH` *(optionnel)* | chemin du compose de prod sur le VPS |

> 💚 **Tout ça est gratuit.** GHCR + le `GITHUB_TOKEN` natif ne coûtent rien et le package se lie
> automatiquement à votre repo. Gardez votre image **publique** pour simplifier le `pull` côté VPS
> (sinon, prévoyez un login GHCR sur le serveur). Les minutes Actions du tier gratuit suffisent
> largement.

> 🗒️ *Un corrigé complet existe côté formateur* (sur une branche séparée invisible depuis votre
> template). Inutile de le chercher : la valeur du TP est dans **votre** construction.

---

## 9. Barème indicatif 📊

> **Barème indicatif sur /20 — ajustable par le formateur.** L'essentiel des points est sur le
> **pipeline qui marche** et la **sécurité** ; les bonus viennent en plus.

| Bloc | Points | Détail |
|---|---:|---|
| **Pipeline fonctionnel (EX1–EX6)** | **11** | trigger push/PR (1) · tests en CI bloquants (2) · build image (1) · push GHCR tagué (2) · scan Trivy en gate (3) · deploy SSH + app en ligne (2) |
| **Sécurité & secrets (EX7 + isolation réseau)** | **4** | aucun secret committé (2) · `db`/`cache` non exposés en prod, topologie 2 réseaux respectée (2) |
| **Qualité & documentation** | **3** | pipeline lisible / fail-fast respecté / deploy limité à `main` (2) · justification des choix, ex. seuil Trivy (1) |
| **Bonus** | **+3 max** | jusqu'à 3 points au-dessus du socle (readiness, cache layers, matrice, staging, badge, concurrency) |

*(Total socle : /18 + 2 de marge qualité, bonus jusqu'à +3 — le formateur fixe la pondération
finale.)*

---

## 10. Pistes & ressources 🧭

Voici **quoi chercher** (et non la conf clé en main). Lisez les **docs officielles** :

- **GitHub Actions** — la base : *workflow syntax*, *events that trigger workflows*
  (`push`, `pull_request`), *using secrets*, *jobs.<id>.permissions*, dépendances entre jobs
  (`needs`) et conditions (`if`).
- **Build & push d'image** — l'action officielle `docker/build-push-action`.
- **Connexion au registry** — `docker/login-action` (pour GHCR : `ghcr.io` + le `GITHUB_TOKEN`).
- **GHCR** — *Working with the Container registry* (publier/lier un package, image publique/privée).
- **Scan de sécurité (gate)** — l'action **`aquasecurity/trivy-action`** : comment scanner une image
  ou un FS, et surtout comment la **faire échouer** sur une sévérité (cherchez les options de
  *severity* et *exit-code*).
- **Déploiement SSH** — une action de type **`appleboy/ssh-action`** pour exécuter des commandes à
  distance sur le VPS.
- **Docker Compose** — référence des fichiers compose (réseaux, `image:` vs `build:`, volumes
  nommés), et **Nginx Proxy Manager** (création d'un *Proxy Host*).

> 🛡️ **Bonus sécurité — Snyk.** En plus (ou à la place) de Trivy, **Snyk** est un scanner de
> vulnérabilités très utilisé en entreprise. L'intégrer est un **bonus** : il demande la création
> d'un **compte** et d'un **token** (à stocker en secret GitHub). Considérez-le comme un pointeur à
> explorer si vous voulez creuser le sujet « scan de sécu » — l'exemple outillé reste côté formateur.

---

## 11. Livrable & évaluation 📦

**Ce qu'on attend de vous :**

1. Un **pipeline GitHub Actions vert** qui, sur un push `main`, va jusqu'au **déploiement**.
2. L'**application en ligne** et joignable sur votre sous-domaine
   `https://<votre-app>.prenom.nom.mds-nantes.fr`.
3. **Aucun secret committé** dans le dépôt (ni `.env`, ni clé, ni mot de passe).

**Comment rendre :**

- le **lien de votre repo GitHub** (avec le workflow et le compose de prod que vous avez écrits) ;
- l'**URL déployée** de votre application.

**Critères d'évaluation (récapitulatif) :**

- [ ] Le workflow se déclenche sur `push` **et** `pull request` (EX1).
- [ ] Les **tests** s'exécutent et **bloquent** en cas d'échec (EX2).
- [ ] **Trivy** est une **gate bloquante**, avec un seuil **justifié** (EX3).
- [ ] L'**image** est **buildée** (EX4) et **publiée sur GHCR**, taguée (EX5).
- [ ] Le **déploiement SSH** fonctionne et l'app répond sur le sous-domaine (EX6).
- [ ] **Aucun secret** en clair ; usage correct des GitHub secrets (EX7).
- [ ] **Fail-fast** respecté ; **deploy uniquement sur `main`**.
- [ ] En prod : `db`/`cache` **non exposés**, topologie 2 réseaux conservée.

---

## 12. Bonnes pratiques & garde-fous 🧱

- 🔑 **Jamais de secret en clair.** Mots de passe, clés, tokens → **GitHub Actions secrets** + fichier
  `.env` non committé (modelé sur `.env.example`). Le `.gitignore` ignore déjà `.env` : ne le forcez
  pas dans un commit.
- 📌 **Pinnez les versions d'images.** Pas de `:latest` pour les services (`mariadb:11.4`,
  `redis:7-alpine`, `nginx:1.27-alpine`…). Une image `latest` peut sauter une version majeure
  silencieusement au prochain `pull` et casser votre stack.
- 🚪 **N'exposez jamais `db`/`cache`.** Pas de `ports:` pour eux, jamais sur le réseau `frontend`.
  L'isolation se décide à la **définition** du compose.
- 🧪 **Gardez les gates strictes.** Une image qui n'a pas passé les tests **et** le scan ne doit
  **jamais** être publiée ni déployée.
- 🤔 **Sachez justifier vos choix.** Seuil Trivy, ordre des jobs, stratégie de tags : on attend que
  vous **expliquiez** vos décisions, pas seulement qu'elles « marchent ».

---

Bon courage — et souvenez-vous : l'objectif n'est pas seulement d'avoir un pipeline vert, mais de
**comprendre chaque maillon** de la chaîne. 💪
