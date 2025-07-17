import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import path from "path";

// This configuration is for the main application (index.tsx)
export default defineConfig({
  plugins: [react()],
  build: {
    // This build runs first and cleans the output directory.
    outDir: "build",
    emptyOutDir: true,
    // Place the manifest directly in the build/ directory.
    manifest: "manifest-app.json",
    rollupOptions: {
      input: "src/index.tsx",
      output: {
        entryFileNames: "assets/[name]-[hash].js",
        chunkFileNames: "assets/[name]-[hash].js",
        assetFileNames: "assets/[name]-[hash][extname]",
      },
    },
  },
  resolve: {
    alias: {
      "@": path.resolve(process.cwd(), "src"),
    },
  },
});
