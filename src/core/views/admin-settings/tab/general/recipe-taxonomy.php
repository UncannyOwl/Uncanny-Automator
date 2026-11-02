<?php
/**
 * Recipe taxonomy
 * Settings > General > Recipe categories / Recipe tags
 *
 * Embeds the native WordPress term management screen for a recipe taxonomy.
 * The screen stays server-rendered light DOM on purpose: WordPress core's admin-tags and inline-edit-tax scripts locate #addtag, #submit and #the-list in the document, the wp-admin global stylesheets must reach the list table, and the non-JS fallbacks are plain form posts to edit-tags.php; shadow DOM would break all three.
 * Markup mirrors wp-admin/edit-tags.php so those core scripts work unmodified, and mutations are processed by core handlers (add-tag, delete-tag, inline-save-tax, or the edit-tags.php redirect round-trip back to this tab).
 * Being light DOM inside the .uap page scope, the settings design system cascade reaches this markup; the page-settings stylesheet rescales the embedded controls to the settings field anatomy.
 * The panel intentionally renders without a uap-settings-panel-title block (the sidebar item already names the screen), and extension assets keyed to the edit-tags.php admin hook do not load here; the classic URLs remain the supported surface for such extensions.
 *
 * @since   8.0
 * @package Uncanny_Automator
 *
 * Variables:
 * $taxonomy        The taxonomy slug (recipe_category or recipe_tag)
 * $tab_key         The General sub-tab key (recipe-categories or recipe-tags)
 * $screen_id       The wp-admin screen id (edit-recipe_category or edit-recipe_tag)
 * $taxonomy_object The registered WP_Taxonomy for $taxonomy
 * $terms_table     The prepared WP_Terms_List_Table
 * $can_edit_terms  Whether the current user can edit terms
 * $current_page    The clamped current page number of the terms table
 * $notice_type     The uas-alert type for the result message (success or error)
 * $message         The result message resolved from the edit-tags.php redirect, or false
 */

