<?php

declare(strict_types=1);

/*
 * LABO DOCTRINE — vos pronostics.
 *
 * Pour chaque expérience : lisez le code du test (tests/Labo/LxxTest.php), remplacez les null
 * par ce que vous pensez qu'il va se passer, PUIS lancez : make sf-labo c="--filter=L01"
 *
 * Tant qu'un pronostic vaut null, l'expérience ne s'exécute pas (on parie avant de regarder).
 * Un pronostic faux n'est pas un échec : c'est exactement ce qu'on vient chercher.
 * Ce fichier ne se committe pas : `git restore apps/api-symfony/tests/Labo/pronostics.php` à la fin.
 */

return [
    'L01' => [
        'requetes' => null,          // int : combien de requêtes SQL pour les trois lectures ?
        'a_et_c_identiques' => null, // bool : $a === $c ?
    ],
    'L02' => [
        'requetes' => null,          // int : combien de requêtes SQL au total ?
        'updates' => null,           // int : combien d'UPDATE parmi elles ?
    ],
    'L03' => [
        'requetes_avant_flush' => null, // int : requêtes juste après persist()
        'id_connu_avant_flush' => null, // bool : getId() renvoie-t-il déjà un entier juste après persist() ?
        'requetes_apres_flush' => null, // int : total depuis le début
    ],
    'L04' => [
        'apres_find' => null,        // int : requêtes après find()
        'apres_getAuthor' => null,   // int : … après getAuthor()
        'apres_getId' => null,       // int : … après getAuthor()->getId()
        'apres_getName' => null,     // int : … après getAuthor()->getName()
    ],
    'L05' => [
        'updates' => null,           // int : combien d'UPDATE pendant le flush ?
        'auteur_en_base' => null,    // string : le nom de l'auteur de « Kafka sur le rivage » relu en base
    ],
    'L06' => [
        'requetes_boucle' => null,     // int : findAll() + la boucle sur les auteurs
        'requetes_fetch_join' => null, // int : la même chose avec un fetch join
    ],
    'L07' => [
        'critique_acceptee' => null, // bool : le flush avec la nouvelle critique passe-t-il ?
        'etiquette_acceptee' => null, // bool : le flush avec la nouvelle étiquette passe-t-il ?
    ],
    'L08' => [
        'lignes_modifiees' => null,     // int : ce que renvoie execute()
        'en_memoire_apres_update' => null, // bool : $book->isOutOfPrint() juste après l'UPDATE
        'apres_nouveau_find' => null,   // bool : isOutOfPrint() sur le résultat d'un nouveau find()
        'apres_refresh' => null,        // bool : isOutOfPrint() après refresh()
    ],
    'L09' => [
        'entites_findAll' => null,   // int : combien d'objets Book créés par la version A ?
        'entites_where' => null,     // int : … par la version B ?
        'entites_count' => null,     // int : … par la version C ?
    ],
];
