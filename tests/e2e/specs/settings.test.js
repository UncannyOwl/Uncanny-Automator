/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
const { PlaywrightAutomatorSettings } = require('../helpers/playwright-automator-settings');
/**
 * This test will check the settings page of the Automator plugin
 */ 

test.describe( 'Settings page checks', () => {
	test( 'License tab', async ( { admin, page }) => {

		const Settings = new PlaywrightAutomatorSettings(admin, page);

		await Settings.goTo();

		await Settings.openTab('License');

     	await expect(page.getByText('Access app integrations')).toBeVisible();
		await expect(page.getByText('Connect your site')).toBeVisible();
		await expect(page.getByText('Get Uncanny Automator Pro')).toBeVisible();

	} );

	test( 'Data management tab', async ( { admin, page }) => {

		const Settings = new PlaywrightAutomatorSettings(admin, page);

		await Settings.goTo();

		await Settings.openTab('Data management');

		await expect(page.getByText('Prune recipe logs')).toBeVisible();
		await expect(page.getByRole('button', { name: 'Delete Logs' })).toBeVisible();

		await expect(page.getByText('Delete recipe records when user is deleted')).toBeVisible();

		await expect(page.getByText('Immediately delete log entries when recipes are completed')).toBeVisible();

		await expect(page.getByText('Auto-prune activity logs')).toBeVisible();

  	} );

  	
   test( 'Improve Automator tab', async ( { admin, page }) => {
		await page.goto('wp-admin/edit.php?post_type=uo-recipe&page=uncanny-automator-config');
		await page.locator('#improve-automator a').click();	

		await expect(page.getByText('Allow usage tracking')).toBeVisible();
		await expect(page.getByText('Have feedback or requests?')).toBeVisible();
		await expect(page.getByText('Is Automator Useful to you?')).toBeVisible();

	} );

	test( 'App integrations tab', async ( { admin, page }) => {

		await page.goto('wp-admin/edit.php?post_type=uo-recipe&page=uncanny-automator-config');

  		await page.locator('uo-tab').filter({ hasText: 'App integrations' }).locator('a').click();
	
		await expect(page.getByText('App integrations use credits')).toBeVisible();
		await expect(page.locator('.uap-settings-panel uo-button').getByText('Connect your site')).toBeVisible();
		await expect(page.getByText('Learn more')).toBeVisible();

		await expect(page.getByText('ActiveCampaign')).toBeVisible();
		await expect(page.getByText('Brevo')).toBeVisible();
		await expect(page.getByText('ClickUp')).toBeVisible();
		await expect(page.getByText('Constant Contact')).toBeVisible();
		
	
	} );

	test( 'Advanced tab', async ( { admin, page }) => {

		await page.goto('wp-admin/edit.php?post_type=uo-recipe&page=uncanny-automator-config');

		await page.locator('uo-tab').filter({ hasText: 'Advanced' }).locator('a').click();

		await page.locator('#background_actions a').click();

		await expect(page.locator('form').getByText('Background actions')).toBeVisible();

		await expect(page.getByRole('button', { name: 'Save settings' })).toBeVisible();

		await page.locator('#automator_cache a').click();
		await expect(page.locator('form').getByText('Automator cache')).toBeVisible();
			
	} );

} );

