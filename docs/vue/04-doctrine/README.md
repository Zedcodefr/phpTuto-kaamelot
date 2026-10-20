# Étape 4 — Doctrine & DQL

> Doctrine est l'ORM de Symfony. Vous l'avez déjà croisé dans l'API Tâches (`persist`, `flush`, `TaskRepository`)
> sans le regarder de près. Cette étape le démonte pièce par pièce **avant** le projet, où vous allez créer des
> relations (projets, utilisateurs, commentaires) et des requêtes filtrées et paginées.
>
> Si vous avez utilisé **Prisma**, **TypeORM**, **Sequelize** ou **Supabase** côté Node : vous êtes en terrain
> connu, avec quelques différences importantes (signalées par ⚠️). Sinon, pas de souci : tout part du SQL vu à
> l'étape 1 ([SQL et bases relationnelles](../01-fondamentaux/02-sql-et-bases-relationnelles.md)).

## Place dans le parcours

```
1 Fondamentaux ─▶ 2 PHP pur ─▶ 3 Symfony ─▶ ★ 4 Doctrine & DQL (cours + kata) ★ ─▶ 5 Projet (B1/B2/B3)
```

À l'étape 2, vous avez écrit vos requêtes SQL à la main avec PDO et transformé chaque ligne en objet `Task`
(`Task::fromRow()`). Doctrine fait ce travail pour vous… à condition de comprendre **quand** et **comment**
il parle à la base.

## Objectifs

À la fin de cette étape, vous savez :

- expliquer l'**Unit of Work** et l'**Identity Map**, et prédire quand Doctrine exécute du SQL ;
- mapper une entité et ses **relations**, en sachant quel côté est **propriétaire** ;
- générer, relire et appliquer une **migration** sans marcher sur les pieds de vos collègues ;
- écrire des requêtes en **DQL** et avec le **QueryBuilder** : jointures, agrégats, sous-requêtes, pagination,
  projection dans un DTO, mises à jour de masse ;
- repérer et corriger un **N+1** avec le profiler ;
- appliquer la règle d'or : **filtrer, trier, paginer et compter en base**, jamais dans PHP après un `findAll()`.

## Déroulé

> **À votre rythme** : une étape est terminée quand elle est **comprise**. Si vous avancez vite, passez aux bonus
> ou à l'étape suivante. Ce qui compte, c'est de **comprendre** ce que vous écrivez.

1. **Cours** (avec leurs quiz) : chapitres 1 à 3 ci-dessous.
2. **Kata** individuel : 21 petits exercices sous forme de tests à faire passer, socle **K01 → K16 + K20**.
3. **Débrief collectif** sur K06, K12 et K20.
4. **Projet** : les tickets B1/B2/B3 réutilisent directement ce que le kata vous a fait pratiquer.

## Contenu : à voir en séance / référence

Les chapitres sont complets ; en séance, on ne voit que l'essentiel. Le reste sert de **référence pendant le kata
et le projet** (l'IA peut vous l'expliquer aussi : vérifiez ensuite avec `make dql` et le profiler).

| # | Chapitre | À voir en séance | Référence pendant le kata / le projet |
|---|---|---|---|
| 1 | [ORM, Unit of Work et mapping](01-orm-et-mapping.md) | [Pourquoi un ORM](01-orm-et-mapping.md#11-pourquoi-un-orm), [EntityManager : pas un store Pinia](01-orm-et-mapping.md#13-lentitymanager--surtout-pas-un-store-pinia), [Unit of Work et états d'une entité](01-orm-et-mapping.md#15-lunit-of-work-et-les-états-dune-entité), [migrations à trois](01-orm-et-mapping.md#travailler-à-trois-sans-se-marcher-dessus) | Transactions, détail du mapping, listeners (B3), fixtures, repositories |
| 2 | [Relations](02-relations.md) | [Côté propriétaire / inverse](02-relations.md#22-côté-propriétaire-côté-inverse--le-piège), [cascade et orphanRemoval](02-relations.md#25-cascade-orphanremoval-et-ondelete), [le problème N+1](02-relations.md#27-le-problème-n1) | Collections, chargement paresseux, sérialisation, application au projet |
| 3 | [DQL et QueryBuilder](03-dql-et-query-builder.md) | [La règle d'or](03-dql-et-query-builder.md#31-la-règle-dor--le-travail-se-fait-en-base), [SQL ↔ DQL ↔ JS ↔ Prisma](03-dql-et-query-builder.md#33-sql--dql--js--prisma), [paramètres](03-dql-et-query-builder.md#34-paramètres), [QueryBuilder](03-dql-et-query-builder.md#35-le-querybuilder), [fetch join et pagination](03-dql-et-query-builder.md#38-fetch-join-et-pagination) | Hydratation, `SELECT NEW`, `UPDATE` DQL, SQL natif, [anti-sèche](03-dql-et-query-builder.md#313-anti-sèche) |
| 4 | [Kata Doctrine (K01 → K21)](04-kata-doctrine.md) | K01 → K16 et K20, débrief K06 / K12 / K20 | K17, K18, K19, K21 en bonus |

L'IA est autorisée ; l'exigence est de savoir expliquer chaque requête et le SQL qu'elle génère
(voir [le kata](04-kata-doctrine.md#42-lia-pendant-le-kata) et la [charte IA](../../00-charte-ia.md)).

## Commandes utiles

| Commande | Effet |
|---|---|
| `make dql q="SELECT a.name FROM App\Kata\Entity\Author a"` | Exécute une requête **DQL** et affiche le résultat (tableaux) |
| `make sf c='doctrine:query:dql --show-sql "SELECT ..."'` | Affiche le **SQL généré** à partir d'un DQL, sans l'exécuter |
| `make sql q="SELECT * FROM kata_book"` | Exécute du **SQL** brut sur la base Symfony |
| `make sf-psql` | Console PostgreSQL (pour `EXPLAIN`, `\d kata_book`…) |
| `make sf-kata` / `make sf-kata c="--filter=K05"` | Lance le kata (tous les tests, ou un seul) |
| `make sf c="doctrine:mapping:info"` | Liste les entités connues de Doctrine |
| `make sf c="doctrine:schema:validate"` | Vérifie le mapping **et** sa cohérence avec la base |
| `make sf-migration` / `make sf-migrate` | Génère / exécute les migrations |

**Profiler** : http://localhost:8082/_profiler → choisir une requête → onglet **Doctrine**. Vous y voyez chaque
requête SQL, ses paramètres, un bouton *Explain*, et les requêtes identiques regroupées. C'est l'équivalent de
l'onglet *Réseau* des DevTools… mais pour les requêtes que votre API envoie à la base. Votre meilleur outil contre le N+1.

> 💡 Dans `make dql`, écrivez les noms de classe **complets** (`App\Kata\Entity\Book`). Doctrine ORM 3 ne
> connaît plus les alias courts du type `Kata:Book` que vous trouverez dans de vieux tutoriels (et que l'IA
> propose encore souvent).
