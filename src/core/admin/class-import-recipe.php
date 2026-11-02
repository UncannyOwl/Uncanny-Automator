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
	 * A failed import's reason, for the importing user (+ user ID), 30 seconds.
	 */
	const ERROR_TRANSIENT = 'automator_recipe_import_result_';

	/**
	 * A bulk import's recipe IDs, for the importing user (+ user ID), 30 seconds.
	 */
	const SUCCESS_TRANSIENT = 'automator_recipe_import_success_';


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
		add_action( 'in_admin_header', array( $this, 'render_import_form' ) );

		// Clear the warning meta when the recipe is changed.
		add_action( 'automator_event_recipe_changed', array( $this, 'clear_imported_recipe_warning' ) );
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

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->set_import_error( _x( 'You do not have permission to import recipes.', 'Import Recipe', 'uncanny-automator' ) );

			return;
		}

		if ( ! isset( $_FILES['recipejson'] ) ) {
			$this->set_import_error( _x( 'No recipe .json file uploaded.', 'Import Recipe', 'uncanny-automator' ) );

			return;
		}

		$file = $_FILES['recipejson']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$temp = isset( $file['tmp_name'] ) ? $file['tmp_name'] : null;
		$name = isset( $file['name'] ) ? sanitize_file_name( $file['name'] ) : '';

		if ( empty( $temp ) ) {
			$this->set_import_error( _x( 'The uploaded file is not a valid recipe .json file.', 'Import Recipe', 'uncanny-automator' ) );

			return;
		}

		$filetype = wp_check_filetype( $name, array( 'json' => 'application/json' ) );
		if ( 'application/json' !== $filetype['type'] ) {
			$this->set_import_error( _x( 'The uploaded file is not a valid recipe .json file.', 'Import Recipe', 'uncanny-automator' ) );

			return;
		}

		// Read the file.
		$recipe_json = file_get_contents( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $recipe_json ) {
			$this->set_import_error( _x( 'Unable to read the uploaded file.', 'Import Recipe', 'uncanny-automator' ) );

			return;
		}

		$recipe_json = json_decode( $recipe_json );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$this->set_import_error( _x( 'The uploaded file contains invalid JSON.', 'Import Recipe', 'uncanny-automator' ) );

			return;
		}

		if ( ! $recipe_json ) {
			$this->set_import_error( _x( 'The uploaded file is not a valid recipe .json file.', 'Import Recipe', 'uncanny-automator' ) );

			return;
		}

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

		// Check if the import was successful
		if ( empty( $new_recipe_id ) || ! is_numeric( $new_recipe_id ) ) {
			$this->set_import_error( _x( 'Unable to create imported recipe.', 'Import Recipe', 'uncanny-automator' ) );

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
	 * A bad file imports nothing: every entry is checked before any is imported, and an
	 * entry that fails during its import removes the recipes the file imported before it.
	 * Each recipe is announced once every entry is in.
	 *
	 * @since 8.0.0 Checks every entry first, and imports all of them or none.
	 *
	 * @param array $recipes - The array of recipes to import.
	 *
	 * @return void
	 */
	public function handle_bulk_import( $recipes ) {

		foreach ( $recipes as $recipe_json ) {

			$refusal = $this->check_recipe_json( $recipe_json );

			if ( is_wp_error( $refusal ) ) {
				$this->set_import_error( $refusal->get_error_message() );
				return;
			}
		}

		$imported_recipes = array();

		// Import each recipe.
		foreach ( $recipes as $recipe_json ) {

			$new_recipe_id = $this->import_recipe_json( $recipe_json );

			if ( is_wp_error( $new_recipe_id ) ) {
				$this->remove_imported_recipes( $imported_recipes );
				$this->set_import_error( $new_recipe_id->get_error_message() );
				return;
			}

			// Check if the import was successful
			if ( empty( $new_recipe_id ) || ! is_numeric( $new_recipe_id ) ) {
				$this->remove_imported_recipes( $imported_recipes );
				$this->set_import_error( _x( 'Unable to create imported recipe.', 'Import Recipe', 'uncanny-automator' ) );
				return;
			}

			$imported_recipes[] = $new_recipe_id;
		}

		foreach ( $imported_recipes as $new_recipe_id ) {
			do_action( 'automator_recipe_imported', $new_recipe_id );
		}

		$this->set_bulk_import_success_message( $imported_recipes );
	}

	/**
	 * Check decoded JSON the way import_recipe_json() does before it writes anything.
	 *
	 * @since 8.0.0
	 *
	 * @param object|array $json The decoded JSON to check.
	 *
	 * @return true|\WP_Error True when the import would start, the import's own refusal otherwise.
	 */
	public function check_recipe_json( $json ) {

		try {
			$importer = new \Uncanny_Automator\App\Recipe_Builder\Recipe\Portability\Recipe_Importer();
			$refusal  = $this->is_old_format( $json )
				? $importer->check_legacy( $json )
				: $importer->check( $this->portable_document( $json ) );
		} catch ( \Throwable $e ) {
			$refusal = new WP_Error( 'import_failed', $e->getMessage() );
		}

		return null === $refusal ? true : $this->user_facing_import_error( $refusal );
	}

	/**
	 * Permanently delete the recipes a bulk import created before one of its entries failed,
	 * so the file imports nothing. Each recipe's steps go with it.
	 *
	 * @since 8.0.0
	 *
	 * @param int[] $recipe_ids Recipes the file imported.
	 *
	 * @return void
	 */
	private function remove_imported_recipes( array $recipe_ids ) {

		foreach ( $recipe_ids as $recipe_id ) {

			$removed = automator_delete_recipe( (int) $recipe_id, true );

			if ( is_wp_error( $removed ) ) {
				automator_log( sprintf( 'Recipe %d, imported before the bulk import failed, could not be removed: %s', (int) $recipe_id, $removed->get_error_message() ), 'Recipe import failed', true, 'recipe-import' );
			}
		}
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
	 * Import a recipe from decoded JSON by delegating to the portability
	 * engine: versioned envelopes go through `automator_import_recipe()`,
	 * old-format raw post+meta dumps through the adapter path
	 * (`automator_import_legacy_recipe()`). It also writes the dated
	 * `uap_recipe_imported` sentence, which the `recipe_imported` recipe
	 * notice sends to the builder until the recipe's first change.
	 *
	 * @param object|array $json The decoded JSON to import.
	 *
	 * @return int|\WP_Error The new recipe ID on success, WP_Error on failure.
	 */
	public function import_recipe_json( $json ) {

		$result = $this->is_old_format( $json )
			? automator_import_legacy_recipe( $json )
			: automator_import_recipe( $this->portable_document( $json ) );

		if ( is_wp_error( $result ) ) {
			return $this->user_facing_import_error( $result );
		}

		$new_recipe_id = (int) $result['recipe_id'];

		update_post_meta(
			$new_recipe_id,
			self::IMPORTED_RECIPE_WARNING_META,
			sprintf(
				/* translators: %s - Y-m-d date */
				_x( 'Recipe imported on %s. Please make sure to set the correct values before you take this recipe live.', 'Import Recipe', 'uncanny-automator' ),
				date_i18n( 'Y-m-d', time() )
			)
		);

		return $new_recipe_id;
	}

	/**
	 * Whether decoded JSON is an old-format file: old files carry the raw dump shape
	 * (recipe->post); new exports are versioned portable documents.
	 *
	 * @since 8.0.0
	 *
	 * @param mixed $json The decoded JSON.
	 *
	 * @return bool
	 */
	private function is_old_format( $json ) {
		return is_object( $json ) && isset( $json->recipe->post );
	}

	/**
	 * Decoded JSON as the portable document array the importer reads.
	 *
	 * @since 8.0.0
	 *
	 * @param mixed $json The decoded JSON.
	 *
	 * @return array
	 */
	private function portable_document( $json ) {
		return (array) json_decode( (string) wp_json_encode( $json ), true );
	}

	/**
	 * Keep user-level import errors verbatim; translate internal engine
	 * failures into a friendly message and log the technical detail —
	 * validation/CRUD internals must never reach the admin notice.
	 *
	 * @param \WP_Error $error The engine failure.
	 *
	 * @return \WP_Error User-facing error.
	 */
	private function user_facing_import_error( WP_Error $error ) {

		$user_level_codes = array(
			'invalid-recipe-json',
			'invalid_import_document',
			'import_unsupported_schema_version',
			'import_invalid_document',
		);

		if ( in_array( $error->get_error_code(), $user_level_codes, true ) ) {
			return $error;
		}

		automator_log( $error->get_error_code() . ': ' . $error->get_error_message(), 'Recipe import failed', true, 'recipe-import' );

		return new WP_Error(
			'import_failed',
			esc_html_x( 'The recipe could not be imported. The technical details have been recorded in the Automator logs.', 'Import Recipe', 'uncanny-automator' )
		);
	}

	/**
	 * Set the import result to transient.
	 *
	 * @param string $message - The message to display.
	 *
	 * @return void
	 */
	public function set_import_error( $message ) {
		set_transient( self::ERROR_TRANSIENT . get_current_user_id(), (string) $message, 30 );
	}

	/**
	 * Set the import success message to transient.
	 *
	 * @param array $recipe_ids.
	 *
	 * @return void
	 */
	public function set_bulk_import_success_message( $recipe_ids ) {
		set_transient( self::SUCCESS_TRANSIENT . get_current_user_id(), array_map( 'absint', (array) $recipe_ids ), 30 );
	}

	/**
	 * Clear the import warning once the imported recipe is changed.
	 *
	 * Listens to `automator_event_recipe_changed`, so any change to the recipe or
	 * its items, from the builder, MCP or an automator_*() helper, clears it. The
	 * import's own created event fires before the warning is written.
	 *
	 * @param object $event The recipe_changed event.
	 *
	 * @return void
	 */
	public function clear_imported_recipe_warning( $event ) {
		$recipe_id = (int) $event->recipe_id();

		if ( get_post_meta( $recipe_id, self::IMPORTED_RECIPE_WARNING_META, true ) ) {
			delete_post_meta( $recipe_id, self::IMPORTED_RECIPE_WARNING_META );
		}
	}
}
