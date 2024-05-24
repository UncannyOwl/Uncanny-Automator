<?php
//phpcs:disable PHPCompatibility.Operators.NewOperators.t_coalesceFound
namespace Uncanny_Automator\Integrations\Google_Sheet\Migrations;

use Exception;
use Uncanny_Automator\Automator_Review;
use Uncanny_Automator\Google_Sheet_Helpers;
use Uncanny_Automator\Utilities;

/**
 * Class Migration for version 5.8
 *
 * @package Uncanny_Automator\Integrations\Google_Sheet\Migrations\Migrate_58
 */
class Migrate_58 {

	/**
	 * @var string
	 */
	protected $id = 'automator_google_sheets_migrate_58';

	/**
	 * @var string[]
	 */
	const ACTION_CODES_LEGACY = array( 'GOOGLESHEETADDRECORD', 'GOOGLESHEETUPDATERECORD' );

	/**
	 * @var string[]
	 */
	const ACTION_CODES_NEW = array( 'SHEET_ADD_ROW_V2', 'SHEET_UPDATE_ROW_V2' );

	/**
	 * @var string
	 */
	const ACTION_CODE_KEY = 'code';

	/**
	 * @var \Uncanny_Automator\Google_Sheet_Helpers
	 */
	protected $helper = null;

	/**
	 * @var false
	 */
	protected static $review_banner_has_loaded = false;

	/**
	 * Assigns an helper class to class property.
	 *
	 * @param Google_Sheet_Helpers $helper
	 *
	 * @return void
	 */
	public function __construct( \uncanny_automator\Google_Sheet_Helpers $helper ) {
		$this->helper = $helper;
	}

	/**
	 * Registers the necessary hooks for migration.
	 *
	 * @return void
	 */
	public function register_hooks() {

		// Shows admin notice on automator related pages.
		add_action( 'automator_show_internal_admin_notice', array( $this, 'show_notice' ), 10, 1 );

		// Shows admin notice on non-automator related pages.
		add_action( 'admin_notices', array( $this, 'show_notice_fallback' ), 20, 1 );

		// Run migration conditionally on init so it automatically imports.
		add_action( 'init', array( $this, 'migrate' ) );
	}

	/**
	 * Checks if the migration has already been performed.
	 *
	 * @return bool
	 */
	public function has_migrated() {

		$migrated = get_option( $this->id, false );

		$has_migrated = false !== $migrated && is_numeric( $migrated );

		return apply_filters( 'automator_google_sheet_migrations_migrated_58_has_migrated', $has_migrated );
	}

	/**
	 * Migrates the legacy action to a new one.
	 *
	 * Won't migrate if the migration has already ran or if there is still an existing drive scope.
	 *
	 * @return bool - False if migrated already.
	 */
	public function migrate() {

		if ( $this->has_migrated() || $this->has_drive_scope() ) {
			return false;
		}

		$log          = array();
		$actions      = Automator()->utilities->fetch_integration_actions( 'GOOGLESHEET' );
		$spreadsheets = get_option( Google_Sheet_Helpers::SPREADSHEETS_OPTIONS_KEY, array() );

		$migrated_spreadsheets = array();
		$selected_spreadsheets = array();

		foreach ( $actions as $action ) {

			$action_id = $action['ID'] ?? null;

			if ( null === $action_id ) {
				continue;
			}

			try {

				$migrated = $this->migrate_action( $action_id, $spreadsheets );

				$migrated_spreadsheets[] = $migrated['injected_spreadsheets'] ?? array();
				$selected_spreadsheets[] = $migrated['selected_spreadsheets'] ?? array();

				$this->create_log( $log, "✅ Migration of '{$migrated['action_sentence']}: [{$action_id}]' ok... {$migrated['migrated']}" );

			} catch ( Exception $e ) {

				$this->create_log( $log, '❌ ' . $e->getMessage() );
				continue; // Skip.

			}
		}

		$selected_spreadsheets = $this->trim_unique( $selected_spreadsheets, 'id' );
		$migrated_spreadsheets = $this->trim_unique( $migrated_spreadsheets, 'id' );

		if ( is_array( $migrated_spreadsheets ) && ! empty( $migrated_spreadsheets ) ) {
			$this->create_log( $log, 'The following spreadsheets were automatically added in the spreadsheets options' );
			$this->create_log( $log, $migrated_spreadsheets );

			// Merge the selected spreadsheets with the existing ones if there are any.
			update_option( Google_Sheet_Helpers::SPREADSHEETS_OPTIONS_KEY, $selected_spreadsheets );
		}

		add_option( $this->id, time() );

		automator_log( $log, 'Google Sheets 5.8 Migration', true, 'google-sheets-5-8' );

		return true;
	}

	/**
	 * Trims unique array associative by key.
	 *
	 * @param mixed[] $input_array
	 * @param string $key
	 * @return array
	 */
	public function trim_unique( $input_array, $key ) {

		$result_array = array();
		$seen_ids     = array();

		foreach ( $input_array as $item ) {

			if ( ! isset( $item[ $key ] ) ) {
				continue;
			}

			if ( ! in_array( $item[ $key ], $seen_ids ) ) {
				$seen_ids[]     = $item[ $key ];
				$result_array[] = $item;
			}
		}

		return $result_array;
	}

