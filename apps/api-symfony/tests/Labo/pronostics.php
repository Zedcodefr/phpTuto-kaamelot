<?php

declare(strict_types=1);

/*
 * LABO DOCTRINE — pronostics justes (formateur).
 *
 * cp formateur/solutions/doctrine-labo/pronostics.php apps/api-symfony/tests/Labo/
 * make sf-labo   # 9/9 verts
 *
 * Le pourquoi de chaque réponse est dans formateur/labo-doctrine.md.
 */

return [
    'L01' => [
        'requetes' => 2,               // find() n°2 servi par l'Identity Map ; findOneBy() interroge la base
        'a_et_c_identiques' => true,   // … mais renvoie l'objet déjà en mémoire (une ligne = un objet)
    ],
    'L02' => [
        'requetes' => 3,               // 2 SELECT + 1 UPDATE
        'updates' => 1,                // « Mort sur le Nil » n'a pas changé : rien à écrire
    ],
    'L03' => [
        'requetes_avant_flush' => 0,   // persist() ne fait que planifier
        'id_connu_avant_flush' => false, // colonne IDENTITY : l'id vient de la base, à l'INSERT
        'requetes_apres_flush' => 1,   // l'INSERT (dans une transaction ouverte par flush)
    ],
    'L04' => [
        'apres_find' => 1,
        'apres_getAuthor' => 1,        // un objet fantôme (lazy) : seul l'author_id est connu
        'apres_getId' => 1,            // l'identifiant ne déclenche pas le chargement
        'apres_getName' => 2,          // le premier vrai accès charge l'auteur
    ],
    'L05' => [
        'updates' => 0,                // Author::$books est le côté inverse (mappedBy) : ignoré à l'écriture
        'auteur_en_base' => 'Haruki Murakami',
    ],
    'L06' => [
        'requetes_boucle' => 5,        // 1 + 4 auteurs distincts (pas 1 + 9 : l'Identity Map dédoublonne)
        'requetes_fetch_join' => 1,
    ],
    'L07' => [
        'critique_acceptee' => true,   // Book::$reviews a cascade: ['persist']
        'etiquette_acceptee' => false, // Book::$tags non : ORMInvalidArgumentException
    ],
    'L08' => [
        'lignes_modifiees' => 1,
        'en_memoire_apres_update' => false, // le DQL écrit en base sans passer par l'Unit of Work
        'apres_nouveau_find' => false,      // find() renvoie l'objet de l'Identity Map, sans requête
        'apres_refresh' => true,            // refresh() relit la ligne
    ],
    'L09' => [
        'entites_findAll' => 9,
        'entites_where' => 2,
        'entites_count' => 0,
    ],
];
