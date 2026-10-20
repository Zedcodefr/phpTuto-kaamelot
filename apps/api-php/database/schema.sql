-- Schéma de la base de l'API PHP pur (taskboard_php).
-- Rejouable à volonté : make php-db-reset
DROP TABLE IF EXISTS task;

CREATE TABLE task (
    id          SERIAL PRIMARY KEY,
    title       VARCHAR(255) NOT NULL,
    description TEXT NULL,
    status      VARCHAR(20)  NOT NULL DEFAULT 'todo' CHECK (status IN ('todo', 'in_progress', 'done')),
    created_at  TIMESTAMP(0) WITH TIME ZONE NOT NULL DEFAULT NOW()
);

INSERT INTO task (title, description, status) VALUES
    ('Installer l''environnement', 'make install puis make up', 'done'),
    ('Découvrir la syntaxe PHP', 'Variables, tableaux, classes, enums...', 'in_progress'),
    ('Écrire l''API en PHP pur', 'Router, PDO, JSON', 'todo'),
    ('Migrer l''API vers Symfony', NULL, 'todo');
