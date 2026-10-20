<?php

declare(strict_types=1);

namespace App\Database;

/**
 * 🅱️ LOT B — Création de la connexion PDO (≈ SqlConnection en ADO.NET).
 */
final class Connection
{
    /**
     * Crée un PDO à partir des variables d'environnement DB_DSN, DB_USER, DB_PASSWORD
     * (définies dans compose.yaml, à lire avec getenv()).
     *
     * Attentes :
     * - les erreurs SQL lèvent des exceptions (PDO::ERRMODE_EXCEPTION)
     * - les lignes sont renvoyées en tableaux associatifs (PDO::FETCH_ASSOC)
     * - vraies requêtes préparées (PDO::ATTR_EMULATE_PREPARES à false)
     * - fuseau de la session SQL = date_default_timezone_get() (sinon les dates reviennent en UTC)
     */
    public static function fromEnvironment(): \PDO
    {
        throw new \LogicException('TODO Lot B : Connection::fromEnvironment()');
    }
}
