<?php

declare(strict_types=1);

namespace App\Http;

/**
 * 🅰️ LOT A — Routeur : associe (méthode HTTP, motif d'URL) à un "handler".
 *
 * Contrat d'un handler : function (Request $request, string ...$params): JsonResponse
 * Les paramètres {xxx} du motif sont passés en ARGUMENTS NOMMÉS :
 *   $router->get('/api/tasks/{id}', $controller->show(...));
 *   GET /api/tasks/42  →  $controller->show($request, id: '42')
 * Les paramètres de route ne contiennent que des chiffres.
 */
final class Router
{
    public function get(string $pattern, callable $handler): void
    {
        throw new \LogicException('TODO Lot A : Router::get()');
    }

    public function post(string $pattern, callable $handler): void
    {
        throw new \LogicException('TODO Lot A : Router::post()');
    }

    public function patch(string $pattern, callable $handler): void
    {
        throw new \LogicException('TODO Lot A : Router::patch()');
    }

    public function delete(string $pattern, callable $handler): void
    {
        throw new \LogicException('TODO Lot A : Router::delete()');
    }

    /**
     * Exécute le handler de la première route qui correspond.
     *
     * @throws HttpException 404 si aucun motif ne correspond au chemin,
     *                       405 si le chemin existe mais pas pour cette méthode
     */
    public function dispatch(Request $request): JsonResponse
    {
        throw new \LogicException('TODO Lot A : Router::dispatch()');
    }
}
