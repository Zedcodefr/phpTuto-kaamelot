<?php

declare(strict_types=1);

namespace App\Database;

final class Connection
{
    /**
     * PDO ≈ ADO.NET (SqlConnection + SqlCommand) : une API unique pour toutes les bases.
     */
    public static function fromEnvironment(): \PDO
    {
        // getenv() renvoie false si la variable est absente. Un DSN vide n'a pas de sens (`?:` suffit),
        // mais un mot de passe vide est légitime : on ne teste que `false`.
        $user = getenv('DB_USER');
        $password = getenv('DB_PASSWORD');
        $pdo = new \PDO(
            dsn: getenv('DB_DSN') ?: throw new \RuntimeException('DB_DSN is not defined'),
            username: $user === false ? null : $user,
            password: $password === false ? null : $password,
        );

        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);       // erreurs SQL => exceptions
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);  // lignes => tableaux associatifs
        $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);                // vraies requêtes préparées

        // Les dates sont stockées en UTC, on les relit dans le fuseau de l'application
        // SET TIME ZONE n'accepte pas de paramètre : set_config() permet une requête préparée, sans concaténation.
        $pdo->prepare("SELECT set_config('TimeZone', :tz, false)")->execute(['tz' => date_default_timezone_get()]);

        return $pdo;
    }
}
