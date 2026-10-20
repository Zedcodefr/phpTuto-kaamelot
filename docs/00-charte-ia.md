# Charte d'usage de l'IA

> **Vous pouvez tout faire avec l'IA, sauf commiter du code que vous n'avez pas lu et compris.**
>
> ChatGPT, Claude, Copilot, Cursor… sont autorisés **partout et tout le temps** : cours, exercices, lots, kata,
> backlog, front. Aucune partie de la formation ne les interdit. En contrepartie, chaque ligne que vous livrez
> est **la vôtre** : vous l'avez lue, vous savez l'expliquer et vous en répondez.

---

## 1. Pourquoi cette règle

Cette formation prépare un **vrai projet**. Sur ce projet :

- vous serez **responsables** du code en production, y compris de celui qu'une IA a écrit ;
- c'est vous qui ferez le **debug** quand une requête Doctrine renverra les mauvaises lignes, ou quand un `useEffect`
  bouclera, souvent sans l'IA sous la main pour vous dire pourquoi ;
- vos collègues feront la **revue de code** : « c'est l'IA qui l'a écrit » ne sera jamais une réponse à « pourquoi ? ».

L'IA est donc un outil de travail normal, au même titre que l'IDE ou la doc. Ce qu'on vérifie pendant la formation,
c'est que vous gardez la main dessus.

## 2. Ce qu'on attend de tout code commité

Que vous l'ayez tapé, complété avec Copilot ou généré entièrement, avant de commiter :

| Attente | Concrètement |
|---|---|
| **Lu** | Chaque ligne, pas seulement « ça compile ». Ce qui paraît magique (option, attribut, flag) se vérifie dans la doc. |
| **Expliqué** | Vous pouvez le raconter à un collègue, avec vos mots, code ouvert. |
| **Exécuté et testé** | `make php-check-x`, `make sf-test`, `make sf-kata`, `make front-lint`, un `curl`, le navigateur. |
| **Adapté au projet** | Conventions du dépôt : `declare(strict_types=1)`, DTO, `final`, `format: 'json'`, contrat d'API, structure des dossiers. |
| **À la bonne version** | PHP 8.4, Symfony 7.4, Doctrine ORM 3, React 19, TypeScript strict. Les IA proposent souvent des syntaxes anciennes (section 6). |

## 3. La règle des 3 questions

Avant chaque commit, répondez à ces 3 questions sur le code que vous livrez :

1. **Que fait chaque ligne ?**
2. **Pourquoi ce choix plutôt qu'un autre ?** Un DTO plutôt que l'entité, `persist` ici et pas là, `useEffect` plutôt qu'un calcul pendant le rendu…
3. **Que se passe-t-il si… ?** Si l'id n'existe pas, si le JSON est vide, si deux utilisateurs le font en même temps, si la liste contient 10 000 éléments.

Une question sans réponse ? Ne committez pas encore : creusez (avec l'IA si vous voulez, c'est fait pour ça),
ou demandez au formateur.

## 4. Transparence : la section PR

Chaque PR contient cette section dans sa description :

```markdown
## Ce que l'IA a produit / ce que j'ai vérifié
- Ce que l'IA a fait (explication, génération, relecture...) : ...
- Ce que j'ai vérifié, corrigé ou adapté : ...
- Notions nouvelles pour moi : ...
- Le point que je trouve encore flou : ...
```

Ce n'est pas un aveu. « L'IA a généré le voter, j'ai corrigé la gestion du rôle admin et ajouté le test du 403 »
est une **excellente** réponse. Le dernier point (« encore flou ») est le plus utile au formateur.

## 5. Bien utiliser l'IA pour apprendre (conseils)

Sur les exercices (lots de la partie PHP, kata Doctrine), l'objectif est d'**apprendre**, pas seulement de livrer.
Ces conseils vous feront gagner beaucoup plus que du temps ; ce sont des conseils, pas des obligations :

- **Essayez d'abord par vous-même** avec le cours et le message d'erreur, puis demandez.
- **Demandez une explication plutôt qu'une solution** : « explique-moi la cause, sans me donner le code », ou « donne-moi un indice ».
- **Faites-vous interroger** : « pose-moi 3 questions pour vérifier que j'ai compris les voters Symfony », puis répondez avant de lire les réponses.
- **Comparez avec ce que vous connaissez** : « quelle est la différence avec C# / ASP.NET Core / EF Core / Blazor ? ».
- **Donnez le contexte** : « PHP 8.4, Symfony 7.4, Doctrine ORM 3, attributs PHP, pas d'annotations », ou « React 19, TypeScript strict, composants fonctions ».
- **Demandez la source** : « où est-ce documenté sur php.net / symfony.com / react.dev ? », puis allez vérifier.
- **Si vous générez du code, faites-vous l'expliquer** ligne par ligne, puis modifiez-le vous-même (renommer, ajouter un cas) pour vérifier que vous le maîtrisez.

