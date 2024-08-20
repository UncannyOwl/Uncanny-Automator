import { test, expect } from '@wordpress/e2e-test-utils-playwright';


exports.PlaywrightAutomatorRecipe = class PlaywrightAutomatorRecipe {

  /**
   * @param {import('@playwright/test').Page} page
   */
  constructor(admin, page) {
    this.admin = admin;
    this.page = page;

  }

  async create( $type = 'logged-in', $title ) {

    var addNewUrl = 'post-new.php?post_type=uo-recipe';

    await this.admin.visitAdminPage( addNewUrl );

    if ( await this.page.getByLabel('Close Tour').isVisible() ) {
      await this.page.getByLabel('Close Tour').click();
      await this.page.getByRole('button', { name: 'Confirm' }).nth(1).click();
    }

    if ( 'logged-in' === $type ) {
        await this.page.getByText('Logged-in users Recipe will').click();
    } else {
        await this.page.getByText('Everyone').click();
    }

    await this.page.getByRole('button', { name: 'Confirm' }).click();

    await this.waitForSnackbar( 'Recipe type set successfully' );

    return await this.setTitle( $title );
    
  }

  async setTitle( $title ) {
    
    await this.page.getByLabel('Add title').click();
    await this.page.getByLabel('Add title').fill($title);
    await this.page.keyboard.down('Tab');
    await this.page.locator('#recipe-triggers-ui').getByText('Trigger', { exact: true });
    await this.waitForSnackbar( 'Recipe title updated successfully' ); 

    return $title;
  }

  async waitForSnackbar( $text ) {
    await expect(this.page.locator('uo-snackbar').getByText($text) ).toBeVisible();
    await expect(this.page.locator('uo-snackbar').getByText($text) ).toBeHidden();
  }

  async import( $pathToFile ) {
    await this.admin.visitAdminPage( 'edit.php?post_type=uo-recipe' );

    await this.page.locator('#ua-recipe-import-title-button').click();

    //await this.page.getByLabel('Recipe json file').click();

    await this.page.getByLabel('Recipe json file').setInputFiles($pathToFile);
     
    await this.page.getByRole('button', { name: 'Import' }).click();
  }

};