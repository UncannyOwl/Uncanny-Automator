<?php
/**
 * Class Name
 *
 * Short description
 *
 * @class   Recipe_Metaboxes
 * @since   3.0
 * @version 3.0
 * @package Uncanny_Automator
 * @author  Saad S.
 */


namespace Uncanny_Automator;

/**
 * Class Recipe_Post_Metabox
 *
 * @package Uncanny_Automator
 */
class Recipe_Post_Metabox {
	/**
	 * Recipe_Post_Metabox constructor.
	 */
	public function __construct() {
		// Adding entry point for JS based triggers and actions UI into Meta Boxes
		add_action( 'add_meta_boxes', array( $this, 'recipe_add_meta_box_ui' ), 11 );

	}

	/**
	 * Creates an entry point with in a metabox to add JS / Rest-Api based UI
	 */
	public function recipe_add_meta_box_ui() {
		// Get global $post
		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		// Get recipe type
		$recipe_type = get_post_meta( $post->ID, 'uap_recipe_type', true );

		// Create variable to save the title of the triggers metabox,
		// and add the default value (on load value)
		/* translators: Trigger type. Logged-in triggers are triggered only by logged-in users */

		// Check if the user didn't select a recipe type yet
		if ( empty( $recipe_type ) ) {
			$triggers_metabox_title = apply_filters( 'uap_meta_box_title', esc_attr__( 'Triggers', 'uncanny-automator' ), $recipe_type );
		} else {
			if ( 'anonymous' === (string) $recipe_type ) {
				$triggers_metabox_title = apply_filters( 'uap_meta_box_title', esc_attr__( 'Trigger', 'uncanny-automator' ), $recipe_type );
			} else {
				$triggers_metabox_title = apply_filters( 'uap_meta_box_title', esc_attr__( 'Triggers', 'uncanny-automator' ), $recipe_type );
			}
		}

		add_meta_box(
			'uo-recipe-triggers-meta-box-ui',
			$triggers_metabox_title,
			function () {
				ob_start();
				?>
				<div class="uap">
					<div id="recipe-triggers-ui" class="metabox__content uap-clear">

						<!-- Placeholder content -->
						<div class="uap-placeholder">
							<div class="item item--trigger">
								<div>
									<div class="item-actions">
										<div class="item-actions__btn">
											<i class="uo-icon uo-icon--ellipsis-h"></i>
										</div>
									</div>
									<div class="item-icon"></div>
									<div class="item-title"></div>
								</div>
								<div class="item__content">
									<div class="item-integrations">
										<div class="item-integration">
											<div class="item-integration__logo"></div>
											<div class="item-integration__name"></div>
										</div>
										<div class="item-integration">
											<div class="item-integration__logo"></div>
											<div class="item-integration__name"></div>
										</div>
									</div>
								</div>
							</div>
						</div>
						<!-- End of placeholder content -->

					</div>
				</div>
				<?php
				// HTML is included. Ignoring
				echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			},
			'uo-recipe',
			'uap_items',
			'high'
		);

		add_meta_box(
			'uo-recipe-actions-meta-box-ui',
			esc_attr__( 'Actions', 'uncanny-automator' ),
			function () {
				ob_start();
				?>
				<div class="uap">
					<div id="recipe-actions-ui" class="metabox__content uap-clear">

						<!-- Placeholder content -->
						<div class="uap-placeholder">
							<div class="item item--action">
								<div>
									<div class="item-actions">
										<div class="item-actions__btn">
											<i class="uo-icon uo-icon--ellipsis-h"></i>
										</div>
									</div>
									<div class="item-icon"></div>
									<div class="item-title"></div>
								</div>
							</div>
							<div class="metabox__footer">
								<div class="uap-placeholder-checkbox">
									<div class="uap-placeholder-checkbox__field"></div>
									<div class="uap-placeholder-checkbox__label"></div>
								</div>
							</div>
						</div>
						<!-- End of placeholder content -->

					</div>
				</div>
				<?php
				// HTML is included. Ignoring
				echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			},
			'uo-recipe',
			'uap_items',
			'high'
		);

		add_action(
			'edit_form_after_title',
			function () {
				global $post, $wp_meta_boxes;
				do_meta_boxes( get_current_screen(), 'uap_items', $post );
				unset( $wp_meta_boxes[ get_post_type( $post ) ]['uap_items'] );
			}
		);

		add_meta_box(
			'uo-automator-publish',
			esc_attr__( 'Recipe', 'uncanny-automator' ),
			function () {
				ob_start();
				?>
				<div id="uo-automator-publish-metabox" class="uap">

					<!-- Placeholder content -->
					<div class="uap-placeholder">
						<div id="uap-publish-metabox">
							<div class="metabox__content">
								<div class="publish-row">
									<div class="publish-row__visible">
										<span class="publish-row__icon"></span>
										<span class="publish-row__name"></span>
										<span class="publish-row__value"></span>
										<span class="publish-row__edit"></span>
									</div>
								</div>
								<div class="publish-row">
									<div class="publish-row__visible">
										<span class="publish-row__icon"></span>
										<span class="publish-row__name"></span>
										<span class="publish-row__value"></span>
										<span class="publish-row__edit"></span>
									</div>
								</div>
								<div class="publish-row">
									<div class="publish-row__visible">
										<span class="publish-row__icon"></span>
										<span class="publish-row__name"></span>
										<span class="publish-row__value"></span>
										<span class="publish-row__edit"></span>
									</div>
								</div>
							</div>
							<div class="metabox__footer">
								<div class="publish-footer">
									<div class="publish-footer__row uap-clear">
										<div class="publish-footer__left">
											<a class="publish-footer__move-to-draft"></a>
										</div>
										<div class="publish-footer__right"></div>
									</div>
								</div>
							</div>
						</div>
					</div>
					<!-- End of placeholder content -->

				</div>
				<?php
				// HTML is included. Ignoring
				echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			},
			'uo-recipe',
			'side',
			'high'
		);
	}
}
