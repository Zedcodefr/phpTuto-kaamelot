<?php

/**
 * Front controller : TOUTES les requêtes HTTP arrivent dans ce fichier
 * (le serveur web est configuré pour ça). C'est votre "Program.cs".
 *
 * ⚠️ Différence majeure avec .NET : ce script est exécuté de zéro à CHAQUE requête,
 * puis tout est détruit (modèle "share nothing"). Rien ne reste en mémoire entre deux requêtes.
 *
 * Point de départ de la partie 1 : voir docs/01-php/04-exercices-api-php-pur.md
 * Les classes de src/ sont des squelettes répartis en 3 lots (🅰️ 🅱️ 🅲) : make php-check-a / -b / -c
 */

declare(strict_types=1); // Active le typage strict pour ce fichier (à mettre partout !)

// Autoloader généré par Composer : charge automatiquement les classes de src/ (namespace App\)
require dirname(__DIR__) . '/vendor/autoload.php';

// Les "superglobales" $_SERVER, $_GET, $_POST... contiennent la requête HTTP courante
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Les en-têtes doivent être envoyés AVANT le moindre echo
header('Content-Type: application/json; charset=utf-8');

if ($method === 'GET' && $path === '/api/health') {
    echo json_encode(['status' => 'ok']);

    return;
}

// TODO Exercice 2 (en mob) : GET /api/tasks avec un tableau en dur
// TODO Intégration (Lot A) : remplacer cette suite de "if" par les classes de src/
//      (Router, TaskController, TaskRepository...) une fois les 3 lots terminés

http_response_code(404);
echo json_encode([
    'title' => 'Not Found',
    'status' => 404,
    'detail' => "No route for $method $path",
]);
