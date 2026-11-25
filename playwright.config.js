const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests/Browser',
  testMatch: '*.spec.js',
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  use: {
    baseURL: 'http://127.0.0.1:8123',
    channel: process.env.PLAYWRIGHT_CHROME_CHANNEL || undefined,
    trace: 'retain-on-failure',
  },
  webServer: {
    command: 'npm run build:test-css && php -S 127.0.0.1:8123 tests/Browser/server.php',
    url: 'http://127.0.0.1:8123/health',
    reuseExistingServer: false,
    stderr: 'ignore',
    timeout: 60000,
  },
});
