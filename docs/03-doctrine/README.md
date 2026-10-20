# Partie 3 — Doctrine & DQL

> Doctrine est l'ORM de Symfony, l'équivalent d'**Entity Framework Core**. Vous l'avez déjà utilisé sans
> le savoir dans l'API Tâches (`persist`, `flush`, `TaskRepository`). Cette partie le démonte pièce par pièce
> **avant** le backlog, où vous allez créer des relations (projets, utilisateurs, commentaires) et des requêtes
> filtrées et paginées.

## Place dans le parcours

```
Étape 1 PHP pur ─▶ Étape 2 Symfony ─▶ ★ Étape 3 Doctrine & DQL (cours + kata) ★ ─▶ Étape 4 backlog (B1/B2/B3)
```

## Objectifs

À la fin de cette partie, vous savez :

- expliquer l'**Unit of Work** et l'**Identity Map**, et prédire quand Doctrine exécute du SQL ;
- mapper une entité et ses **relations**, en sachant quel côté est **propriétaire** ;
- générer, relire et appliquer une **migration** sans marcher sur les pieds de vos collègues ;
- écrire des requêtes en **DQL** et avec le **QueryBuilder** : jointures, agrégats, sous-requêtes, pagination,
  projection dans un DTO, mises à jour de masse ;
- repérer et corriger un **N+1** avec le profiler.

## Contenu : à voir en séance / référence

> **À votre rythme** : une étape est terminée quand elle est **comprise**. Si vous avancez vite, passez aux bonus ou à l'étape suivante. Ce qui compte, c'est de **comprendre** ce que vous écrivez.

Les chapitres sont complets ; pendant le cours, on ne voit que l'essentiel. Le reste sert de **référence pendant le kata
et le backlog** (l'IA peut vous l'expliquer aussi : vérifiez ensuite avec `make dql` et le profiler).

| # | Chapitre | À voir en séance | Référence pendant le kata / backlog |
|---|---|---|---|
| 1 | [ORM, Unit of Work et mapping](01-orm-et-mapping.md) | [EntityManager ≈ DbContext](01-orm-et-mapping.md#12-entitymanager--dbcontext), [Identity Map](01-orm-et-mapping.md#13-lidentity-map), [Unit of Work et états d'une entité](01-orm-et-mapping.md#14-lunit-of-work-et-les-états-dune-entité), [migrations à trois](01-orm-et-mapping.md#travailler-à-trois-sans-se-marcher-dessus) | Transactions, détail du mapping, listeners (B3), fixtures et références, repositories |
| 2 | [Relations](02-relations.md) | [Côté propriétaire / inverse](02-relations.md#22-côté-propriétaire-côté-inverse--le-piège), [cascade et orphanRemoval](02-relations.md#25-cascade-orphanremoval-et-ondelete), [le problème N+1](02-relations.md#27-le-problème-n1) | Collections, modes de chargement, sérialisation, application au backlog |
| 3 | [DQL et QueryBuilder](03-dql-et-query-builder.md) | [SQL ↔ DQL ↔ LINQ](03-dql-et-query-builder.md#32-sql--dql--linq), [paramètres](03-dql-et-query-builder.md#33-paramètres), [QueryBuilder](03-dql-et-query-builder.md#34-le-querybuilder), [fetch join et pagination](03-dql-et-query-builder.md#37-fetch-join-et-pagination) | Hydratation, `SELECT NEW`, `UPDATE` DQL, SQL natif, [anti-sèche](03-dql-et-query-builder.md#312-anti-sèche) |
| 4 | [Kata Doctrine (K01 → K21)](04-kata-doctrine.md) | K01 → K16 et K20 obligatoires, débrief K06 / K12 / K20 | K17, K18, K19, K21 en bonus |

Le cours (avec ses quiz) d'abord, puis le kata.
L'IA est autorisée ; l'exigence est de savoir expliquer chaque requête et le SQL qu'elle génère
(voir [le kata](04-kata-doctrine.md#42-lia-pendant-le-kata) et la [charte IA](../00-charte-ia.md)).

## Commandes utiles

| Commande | Effet |
|---|---|
| `make dql q="SELECT a.name FROM App\Kata\Entity\Author a"` | Exécute une requête **DQL** et affiche le résultat (tableaux) |
| `make sf c='doctrine:query:dql --show-sql "SELECT ..."'` | Affiche le **SQL généré** à partir d'un DQL, sans l'exécuter |
| `make sql q="SELECT * FROM kata_book"` | Exécute du **SQL** brut sur la base Symfony |
| `make sf-psql` | Console PostgreSQL (pour `EXPLAIN`, `\d kata_book`, etc.) |
| `make sf-kata` / `make sf-kata c="--filter=K05"` | Lance le kata (tous les tests, ou un seul) |
| `make sf c="doctrine:mapping:info"` | Liste les entités connues de Doctrine |
| `make sf c="doctrine:schema:validate"` | Vérifie le mapping **et** sa cohérence avec la base |
| `make sf-migration` / `make sf-migrate` | Génère / exécute les migrations |

**Profiler** : http://localhost:8082/_profiler → choisir une requête → onglet **Doctrine**. Vous y voyez chaque
requête SQL, son temps, ses paramètres, et un bouton *Explain*. C'est votre meilleur outil contre le N+1.

> 💡 Dans `make dql`, écrivez les noms de classe **complets** (`App\Kata\Entity\Book`). Doctrine ORM 3 ne
> connaît plus les alias courts du type `Kata:Book` que vous trouverez dans de vieux tutoriels.
