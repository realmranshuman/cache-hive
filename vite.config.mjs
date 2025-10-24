import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import path from "path";

export default defineConfig({
  plugins: [
    react({
      jsxRuntime: "classic",
    }),
  ],
  build: {
    outDir: "build",
    emptyOutDir: true,
    manifest: false,
    rollupOptions: {
      external: ["react", "react-dom", "@wordpress/element"],
      input: "src/index.tsx",
      output: {
        format: "iife",
        entryFileNames: "index.js",
        globals: {
          react: "React",
          "react-dom": "ReactDOM",
          "@wordpress/element": "wp.element",
        },
      },
    },
  },
  resolve: {
    alias: {
      "@": path.resolve(process.cwd(), "src"),
    },
  },
});
