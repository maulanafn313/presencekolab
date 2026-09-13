import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    reporter: [['list'], ['html', { open: 'never' }]],
    testDir: './tests/Browser',
    use: { baseURL: 'http://127.0.0.1:8028', trace: 'retain-on-failure' },
    projects: [
        { name: 'desktop-chromium', use: { ...devices['Desktop Chrome'] } },
        { name: 'android-emulation', use: { ...devices['Pixel 7'] } },
    ],
    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=8028',
        url: 'http://127.0.0.1:8028/up', reuseExistingServer: false,
        env: { BACKUP_AUTO_ENABLED: 'false' },
    },
});
