# 3. Sécurité côté serveur

Côté front, la sécurité, c'est souvent : ne pas utiliser `v-html` avec du contenu utilisateur, masquer les boutons
que l'utilisateur n'a pas le droit d'utiliser, stocker un token. Côté back, c'est **le seul endroit où la sécurité
est réelle** : tout ce qui arrive du réseau peut avoir été fabriqué à la main.

## 3.1 Ne jamais faire confiance au client

Le code Vue tourne dans le navigateur de l'utilisateur. Il peut l'ouvrir dans les DevTools, le modifier,
ou ne pas l'utiliser du tout. Votre formulaire impose un titre d'au moins 3 caractères ? Voici comment s'en passer :

```bash
curl -i -X POST http://localhost:8082/api/tasks \
     -H 'Content-Type: application/json' \
     -d '{"title":"", "status":"nimporte"}'
```

Si le serveur ne valide pas, cette tâche invalide **entre en base**. L'API Symfony du projet répond `422` avec la liste des violations :
c'est elle qui protège la donnée, pas le formulaire.

| Côté front | Côté back |
|---|---|
| Validation = **confort** : retour immédiat, pas d'aller-retour réseau | Validation = **garantie** : la seule qui compte |
| Masquer le bouton « Supprimer » | Vérifier **à chaque requête** que l'utilisateur a le droit de supprimer **cette** tâche |
| Désactiver un champ en lecture seule | Ignorer ou refuser les champs que le client n'a pas le droit de modifier (`id`, `createdAt`, `author`…) |
| Typage TypeScript | Effacé à l'exécution : le JSON reçu peut contenir **n'importe quoi** |

