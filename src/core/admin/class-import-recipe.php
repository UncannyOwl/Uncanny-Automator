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
	 * The post types that should be imported as draft.
	 *
	 * @var array
	 */
	private $draft_post_types = array(
		'uo-recipe',
		'uo-trigger',
		'uo-action',
	);

	/**
	 * The trigger codes that should be published on import.
	 *
	 * @var array
	 */
	private $published_trigger_codes = array(
		'RECIPE_MANUAL_TRIGGER_ANON',
	);

	/**
	 * The import URL.
	 *
	 * @var string
	 */
	private $import_url = '';

	/**
	 * Current site URL.
	 *
	 * @var string
	 */
	private $site_url = '';

	/**
	 * Whether to modify URLs.
	 *
	 * @var bool
	 */
	private $modify_urls = false;

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
		add_action( 'automator_show_internal_admin_notice', array( $this, 'maybe_display_bulk_import_success' ) );
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

		// Run filters.
		$this->pre_import_filters();

		// Check if the JSON is a bulk import.
		if ( $this->is_bulk_import( $recipe_json ) ) {
			$this->handle_bulk_import( $recipe_json );
			return;
		}

		// Handle single import.
		$this->handle_single_import( $recipe_json );
	}

	/**
	 * Check if the JSON is a bulk import.
	 *
	 * @param object $recipe_json - The JSON object to check.
	 *
	 * @return bool - Whether the JSON is a bulk import.
	 */
	public function is_bulk_import( $recipe_json ) {
		return is_array( $recipe_json );
	}

	/**
	 * Import a single recipe from JSON.
	 *
	 * @param object $recipe_json - The JSON object to import.
	 *
	 * @return void
	 */
	public function handle_single_import( $recipe_json ) {

		// Import the recipe.
		$new_recipe_id = $this->import_recipe_json( $recipe_json );

		if ( is_wp_error( $new_recipe_id ) ) {
			$this->set_import_error( $new_recipe_id->get_error_message() );

			return;
		}

		do_action( 'automator_recipe_imported', $new_recipe_id );

		// Success - redirect to newly imported recipe.
		$redirect_url = get_edit_post_link( $new_recipe_id, 'url' );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Handle a bulk import of recipes.
	 *
	 * @param array $recipes - The array of recipes to import.
	 *
	 * @return void
	 */
	public function handle_bulk_import( $recipes ) {

		$imported_recipes = array();

		// Import each recipe.
		foreach ( $recipes as $recipe_json ) {

			$new_recipe_id = $this->import_recipe_json( $recipe_json );

			if ( is_wp_error( $new_recipe_id ) ) {
				$this->set_import_error( $new_recipe_id->get_error_message() );
				return;
			}

			do_action( 'automator_recipe_imported', $new_recipe_id );

			$imported_recipes[] = $new_recipe_id;
		}

		$this->set_bulk_import_success_message( $imported_recipes );
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
	 * Run pre-import filters.
	 *
	 * @return void
	 */
	private function pre_import_filters() {
		$this->draft_post_types        = apply_filters( 'automator_recipe_import_draft_post_types', $this->draft_post_types );
		$this->published_trigger_codes = apply_filters( 'automator_recipe_import_published_trigger_codes', $this->published_trigger_codes );

		// Maybe adjust hardcoded meta URLs.
		add_filter( 'automator_recipe_part_meta_value', array( $this, 'maybe_adjust_hardcoded_meta_urls' ), 10, 4 );
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
		$this->import_url                   = $this->get_import_url_from_recipe_post( $recipe->post );
		$this->site_url                     = get_site_url();
		$this->modify_urls                  = ! empty( $this->import_url ) && $this->import_url !== $this->site_url;

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

				$status       = $this->maybe_adjust_recipe_part_status( $recipe_part );
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
	 * Adjust the status of a recipe part based on its type.
	 *
	 * @param object $recipe_part - The recipe part to adjust.
	 *
	 * @return string - The import status.
	 */
	private function maybe_adjust_recipe_part_status( $recipe_part ) {

		$post_type = isset( $recipe_part->post->post_type ) ? $recipe_part->post->post_type : null;

		// Not a post type.
		if ( ! $post_type ) {
			return 'draft';
		}

		// Not in the list of post types to adjust.
		if ( ! in_array( $post_type, $this->draft_post_types, true ) ) {
			// Return the original post status or 'publish' if it's not set.
			return isset( $recipe_part->post->post_status ) ? $recipe_part->post->post_status : 'publish';
		}

		// Return draft status.
		$status = 'draft';

		// Check if the trigger is a published trigger.
		if ( 'uo-trigger' === $post_type ) {
			$code = isset( $recipe_part->meta->code ) && isset( $recipe_part->meta->code[0] ) ? $recipe_part->meta->code[0] : null;
			if ( in_array( $code, $this->published_trigger_codes, true ) ) {
				$status = 'publish';
			}
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
				$status       = $this->maybe_adjust_recipe_part_status( $item );
				$item_post_id = $this->copy_recipe_parts->copy( $item->post->ID, $new_loop_id, $status, $item->post, (array) $item->meta );
			}
		}
	}

	/**
	 * Get the import URL from a recipe post.
	 *
	 * @param object $recipe_post - The recipe post object.
	 *
	 * @return string - The import URL.
	 */
	private function get_import_url_from_recipe_post( $recipe_post ) {
		// Extract the GUID url from the recipe post.
		$guid = isset( $recipe_post->guid ) ? $recipe_post->guid : null;

		if ( ! $guid ) {
			return '';
		}

		// Parse the URL from the GUID.
		$parts = wp_parse_url( $guid );

		if ( ! isset( $parts['scheme'] ) || ! isset( $parts['host'] ) ) {
			return '';
		}

		// Return the base URL.
		return $parts['scheme'] . '://' . $parts['host'];
	}

	/**
	 * Maybe adjust hardcoded meta URLs.
	 *
	 * @param string $value - The meta value.
	 * @param int $old_post_id - The old post ID.
	 * @param int $new_post_id - The new post ID.
	 * @param string $meta_key - The meta key.
	 *
	 * @return string - The adjusted meta value.
	 */
	public function maybe_adjust_hardcoded_meta_urls( $value, $old_post_id, $new_post_id, $meta_key ) {

		// Bail if urls should not be modified.
		if ( ! $this->modify_urls ) {
			return $value;
		}

		// Bail if the meta key is in the do not modify list.
		if ( in_array( $meta_key, $this->copy_recipe_parts->do_not_modify_meta_keys, true ) ) {
			return $value;
		}

		// Handle string values.
		if ( is_string( $value ) ) {
			return $this->maybe_replace_url_in_string( $value, $meta_key );
		}

		// Handle arrays.
		if ( is_array( $value ) ) {
			// Loop through the array and adjust the URLs.
			foreach ( $value as $key => $val ) {
				$value[ $key ] = $this->maybe_replace_url_in_string( $val, $meta_key );
			}
			return $value;
		}

		// Handle objects.
		if ( is_object( $value ) ) {
			// Loop through the object and adjust the URLs.
			foreach ( $value as $key => $val ) {
				$value->$key = $this->maybe_replace_url_in_string( $val, $meta_key );
			}
			return $value;
		}

		return $value;
	}

	/**
	 * Maybe replace the import URL in a string.
	 *
	 * @param string $value - The value to check.
	 *
	 * @return string - The adjusted value.
	 */
	private function maybe_replace_url_in_string( $value, $meta_key ) {

		// Bail if the value is empty or an integrer.
		if ( empty( $value ) || is_int( $value ) ) {
			return $value;
		}

		// Send back to the main function to handle arrays and objects recursively.
		if ( is_object( $value ) || is_array( $value ) ) {
			return $this->maybe_adjust_hardcoded_meta_urls( $value, 0, 0, $meta_key );
		}

		// Check if the value contains the import URL.
		if ( false === strpos( $value, $this->import_url ) ) {
			return $value;
		}

		// Replace the import URL with the site URL.
		return str_replace( $this->import_url, $this->site_url, $value );
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
	 * Set the import success message to transient.
	 *
	 * @param array $recipe_ids.
	 *
	 * @return void
	 */
	public function set_bulk_import_success_message( $recipe_ids ) {

		// Get the recipe titles and links and format them to a message.
		$message = '<ul>';
		foreach ( $recipe_ids as $recipe_id ) {
			$recipe_title = get_the_title( $recipe_id );
			$recipe_link  = get_edit_post_link( $recipe_id, 'url' );
			$message      .= sprintf( '<li><a href="%s" target="_blank">%s</a></li>', esc_url( $recipe_link ), esc_html( $recipe_title ) );
		}
		$message .= '</ul>';

		set_transient(
			'automator_recipe_import_success_' . get_current_user_id(),
			sprintf(
				/* translators: %s - error message */
				_x( 'Recipes Imported Successfully : %s', 'Import Recipe', 'uncanny-automator' ),
				$message
			),
			30
		);
	}

	/**
	 * Maybe display the bulk import success message.
	 *
	 * @return string - HTML for the import results if they exist in transient.
	 */
	public function maybe_display_bulk_import_success() {

		// Check if transient is set for current user.
		$transient_key = 'automator_recipe_import_success_' . get_current_user_id();
		$success       = get_transient( $transient_key );

		if ( ! $success ) {
			return;
		}

		// Display the success message.
		echo $this->generate_notice( 'success', $success ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

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

		$allowed_html = array(
			'a'  => array(
				'href'   => array(),
				'target' => array(),
			),
			'ul' => array(),
			'li' => array(),
		);

		$html = '<div class="uap notice notice-' . esc_attr( $type ) . '" style="padding:0">';
		$html .= '<uo-alert type="' . esc_attr( $type ) . '" no-radius>';
		$html .= '<strong>' . wp_kses( $message, $allowed_html ) . '</strong>';
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
