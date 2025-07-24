<?php
namespace Uncanny_Automator;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}
?>

<a id="ua-recipe-import-title-button" href="#" class="upload-view-toggle page-title-action" style="display:none">
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
	// Function to initialize the import form
	function initializeImportForm() {
		var titleButton = document.getElementById('ua-recipe-import-title-button');
		var pageTitleAction = document.querySelector('.wrap .page-title-action');
		var form = document.getElementById('ua-recipe-import-form');
		var headerEnd = document.querySelector('.wrap .wp-header-end');
		
		// Always show the button if we have it
		if (titleButton) {
			titleButton.style.display = "inline-block";
			
			// Add click handler if not already added
			if (!titleButton.hasAttribute('data-initialized')) {
				titleButton.addEventListener('click', function (e) {
					e.preventDefault();
					document.body.classList.toggle('show-upload-view');
				});
				titleButton.setAttribute('data-initialized', 'true');
			}
		}
		
		// Move button if target element is available
		if (titleButton && pageTitleAction) {
			pageTitleAction.parentNode.insertBefore(titleButton, pageTitleAction.nextSibling);
		}
		
		// Move form if target element is available
		if (form && headerEnd) {
			headerEnd.parentNode.insertBefore(form, headerEnd.nextSibling);
		}
		
		// Return true if both elements were found
		return !!(pageTitleAction && headerEnd);
	}

	// Try to initialize immediately
	var success = initializeImportForm();
	
	// If not successful, retry with increasing delays
	if (!success) {
		var attempts = 0;
		var maxAttempts = 5;
		
		function retry() {
			attempts++;
			if (attempts <= maxAttempts) {
				setTimeout(function() {
					if (!initializeImportForm()) {
						retry();
					}
				}, attempts * 5); // 5ms, 10ms, 15ms, 20ms, 25ms
			}
		}
		
		retry();
	}
</script>
