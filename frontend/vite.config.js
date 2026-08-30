import { defineConfig } from 'vite';
import { resolve } from 'node:path';

// Сборка фронта нового стека. Бандлы с хешами кладём в public/dist,
// манифест читает Twig (ViteAssets) и подставляет актуальные файлы.
export default defineConfig({
  root: resolve(__dirname, 'src'),
  base: '/dist/',
  build: {
    outDir: resolve(__dirname, '../public/dist'),
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        menu: resolve(__dirname, 'src/entries/menu.js'),
      },
    },
  },
  server: {
    port: 5173,
    strictPort: true,
  },
});
