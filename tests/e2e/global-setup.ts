import { execFileSync } from 'node:child_process';
import { closeSync, existsSync, mkdirSync, openSync } from 'node:fs';
import path from 'node:path';
import { e2eDatabase, e2eEnv } from '../../playwright.config';

export default function globalSetup(): void {
    mkdirSync(path.dirname(e2eDatabase), { recursive: true });
    if (!existsSync(e2eDatabase)) {
        closeSync(openSync(e2eDatabase, 'a'));
    }

    execFileSync('php', ['artisan', 'config:clear'], {
        cwd: process.cwd(),
        env: { ...process.env, ...e2eEnv },
        stdio: 'inherit',
    });
    execFileSync('php', ['artisan', 'migrate:fresh', '--seed', '--force', '--no-interaction'], {
        cwd: process.cwd(),
        env: { ...process.env, ...e2eEnv },
        stdio: 'inherit',
    });
    console.log(`Playwright database prepared at ${e2eDatabase}`);
    execFileSync('php', ['artisan', 'db:seed', '--class=BriefSourceE2ESeeder', '--force', '--no-interaction'], {
        cwd: process.cwd(),
        env: { ...process.env, ...e2eEnv },
        stdio: 'inherit',
    });
    execFileSync('php', ['artisan', 'db:seed', '--class=FinanceE2ESeeder', '--force', '--no-interaction'], {
        cwd: process.cwd(), env: { ...process.env, ...e2eEnv }, stdio: 'inherit',
    });

}
