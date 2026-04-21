import { defineConfig, loadEnv } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import path from 'node:path';

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), 'VITE_');
  const apiTarget = env.VITE_API_PROXY_TARGET ?? 'http://localhost:8000';

  return {
    plugins: [react(), tailwindcss()],
    resolve: {
      alias: {
        '@': path.resolve(__dirname, './src'),
      },
    },
    server: {
      port: 5173,
      strictPort: true,
      // Proxy /api → Laravel during dev so the browser sees a single origin
      // (no CORS preflight overhead for the local dev loop). Set
      // VITE_API_PROXY_TARGET in /frontend/.env.local to override.
      proxy: {
        '/api': {
          target: apiTarget,
          changeOrigin: true,
          secure: false,
        },
      },
    },
    build: {
      // Build directly into /public/admin-v2 so Herd serves the assets at
      // the same path the SPA is mounted on — keeps asset URLs and router
      // basename aligned without a redirect layer.
      outDir: '../public/admin-v2',
      emptyOutDir: true,
      assetsDir: 'assets',
    },
    base: mode === 'production' ? '/admin-v2/' : '/',
  };
});
