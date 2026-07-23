/// <reference types="vitest/config" />
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      // local dev without VITE_API_URL: proxy API calls to the backend
      // (docker compose maps the app container to host port 8082)
      '/api': 'http://localhost:8082',
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: './vitest.setup.ts',
  },
});
