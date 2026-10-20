<?php

/**
 * Vérifie qu'une API respecte la partie 1 du contrat (docs/api-contract.md).
 *
 *   make php-check   (API PHP pur)
 *   make sf-check    (API Symfony)
 *
 * Écrit en PHP pur, sans dépendance : n'hésitez pas à le lire, c'est aussi un exemple de code !
 */

declare(strict_types=1);

$baseUrl = rtrim($argv[1] ?? 'http://localhost:8000', '/');
$failures = 0;

// Le script tourne DANS le conteneur api-php : $baseUrl est une adresse du réseau Docker.
// Depuis votre poste (navigateur, curl), la même API se joint via le port publié dans compose.yaml.
$hostUrl = [
    'http://localhost:8000' => 'http://localhost:8081', // make php-check
    'http://api-php:8000' => 'http://localhost:8081',
    'http://nginx-symfony' => 'http://localhost:8082',  // make sf-check
][$baseUrl] ?? null;
$target = $hostUrl === null ? $baseUrl : "$hostUrl \033[2m(depuis le conteneur : $baseUrl)\033[0m";

/**
 * Envoie une requête HTTP et renvoie [code HTTP, corps décodé, en-têtes].
 *
 * @return array{0: int, 1: mixed, 2: array<string, string>}
 */
function call(string $method, string $url, ?array $body = null): array
{
    $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
        'content' => $body === null ? '' : json_encode($body),
        'ignore_errors' => true, // ne pas lever d'erreur sur 4xx/5xx : on veut lire la réponse
        'timeout' => 5,
    ]]);

    $raw = @file_get_contents($url, false, $context);
    if ($raw === false) {
        fwrite(STDERR, "\033[31mImpossible de joindre $url\033[0m\n");
        exit(2);
    }

    // $http_response_header est une variable "magique" remplie par file_get_contents (PHP < 8.5)
    $responseHeaders = function_exists('http_get_last_response_headers')
        ? http_get_last_response_headers()
        : $http_response_header;

    preg_match('#HTTP/\S+ (\d{3})#', $responseHeaders[0], $m);
    $headers = [];
    foreach (array_slice($responseHeaders, 1) as $line) {
        [$name, $value] = array_map(trim(...), explode(':', $line, 2)) + [1 => ''];
        $headers[strtolower($name)] = $value;
    }

    return [(int) $m[1], json_decode($raw, true), $headers];
}

function check(string $label, bool $ok, mixed $debug = null): void
{
    global $failures;
    if ($ok) {
        echo "  \033[32m✔\033[0m $label\n";

        return;
    }
    $failures++;
    echo "  \033[31m✘ $label\033[0m\n";
    if ($debug !== null) {
        echo '      reçu : ' . json_encode($debug, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }
}

function isTask(mixed $t): bool
{
    return is_array($t)
        && is_int($t['id'] ?? null)
        && is_string($t['title'] ?? null)
        && array_key_exists('description', $t)
        && in_array($t['status'] ?? null, ['todo', 'in_progress', 'done'], true)
        && is_string($t['createdAt'] ?? null)
        && DateTimeImmutable::createFromFormat(DATE_ATOM, $t['createdAt']) !== false;
}

echo "\nContrat d'API — partie 1 — $target\n\n";

echo "GET /api/health\n";
[$code, $body] = call('GET', "$baseUrl/api/health");
check('200 + {"status": "ok"}', $code === 200 && $body === ['status' => 'ok'], [$code, $body]);

echo "GET /api/tasks\n";
[$code, $body] = call('GET', "$baseUrl/api/tasks");
check('200 + tableau JSON', $code === 200 && is_array($body) && array_is_list($body), [$code, $body]);
check('chaque élément a la forme d\'une Task', is_array($body) && array_all($body, isTask(...)), $body[0] ?? $body);

echo "POST /api/tasks\n";
[$code, $created, $headers] = call('POST', "$baseUrl/api/tasks", ['title' => 'Tâche de test', 'description' => 'créée par contract-check']);
check('201 + Task', $code === 201 && isTask($created), [$code, $created]);
check('status par défaut = todo', ($created['status'] ?? null) === 'todo', $created);
$id = $created['id'] ?? 0;
check('en-tête Location', ($headers['location'] ?? '') !== '' && str_ends_with($headers['location'], "/api/tasks/$id"), $headers['location'] ?? null);

[$code, $body] = call('POST', "$baseUrl/api/tasks", ['title' => '']);
check('titre vide => 422 + violation sur "title"', $code === 422 && in_array('title', array_column($body['violations'] ?? [], 'propertyPath'), true), [$code, $body]);

[$code, $body] = call('POST', "$baseUrl/api/tasks", ['title' => 'Titre valide', 'status' => 'nimporte']);
check('statut invalide => 422 + violation sur "status"', $code === 422 && in_array('status', array_column($body['violations'] ?? [], 'propertyPath'), true), [$code, $body]);

echo "GET /api/tasks/{id}\n";
[$code, $body] = call('GET', "$baseUrl/api/tasks/$id");
check('200 + la tâche créée', $code === 200 && ($body['title'] ?? null) === 'Tâche de test', [$code, $body]);
[$code, $body] = call('GET', "$baseUrl/api/tasks/999999");
check('id inexistant => 404', $code === 404, [$code, $body]);

echo "PATCH /api/tasks/{id}\n";
[$code, $body] = call('PATCH', "$baseUrl/api/tasks/$id", ['status' => 'done']);
check('200 + statut modifié', $code === 200 && ($body['status'] ?? null) === 'done', [$code, $body]);
check('les autres champs sont conservés', ($body['title'] ?? null) === 'Tâche de test' && ($body['description'] ?? null) === 'créée par contract-check', $body);
[$code, $body] = call('PATCH', "$baseUrl/api/tasks/$id", ['title' => 'ab']);
check('titre trop court => 422', $code === 422, [$code, $body]);
[$code] = call('PATCH', "$baseUrl/api/tasks/999999", ['status' => 'done']);
check('id inexistant => 404', $code === 404, $code);

echo "DELETE /api/tasks/{id}\n";
[$code, $body] = call('DELETE', "$baseUrl/api/tasks/$id");
check('204', $code === 204, [$code, $body]);
[$code] = call('GET', "$baseUrl/api/tasks/$id");
check('la tâche n\'existe plus => 404', $code === 404, $code);
[$code] = call('DELETE', "$baseUrl/api/tasks/$id");
check('suppression d\'un id inexistant => 404', $code === 404, $code);

echo "Divers\n";
[$code, $body] = call('GET', "$baseUrl/api/nexistepas");
check('route inconnue => 404', $code === 404, [$code, $body]);

echo $failures === 0
    ? "\n\033[42;30m TOUT EST VERT \033[0m l'API respecte le contrat 🎉\n\n"
    : "\n\033[41;37m $failures vérification(s) en échec \033[0m\n\n";

exit($failures === 0 ? 0 : 1);
