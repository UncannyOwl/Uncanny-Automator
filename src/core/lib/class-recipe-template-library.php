<?php

namespace Uncanny_Automator;

use WP_REST_Response;
use Exception;

/**
 * Class Recipe_Template_Library
 *
 * @since   0.0
 * @version 0.0
 * @package Uncanny_Automator
 */
class Recipe_Template_Library {

	/**
	 * Library version.
	 */
	public $version = '0.0.1';

	/**
	 * External feed base URL
	 *
	 * @var string
	 */
	private $api_base = 'https://automatorplugin.com/';

	/**
	 * External feed library directory.
	 *
	 * @var string
	 */
	private $library_directory = 'wp-content/uploads/automator-template-library/';

	/**
	 * Templates
	 *
	 * @var array
	 */
	public $templates = array();

	/**
	 * Integrations
	 *
	 * @var array
	 */
	public $integrations = array();

	/**
	 * Categories
	 *
	 * @var array
	 */
	public $categories = array();

	/**
	 * Tags
	 *
	 * @var array
	 */
	public $tags = array();

	/**
	 * Description
	 *
	 * @var string
	 */
	public $description = '';

	/**
	 * Per page
	 *
	 * @var int
	 */
	public $per_page = 24;

	/**
	 * Library file.
	 *
	 * @var string
	 */
	const LIBRARY_FILE = 'library.json';

	/**
	 * Class constructor
	 *
	 * @return void
	 */
	public function __construct() {
		// Set the base URL if it's defined.
		if ( defined( 'AUTOMATOR_PLUGIN_TEMPLATE_LIBRARY_URL' ) ) {
			$this->api_base = AUTOMATOR_PLUGIN_TEMPLATE_LIBRARY_URL;
		}
		if ( defined( 'AUTOMATOR_PLUGIN_TEMPLATE_LIBRARY_DIRECTORY' ) ) {
			$this->library_directory = AUTOMATOR_PLUGIN_TEMPLATE_LIBRARY_DIRECTORY;
		}
	}

	/**
	 * Load the library.
	 *
	 * @return void
	 */
	public function load() {

		// Get the feed.
		$feed = $this->get_library_feed();
		if ( ! empty( $feed ) && is_array( $feed ) ) {
			$this->templates    = isset( $feed['templates'] ) ? $feed['templates'] : array();
			$this->integrations = isset( $feed['integrations'] ) ? $feed['integrations'] : array();
			$this->categories   = isset( $feed['categories'] ) ? $feed['categories'] : array();
			$this->per_page     = isset( $feed['per_page'] ) ? $feed['per_page'] : 24;
		}

		// Do action to load the library.
		do_action( 'automator_template_library_load', $this );
	}

	/**
	* Get feed
	*
	* @return array $feed
	*/
	public function get_library_feed() {

		// Get the feed file.
		$response = wp_remote_get( $this->api_base . $this->library_directory . self::LIBRARY_FILE );
		if ( is_wp_error( $response ) ) {
			return array();
		}

		// Decode the feed.
		$feed = json_decode( wp_remote_retrieve_body( $response ), true );

		// Populate integration data.
		$feed['integrations'] = $this->populate_integration_data( $feed );

		return apply_filters( 'uap_template_library_feed', $feed, $this );
	}

