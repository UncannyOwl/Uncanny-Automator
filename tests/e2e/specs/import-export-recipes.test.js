/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { deleteAllRecipes } from '../helpers/automator-utils.js';
const { PlaywrightAutomatorRecipe } = require('../helpers/playwright-automator-recipe.js');

test.beforeAll( async ({ requestUtils }) => {
	await requestUtils.activatePlugin('uncanny-automator');
    await requestUtils.deleteAllPosts();
} );

test('Import-export a recipe', async ({ admin, page }) => {

    const Recipe = new PlaywrightAutomatorRecipe(admin, page);

    await deleteAllRecipes(admin, page);

    //Import recipe

    await Recipe.import('./tests/e2e/fixtures/recipe-1.json');
    
    await expect(page.getByText('Please make sure to set the correct values before you take this recipe live')).toBeVisible();

    // Ensure the title is correct
    await expect(page.getByLabel('Add title')).toHaveValue('Test recipe 1 (Imported)');

    // CHeck that the trigger is present
    await expect(page.getByText('A user views Page: Any page')).toBeVisible();

    // Check the action
    await page.getByText('Create Type: Post', { exact: true }).click();

    await page.getByText('Type: Post').click();

    // CHeck the token in the post title field
    await expect(page.locator('pre').filter({ hasText: 'Recipe name' })).toBeVisible();

    // Export recipe
    await admin.visitAdminPage( '/edit.php?post_type=uo-recipe' );

    await page.locator('#the-list tr .title').first().hover();

    const downloadPromise = page.waitForEvent('download');

    await page.locator('#the-list tr .title .export').first().click();

    const download = await downloadPromise;

    await expect(download.suggestedFilename()).toContain('test-recipe-1');
    await expect(download.suggestedFilename()).toContain('imported');
    await expect(download.suggestedFilename()).toContain('.json');

    const filePath = process.env.WP_ARTIFACTS_PATH + 'downloads/' + download.suggestedFilename();

    await download.saveAs(filePath);

});

