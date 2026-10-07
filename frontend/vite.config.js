import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'

// Сборка в dist/ раздаётся Caddy. В dev контейнер node пересобирает её при изменениях (vite build --watch);
// dev-сервер Vite наружу не публикуется.
export default defineConfig({
  plugins: [vue()],
})
