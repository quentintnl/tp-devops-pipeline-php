<?php

declare(strict_types=1);

/**
 * Front controller du livre d'or.
 *
 * Responsabilités : bootstrap des adaptateurs I/O (PDO + predis) depuis
 * l'environnement, câblage des services, routage minimal des 4 routes.
 * La logique métier vit dans src/ (testable) ; ici on ne fait que de la glue HTTP.
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Cache\CacheInterface;
use App\Cache\RedisCache;
use App\Guestbook\GuestbookService;
use App\Guestbook\InvalidMessageException;
use App\Guestbook\MessageRepositoryInterface;
use App\Guestbook\PdoMessageRepository;
use App\Health\HealthChecker;
use App\Support\Config;
use App\View\View;
use Predis\Client as PredisClient;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');
if ($path === '') {
    $path = '/';
}

$health = new HealthChecker();

// ── Liveness : aucune dépendance, ne touche ni PDO ni Redis (toujours 200). ──
if ($path === '/health' && $method === 'GET') {
    json_response(200, $health->liveness());
}

// ── Fabriques paresseuses : on ne se connecte qu'au moment où on en a besoin. ──
$repositoryFactory = static function (): MessageRepositoryInterface {
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        Config::require('DB_HOST'),
        Config::getInt('DB_PORT', 3306),
        Config::require('DB_NAME'),
    );
    $pdo = new PDO($dsn, Config::require('DB_USER'), Config::require('DB_PASSWORD'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return new PdoMessageRepository($pdo);
};

$cacheFactory = static function (): CacheInterface {
    $client = new PredisClient([
        'scheme' => 'tcp',
        'host' => Config::require('REDIS_HOST'),
        'port' => Config::getInt('REDIS_PORT', 6379),
    ]);

    return new RedisCache($client);
};

// ── Readiness : ping DB + Redis, 200 si les deux OK sinon 503. ──
if ($path === '/health/ready' && $method === 'GET') {
    try {
        $result = $health->readiness($repositoryFactory(), $cacheFactory());
    } catch (\Throwable) {
        // Échec de connexion = non prêt.
        $result = ['ready' => false, 'checks' => ['db' => false, 'redis' => false]];
    }
    json_response($result['ready'] ? 200 : 503, $result);
}

// ── Routes applicatives (nécessitent DB + Redis). ──
if ($path === '/') {
    $service = new GuestbookService($repositoryFactory(), $cacheFactory());

    if ($method === 'POST') {
        try {
            $service->addMessage(
                (string) ($_POST['author'] ?? ''),
                (string) ($_POST['body'] ?? ''),
            );
        } catch (InvalidMessageException) {
            // Entrée invalide : on redirige quand même (PRG), sans persister.
        }
        // Pattern PRG : on redirige après POST pour éviter le re-POST au refresh.
        header('Location: /', true, 303);
        exit;
    }

    if ($method === 'GET') {
        $views = $service->recordView();
        $messages = $service->recentMessages();
        header('Content-Type: text/html; charset=utf-8');
        echo View::home($views, $messages);
        exit;
    }
}

// ── Aucune route ne correspond. ──
json_response(404, ['error' => 'not_found']);

/**
 * Émet une réponse JSON et termine la requête.
 *
 * @param array<string, mixed> $payload
 */
function json_response(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_THROW_ON_ERROR);
    exit;
}
