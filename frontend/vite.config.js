import { defineConfig, loadEnv } from "vite";
import vue from "@vitejs/plugin-vue";
import path from "path";

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd());
  const API_BASE = env.VITE_API_BASE_URL;
  if (!API_BASE) {
    throw new Error("VITE_API_BASE_URL must be set in environment (no fallback allowed).");
  }

  return {
    plugins: [vue()],
    resolve: {
      alias: {
        "@": path.resolve(__dirname, "./src"),
      },
    },
    server: {
      proxy: {
        "/api": {
          target: API_BASE,
          changeOrigin: true,
          secure: false,
        },
        "/storage": {
          target: API_BASE,
          changeOrigin: true,
          secure: false,
        },
      },
    },
  };
});