Règle : **tout ce qui vient de la requête est hostile** (corps, paramètres d'URL, en-têtes, cookies),
jusqu'à preuve du contraire. On valide le type, la forme, les bornes, les valeurs autorisées — puis on vérifie les droits.

## 3.2 Injection SQL

Le danger le plus connu côté back. Imaginez une recherche écrite par concaténation :

```php
// ❌ NE JAMAIS FAIRE ÇA
$search = $_GET['search'];                       // vient du client
$sql = "SELECT * FROM task WHERE title LIKE '%$search%'";
$rows = $pdo->query($sql)->fetchAll();
```

Le client envoie `?search=' OR 1=1 --` et la requête devient :

```sql
SELECT * FROM task WHERE title LIKE '%' OR 1=1 --%'
```

→ toute la table est renvoyée. Avec d'autres valeurs, on peut lire d'autres tables, voire modifier ou supprimer des données.

La parade est **systématique** : les **requêtes préparées**. La requête et les valeurs voyagent **séparément** ;
la valeur ne peut jamais devenir du code SQL.

```php
// ✅ Requête préparée : :search est un paramètre, jamais du SQL
$statement = $pdo->prepare('SELECT * FROM task WHERE title ILIKE :search');
$statement->execute(['search' => '%' . $search . '%']);
```

C'est l'équivalent back de la règle « jamais de `v-html` avec une donnée utilisateur » : **une donnée ne doit jamais
être interprétée comme du code**. Vous l'appliquerez dans le Lot B (PDO), puis Doctrine le fera pour vous
(`setParameter()`), à condition de ne jamais concaténer de valeur dans une requête DQL non plus.

## 3.3 XSS : de quel côté se protège-t-on ?

Une API JSON stocke et renvoie le texte **tel quel** : si un utilisateur crée une tâche nommée
`<img src=x onerror=alert(1)>`, l'API la renvoie dans un champ JSON (encodé en `<img ...`), sans l'exécuter.

L'échappement HTML se fait **au moment de l'affichage**, donc côté front :

| Affichage Vue | Risque |
|---|---|
| `{{ task.title }}` | ✅ Vue échappe automatiquement |
| `<div v-html="task.title">` | ❌ le HTML est interprété : XSS |

Côté back, on renvoie bien `Content-Type: application/json` (pour que le navigateur ne l'interprète jamais comme du HTML),
on valide ce qui doit l'être (longueurs, formats), et **on ne « nettoie » pas le texte en base** en supprimant des balises :
c'est le rôle de l'affichage, et ça abîmerait la donnée.

## 3.4 Mots de passe

Un mot de passe n'est **jamais** stocké en clair, ni chiffré de façon réversible. On stocke un **hash** :
une empreinte à sens unique, lente à calculer exprès, avec un sel aléatoire intégré.

```php
$hash = password_hash('secret123', PASSWORD_DEFAULT);
// $2y$12$j7CV0jtmjx2kqVNUEw03c.e33crVjG3IuvuyCZTLkjc2H0OkSV4MC   (bcrypt, change à chaque appel)

password_verify('secret123', $hash);   // true
password_verify('autre', $hash);       // false
```

| ❌ À ne pas faire | Pourquoi |
|---|---|
| Stocker le mot de passe en clair | Une fuite de la base = tous les comptes compromis (et souvent d'autres sites) |
| `md5()` / `sha1()` / `sha256()` | Bien trop rapides : des milliards d'essais par seconde |
| Renvoyer le mot de passe ou son hash dans le JSON | Le contrat l'interdit : un `User` n'expose que `id`, `email`, `displayName` |
| Messages distincts « email inconnu » / « mot de passe faux » | Permet de deviner quels emails ont un compte |

Les algorithmes adaptés sont **bcrypt** et **Argon2id** (`PASSWORD_ARGON2ID`). Dans Symfony, le *password hasher*
choisit automatiquement le meilleur disponible (`algorithm: auto`) : vous l'utiliserez dans le ticket B2.

## 3.5 Authentification : qui êtes-vous ?

Côté front, vous avez sans doute stocké un token dans `localStorage` et ajouté un intercepteur axios.
Voici ce que le serveur fait de l'autre côté :

| Étape | Front | Back |
|---|---|---|
| Connexion | `POST /api/auth/login` avec email + mot de passe | Vérifie le hash, **crée un token aléatoire**, l'enregistre en base avec une date d'expiration, le renvoie |
| Requêtes suivantes | `Authorization: Bearer <token>` (intercepteur) | **À chaque requête** : retrouve le token en base, vérifie qu'il n'a pas expiré, charge l'utilisateur |
| Déconnexion | Supprime le token du store | (Idéalement) supprime ou invalide le token en base |

Deux grandes familles :

| | Session + cookie | Token *Bearer* (TaskBoard) |
|---|---|---|
| Où est l'information | Le serveur garde la session ; le navigateur envoie un cookie | Le client envoie le token dans un en-tête |
| Envoi | Automatique par le navigateur | Explicite (intercepteur, `fetch` avec en-tête) |
| Risque principal | CSRF (le navigateur envoie le cookie tout seul) → protections `SameSite`, jeton CSRF | Vol du token (XSS qui lit `localStorage`) |
| Typique pour | Sites rendus côté serveur, même domaine | API consommée par une SPA ou une app mobile |

Le serveur ne fait **jamais** confiance à un identifiant envoyé par le client (`"userId": 3` dans le corps) :
l'utilisateur courant est **déduit du token**.

## 3.6 Autorisation : avez-vous le droit ?

Être authentifié ne donne pas tous les droits. La question à se poser à chaque action :
**« cet utilisateur peut-il faire cette action sur CETTE ressource ? »**

| Code | Signification | Exemple TaskBoard |
|---|---|---|
| `401 Unauthorized` | Je ne sais pas qui vous êtes (pas de token, token invalide ou expiré) | `POST /api/tasks` sans `Authorization` |
| `403 Forbidden` | Je sais qui vous êtes, mais vous n'avez pas le droit | Bob tente `DELETE` sur une tâche créée par Alice |

Piège courant : vérifier qu'on est connecté, mais pas que la ressource **vous appartient**. Il suffit alors de changer l'`id`
dans l'URL pour agir sur les données d'un autre. Dans Symfony, ces règles s'écrivent dans des **voters** (ticket B2).

## 3.7 CORS vu du serveur

Côté front, CORS est cette erreur rouge dans la console. Côté back, c'est une **autorisation donnée par le serveur** :
le navigateur demande (requête `OPTIONS` dite *preflight*) « est-ce que `https://app.exemple.fr` peut lire tes réponses ? »,
et le serveur répond avec `Access-Control-Allow-Origin`.

- CORS est appliqué **par le navigateur** : `curl` ou un autre serveur l'ignorent totalement. **Ce n'est pas une protection de l'API.**
- C'est **le serveur** qui décide des origines autorisées : on liste les origines précises, jamais `*` avec des cookies.
- Dans le projet, le **proxy Vite** fait passer le front et l'API pour la même origine : pas de CORS en développement.
  En bonus, `nelmio/cors-bundle` le configure dans Symfony.

## 3.8 Secrets et variables d'environnement

| Côté front (Vite) | Côté back (Symfony) |
|---|---|
| `import.meta.env.VITE_API_URL` | `%env(DATABASE_URL)%`, `$_ENV`, `getenv()` |
| Les variables `VITE_*` sont **copiées dans le bundle JS** : tout le monde peut les lire | Les variables restent **sur le serveur** |
| ⇒ jamais de secret dans une variable `VITE_*` | ⇒ c'est là que vivent les secrets (mot de passe de base, clés d'API, `APP_SECRET`) |

Règles :

- Un secret ne va **jamais** dans le dépôt Git. Le fichier `.env` versionné contient des valeurs **de développement** ;
  les vraies valeurs sont dans `.env.local` (ignoré par Git) ou dans les variables d'environnement du serveur.
- Symfony propose aussi un coffre chiffré (`secrets:set`) pour la production.
- Un secret qui a fuité (commit, capture d'écran, conversation avec une IA) est **compromis** : on le change.

## 3.9 Erreurs : ne rien révéler en production

En développement, l'API Symfony renvoie la trace complète d'une erreur (essayez `curl http://localhost:8082/api/tasks/99999` :
le JSON contient `class` et `trace`). C'est précieux pour déboguer, et **dangereux en production** : chemins de fichiers,
versions, requêtes SQL… autant d'indices pour un attaquant.

| `APP_ENV=dev` | `APP_ENV=prod` |
|---|---|
| Messages détaillés, trace, profiler | Message générique (`"An error occurred"`), aucun détail interne |
| Pratique pour vous | Les détails partent dans les **logs**, pas dans la réponse |

Côté code : un `500` ne renvoie jamais `$e->getMessage()` d'une exception inattendue au client ; on journalise l'erreur
et on renvoie un message neutre.

## 3.10 Le principe du moindre privilège

Chaque élément n'a que les droits dont il a besoin :

- l'utilisateur de base de données de l'application ne devrait pas pouvoir supprimer la base ;
- un utilisateur standard n'a pas `ROLE_ADMIN` ;
- un token a une **durée de vie** limitée (le contrat fixe son expiration) ;
- les ports des conteneurs ne sont exposés que là où c'est nécessaire.

## Lien avec le projet

Le ticket **B2** du backlog (étape 5) met tout cela en pratique dans Symfony : inscription avec hash du mot de passe,
connexion qui délivre un token, authenticator `access_token`, écritures protégées (`401`), suppression réservée à l'auteur (`403`)
via un voter. Les tickets B1 et B3 l'utilisent ensuite : un commentaire ou une tâche est rattaché à l'utilisateur **déduit du token**.

## À retenir

- **Le serveur ne fait jamais confiance au client** : la validation front est un confort, la validation back une garantie.
- **Requêtes préparées**, toujours : une donnée ne devient jamais du code.
- Mots de passe : **hash lent** (bcrypt / Argon2id), jamais en clair, jamais renvoyés.
- `401` = qui êtes-vous ? `403` = vous n'avez pas le droit **sur cette ressource**.
- CORS est une règle du navigateur, décidée par le serveur ; ce n'est pas une protection de l'API.
- Les secrets vivent **côté serveur**, hors du dépôt ; en production, les erreurs ne révèlent rien.

Étape suivante : [PHP](../02-php/README.md).
