<?php

/**
 * Front controller : TOUTES les requêtes HTTP arrivent ici.
 *
 * Rappel du modèle d'exécution PHP ("share nothing") : ce script est exécuté
 * de zéro à CHAQUE requête, puis tout est détruit. Pas d'état en mémoire
 * entre deux requêtes, pas de singleton qui survit, pas de Program.cs qui tourne en continu.
 */

declare(strict_types=1);

use App\Controller\TaskController;
use App\Database\Connection;
use App\Http\HttpException;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Http\Router;
use App\Repository\TaskRepository;

require dirname(__DIR__) . '/vendor/autoload.php';

// Le serveur intégré "php -S" sert directement les fichiers statiques s'ils existent.
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

$request = Request::fromGlobals();

try {
    // "Conteneur d'injection de dépendances" fait main : on câble les objets nous-mêmes.
    $pdo = Connection::fromEnvironment();
    $taskController = new TaskController(new TaskRepository($pdo));

    $router = new Router();
    $router->get('/api/health', fn (): JsonResponse => new JsonResponse(['status' => 'ok']));
    $router->get('/api/tasks', $taskController->list(...));          // "first-class callable" (PHP 8.1)
    $router->get('/api/tasks/{id}', $taskController->show(...));
    $router->post('/api/tasks', $taskController->create(...));
    $router->patch('/api/tasks/{id}', $taskController->update(...));
    $router->delete('/api/tasks/{id}', $taskController->delete(...));

    $response = $router->dispatch($request);
} catch (HttpException $e) {
    $response = $e->toResponse();
} catch (\Throwable $e) {
    error_log((string) $e); // visible dans `make logs s=api-php`
    $response = new JsonResponse(['title' => 'Internal Server Error', 'status' => 500, 'detail' => $e->getMessage()], 500);
}

$response->send();
