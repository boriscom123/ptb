import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'

// Dev-сервер работает за Caddy: снаружи доступен по HTTPS на APP_DOMAIN.
export default defineConfig({
  plugins: [vue()],
  server: {
    host: '0.0.0.0',
    port: 5173,
    allowedHosts: [process.env.APP_DOMAIN],
    hmr: {
      host: process.env.APP_DOMAIN,
      protocol: 'wss',
      clientPort: 443,
    },
  },
})
