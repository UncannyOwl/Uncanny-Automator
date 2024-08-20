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

/**
 * This test will create a logged-in recipe with a random number in its title
 * It will use the "View page" trigger and the "Create a post" action
 * The title of the created post will be created using the {{recipe_title}} token
 * The script will visit the Sample page and then verify that the post was created
 * with the correct title that has the random number
 * 
*/
test('Create and test a logged-in recipe with tokens', async ({ admin, page }) => {

	const Recipe = new PlaywrightAutomatorRecipe(admin, page);

    await deleteAllRecipes(admin, page);

    // Creata a recipe
    await Recipe.create( 'logged-in', recipeTitle );

    // Wait until the integration selector is visible
	await expect(page.getByText('Select an integration')).toBeVisible();

    // Click to add a trigger
    await expect(page.getByRole('button', { name: 'Add action' })).toBeVisible();
    await page.getByRole('button', { name: 'Add action' }).click();

    // Select the WordPress integration
    await page.locator('#recipe-triggers-ui .item-integration').getByText('WordPress').first().click();

    // Select the "user views page" trigger
    await page.getByText('A user views a page').click();

    // Save the action
    await page.locator('#recipe-triggers-ui').getByText('Save').click();

    await page.waitForTimeout(1000);

    // Wait until the action is saved
    await expect(page.getByText('A user views Page: Any Page')).toBeVisible();

    // Click to add an action
    await page.getByRole('button', { name: 'Add action' }).click();

    // Select the WordPress integration
    await page.locator('#recipe-actions-ui .item-integration').getByText('WordPress').first().click();

    // Select the "Create a post" action
    await page.getByText('Create a post').click();

    // Fill in the fields
    await page.locator('div:nth-child(7) > .form-input > .form-variable > .form-variable__element > .form-variable__render-container > .form-element__input-cm > .CodeMirror').click();
    await page.locator('div:nth-child(7) > .form-input > .form-variable > .form-variable__element > .form-variable__render-container > .form-element__input-cm > .CodeMirror > div > textarea').fill('{{recipe_name}}');
    
    // Save the action
    await page.locator('#recipe-actions-ui').getByText('Save').click();

    // Wait until the action is saved
    await expect(page.getByText('Create Type: Post')).toBeVisible();

    // Publish the recipe
    await page.locator('#uap-publish-metabox').getByText('Draft').click();

    await page.waitForTimeout(500);

    // Wait until the recipe is published
    await expect(page.locator('#uap-publish-metabox').getByText('Live')).toBeVisible();

    await admin.visitAdminPage( '/edit.php?post_type=uo-recipe' );

    await expect(page.locator('#the-list tr').first()).toHaveClass(/status-publish/);

    // Visit the Sample page
    await page.goto('/sample-page/');

    // Wait unit the end of the content is visible
    await expect(page.getByText('Have fun!')).toBeVisible();

    await page.waitForTimeout(500);

    await admin.visitAdminPage( '/edit.php' );

    // Check that there is a new post with the title that has the specific  number in it
    await expect(page.locator('#the-list a.row-title').first()).toContainText(recipeTitle);

    await page.waitForTimeout(500);
});


// Create a new recipe for this one so it doesn't depend on the previous one

// test('Limit recipe runs', async ({ admin, page }) => {

//     await admin.visitAdminPage( '/edit.php?post_type=uo-recipe' );

//     await page.locator('.row-title').getByText(recipeTitle, { exact: true }).click();

//     await expect(page.getByText('Completed runs: 1')).toBeVisible();

//     await page.locator('#uo-automator-publish-metabox').getByText('Edit', { exact: true }).first().click();
//     await page.getByRole('textbox', { name: 'Unlimited' }).click();
//     await page.getByRole('textbox', { name: 'Unlimited' }).fill('2');
//     await page.getByText('Save').first().click();

//     let recipeUrl = page.url();

//     await page.goto('/sample-page/');

//     await page.goto('/sample-page/');

//     await page.goto(recipeUrl);

//     await expect(page.getByText('Completed runs: 2')).toBeVisible();
// });