	/**
	 * Populate integration data.
	 *
	 * @param array $feed
	 *
	 * @return array
	 */
	public function populate_integration_data( $feed ) {

		$integrations = isset( $feed['integrations'] ) ? $feed['integrations'] : array();
		$templates    = isset( $feed['templates'] ) ? $feed['templates'] : array();

		// Get all integrations.
		$all_integrations = get_transient( 'automator_all_integration_items' );
		if ( false === $all_integrations ) {
			$admin_menu_instance = Admin_Menu::get_instance();
			$all_integrations    = $admin_menu_instance->get_integrations();
		}

		// Get active integrations.
		$active_integrations = Automator()->get_integrations();

		// Key to map.
		$keys = array(
			'is_pro'             => 'is_pro',
			'is_elite'           => 'is_elite',
			'is_built_in'        => 'is_built_in',
			'short_description'  => 'short_description',
			'external_permalink' => 'external_permalink',
			'name'               => 'name',
			'integration_id'     => 'code',
			'icon_url'           => 'icon',
		);

		foreach ( $integrations as $key => $integration ) {
			$id        = $integration['id']; // Post ID
			$all_match = isset( $all_integrations[ $id ] ) ? $all_integrations[ $id ] : false;

			// Add integration details from key array.
			foreach ( $keys as $k => $v ) {
				$integrations[ $key ][ $v ] = $all_match && isset( $all_match->$k ) ? $all_match->$k : 0;
				if ( 'short_description' === $k && empty( $integrations[ $key ][ $v ] ) ) {
					$integrations[ $key ][ $v ] = '';
				}
			}

			$code         = $integrations[ $key ]['code']; // Integration code
			$active_match = isset( $active_integrations[ $code ] ) ? $active_integrations[ $code ] : array();
			$is_active    = ! empty( $active_match );

			// Add 'is_installed' from active check.
			$integrations[ $key ]['is_installed'] = $is_active;

			// Check if integration is an app.
			$has_settings  = isset( $active_match['settings_url'] ) && ! empty( $active_match['settings_url'] );
			$has_connected = isset( $active_match['connected'] );
			$is_app        = $has_settings && $has_connected;

			// Add app data.
			$integrations[ $key ]['is_app']        = $is_app;
			$integrations[ $key ]['miscellaneous'] = array(
				'is_app_connected'  => $is_app && ! empty( $active_match['connected'] ),
				'url_settings_page' => $is_app && $has_settings ? $active_match['settings_url'] : '',
			);
		}

		// Check Pro and Elite integration counts.
		$pro_count   = $this->count_templates_by_requires_prop( 'requires_pro', $templates );
		$elite_count = $this->count_templates_by_requires_prop( 'requires_elite', $templates );
		if ( $pro_count > 0 || $elite_count > 0 ) {
			// Get Automator Core.
			$icons    = wp_list_pluck( $all_integrations, 'icon_url', 'integration_id' );
			$icon_url = isset( $icons['UOA'] )
				? $icons['UOA']
				: 'https://integrations.automatorplugin.com/assets/integrations/icons/automator-core-icon.svg';
		}
		// Add Automator Pro integration.
		if ( $pro_count > 0 ) {
			$integrations[] = $this->generate_automator_pro_integration( $pro_count, $icon_url );
		}
		// Add Elite integrations.
		if ( $elite_count > 0 ) {
			$integrations[] = $this->generate_automator_elite_integration( $elite_count, $icon_url );
		}

		return $integrations;
	}

	/**
	 * Generate Automator Pro integration details.
	 *
	 * @param int   $count
	 * @param array $icon_url
	 *
	 * @return array
	 */
	private function generate_automator_pro_integration( $count, $icon_url ) {
		return array(
			'id'                 => -10,
			'name'               => 'Automator Pro',
			'slug'               => 'automator-pro',
			'count'              => $count,
			'code'               => 'UOA',
			'icon'               => $icon_url,
			'is_pro'             => 1,
			'is_elite'           => 0,
			'is_built_in'        => 0,
			'short_description'  => esc_html__( 'Automator Pro includes conditions, delays and loops, thousands of additional triggers, actions and tokens, and unlimited app credits for app integrations.', 'uncanny-automator' ),
			'external_permalink' => 'https://automatorplugin.com/pricing/',
			'is_installed'       => is_automator_pro_active(),
			'is_app'             => false,
			'miscellaneous'      => array(
				'is_app_connected'  => false,
				'url_settings_page' => '',
			),
		);
	}

	/**
	 * Generate Automator Pro integration details.
	 *
	 * @param int   $count
	 * @param array $icon_url
	 *
	 * @return array
	 */
	private function generate_automator_elite_integration( $count, $icon_url ) {
		return array(
			'id'                 => -20,
			'name'               => 'Elite Integrations Addon',
			'slug'               => 'automator-elite',
			'count'              => $count,
			'code'               => 'UOA',
			'icon'               => $icon_url,
			'is_pro'             => 0,
			'is_elite'           => 1,
			'is_built_in'        => 0,
			'short_description'  => esc_html__( 'The Elite Integrations Addon for Uncanny Automator adds additional integrations with enterprise-level software.', 'uncanny-automator' ),
			'external_permalink' => 'https://automatorplugin.com/elite-integrations-addon/',
			'is_installed'       => defined( 'UAEI_PLUGIN_VERSION' ),
			'is_app'             => false,
			'miscellaneous'      => array(
				'is_app_connected'  => false,
				'url_settings_page' => '',
			),
		);
	}

