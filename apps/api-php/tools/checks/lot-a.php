<?php

/**
 * 🅰️ Vérifications du LOT A — HTTP & routage.  make php-check-a
 * Indépendant des lots B et C : on utilise des handlers et des données factices.
 */

declare(strict_types=1);

require __DIR__ . '/harness.php';

use App\Http\HttpException;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Http\Router;
use App\Http\ValidationException;

Checks::title('🅰️  Lot A — HTTP & routage');

Checks::section('Request::fromGlobals()');
$fromGlobals = function (string $method, string $uri, array $get = []): Request {
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $uri;
    $_GET = $get;

    return Request::fromGlobals();
};
Checks::check('méthode en majuscules', fn () => $fromGlobals('patch', '/api/tasks')->method === 'PATCH');
Checks::check('chemin sans query string', fn () => $fromGlobals('GET', '/api/tasks?status=todo', ['status' => 'todo'])->path === '/api/tasks');
Checks::check('query = $_GET', fn () => $fromGlobals('GET', '/api/tasks?status=todo', ['status' => 'todo'])->query === ['status' => 'todo']);
Checks::check('"/" final retiré', fn () => $fromGlobals('GET', '/api/tasks/')->path === '/api/tasks');
Checks::check('"/" seul reste "/"', fn () => $fromGlobals('GET', '/')->path === '/');

Checks::section('JsonResponse::send()');
$output = function (JsonResponse $response): string {
    // En ligne de commande, la sortie a déjà commencé : on ignore les avertissements "headers already sent"
    set_error_handler(fn (int $no, string $message): bool => str_contains($message, 'headers already sent'));
    ob_start();
    try {
        $response->send();
    } finally {
        $body = (string) ob_get_clean();
        restore_error_handler();
    }

    return $body;
};
Checks::check('affiche le JSON des données', fn () => $output(new JsonResponse(['a' => 1, 'b' => [true, null]])) === '{"a":1,"b":[true,null]}');
Checks::check('accents et "/" non échappés', fn () => $output(new JsonResponse(['t' => 'Été / hiver'])) === '{"t":"Été / hiver"}');
Checks::check('utilise JsonSerializable', fn () => $output(new JsonResponse(new class implements \JsonSerializable {
    public function jsonSerialize(): array
    {
        return ['ok' => true];
    }
})) === '{"ok":true}');
Checks::check('204 : aucun corps', fn () => $output(new JsonResponse(['ignored' => true], 204)) === '');
Checks::check('(en-têtes et code HTTP : vérifiés par make php-check)', fn () => true);

Checks::section('HttpException / ValidationException');
Checks::check('toResponse() : code = status', fn () => (new HttpException(404, 'Task 3 not found'))->toResponse()->status === 404);
Checks::check('corps 404 conforme au contrat', fn () => (new HttpException(404, 'Task 3 not found'))->toResponse()->data
    === ['title' => 'Not Found', 'status' => 404, 'detail' => 'Task 3 not found']);
Checks::check('titre 400 = Bad Request', fn () => (new HttpException(400, 'x'))->toResponse()->data['title'] === 'Bad Request');
Checks::check('titre 405 = Method Not Allowed', fn () => (new HttpException(405, 'x'))->toResponse()->data['title'] === 'Method Not Allowed');
Checks::check('ValidationException : 422 + violations', function () {
    $response = (new ValidationException(['title' => 'Too short.', 'status' => 'Bad.']))->toResponse();

    return $response->status === 422
        && $response->data['title'] === 'Validation Failed'
        && $response->data['violations'] === [
            ['propertyPath' => 'title', 'title' => 'Too short.'],
            ['propertyPath' => 'status', 'title' => 'Bad.'],
        ];
});

Checks::section('Router');
$router = new Router();
$handler = fn (string $name) => fn (Request $r, string ...$params): JsonResponse => new JsonResponse(['handler' => $name, 'params' => $params]);
$setup = function () use ($router, $handler): void {
    $router->get('/api/health', $handler('health'));
    $router->get('/api/tasks', $handler('list'));
    $router->post('/api/tasks', $handler('create'));
    $router->get('/api/tasks/{id}', $handler('show'));
    $router->patch('/api/tasks/{id}', $handler('update'));
    $router->delete('/api/tasks/{id}', $handler('delete'));
};
Checks::check('enregistrement des routes', function () use ($setup) {
    $setup();

    return true;
});
$dispatch = fn (string $method, string $path): JsonResponse => $router->dispatch(new Request($method, $path));
Checks::check('GET /api/tasks → handler list', fn () => $dispatch('GET', '/api/tasks')->data['handler'] === 'list');
Checks::check('POST /api/tasks → handler create', fn () => $dispatch('POST', '/api/tasks')->data['handler'] === 'create');
Checks::check('GET /api/tasks/42 → show avec id: "42" (argument nommé)', fn () => $dispatch('GET', '/api/tasks/42')->data === ['handler' => 'show', 'params' => ['id' => '42']]);
Checks::check('PATCH et DELETE /api/tasks/7', fn () => $dispatch('PATCH', '/api/tasks/7')->data['handler'] === 'update'
    && $dispatch('DELETE', '/api/tasks/7')->data['handler'] === 'delete');
Checks::check('GET /api/tasks/abc → 404 (id numérique uniquement)', fn () => Checks::catch(HttpException::class, fn () => $dispatch('GET', '/api/tasks/abc'))?->status === 404);
Checks::check('GET /api/tasks/42/extra → 404 (motif complet)', fn () => Checks::catch(HttpException::class, fn () => $dispatch('GET', '/api/tasks/42/extra'))?->status === 404);
Checks::check('route inconnue → HttpException 404', fn () => Checks::catch(HttpException::class, fn () => $dispatch('GET', '/api/nope'))?->status === 404);
Checks::check('PUT /api/tasks/1 → HttpException 405', fn () => Checks::catch(HttpException::class, fn () => $dispatch('PUT', '/api/tasks/1'))?->status === 405);

Checks::end();