	/**
	 * Migrates a specific action.
	 *
	 * @param int $action_id
	 * @param array $selected_spreadsheets
	 * @return array
	 * @throws Exception
	 */
	public function migrate_action( $action_id = 0, $selected_spreadsheets = array() ) {

		if ( empty( $action_id ) ) {
			throw new Exception( 'Action ID cannot be empty', 400 );
		}

		$action_meta     = Utilities::flatten_post_meta( get_post_meta( $action_id ) );
		$action_code     = $action_meta[ self::ACTION_CODE_KEY ];
		$action_sentence = $action_meta['sentence_human_readable'] ?? '<sentence_html_readable_empty>';

		// Reset.
		$injected_spreadsheets = array();

		if ( self::ACTION_CODES_NEW === $action_code ) {
			throw new Exception( "Skipping action: [{$action_id}]. Already contains new action code.", 400 );
		}

		// Determines whether the action is legacy or not.
		$action_is_legacy = in_array( $action_code, self::ACTION_CODES_LEGACY, true );

		// Update the post meta of the legacy action code into the new one.
		if ( $action_is_legacy ) {

			$is_create_row = 'GOOGLESHEETADDRECORD' === $action_code;
			$is_update_row = 'GOOGLESHEETUPDATERECORD' === $action_code;

			if ( $is_create_row ) {
				$this->migrate_action_to( $action_id, 'SHEET_ADD_ROW_V2', $action_sentence );
			}

			if ( $is_update_row ) {
				$this->migrate_action_to( $action_id, 'SHEET_UPDATE_ROW_V2', $action_sentence );
			}

			$spreadsheet_id   = $action_meta['GSSPREADSHEET'] ?? null;
			$spreadsheet_name = $action_meta['GSSPREADSHEET_readable'] ?? null;

			// Determines whether the spreadsheet ID does not exist in spreadsheet options.
			$is_spreadsheet_not_selected = ! in_array( $spreadsheet_id, array_column( $selected_spreadsheets, 'id' ), true );

			// Should we auto insert the existing spreadsheet ID as if it was selected from filepicker?
			$should_insert_spreadsheet = ! empty( $spreadsheet_id ) && ! empty( $spreadsheet_name ) && $is_spreadsheet_not_selected;

			if ( $should_insert_spreadsheet ) {
				$sheet_props = array(
					'id'                           => $spreadsheet_id,
					'serviceId'                    => 'spread',
					'mimeType'                     => 'application/vnd.google-apps.spreadsheet',
					'name'                         => $spreadsheet_name,
					'automator_legacy_spreadsheet' => true,
				);

				$injected_spreadsheets = $sheet_props;
				$selected_spreadsheets = $sheet_props;
			}
		}

		return array(
			'action_sentence'       => $action_sentence,
			'selected_spreadsheets' => $selected_spreadsheets,
			'injected_spreadsheets' => $injected_spreadsheets,
			'migrated'              => time(),
		);
	}

	/**
	 * Updates the action code for a specific action.
	 *
	 * @param int $action_id
	 * @param string $action_code
	 * @param string $action_sentence
	 * @throws Exception
	 */
	public function migrate_action_to( $action_id, $action_code, $action_sentence ) {

		$updated = update_post_meta( $action_id, self::ACTION_CODE_KEY, $action_code );

		if ( false === $updated ) {
			throw new Exception( "Migration of {$action_sentence} failed. DB error or the action was already migrated.", 400 );
		}

		if ( is_numeric( $updated ) ) {
			throw new Exception( "Migration of {$action_sentence} failed. Meta does not exist.", 400 );
		}
	}

	/**
	 * Includes a template file.
	 *
	 * @param string $relative_path
	 * @param array $args
	 */
	private function include_template( $relative_path, $args ) {

		include trailingslashit( UA_ABSPATH ) . $relative_path . '.php';

	}

	/**
	 * Determines whether the client has drive scope or not.
	 *
	 * @return bool
	 */
	public function has_drive_scope() {

		try {
			$scopes = $this->helper->get_client_scopes();
		} catch ( Exception $e ) {
			automator_log(
				$e->getMessage(),
				'Uncanny_Automator\Integrations\Google_Sheet\Migrations\Migrate_58::show_notice',
				true,
				'migration-5-8-notice'
			);
		}

		$has_drive_scope = in_array( 'https://www.googleapis.com/auth/drive', (array) $scopes, true );

		return apply_filters( 'automator_google_sheet_migrations_migrated_58_has_drive_scope', $has_drive_scope );

	}

	/**
	 * Shows an admin notice.
	 */
	public function show_notice() {

		// Only display on areas where credits are also displayed.
		if ( ! Automator_Review::is_automator_page() ) {
			return;
		}

		$has_drive_scope = $this->has_drive_scope();

		Automator_Review::load_banner_assets();

		// Show if there is a drive scope.
		if ( $has_drive_scope ) {
			$this->include_template(
				'src/integrations/google-sheet/migrations/views/migrate-58',
				array(
					'url_learn_more' => 'https://automatorplugin.com/knowledge-base/google-sheets/#google-permissions-change-in-uncanny-automator-version-5-8',
					'url_settings'   => admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-config&tab=premium-integrations&integration=google-sheet' ),
				)
			);

			self::$review_banner_has_loaded = true;

		}
	}

	/**
	 * Fallback banner for non automator pages.
	 *
	 * @return void
	 */
	public function show_notice_fallback() {

		$has_drive_scope = $this->has_drive_scope();

		// One of the banner has loaded already. Do not show.
		if ( true === self::$review_banner_has_loaded ) {
			return;
		}

		// Show if there is a drive scope.
		if ( $has_drive_scope ) {
			$this->include_template(
				'src/integrations/google-sheet/migrations/views/migrate-58-fallback',
				array(
					'url_learn_more' => 'https://automatorplugin.com/knowledge-base/google-sheets/#google-permissions-change-in-uncanny-automator-version-5-8',
					'url_settings'   => admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-config&tab=premium-integrations&integration=google-sheet' ),
				)
			);
		}

	}

	/**
	 * Creates an entry in an array of log for debugging.
	 *
	 * @param array $log
	 * @param string $message
	 */
	private function create_log( &$log, $message ) {
		$log[] = $message;
	}
}

