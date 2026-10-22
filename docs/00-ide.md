# Configurer votre IDE

PHP, Composer, Node et PostgreSQL tournent dans Docker : votre éditeur n'a besoin d'**aucun PHP installé** sur le poste.
Il doit seulement comprendre le code (autocomplétion, navigation), se connecter à Xdebug pour le débogage pas à pas,
et vous laisser lancer les commandes `make` dans son terminal.

Deux choix possibles : **VS Code** (gratuit, presque tout est déjà configuré dans le projet) ou **PhpStorm**
(payant, le plus complet pour PHP et Symfony). Faites cette configuration **avant** de commencer, après `make install`.

| | VS Code | PhpStorm |
|---|---|---|
| Configuration | Fournie dans le projet (`.vscode/`, `.devcontainer/`) | À faire une fois (une dizaine de minutes) |
| Autocomplétion PHP | Extension *PHP Intelephense* | Intégrée |
| Symfony (routes, services, config) | Peu d'aide spécifique | Plugin *Symfony Support* |
| Débogage Xdebug | Extension *PHP Debug*, configurations fournies | Intégré, deux « serveurs » à déclarer |
| Base de données | Adminer (http://localhost:8090) | Outil *Database* intégré |

---

## VS Code

### Option 1 — le devcontainer (le plus simple)

1. Installez l'extension **Dev Containers** (`ms-vscode-remote.remote-containers`).
2. Ouvrez le dossier du projet, puis *Reopen in Container* (notification, ou palette `F1` → *Dev Containers: Reopen in Container*).
3. Au premier lancement, l'image se construit (quelques minutes). Ensuite, dans le **terminal intégré** : `make install`.

Toutes les extensions sont installées automatiquement, `make` est disponible et pilote le Docker de votre machine.
Les URL (http://localhost:5173, :8081, :8082...) s'ouvrent directement dans votre navigateur.

### Option 2 — VS Code sur votre poste

À l'ouverture du dossier, VS Code propose **« Installer les extensions recommandées »** : acceptez.
(Plus tard : palette `F1` → *Extensions: Show Recommended Extensions*.) Il vous faut alors `make` installé sur le poste.

| Extension | À quoi elle sert |
|---|---|
| PHP Intelephense | Autocomplétion, aller à la définition, imports automatiques, erreurs à la frappe |
| PHP Debug | Débogage pas à pas avec Xdebug |
| YAML | Fichiers de `config/` de Symfony, `compose.yaml` |
| DotENV | Coloration des fichiers `.env` |
| EditorConfig | Indentation et fins de ligne du projet |
| oxc | Lint du front React (dev front) |
| Container Tools | Voir les conteneurs, leurs logs, ouvrir un shell |
| Dev Containers | Pour passer à l'option 1 quand vous voulez |

Les réglages partagés sont dans `.vscode/settings.json` : version PHP 8.4 pour Intelephense, validation PHP locale
désactivée (pas de PHP sur le poste), tags YAML de Symfony reconnus, cache `var/` exclu de la recherche.

**L'autocomplétion des classes Symfony et Doctrine** vient du dossier `vendor/`, créé par `make install`.
Si Intelephense ne trouve pas `AbstractController` ou `EntityManagerInterface` : `make install` est-il passé ?
Puis palette → *Intelephense: Index workspace*.

### Déboguer avec Xdebug (VS Code)

```bash
XDEBUG_MODE=debug make up      # active Xdebug ; `make up` pour revenir à la normale (Xdebug ralentit tout)
```

1. Onglet *Run and Debug* (`Ctrl+Maj+D` / `Cmd+Maj+D`), choisissez **« Xdebug - API PHP pur »** ou **« Xdebug - API Symfony »**
   (fournies dans `.vscode/launch.json`), puis F5.
2. Posez un point d'arrêt (clic dans la marge), appelez l'API : `curl http://localhost:8081/api/tasks` (PHP pur) ou `:8082` (Symfony).
3. L'éditeur s'arrête : variables, pile d'appels, pas à pas. Les tests (`make sf-test`) s'arrêtent aussi sur vos points d'arrêt.

---

## PhpStorm

### 1. Ouvrir le projet

*File › Open* sur le dossier racine du projet (celui qui contient le `Makefile`), puis lancez `make install` dans le
terminal intégré (`Alt+F12`).

### 2. PHP et Composer

- *Settings › PHP* : **PHP language level 8.4**. Pas besoin de *CLI Interpreter* : les commandes passent par `make`.
- Clic droit sur `apps/front/node_modules/` → *Mark Directory as › Excluded* (moins d'indexation), sauf si vous êtes le dev front.
- **Ne pas exclure** `vendor/` (c'est lui qui donne l'autocomplétion de Symfony et Doctrine) ni `apps/api-symfony/var/`
  (le plugin Symfony y lit le conteneur compilé, voir plus bas).

Les deux API utilisent le même namespace `App\` (une dans `apps/api-php`, l'autre dans `apps/api-symfony`) :
PhpStorm peut signaler des « classes en double ». C'est attendu. Pour ne plus le voir, excluez temporairement
`apps/api-php` quand vous travaillez sur Symfony (et inversement).

### 3. Plugin Symfony Support

*Settings › Plugins › Marketplace* : **Symfony Support** (gratuit), puis redémarrez.
Activez-le pour le projet (*Settings › PHP › Symfony*, case *Enable for this project*). L'application Symfony est dans
un sous-dossier : si routes et services ne sont pas reconnus, indiquez sur cette même page les chemins sous
`apps/api-symfony/` (dossier `public`, et dans la liste des conteneurs le fichier
`apps/api-symfony/var/cache/dev/App_KernelDevDebugContainer.xml`, créé au premier appel de l'API Symfony).

Il apporte la navigation vers les routes et les services, l'autocomplétion des noms de routes, des clés de config YAML
et des paramètres `%env()%`.

### 4. Xdebug

Les conteneurs annoncent un nom de serveur (`PHP_IDE_CONFIG` dans `compose.yaml`) : PhpStorm s'en sert pour trouver
la correspondance des chemins. Déclarez **deux serveurs**, *Settings › PHP › Servers*, cochez *Use path mappings* :

| Name (exactement) | Host | Port | Mapping |
|---|---|---|---|
| `taskboard-php` | `localhost` | `8081` | `apps/api-php` → `/app` |
| `taskboard-symfony` | `localhost` | `8082` | `apps/api-symfony` → `/app` |

Puis :

```bash
XDEBUG_MODE=debug make up      # active Xdebug ; `make up` pour revenir à la normale
```

1. Cliquez sur l'icône **Start Listening for PHP Debug Connections** (le téléphone, en haut à droite). Port par défaut : 9003.
2. Posez un point d'arrêt, appelez l'API (`curl`, le front ou un fichier *HTTP Client*) : PhpStorm s'arrête.
3. Si PhpStorm demande de choisir un fichier ou un serveur : le nom du serveur ne correspond pas exactement au tableau.

### 5. Base de données

Panneau *Database* → *+* → *Data Source › PostgreSQL* : host `localhost`, port **5433**, utilisateur `app`,
mot de passe `app`, base `taskboard` (Symfony) ou `taskboard_php` (PHP pur). PhpStorm autocomplète alors aussi le SQL
écrit dans votre code PHP.

### 6. Requêtes HTTP

Un fichier `.http` (*File › New › HTTP Request*) remplace `curl` et garde vos requêtes sous la main :

```http
POST http://localhost:8082/api/tasks
Content-Type: application/json

{"title": "Tester depuis PhpStorm"}
```

---

## En cas de problème

| Problème | Solution |
|---|---|
| Aucune autocomplétion sur les classes Symfony | `make install` est-il passé (dossier `apps/api-symfony/vendor/`) ? Puis réindexer (VS Code : *Intelephense: Index workspace* ; PhpStorm : *File › Invalidate Caches*) |
| VS Code : « Cannot validate since no PHP executable is set » | Réglage `php.validate.enable: false` (fourni dans `.vscode/settings.json`) : vérifiez que le dossier ouvert est bien la racine du projet |
| Le point d'arrêt n'est jamais atteint | `XDEBUG_MODE=debug make up` lancé ? L'éditeur écoute-t-il (VS Code : F5 ; PhpStorm : le téléphone) ? Le port 9003 est-il pris par un autre éditeur ouvert ? |
| PhpStorm s'arrête sur un fichier inconnu ou demande un mapping | Nom du serveur différent de `taskboard-php` / `taskboard-symfony` |
| Une requête ne répond plus | L'éditeur est arrêté sur un point d'arrêt : continuez l'exécution, ou `make up` sans Xdebug |
| Devcontainer : `curl localhost:...` ne répond pas | La stack est-elle démarrée (`make up`) ? Puis `bash .devcontainer/forward-ports.sh` |
