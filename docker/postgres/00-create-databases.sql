-- Exécuté une seule fois, à la création du volume PostgreSQL.
-- La base "taskboard" (Symfony) est créée par POSTGRES_DB.
-- La base "taskboard_php" sert à l'API en PHP pur.
CREATE DATABASE taskboard_php OWNER app;
