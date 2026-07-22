import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  server: {
    port: 3000,
    proxy: {
      '/api': {
        target: 'http://localhost:8080',
        changeOrigin: true,
      }
    }
  },
  resolve: {
    alias: {
      '@': '/src'
    }
  },
  optimizeDeps: {
    include: ['pdfjs-dist', 'epubjs']
  },
  build: {
    rollupOptions: {
      output: {
        assetFileNames: (assetInfo) => {
          // Output the PDF.js worker as .js (not .mjs) so every nginx/browser
          // serves it with the correct application/javascript MIME type without
          // needing special mime.types configuration.
          if (assetInfo.name && assetInfo.name.includes('pdf.worker')) {
            return 'assets/pdf.worker-[hash].js'
          }
          return 'assets/[name]-[hash][extname]'
        }
      }
    }
  }
})
