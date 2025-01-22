<?php
/**
 * The Wrapper template for displaying Automator template library admin page.
 * Utlizes the Automator Template Library Lit components.
 *
 * @package     Uncanny_Automator/views
 * @version     0.0.1
*/

namespace Uncanny_Automator;

?>

<div id="uap-template-library" class="uap-template-library__page-wrap">
	<div id="uap-template-library__header">
		<h1 class="wp-heading-inline uap-template-library__page-title">
			<?php echo esc_html__( 'Recipe templates', 'uncanny-automator' ); ?>
		</h1>

	</div><!-- #uap-template-library__header -->

	<div id="uap-template-library__content-wrapper">

		<aside class="uap-template-library__sidebar">
			<div class="uap-template-library__search-wrapper">
				<label for="uap-template-library__search" class="screen-reader-text">
					<?php echo esc_html__( 'Search templates', 'automator' ); ?>
				</label>
				<uap-rtl-search></uap-rtl-search>
			</div><!-- .uap-template-library__search-wrapper -->

			<div class="uap-template-library__filters-wrapper">
				<uap-rtl-results-base-filters></uap-rtl-results-base-filters>
			</div><!-- .uap-template-library__filters-wrapper -->

			<div id="uap-template-library__menu-wrapper">
				<uap-rtl-category-menu></uap-rtl-category-menu>
				<uap-rtl-integration-menu></uap-rtl-integration-menu>
			</div><!-- .uap-template-library__filters-wrapper -->

		</aside><!-- #uap-template-library__sidebar -->

		<div class="uap-template-library__content">
			<uap-rtl-grid-display></uap-rtl-grid-display>
		</div><!-- .uap-template-library__content -->

	</div><!-- #uap-template-library__content-wrapper -->

</div><!-- #uap-template-library -->