namespace Uncanny_Automator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="uap-settings-panel">
	<div class="uap-settings-panel-top">

		<div class="uap-settings-panel-content uap-recipe-taxonomy">

			<?php

			// The settings page hides every .notice element, so the result of an edit-tags.php round-trip surfaces through the design-system alert instead.
			if ( $message ) {
				printf(
					'<uas-alert id="message" type="%1$s">%2$s</uas-alert>',
					esc_attr( $notice_type ),
					esc_html( $message )
				);
			}

			?>

			<div id="ajax-response"></div>

			<form class="search-form wp-clearfix" method="get">
				<input type="hidden" name="post_type" value="<?php echo esc_attr( AUTOMATOR_POST_TYPE_RECIPE ); ?>" />
				<input type="hidden" name="page" value="uncanny-automator-config" />
				<input type="hidden" name="tab" value="general" />
				<input type="hidden" name="general" value="<?php echo esc_attr( $tab_key ); ?>" />
				<?php $terms_table->search_box( $taxonomy_object->labels->search_items, 'tag' ); ?>
			</form>

			<?php if ( $can_edit_terms ) { ?>

			<div id="col-container" class="wp-clearfix">

				<div id="col-left">
					<div class="col-wrap">

						<?php
						/** This action is documented in wp-admin/edit-tags.php */
						do_action( "{$taxonomy}_pre_add_form", $taxonomy ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
						?>

						<div class="form-wrap">
							<h2><?php echo esc_html( $taxonomy_object->labels->add_new_item ); ?></h2>

							<form id="addtag" method="post" action="<?php echo esc_url( admin_url( 'edit-tags.php' ) ); ?>" class="validate"
								<?php
								/** This action is documented in wp-admin/edit-tags.php */
								do_action( "{$taxonomy}_term_new_form_tag" ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
								?>
							>
								<input type="hidden" name="action" value="add-tag" />
								<input type="hidden" name="screen" value="<?php echo esc_attr( $screen_id ); ?>" />
								<input type="hidden" name="taxonomy" value="<?php echo esc_attr( $taxonomy ); ?>" />
								<input type="hidden" name="post_type" value="<?php echo esc_attr( AUTOMATOR_POST_TYPE_RECIPE ); ?>" />
								<?php wp_nonce_field( 'add-tag', '_wpnonce_add-tag' ); ?>

								<div class="form-field form-required term-name-wrap">
									<label for="tag-name"><?php echo esc_html_x( 'Name', 'Recipe taxonomy', 'uncanny-automator' ); ?></label>
									<input name="tag-name" id="tag-name" type="text" value="" size="40" aria-required="true" aria-describedby="name-description" />
									<p id="name-description"><?php echo esc_html_x( 'The name is how it appears in recipe lists and filters.', 'Recipe taxonomy', 'uncanny-automator' ); ?></p>
								</div>

								<div class="form-field term-slug-wrap">
									<label for="tag-slug"><?php echo esc_html_x( 'Slug', 'Recipe taxonomy', 'uncanny-automator' ); ?></label>
									<input name="slug" id="tag-slug" type="text" value="" size="40" aria-describedby="slug-description" />
									<p id="slug-description"><?php echo esc_html_x( 'The "slug" is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.', 'Recipe taxonomy', 'uncanny-automator' ); ?></p>
								</div>

								<?php if ( is_taxonomy_hierarchical( $taxonomy ) ) { ?>

								<div class="form-field term-parent-wrap">
									<label for="parent"><?php echo esc_html( $taxonomy_object->labels->parent_item ); ?></label>
									<?php
									wp_dropdown_categories(
										array(
											'hide_empty'   => 0,
											'hide_if_empty' => false,
											'taxonomy'     => $taxonomy,
											'name'         => 'parent',
											'orderby'      => 'name',
											'hierarchical' => true,
											'show_option_none' => esc_html_x( 'None', 'Recipe taxonomy', 'uncanny-automator' ),
											'aria_describedby' => 'parent-description',
										)
									);
									?>
									<p id="parent-description"><?php echo esc_html_x( 'Assign a parent term to create a hierarchy.', 'Recipe taxonomy', 'uncanny-automator' ); ?></p>
								</div>

								<?php } ?>

								<div class="form-field term-description-wrap">
									<label for="tag-description"><?php echo esc_html_x( 'Description', 'Recipe taxonomy', 'uncanny-automator' ); ?></label>
									<textarea name="description" id="tag-description" rows="5" cols="40" aria-describedby="description-description"></textarea>
									<p id="description-description"><?php echo esc_html_x( 'A short description of this term.', 'Recipe taxonomy', 'uncanny-automator' ); ?></p>
								</div>

								<?php
								/** This action is documented in wp-admin/edit-tags.php */
								do_action( "{$taxonomy}_add_form_fields", $taxonomy ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
								?>

								<p class="submit">
									<?php submit_button( $taxonomy_object->labels->add_new_item, 'primary', 'submit', false ); ?>
									<span class="spinner"></span>
								</p>

								<?php
								/** This action is documented in wp-admin/edit-tags.php */
								do_action( "{$taxonomy}_add_form", $taxonomy ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
								?>
							</form>
						</div>

					</div>
				</div><!-- /col-left -->

				<div id="col-right">
					<div class="col-wrap">

			<?php } ?>

						<form id="posts-filter" method="post" action="<?php echo esc_url( admin_url( 'edit-tags.php' ) ); ?>">
							<input type="hidden" name="taxonomy" value="<?php echo esc_attr( $taxonomy ); ?>" />
							<input type="hidden" name="post_type" value="<?php echo esc_attr( AUTOMATOR_POST_TYPE_RECIPE ); ?>" />

							<?php if ( $current_page > 1 ) { ?>
								<input type="hidden" name="paged" value="<?php echo esc_attr( $current_page ); ?>" />
							<?php } ?>

							<?php $terms_table->display(); ?>
						</form>

						<?php
						/** This action is documented in wp-admin/edit-tags.php */
						do_action( "after-{$taxonomy}-table", $taxonomy ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.NamingConventions.ValidHookName.UseUnderscores -- Core hook name from wp-admin/edit-tags.php.
						?>

			<?php if ( $can_edit_terms ) { ?>

					</div>
				</div><!-- /col-right -->

			</div><!-- /col-container -->

			<?php } ?>

			<?php

			if ( $can_edit_terms ) {
				$terms_table->inline_edit();
			}

			?>

		</div>

	</div>
</div>
