<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

/**
 * Class Trello_Helpers
 *
 * @package Uncanny_Automator
 */
class Trello_Helpers {

	/**
	 * functions
	 *
	 * @var Trello_Functions
	 */
	public $functions;

	public function __construct() {

		$this->functions = new Trello_Functions();

		add_action( 'init', array( $this->functions, 'disconnect' ) );
		add_action( 'init', array( $this->functions, 'capture_oauth_token' ) );

		add_action( 'admin_enqueue_scripts', array( $this->functions, 'load_scripts' ) );

		$this->register_ajax_endpoints();

		// Load the settings page.
		require_once __DIR__ . '/../settings/settings-trello.php';
		new Trello_Settings( $this );
	}

	public function register_ajax_endpoints() {
		add_action( 'wp_ajax_automator_trello_get_board_lists', array( $this->functions, 'ajax_get_board_lists_options' ) );
		add_action( 'wp_ajax_automator_trello_get_board_members', array( $this->functions, 'ajax_get_board_members_options' ) );
		add_action( 'wp_ajax_automator_trello_get_board_labels', array( $this->functions, 'ajax_get_board_labels_options' ) );
		add_action( 'wp_ajax_automator_trello_api_get_custom_fields', array( $this->functions, 'ajax_get_custom_fields' ) );
		add_action( 'wp_ajax_automator_trello_get_list_cards', array( $this->functions, 'ajax_get_list_cards_options' ) );
		add_action( 'wp_ajax_automator_trello_get_card_checklists', array( $this->functions, 'ajax_get_card_checklists' ) );
	}

}
