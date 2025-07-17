import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import path from "path";

// This configuration is ONLY for the toolbar.tsx entry point.
export default defineConfig({
  plugins: [react()],
  build: {
    // This build runs second and MUST NOT clean the output directory.
    outDir: "build",
    emptyOutDir: false,
    // Place the manifest directly in the build/ directory.
    manifest: "manifest-toolbar.json",
    rollupOptions: {
      input: "src/toolbar.tsx",
      output: {
        entryFileNames: "toolbar.js",
        // This is the key setting that forces all code into one file.
        inlineDynamicImports: true,
        assetFileNames: "assets/toolbar-[name][extname]",
      },
    },
  },
  resolve: {
    alias: {
      "@": path.resolve(process.cwd(), "src"),
    },
  },
});
