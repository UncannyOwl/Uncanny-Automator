

exports.deleteAllRecipes = async function deleteAllRecipes(admin, page) {

	await admin.visitAdminPage( 'edit.php?post_type=uo-recipe' );

    if ( await page.locator('#doaction').isVisible() ) {
      await page.locator('#cb-select-all-1').check();
      await page.locator('#bulk-action-selector-top').selectOption('Move to Trash');
      await page.locator('#doaction').click();
    }
}