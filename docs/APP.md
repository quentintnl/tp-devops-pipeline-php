# APP.md — documentation technique interne

> Doc **interne** (structure du code, contrat des interfaces, comment lancer les tests).
> Le `README.md` (cahier des charges pédagogique) est rédigé par l'agent doc — ne pas le confondre.

## 1. Ce que fait l'app

Mini livre d'or (« Guestbook ») rendu **PHP server-side**, qui prouve **simultanément** la base de
données **et** le cache :

- **MariaDB (PDO)** : persiste les messages (`author`, `body`, `created_at`).
- **Redis (predis)** : compteur de vues de `/` (INCR à chaque GET) + cache best-effort de la liste
  des 5 derniers messages (TTL court, invalidé à chaque POST).

### Routes

| Route | Méthode | Rôle | Dépendances |
|---|---|---|---|
| `/` | GET | compteur (Redis INCR) + formulaire + liste (cache→DB) | Redis + DB |
| `/` | POST | ajoute un message (insert DB + invalide cache liste), puis **redirige 303** (PRG) | Redis + DB |
| `/health` | GET | liveness → `{"status":"ok"}`, **200**, aucune dépendance | aucune |
| `/health/ready` | GET | readiness → ping DB + ping Redis, **200** si les deux OK sinon **503** | Redis + DB |

## 2. Architecture du code (logique pure ↔ I/O)

Principe : la **logique métier** ne dépend que d'**interfaces**, jamais des implémentations PDO/Redis.
Résultat : PHPUnit reste **vert sans aucun service Docker** (les tests branchent des fakes in-memory).

```
src/
├── Support/
│   ├── Config.php                  # lecture env (getenv), fail-fast si var requise manquante
│   └── MissingConfigException.php
├── Guestbook/
│   ├── Message.php                 # value object immuable + validation (Fail Fast)
│   ├── InvalidMessageException.php
│   ├── MessageRepositoryInterface.php   # contrat persistance (add / latest / ping)
│   ├── PdoMessageRepository.php          # impl MariaDB (PDO, requêtes préparées)
│   └── GuestbookService.php              # CŒUR testable : recordView / recentMessages / addMessage
├── Cache/
│   ├── CacheInterface.php          # contrat cache (increment / get / set / delete / ping)
│   └── RedisCache.php              # impl predis (valeurs non scalaires en JSON)
├── Health/
│   └── HealthChecker.php           # liveness() + readiness(repo, cache)
└── View/
    └── View.php                    # rendu HTML, échappement systématique (htmlspecialchars)

public/
└── index.php                       # front controller : routing + bootstrap PDO/predis + PRG

tests/
├── Fakes/                          # InMemoryMessageRepository, InMemoryCache, ThrowingCache
├── Guestbook/                      # MessageTest, GuestbookServiceTest
├── Health/                         # HealthCheckerTest
└── View/                           # ViewTest
```

### Contrat des interfaces

**`MessageRepositoryInterface`**
- `add(Message $message): void` — persiste (impl PDO : INSERT préparé).
- `latest(int $limit): list<Message>` — les N derniers, **plus récent d'abord**.
- `ping(): bool` — sonde readiness DB.

**`CacheInterface`**
- `increment(string $key): int` — INCR atomique, renvoie la nouvelle valeur.
- `get(string $key): mixed` — `null` si miss.
- `set(string $key, mixed $value, int $ttlSeconds): void`.
- `delete(string $key): void` — invalidation.
- `ping(): bool` — sonde readiness cache.

### `GuestbookService` (le cœur)

- `recordView(): int` → `cache->increment('guestbook:views')`.
- `recentMessages(): list<Message>` → lit `guestbook:recent` ; **miss** ⇒ `repo->latest(5)` puis
  peuple le cache (TTL 30 s).
- `addMessage(string $author, string $body): Message` → `Message::create()` (valide) ⇒ `repo->add()`
  ⇒ `cache->delete('guestbook:recent')` (invalidation).

## 3. Validation & sécurité

- **Fail Fast** : `Message::create()` *trim* puis refuse (exception typée
  `InvalidMessageException`) un auteur/texte vide ou trop long (`MAX_AUTHOR_LENGTH=60`,
  `MAX_BODY_LENGTH=1000`). On **refuse**, on ne tronque pas. `Config::require()` lève si une var
  d'env obligatoire manque.
- **Anti-XSS** : `View::escape()` (= `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`)
  échappe **toute** sortie utilisateur. Couvert par `ViewTest`.
- **Anti-injection SQL** : `PdoMessageRepository` n'utilise que des requêtes **préparées**
  (paramétrées), `PDO::ATTR_EMULATE_PREPARES = false`.
- **Secrets** : aucun en dur — tout via l'environnement (`.env`, modelé sur `.env.example`).

## 4. Configuration (variables d'environnement)

Voir `.env.example`. Requises au runtime : `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`
(+ `DB_PORT` défaut 3306), `REDIS_HOST` (+ `REDIS_PORT` défaut 6379). `/health` ne lit aucune de ces
variables (liveness sans dépendance).

## 5. Lancer les tests (sans aucun service Docker)

```bash
composer install            # génère vendor/ (ignoré par git) + composer.lock (committé)
composer test               # = phpunit
```

PHP/Composer absents de la machine ? Via Docker :

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 install --no-interaction --no-progress
docker run --rm -v "$PWD":/app -w /app composer:2 test
```

Les tests ne font **aucune** I/O réseau (fakes in-memory) → verts en CI sans MariaDB ni Redis.

## 6. Frontières (hors périmètre backend)

`Dockerfile`, `docker-compose*.yml`, `docker/` (config Nginx), `.github/workflows/` et `README.md`
sont produits par **devops** / **doc** — pas par le backend.