| Usage | Exemple de demande |
|---|---|
| Expliquer une erreur | « Voici l'erreur et mon code. Explique-moi la **cause**. » |
| Expliquer une notion | « Explique le Unit of Work de Doctrine à un dev qui connaît bien EF Core. » |
| Trouver l'équivalent C# | « Quel est l'équivalent PHP de `IEnumerable.Select` ? Quelles différences de comportement ? » |
| Lire du code | « Que fait cette ligne : `array_filter($m, is_string(...), ARRAY_FILTER_USE_KEY)` ? » |
| Relire son code | « Relis ce contrôleur Symfony : qu'est-ce qui n'est pas idiomatique ? » |
| Proposer des cas de test | « Quels cas limites dois-je tester pour cette validation ? » |
| Générer du code ou des données | « Écris la requête QueryBuilder qui… puis explique chaque ligne. » |

## 6. Les pièges classiques des IA sur cette stack

Les IA ont été entraînées sur 20 ans de PHP : une bonne partie est obsolète. Méfiez-vous de :

| Piège | Ce qu'il faut à la place |
|---|---|
| Code PHP 5 / 7 : `array()`, pas de types, `isset` partout, `==` | PHP 8.4 : `[]`, types partout, `declare(strict_types=1)`, `===`, `match`, enums, `readonly` |
| Annotations Doctrine en commentaires (`/** @ORM\Column */`) | **Attributs** PHP 8 : `#[ORM\Column]` |
| `sensio/framework-extra-bundle`, `@ParamConverter`, `@Route` en annotations | Symfony 7 : `#[Route]`, `#[MapEntity]`, `#[MapRequestPayload]`, `#[IsGranted]` natifs |
| `security.yaml` ancienne syntaxe : `anonymous: true`, `encoders:`, Guard authenticators | `password_hashers:`, `access_token:`, authenticators modernes, `PUBLIC_ACCESS` |
| `$this->getDoctrine()` dans un contrôleur | Injection de `EntityManagerInterface` ou du repository |
| Proposer **API Platform** ou **LexikJWT** « pour aller plus vite » | Hors sujet ici : on apprend les briques de base (voir le backlog) |
| `$em->persist()` sur une entité déjà gérée, `flush()` dans une boucle | Comprendre le Unit of Work (cours Doctrine) |
| React : composants **classes**, `componentDidMount` | Composants fonctions + hooks |
| `useEffect` pour calculer une valeur dérivée, ou pour réagir à un clic | Calcul pendant le rendu, gestionnaire d'événement |
| Ajout de Redux / Axios / une lib de formulaires sans besoin | `useState`, `fetch`, formulaires contrôlés (voir le cours React) |
| Des méthodes ou options qui **n'existent pas** (hallucinations) | Vérifier dans la doc officielle, lancer le code |

## 7. Données sensibles

- Ne collez jamais de secrets (mots de passe, tokens, `APP_SECRET` de production, clés d'API) dans une IA.
- Ici tout est fictif (`app`/`app`, `password`), mais prenez le réflexe pour le vrai projet.
- Respectez la politique de votre entreprise sur les outils d'IA autorisés et le code que l'on peut y envoyer.

## 8. Comment la compréhension est vérifiée

On a le droit d'avoir utilisé l'IA. On n'a pas le droit de ne pas savoir expliquer son code.

| Dispositif | Quand | Comment |
|---|---|---|
| **Quiz** (application de formation) | Fin de chaque cours | QCM corrigé avec explications. Répondez d'abord seul : objectif **≥ 80 %**, sinon relire et refaire. |
| **Questions à expliquer à l'oral** | Fin de chaque cours | Affichées sous chaque quiz : préparez-les, avec vos mots. |
| **Revue orale** (courte, avec le formateur) | Fin des lots (étape PHP), après le kata (étape Doctrine), à la démo finale | Sur **votre** code, assistant fermé pendant l'oral : vous expliquez, le formateur demande « pourquoi ? » et « que se passe-t-il si… ? », et peut demander une petite modification en direct. |
| **Revue de code** entre devs | Chaque PR | Le relecteur doit comprendre le code, et il pose ses questions dans la PR. |
| **Démo** | Fin de l'étape PHP et fin du projet | Chacun présente un morceau de code et un piège rencontré. |

Une revue orale ratée n'est pas une sanction. Elle signifie : on reprend la notion ensemble avant d'aller plus loin.
