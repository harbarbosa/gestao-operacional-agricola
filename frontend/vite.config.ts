import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { VitePWA } from 'vite-plugin-pwa'

export default defineConfig({
  plugins: [react(), VitePWA({
    registerType: 'prompt',
    manifest: {
      name: 'Agroperi', short_name: 'Agroperi',
      lang: 'pt-PT', start_url: '/', display: 'standalone',
      theme_color: '#123f32', background_color: '#f5f7f2',
      icons: [{ src: '/icon-192.png', sizes: '192x192', type: 'image/png', purpose: 'any' }, { src: '/icon-512.png', sizes: '512x512', type: 'image/png', purpose: 'any' }, { src: '/icon.svg', sizes: 'any', type: 'image/svg+xml', purpose: 'any' }],
    },
    workbox: { navigateFallbackDenylist: [/^\/api/], globPatterns: ['**/*.{js,css,html,svg,png}'] },
  })],
  server: { proxy: { '/api': 'http://localhost:8080' } },
})
