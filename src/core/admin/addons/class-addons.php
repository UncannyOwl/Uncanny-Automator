<?php

namespace Uncanny_Automator;

/**
 * Class Addons
 * Handles the Automator addons admin page functionality
 *
 * @package Uncanny_Automator
 */
class Addons {

	/**
	 * The page identifier used in URLs and hooks
	 *
	 * @var string
	 */
	public $page_id = 'addons';

	/**
	 * Initialize the class and set up hooks
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'addons_menu' ) );
	}

	/**
	 * Adds the Addons submenu page to the WordPress admin menu
	 *
	 * @return void
	 */
	public function addons_menu() {
		$parent_slug = 'edit.php?post_type=uo-recipe';
		$page_title = esc_attr__( 'Addons', 'uncanny-automator' );
		
		// Check if 'automator' is in the page parameter or post_type is 'uo-recipe'
		$current_page = automator_filter_input( 'page' );
		$post_type = automator_filter_input( 'post_type' );
		$menu_title = esc_attr__( 'Addons', 'uncanny-automator' );
		
		// Add the chip if 'automator' is in the page parameter or post_type is 'uo-recipe'
		if ( (null !== $current_page && false !== strpos( $current_page, 'automator' )) || 'uo-recipe' === $post_type ) {
			$menu_title = sprintf(
				'%s<uo-chip color="error" size="xsmall" filled style="margin-left: 3px">%s</uo-chip>',
				$menu_title,
				esc_html( __( 'New', 'uncanny-automator' ) )
			);
		}
		
		$capability = 'manage_options';
		$menu_slug = 'uncanny-automator-' . $this->page_id;
		$function = array( $this, 'addons_view' );
		$position = 9;

		add_submenu_page(
			$parent_slug,
			$page_title,
			$menu_title,
			$capability,
			$menu_slug,
			$function,
			$position
		);
	}

	/**
	 * Renders the addons admin page view
	 *
	 * @return void
	 */
	public function addons_view() {
		include_once 'view-addons.php';
	}

	/**
	 * Get the addon details by addon ID.
	 *
	 * @param int $addon_id The addon ID.
	 *
	 * @return array|false The addon details or false if not found.
	 */
	public static function get_addon_details( $addon_id ) {
		// Get addons data.
		$addons_data = self::get_addon_feed();

		// Extract the addon details from the addons data.
		$addon = false;
		foreach ( $addons_data as $addon_data ) {
			if ( absint( $addon_data['id'] ) === absint( $addon_id ) ) {
				$addon = $addon_data;
				break;
			}
		}
		return $addon;
	}

	/**
	 * Get the addon admin URL.
	 *
	 * @param array $args The query arguments.
	 *
	 * @return string The addon admin URL.
	 */
	public static function get_addon_admin_url( $args = array() ) {

		$defaults = array(
			'post_type' => 'uo-recipe',
			'page'      => 'uncanny-automator-addons',
		);

		$args = wp_parse_args( $args, $defaults );

		return add_query_arg( $args, admin_url( 'edit.php' ) );
	}

	/**
	 * Get the addon feed.
	 *
	 * @return array The addon feed.
	 */
	public static function get_addon_feed() {
		static $addons_data = null;
		if ( null === $addons_data ) {
			$addons_data = ( new Services\Addons\Data\External_Feed() )->get_feed();
		}
		return $addons_data;
	}

	/**
	 * Get the addon settings page URL if available.
	 *
	 * @param mixed ( int|array ) $addon
	 *
	 * @return mixed ( string|int )
	 */
	public static function get_addon_settings_url( $addon, $args = array() ) {

		$tab = is_string( $addon ) ? $addon : false;
		if ( ! $tab && is_array( $addon ) ) {
			$tab = isset( $addon['tab'] ) ? $addon['tab'] : false;
		}

		if ( empty( $tab ) ) {
			return false;
		}

		$url = Admin_Settings_Addons::utility_get_addons_page_link( $tab );
		if ( ! empty( $args ) && is_array( $args ) ) {
			$url = add_query_arg( $args, $url );
		}

		return $url;
	}
}
