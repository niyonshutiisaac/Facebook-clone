import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'node:path';

// The dev server runs inside a sandbox and is reached through a proxied host,
// so it must bind to 0.0.0.0 and accept any Host header.
export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  server: {
    host: true,
    port: 5173,
    strictPort: true,
    allowedHosts: true,
    // /api and /broadcasting go to Laravel when it is running (see
    // docker-compose.yml). Without a backend the app falls back to its
    // in-browser mock adapter so the UI is still fully explorable.
    proxy: {
      '/api': {
        target: process.env.VITE_API_PROXY ?? 'http://backend:8000',
        changeOrigin: true,
        secure: false,
      },
      '/broadcasting': {
        target: process.env.VITE_API_PROXY ?? 'http://backend:8000',
        changeOrigin: true,
        ws: true,
      },
    },
  },
  preview: {
    host: true,
    port: 4173,
    allowedHosts: true,
  },
  build: {
    outDir: 'dist',
    sourcemap: true,
    rollupOptions: {
      output: {
        manualChunks: {
          react: ['react', 'react-dom', 'react-router-dom'],
          motion: ['framer-motion'],
          query: ['@tanstack/react-query'],
        },
      },
    },
  },
});
