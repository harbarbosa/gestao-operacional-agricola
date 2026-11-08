import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { VitePWA } from 'vite-plugin-pwa'

export default defineConfig({
  plugins: [react(), VitePWA({
    registerType: 'prompt',
    manifest: {
      name: 'Gestão Operacional Agrícola', short_name: 'Gestão Agrícola',
      lang: 'pt-PT', start_url: '/', display: 'standalone',
      theme_color: '#165640', background_color: '#f5f7f3',
      icons: [{ src: '/icon.svg', sizes: 'any', type: 'image/svg+xml', purpose: 'any' }],
    },
    workbox: { navigateFallbackDenylist: [/^\/api/], globPatterns: ['**/*.{js,css,html,svg}'] },
  })],
  server: { proxy: { '/api': 'http://localhost:8080' } },
})
