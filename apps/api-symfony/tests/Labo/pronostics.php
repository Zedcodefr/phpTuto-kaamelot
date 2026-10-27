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
        'requetes' => 2,          // int : combien de requêtes SQL pour les trois lectures ?
        'a_et_c_identiques' => true, // bool : $a === $c ?
    ],
    'L02' => [
        'requetes' => 3,          // int : combien de requêtes SQL au total ?
        'updates' => 1,           // int : combien d'UPDATE parmi elles ?
    ],
    'L03' => [
        'requetes_avant_flush' => 0, // int : requêtes juste après persist()
        'id_connu_avant_flush' => false, // bool : getId() renvoie-t-il déjà un entier juste après persist() ?
        'requetes_apres_flush' => 1, // int : total depuis le début
    ],
    'L04' => [
        'apres_find' => 1,        // int : requêtes après find()
        'apres_getAuthor' => 1,   // int : … après getAuthor()
        'apres_getId' => 1,       // int : … après getAuthor()->getId()
        'apres_getName' => 1,     // int : … après getAuthor()->getName()
    ],
    'L05' => [
        'updates' => 0,           // int : combien d'UPDATE pendant le flush ?
        'auteur_en_base' => 'Haruki Murakami',    // string : le nom de l'auteur de « Kafka sur le rivage » relu en base
    ],
    'L06' => [
        'requetes_boucle' => 5,     // int : findAll() + la boucle sur les auteurs
        'requetes_fetch_join' => 1, // int : la même chose avec un fetch join
    ],
    'L07' => [
        'critique_acceptee' => true, // bool : le flush avec la nouvelle critique passe-t-il ?
        'etiquette_acceptee' => false, // bool : le flush avec la nouvelle étiquette passe-t-il ?
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
