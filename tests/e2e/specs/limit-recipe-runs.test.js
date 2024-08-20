/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { deleteAllRecipes } from '../helpers/automator-utils.js';
const { PlaywrightAutomatorRecipe } = require('../helpers/playwright-automator-recipe.js');

const recipeTitle = 'Test recipe: ' + Math.random();

test.beforeAll( async ({ requestUtils }) => {
	await requestUtils.activatePlugin('uncanny-automator');
    await requestUtils.deleteAllPosts();
} );

// Create a new recipe for this one so it doesn't depend on the previous one

test('Limit total recipe runs', async ({ admin, page }) => {

    const Recipe = new PlaywrightAutomatorRecipe(admin, page);

    await deleteAllRecipes(admin, page);

    //Import recipe

    await Recipe.import('./tests/e2e/fixtures/recipe-total-times-test.json');

    // Publish the trigger
    await page.locator('#recipe-triggers-ui').getByText('Draft').click();

    // Wait until the trigger is published
    await expect(page.locator('#recipe-triggers-ui').getByText('Live')).toBeVisible();

    // Publish the action
    await page.locator('#recipe-actions-ui').getByText('Draft').click();

    await page.waitForTimeout(500);
    
    // Wait until the action is published
    await expect(page.locator('#recipe-actions-ui').getByText('Live')).toBeVisible();

    await expect(page.locator('#recipe-actions-ui').getByText('You have to add at least one live triger to your recipe')).toBeHidden();
    await expect(page.locator('#recipe-actions-ui').getByText('You have to add at least one live action to your recipe')).toBeHidden();

    await page.waitForTimeout(1000);

    // Publish the recipe
    await page.locator('#uap-publish-metabox').getByText('Draft').click();
    await page.waitForTimeout(1000);

    // Wait until the recipe is published
    await expect(page.locator('#uap-publish-metabox').getByText('Live', { exact: true })).toBeVisible();

    await page.waitForTimeout(500);

    // Check that the recipe is live
    await admin.visitAdminPage( '/edit.php?post_type=uo-recipe' );

    await expect(page.locator('#the-list tr').first()).toHaveClass(/status-publish/);


    // Visit the Sample page four times
    await page.goto('/sample-page/');
    await page.goto('/sample-page/');
    await page.goto('/sample-page/');

    await admin.visitAdminPage( '/edit.php' );

    // Check that there are only two posts
    await expect(page.locator('#the-list a.row-title')).toHaveCount(2);

    await page.waitForTimeout(500);

});