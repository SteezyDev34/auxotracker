import { fileURLToPath, URL } from 'node:url';

import { PrimeVueResolver } from '@primevue/auto-import-resolver';
import vue from '@vitejs/plugin-vue';
import Components from 'unplugin-vue-components/vite';
import { defineConfig, loadEnv } from 'vite';

// https://vitejs.dev/config/
export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd());
    const API_BASE = env.VITE_API_BASE_URL;
    
    if (!API_BASE) {
        throw new Error("VITE_API_BASE_URL must be set in environment (no fallback allowed).");
    }

    return {
        optimizeDeps: {
            noDiscovery: true
        },
        plugins: [
            vue(),
            Components({
                resolvers: [PrimeVueResolver()]
            })
        ],
        resolve: {
            alias: {
                '@': fileURLToPath(new URL('./src', import.meta.url))
            }
        },
        server: {
            proxy: {
                '/api': {
                    target: API_BASE,
                    changeOrigin: true,
                    secure: false,
                },
                '/storage': {
                    target: API_BASE,
                    changeOrigin: true,
                    secure: false,
                },
            },
        },
    };
});
