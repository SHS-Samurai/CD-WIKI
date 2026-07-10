import { resolve } from "node:path";

import { defineConfig } from "vite";


export default defineConfig({
  build: {
    emptyOutDir: true,
    lib: {
      entry: resolve(import.meta.dirname, "src/wiki-editor.js"),
      fileName: () => "wiki-editor.js",
      formats: ["iife"],
      name: "CdWikiEditor",
    },
    outDir: resolve(import.meta.dirname, "../../static/editor"),
  },
});
