import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.dirname(fileURLToPath(import.meta.url));
const e2eDatabase = path.join(root, 'database', 'e2e.sqlite');
const appUrl = 'http://127.0.0.1:8123';
const e2eEnv = {
    APP_ENV: 'testing',
    APP_DEBUG: 'false',
    APP_URL: appUrl,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: e2eDatabase,
    DEMO_DATA: 'true',
    CACHE_STORE: 'array',
    // 'array' descarta a sessão entre requisições, o que apaga os erros de
    // validação em flash e impede o navegador de cobrir esse caminho.
    SESSION_DRIVER: 'file',
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'array',
    HEALTH_CHECK_TOKEN: 'cycle01-health-token',
};

export default defineConfig({
    testDir: './tests/e2e',
    globalSetup: './tests/e2e/global-setup.ts',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: 1,
    reporter: process.env.CI ? [['line'], ['html', { open: 'never' }]] : 'list',
    use: {
        baseURL: appUrl,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    projects: [
        { name: 'desktop', use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } } },
        { name: 'tablet', use: { ...devices['Desktop Chrome'], viewport: { width: 1024, height: 768 } } },
        { name: 'mobile', use: { ...devices['Desktop Chrome'], viewport: { width: 390, height: 844 }, isMobile: true } },
    ],
    webServer: {
        // The readiness probe runs before globalSetup on a fresh checkout.
        command: 'npm run build && php artisan migrate --force --no-interaction && php artisan serve --host=127.0.0.1 --port=8123',
        cwd: root,
        url: `${appUrl}/login`,
        reuseExistingServer: false,
        timeout: 120_000,
        env: { ...process.env, ...e2eEnv },
    },
});

export { e2eDatabase, e2eEnv };
