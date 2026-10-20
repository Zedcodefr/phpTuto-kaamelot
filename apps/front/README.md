# Front TaskBoard — React + TypeScript + Vite

Point de départ volontairement minimal : un composant `TaskList` qui affiche `GET /api/tasks`.
À enrichir en suivant [les exercices front](../../docs/04-react/03-exercices-front.md).

| | |
|---|---|
| URL | http://localhost:5173 (rechargement à chaud) |
| API utilisée | `make use-php` (partie 1) ou `make use-symfony` (partie 2) — voir `vite.config.ts` |
| Contrat | [docs/api-contract.md](../../docs/api-contract.md) |
| Installer une lib | `make npm c="install react-router"` |
| Vérifier | `make front-lint` (oxlint + TypeScript) |
| Logs du serveur Vite | `make logs s=front` |

```
src/
├── main.tsx               point d'entrée : monte <App /> dans index.html
├── App.tsx                composant racine
├── components/TaskList.tsx
├── types.ts               types TypeScript issus du contrat d'API
└── index.css
```
