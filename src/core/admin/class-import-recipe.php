<?php

namespace Uncanny_Automator;

use WP_Error;

/**
 * Class Import_Recipe
 *
 * @package Uncanny_Automator
 */
class Import_Recipe {

	/**
	 * Copy recipe parts class instance.
	 *
	 * @var \Uncanny_Automator\Copy_Recipe_Parts
	 */
	public $copy_recipe_parts = null;

	/**
	 * The meta key for the imported recipe warning.
	 *
	 * @var string
	 */
	const IMPORTED_RECIPE_WARNING_META = 'uap_recipe_imported';

	/**
	 * Import_Recipe constructor.
	 *
	 * @return void
	 */
	public function __construct() {

		// Add upload handler and form to the recipe list page.
		add_action( 'admin_init', array( $this, 'handle_upload' ) );
		add_action( 'admin_footer', array( $this, 'render_import_form' ) );

		// Notices.
		add_action( 'automator_show_internal_admin_notice', array( $this, 'maybe_display_import_errors' ) );
		add_action( 'automator_show_internal_admin_notice', array( $this, 'maybe_display_imported_recipe_warning' ) );

		// Clear the warning meta when a recipe is updated.
		add_action( 'automator_recipe_item_deleted', array( $this, 'clear_imported_recipe_warning' ), 10, 3 );
		add_action( 'automator_recipe_option_updated', array( $this, 'clear_imported_recipe_warning' ), 10, 6 );
		add_action( 'automator_recipe_status_updated', array( $this, 'clear_imported_recipe_warning' ), 10, 4 );
		add_action( 'automator_recipe_title_updated', array( $this, 'clear_imported_recipe_warning' ), 10, 3 );

	}

