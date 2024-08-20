/**
 * External dependencies
 */
import path from 'node:path';
import { defineConfig, devices } from '@playwright/test';
require('dotenv').config({path: './tests/e2e/.env'});

/**
 * WordPress dependencies
 */
process.env.WP_ARTIFACTS_PATH ??= './tests/e2e/.artifacts/';
process.env.STORAGE_STATE_PATH ??= './tests/e2e/.artifacts/storage-states/admin.json';

process.env.SCREENSHOTS_PATH ??= path.join(	process.env.WP_ARTIFACTS_PATH, 'screenshots' );

const outputFolder = path.join( process.env.WP_ARTIFACTS_PATH, 'test-results' );
const reportFolder = path.join( process.env.WP_ARTIFACTS_PATH, 'test-report' );

const config = defineConfig( {
	testDir: './tests/e2e/specs',
	reporter: process.env.CI ? [ [ 'github' ], ['html', { outputFolder: reportFolder } ] ] : [ [ 'list' ], ['html', { outputFolder: reportFolder }] ],
	forbidOnly: !! process.env.CI,
	// fullyParallel: false,
	workers: 1,
	retries: process.env.CI ? 2 : 0,
	timeout: parseInt( process.env.TIMEOUT || '', 10 ) || 100_000, // Defaults to 100 seconds.
	// Don't report slow test "files", as we will be running our tests in serial.
	reportSlowTests: null,
	outputDir: outputFolder,
	snapshotPathTemplate:
		'{testDir}/{testFileDir}/__snapshots__/{arg}-{projectName}{ext}',
	use: {
		baseURL: process.env.WP_BASE_URL,
		headless: (process.env.HEADLESS === 'true'),
		viewport: {
			width: 960,
			height: 700,
		},
		ignoreHTTPSErrors: true,
		locale: 'en-US',
		contextOptions: {
			reducedMotion: 'reduce',
			strictSelectors: true,
		},
		storageState: process.env.STORAGE_STATE_PATH,
		actionTimeout: 10_000, // 10 seconds.
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: 'on-first-retry',
	},
	// webServer: {
	// 	command: 'npm run wp-env start',
	// 	port: 8889,
	// 	timeout: 120_000, // 120 seconds.
	// 	reuseExistingServer: true,
	// },
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
	globalSetup: require.resolve( './tests/e2e/config/global-setup.js' ),
} );

export default config;
