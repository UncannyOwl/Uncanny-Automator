/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
const { PlaywrightAutomatorDashboard } = require('../helpers/playwright-automator-dashboard');

test.beforeEach( async ({ requestUtils }) => {
	await requestUtils.activatePlugin('uncanny-automator');
} );

/**
 * This test will go to Automator Dashboard and confirm that the common menus elements are visible
 */
test('Check Automator menu items', async ({ admin, page }) => {

	const Dashboard = new PlaywrightAutomatorDashboard(admin, page);

	await Dashboard.goTo();

	let menu = page.locator('#menu-posts-uo-recipe');

	let menus = [
		'Setup wizard',
		'Dashboard',
		'All recipes',
		'Add new',
		'Categories',
		'Tags',
		'All integrations',
		'App integrations',
		'Logs',
		'Status',
		'Settings',
		'Upgrade to Pro'
	];

	for (let i = 0; i < menus.length; i++) {
		await expect(menu.getByRole('link', { name: menus[i] })).toBeVisible();
	}

  });

