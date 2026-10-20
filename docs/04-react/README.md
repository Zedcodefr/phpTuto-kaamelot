# Parcours React — dev front

> Public : développeur C# / .NET (MVC, Blazor, WPF…) qui n'a jamais fait de React.
> Objectif : être autonome sur le front TaskBoard, à votre rythme et en parallèle des devs back :
> le front reste volontairement plus léger que le back.
>
> IA autorisée partout ; vous commitez du code que vous avez lu et compris ([charte](../00-charte-ia.md)).

## Sommaire

| # | Document | Contenu |
|---|---|---|
| 1 | [Principes](01-principes.md) | SPA, écosystème npm/Vite, TypeScript vu depuis C#, JSX, composants, props, état, rendu, `useEffect`, lecture commentée de `TaskList.tsx` + quiz |
| 2 | [Particularités & pièges](02-particularites.md) | Règles des hooks, dépendances, StrictMode, immutabilité, formulaires, custom hooks, Context, routing, appels d'API, token + quiz |
| 3 | [Exercices / backlog front](03-exercices-front.md) | Tickets F1 → F10 (socle) et F11 (bonus), alignés sur le [contrat d'API](../api-contract.md) |

En séance, les cours sont parcourus à l'essentiel (principes et pièges) ; le reste sert de référence pendant les tickets.

## Objectifs

À la fin du parcours, le dev front sait :

- lire et écrire des composants fonctionnels React typés en TypeScript ;
- gérer l'état local (`useState`), les effets (`useEffect`) et factoriser la logique dans des **custom hooks** ;
- appeler l'API de façon typée, afficher chargement / erreurs / erreurs de validation `422` par champ ;
- naviguer entre pages avec `react-router`, partager l'authentification avec un **Context** ;
- travailler en binôme avec les devs back **à partir du contrat d'API**, y compris avant que l'API soit livrée (mocks).

## Déroulé (aligné sur les étapes back)

À votre rythme : un ticket est terminé quand il est compris ; si vous avancez vite, passez au ticket suivant ou aux bonus.

| Étape back | Back (3 devs) | Front (1 dev) |
|---|---|---|
| Lancement | Contrat, charte | Idem |
| 1 — PHP | Cours PHP, mob, lots A/B/C, intégration | Cours [01-principes](01-principes.md) puis [02-particularites](02-particularites.md) + quiz, puis tickets **F1 → F4** sur l'API Symfony déjà prête (`make use-symfony`) ; validation sur l'API PHP pur (`make use-php`) quand les back ont intégré |
| 2 et 3 — Symfony, Doctrine | Cours, kata | **F5 → F7** (routing, création, modification, suppression de tâches) + relecture d'une PR back |
| 4 — Projet | B1 Projets · B2 Auth · B3 Filtres/commentaires | **F8 → F10**, au fil des livraisons back (mocks en attendant) ; démo finale |
| Une fois le socle terminé | Bonus du backlog | **F11** et les bonus des tickets |

## Commandes utiles

Tout passe par le Makefile à la racine — pas besoin d'installer Node sur votre poste.

| Commande | Effet |
|---|---|
| `make up` | Démarre tout, front sur http://localhost:5173 |
| `make logs s=front` | Logs du serveur Vite |
| `make npm c="install react-router"` | Ajouter une dépendance (≈ `dotnet add package`) |
| `make front-lint` | Lint (oxlint) + vérification des types (`tsc`) ; échoue aussi sur les warnings |
| `make front-build` | Build de production |
| `make use-php` / `make use-symfony` | Choisir l'API appelée par le front |
| `make front-sh` | Shell dans le conteneur Node |

> ⚠️ N'exécutez pas `npm install` directement sur votre machine : les dépendances sont
> installées dans le conteneur (Linux). Mélanger les deux casse certains binaires natifs.
