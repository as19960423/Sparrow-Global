import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import path from 'path';
import {defineConfig} from 'vite';

export default defineConfig(() => {
  return {
    plugins: [react(), tailwindcss()],
    resolve: {
      alias: {
        '@': path.resolve(__dirname, '.'),
      },
    },
    build: {
      // Готовый к деплою корень сайта: статика + api/*.php + .htaccess (из public/)
      outDir: 'htdocs',
      emptyOutDir: true,
    },
    server: {
      // В dev-режиме PHP API проксируется на локальный php -S (npm run dev:php)
      proxy: {
        '/api': 'http://localhost:8080',
      },
    },
  };
});
