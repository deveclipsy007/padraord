import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        include: ['resources/js/**/*.test.{ts,tsx}'],
        exclude: ['tests/e2e/**', 'node_modules/**'],
    },
});
