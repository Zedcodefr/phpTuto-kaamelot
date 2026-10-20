import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// Le front appelle toujours "/api/..." (même origine => pas de problème de CORS).
// En dev, Vite redirige ces appels vers le backend choisi :
//   make use-php      -> http://api-php:8000       (partie 1)
//   make use-symfony  -> http://nginx-symfony      (partie 2)
const apiTarget = process.env.API_TARGET ?? 'http://localhost:8081'

export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      '/api': { target: apiTarget, changeOrigin: true },
    },
    // Les fichiers sont montés depuis l'hôte dans Docker : le polling fiabilise le rechargement à chaud
    watch: { usePolling: true },
  },
})
