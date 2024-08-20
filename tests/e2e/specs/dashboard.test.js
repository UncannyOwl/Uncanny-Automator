/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
const { PlaywrightAutomatorDashboard } = require('../helpers/playwright-automator-dashboard');

test.beforeEach( async ({ requestUtils }) => {
	await requestUtils.activatePlugin('uncanny-automator');
} );

/**
 * This test will go to Automator Dashboard and check if the common widgets are visible
 */
test('Dashboard page loads and has the common widgets', async ({ admin, page }) => {

	const Dashboard = new PlaywrightAutomatorDashboard(admin, page);

	await Dashboard.goTo();

	// Check title
	await expect(Dashboard.title).toBeVisible();

	// Check the connect button URL
	await expect(Dashboard.button).toHaveAttribute('href', /page=uncanny-automator-setup-wizard/ );
	
	// Check the common articles
	await expect(Dashboard.kbList.getByText('Getting started')).toBeVisible();
	await expect(Dashboard.kbList.getByText('Key resources')).toBeVisible();
	await expect(Dashboard.kbList.getByText('Integrations FAQ')).toBeVisible();
	await expect(Dashboard.kbList.getByText('Registering users')).toBeVisible();

  });