	/**
	 * Handle uploads of recipe JSON files.
	 */
	public function handle_upload() {

		if ( ! automator_filter_has_var( 'import-recipe-submit', INPUT_POST ) ) {
			return;
		}

		if ( ! automator_filter_has_var( '_wpnonce_ua_recipe_import', INPUT_POST ) ) {
			$this->set_import_error( _x( 'Security issue, invalid nonce. Please refresh the page and try again.', 'Import Recipe', 'uncanny-automator' ) );
			return;
		}

		if ( ! wp_verify_nonce( automator_filter_input( '_wpnonce_ua_recipe_import', INPUT_POST ), 'Aut0Mat0R' ) ) {
			$this->set_import_error( _x( 'Security issue, invalid nonce. Please refresh the page and try again.', 'Import Recipe', 'uncanny-automator' ) );
			return;
		}

		if ( ! isset( $_FILES['recipejson'] ) ) {
			$this->set_import_error( _x( 'No recipe .json file uploaded.', 'Import Recipe', 'uncanny-automator' ) );
			return;
		}

		$file = wp_unslash( $_FILES['recipejson'] ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( 'application/json' !== $file['type'] ) {
			$this->set_import_error( _x( 'The uploaded file is not a valid recipe .json file.', 'Import Recipe', 'uncanny-automator' ) );
			return;
		}

		// Read the file.
		$recipe_json = file_get_contents( $file['tmp_name'] ); //phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$recipe_json = json_decode( $recipe_json );

		if ( ! $recipe_json ) {
			$this->set_import_error( _x( 'The uploaded file is not a valid recipe .json file.', 'Import Recipe', 'uncanny-automator' ) );
			return;
		}

		// Import the recipe.
		$new_recipe_id = $this->import_recipe_json( $recipe_json );
		do_action( 'automator_recipe_imported', $new_recipe_id );

		if ( is_wp_error( $new_recipe_id ) ) {
			$this->set_import_error( $new_recipe_id->get_error_message() );
			return;
		}

		// Success - redirect to newly imported recipe.
		$redirect_url = get_edit_post_link( $new_recipe_id, 'url' );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Render the import form.
	 *
	 * @return string - HTML and JS for the import form.
	 */
	public function render_import_form() {

		$current_screen = get_current_screen();
		if ( ! $current_screen instanceof \WP_Screen ) {
			return;
		}

		if ( 'edit-uo-recipe' !== $current_screen->id ) {
			return;
		}

		include Utilities::automator_get_view( 'recipe-adminlist-import.php' );
	}

	/**
	 * Import a recipe from JSON.
	 *
	 * @param object $json - The JSON object to import.
	 *
	 * @return void
	 */
	public function import_recipe_json( $json ) {

		$recipe = isset( $json->recipe ) ? $json->recipe : null;

		// Validate recipe
		if ( ! is_object( $recipe ) || ! isset( $recipe->post, $recipe->meta ) ) {
			return new WP_Error( 'invalid-recipe-json', _x( 'The Uploaded file is not a valid recipe .json file; the recipe object must contain post and meta.', 'Import Recipe', 'uncanny-automator' ) );
		}

		if ( is_null( $this->copy_recipe_parts ) ) {
			$this->copy_recipe_parts = Automator_Load::get_core_class_instance( 'Copy_Recipe_Parts' );
		}

		$this->copy_recipe_parts->is_import = true;

		// Set imported meta message.
		$recipe_meta                                       = (array) $recipe->meta;
		$recipe_meta[ self::IMPORTED_RECIPE_WARNING_META ] = array(
			sprintf(
				/* translators: %s - Y-m-d date */
				_x( 'Recipe imported on %s. Please make sure to set the correct values before you take this recipe live.', 'Import Recipe', 'uncanny-automator' ),
				date_i18n( 'Y-m-d', time() )
			),
		);

		// Copy the recipe.
		$new_recipe_id = $this->copy_recipe_parts->copy( $recipe->post->ID, 0, 'draft', $recipe->post, $recipe_meta );
		if ( empty( $new_recipe_id ) ) {
			$this->copy_recipe_parts->is_import = false;
			return new WP_Error( 'error-copying-recipe', _x( 'Unable to create imported recipe.', 'Import Recipe', 'uncanny-automator' ) );
		}

		// Copy the recipe parts.
		$parts = array( 'triggers', 'actions', 'loops', 'closure' );
		foreach ( $parts as $part ) {
			if ( ! isset( $json->$part ) || ! is_array( $json->$part ) ) {
				continue;
			}

			foreach ( $json->$part as $recipe_part ) {
				if ( ! isset( $recipe_part->post ) || ! isset( $recipe_part->post->ID ) || ! isset( $recipe_part->meta ) ) {
					continue;
				}

				$status       = $this->maybe_adjust_recipe_part_status( 'draft', $recipe_part );
				$part_post_id = $this->copy_recipe_parts->copy( $recipe_part->post->ID, $new_recipe_id, $status, $recipe_part->post, (array) $recipe_part->meta );

				// Handle loops.
				if ( ! empty( $part_post_id ) && 'loops' === $part ) {
					$this->import_loop( $recipe_part, $part_post_id, $new_recipe_id, $recipe->post->ID );
				}
			}
		}

		// Update the conditions meta.
		$this->copy_recipe_parts->copy_action_conditions( $recipe->post->ID, $new_recipe_id );

		$this->copy_recipe_parts->is_import = false;

		return $new_recipe_id;
	}

	/**
	 * Adjust the status of a recipe part ( Run Now triggers should be published )
	 *
	 * @param string $status - The status to adjust.
	 * @param object $recipe_part - The recipe part to adjust.
	 *
	 * @return string - The adjusted status.
	 */
	private function maybe_adjust_recipe_part_status( $status, $recipe_part ) {

		// Bail if the recipe part is not a trigger.
		if ( 'uo-trigger' !== $recipe_part->post->post_type ) {
			return $status;
		}

		// Check if the trigger is a Run Now trigger.
		$code = isset( $recipe_part->meta->code ) && isset( $recipe_part->meta->code[0] ) ? $recipe_part->meta->code[0] : null;
		if ( 'RECIPE_MANUAL_TRIGGER_ANON' === $code ) {
			$status = 'publish';
		}

		return $status;
	}

	/**
	 * Import loop for the recipe.
	 *
	 * @param object $recipe_part - The recipe part to import.
	 * @param int $new_loop_id - The new loop ID.
	 * @param int $new_recipe_id - The new recipe ID.
	 * @param int $original_recipe_id - The original recipe ID.
	 *
	 * @return void
	 */
	public function import_loop( $recipe_part, $new_loop_id, $new_recipe_id, $original_recipe_id ) {

		$loops = isset( $recipe_part->loops ) && ! empty( $recipe_part->loops ) ? (array) $recipe_part->loops : null;

		if ( ! is_array( $loops ) ) {
			return;
		}

		foreach ( $loops as $loop_item_type => $loop_items ) {

			if ( ! is_array( $loop_items ) || empty( $loop_items ) ) {
				continue;
			}

			foreach ( $loop_items as $i => $item ) {

				if ( ! isset( $item->post ) || ! isset( $item->post->ID ) || ! isset( $item->meta ) ) {
					continue;
				}

				$item_post_id = $this->copy_recipe_parts->copy( $item->post->ID, $new_loop_id, 'draft', $item->post, (array) $item->meta );
			}
		}
	}

	/**
	 * Set the import result to transient.
	 *
	 * @param string $message - The message to display.
	 *
	 * @return void
	 */
	public function set_import_error( $message ) {
		set_transient(
			'automator_recipe_import_result_' . get_current_user_id(),
			sprintf(
				/* translators: %s - error message */
				_x( 'Recipe Import Error : %s', 'Import Recipe', 'uncanny-automator' ),
				$message
			),
			30
		);
	}

	/**
	 * Maybe display any import errors.
	 *
	 * @return string - HTML for the import results if they exist in transient.
	 */
	public function maybe_display_import_errors() {

		// Check if transient is set for current user.
		$transient_key = 'automator_recipe_import_result_' . get_current_user_id();
		$error         = get_transient( $transient_key );
		if ( ! $error ) {
			return;
		}

		// Display the error.
		echo $this->generate_notice( 'error', $error ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		// Remove transient.
		delete_transient( $transient_key );
	}

	/**
	 * Maybe display a message for imported recipes.
	 *
	 * @return string - HTML for the imported recipe message if meta exists.
	 */
	public function maybe_display_imported_recipe_warning() {

		// Check if we are on the edit screen for a recipe.
		$screen = get_current_screen();
		if ( ! $screen instanceof \WP_Screen ) {
			return;
		}

		if ( 'uo-recipe' !== $screen->post_type || 'post' !== $screen->base ) {
			return;
		}

		// Check if the recipe has the imported meta.
		$recipe_id = get_the_ID();
		$warning   = get_post_meta( $recipe_id, self::IMPORTED_RECIPE_WARNING_META, true );
		if ( ! $warning ) {
			return;
		}

		// Display the notice.
		echo $this->generate_notice( 'warning', $warning ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Generate a notice.
	 *
	 * @param string $type - The type of notice.
	 * @param string $message - The message to display.
	 *
	 * @return string - HTML for the notice.
	 */
	public function generate_notice( $type, $message ) {
		$html = '<div class="uap notice notice-' . esc_attr( $type ) . '" style="padding:0">';
		$html .= '<uo-alert type="' . esc_attr( $type ) . '" no-radius>';
		$html .= '<strong>' . esc_html( $message ) . '</strong>';
		$html .= '</uo-alert>';
		$html .= '</div>';
		return $html;
	}

	/**
	 * Clear the imported recipe warning meta when a recipe is updated.
	 *
	 * @param mixed ...$args - The arguments passed to the hook.
	 *
	 * @return void
	 */
	public function clear_imported_recipe_warning( ...$args ) {
		$recipe_id = null;

		switch ( current_filter() ) {
			// recipe_id is the first argument.
			case 'automator_recipe_title_updated':
				$recipe_id = $args[0];
				break;
			// recipe_id is the second argument.
			case 'automator_recipe_item_deleted':
			case 'automator_recipe_status_updated':
				$recipe_id = $args[1];
				break;
			// recipe_id is the fifth argument.
			case 'automator_recipe_option_updated':
				$recipe_id = $args[4];
				break;
		}

		if ( null !== $recipe_id ) {
			// Check if the recipe has the imported meta.
			$meta = get_post_meta( $recipe_id, self::IMPORTED_RECIPE_WARNING_META, true );
			if ( ! $meta ) {
				return;
			}

			delete_post_meta( $recipe_id, self::IMPORTED_RECIPE_WARNING_META );
		}
	}

}