	/**
	 * Count templates by requires property ( requires_pro, requires_elite ).
	 *
	 * @param string $prop
	 *
	 * @return int
	 */
	public function count_templates_by_requires_prop( $prop, $templates ) {
		$templates = wp_list_pluck( $templates, $prop );
		return count( array_filter( $templates ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/template-library',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_rest_requests' ),
				'args'                => array(),
				'permission_callback' => array( $this, 'rest_permission_callback' ),
			)
		);
	}

	/**
	 * REST permission callback.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return bool
	 */
	public function rest_permission_callback( $request ) {

		// Validate the request event.
		$whitelist = array( 'template-library-search', 'template-library-import' );
		if ( ! in_array( $request->get_param( 'event' ), $whitelist, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Handle rest requests.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function handle_rest_requests( $request ) {

		$event = $request->get_param( 'event' );

		// Log search event.
		if ( 'template-library-search' === $event ) {
			return $this->reports()->log_event( $request );
		}

		// Import template.
		if ( 'template-library-import' === $event ) {
			return $this->import_template( $request );
		}
	}

	/**
	 * Import template.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	private function import_template( $request ) {
		$params = $request->get_params();
		try {
			// Import template.
			$template_id = isset( $params['template'] ) ? absint( $params['template'] ) : 0;
			if ( empty( $template_id ) ) {
				throw new Exception( 'Invalid template ID', 400 );
			}

			// Fetch the template export.
			$response = wp_remote_get( $this->api_base . $this->library_directory . $template_id . '.json' );
			if ( is_wp_error( $response ) ) {
				throw new Exception( esc_html( $response->get_error_message() ), 400 );
			}

			if ( 404 === wp_remote_retrieve_response_code( $response ) ) {
				throw new Exception( 'Template not found', 400 );
			}

			$recipe_json = json_decode( wp_remote_retrieve_body( $response ) );

			// Check for decode error.
			if ( json_last_error() !== JSON_ERROR_NONE || empty( $recipe_json ) ) {
				throw new Exception( 'Invalid template data', 400 );
			}

			// Pre-import filters.
			$this->importer()->pre_import_filters();

			// Import the recipe.
			$new_recipe_id = $this->importer()->import_recipe_json( $recipe_json );
			if ( is_wp_error( $new_recipe_id ) ) {
				throw new Exception( esc_html( $new_recipe_id->get_error_message() ), 400 );
			}

			do_action( 'automator_recipe_imported', $new_recipe_id );

			// Set the title as the value property for the log event.
			$request->set_param( 'value', $recipe_json->recipe->post->post_title );

			// Log the import event.
			$this->reports()->log_event( $request );

			// Success - return redirect url to new recipe.
			return new WP_REST_Response(
				array(
					'success'  => true,
					'redirect' => get_edit_post_link( $new_recipe_id, 'url' ),
				),
				200
			);

		} catch ( Exception $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $e->getMessage(),
				),
				200
			);
		}
	}

	/**
	 * Usage reports instance.
	 *
	 * @return Usage_Reports
	 */
	public function reports() {
		static $reports = null;
		if ( null === $reports ) {
			$reports = Automator_Load::get_core_class_instance( 'Usage_Reports' );
			$reports = is_a( $reports, 'Usage_Reports' ) ? $reports : new Usage_Reports();
		}
		return $reports;
	}

	/**
	 * Importer instance.
	 *
	 * @return Importer
	 */
	public function importer() {
		static $importer = null;
		if ( null === $importer ) {
			$importer = Automator_Load::get_core_class_instance( 'Import_Recipe' );
			$importer = is_a( $importer, 'Import_Recipe' ) ? $importer : new Import_Recipe();
		}
		return $importer;
	}
}
