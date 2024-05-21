<?php
namespace Uncanny_Automator;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}
?>

<a id="ua-recipe-import-title-button" href="#" class="upload-view-toggle page-title-action" style="opacity:0;transition:opacity 1s ease-in-out">
	<?php echo esc_attr_x( 'Import', 'Import Recipe', 'uncanny-automator' ); ?>
</a>

<div id="ua-recipe-import-form" class="upload-plugin-wrap">
	<div class="upload-plugin">
		<p class="install-help">
			<?php echo esc_attr_x( 'If you have a recipe in .json format, you can import it by uploading it in the section below.', 'Import Recipe', 'uncanny-automator' ); ?>
		</p>
		<form method="post" enctype="multipart/form-data" class="wp-upload-form" action="">
			<?php wp_nonce_field( 'bulk-posts' ); // Prevents table nonce checks from causing issues. ?>
			<?php wp_nonce_field( 'Aut0Mat0R', '_wpnonce_ua_recipe_import', false ); ?>
			<label class="screen-reader-text" for="recipejson">
				<?php echo esc_attr_x( 'Recipe json file', 'Import Recipe', 'uncanny-automator' ); ?>
			</label>
			<input type="file" id="recipejson" name="recipejson" accept=".json">
			<input type="submit" name="import-recipe-submit" id="import-recipe-submit" class="button" value="<?php echo esc_attr_x( 'Import', 'Import Recipe', 'uncanny-automator' ); ?>" disabled="">
		</form>
	</div>
</div>
<script id="ua-recipe-import">
	document.addEventListener("DOMContentLoaded", function () {
		// Move the button after the title
		var titleButton = document.getElementById('ua-recipe-import-title-button');
		var pageTitleAction = document.querySelector('.wrap .page-title-action');
		pageTitleAction.parentNode.insertBefore(titleButton, pageTitleAction.nextSibling);

		// Fade in the titleButton
		setTimeout(function() {
			titleButton.style.opacity = "1";
		}, 100);

		// Toggle body class to show/hide the form.
		titleButton.addEventListener('click', function (e) {
			e.preventDefault();
			document.body.classList.toggle('show-upload-view');
		});

		// Move the form after the header hr
		var form = document.getElementById('ua-recipe-import-form');
		var headerEnd = document.querySelector('.wrap .wp-header-end');
		headerEnd.parentNode.insertBefore(form, headerEnd.nextSibling);
	});
</script>
