import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';
import fs from 'fs';

function wpAssets() {
  return {
    name: 'wp-assets',
    closeBundle() {
      const outDir = path.resolve(__dirname, 'assets/dist');
      ['admin', 'frontend'].forEach((name) => {
        const content = `<?php\nreturn array(\n\t'dependencies' => array(),\n\t'version' => '${Date.now()}',\n);\n`;
        fs.writeFileSync(path.join(outDir, `${name}.asset.php`), content);
      });
    },
  };
}

export default defineConfig({
  plugins: [react(), wpAssets()],
  build: {
    outDir: 'assets/dist',
    emptyOutDir: true,
    cssCodeSplit: true,
    rollupOptions: {
      input: {
        admin: path.resolve(__dirname, 'src/admin/main.jsx'),
        frontend: path.resolve(__dirname, 'src/frontend/main.jsx'),
      },
      output: {
        entryFileNames: '[name].js',
        chunkFileNames: 'chunks/[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          const names = assetInfo.names || (assetInfo.name ? [assetInfo.name] : []);
          const cssEntry = names.find((n) => n.endsWith('.css'));
          if (cssEntry) {
            if (cssEntry.includes('admin')) return 'admin.css';
            if (cssEntry.includes('frontend')) return 'frontend.css';
            // Fallback: map by source module path when available
            return '[name][extname]';
          }
          return 'assets/[name]-[hash][extname]';
        },
      },
    },
  },
});
