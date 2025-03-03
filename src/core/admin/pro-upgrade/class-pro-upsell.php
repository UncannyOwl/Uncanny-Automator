<?php

namespace Uncanny_Automator;

/**
 *
 */
class Pro_Upsell {
	/**
	 *
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'pro_upsell_menu' ) );
		add_action( 'admin_head', array( $this, 'adjust_pro_menu_item' ) );
		add_action( 'admin_head', array( $this, 'admin_menu_styles' ) );

		add_filter(
			'admin_body_class',
			function ( $classes ) {
				global $current_screen;
				if ( 'uo-recipe_page_uncanny-automator-pro-upgrade' !== $current_screen->id ) {
					return $classes;
				}

				return "$classes uo-recipe_page_uncanny-automator-config";
			}
		);
	}

	/**
	 * @return void
	 */
	public function pro_upsell_menu() {
		$page_meta = array(
			'title' => esc_attr__( 'Automator Plans: Compare features & upgrade options', 'uncanny-automator' ),
			'menu'  => esc_attr__( 'Plans', 'uncanny-automator' ),
		);

		if ( ! defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' ) ) {
			$page_meta['title'] = esc_attr__( 'Upgrade to Automator Pro', 'uncanny-automator' );
			$page_meta['menu']  = esc_attr__( 'Upgrade to Pro', 'uncanny-automator' );
		}

		add_submenu_page(
			'edit.php?post_type=uo-recipe',
			$page_meta['title'],
			'<span class="dashicons dashicons-superhero-alt"></span>' . $page_meta['menu'],
			'manage_options',
			'uncanny-automator-pro-upgrade',
			array(
				$this,
				'pro_upgrade_view',
			),
			PHP_INT_MAX
		);
	}

	/**
	 * Make changes to the PRO menu item.
	 */
	public function adjust_pro_menu_item() {

		global $submenu;

		// Bail if plugin menu is not registered.
		if ( ! isset( $submenu['edit.php?post_type=uo-recipe'] ) ) {
			return;
		}

		$upgrade_link_position = key(
			array_filter(
				$submenu['edit.php?post_type=uo-recipe'],
				function ( $item ) {
					return strpos( $item[2], 'uncanny-automator-pro-upgrade' ) !== false;
				}
			)
		);

		// Bail if "Upgrade to Pro" menu item is not registered.
		if ( $upgrade_link_position === null ) {
			return;
		}

		// Add the PRO badge to the menu item.
		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		if ( isset( $submenu['edit.php?post_type=uo-recipe'][ $upgrade_link_position ][4] ) ) {
			$submenu['edit.php?post_type=uo-recipe'][ $upgrade_link_position ][4] .= ' uap-sidebar-upgrade-pro';
		} else {
			$submenu['edit.php?post_type=uo-recipe'][ $upgrade_link_position ][] = 'uap-sidebar-upgrade-pro';
		}
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Output inline styles for the admin menu.
	 */
	public function admin_menu_styles() {
		// Base selector for the admin menu
		$base_selector = '#adminmenu .wp-submenu li';

		// Start the output of the styles
		ob_start();

		?>

		<style>
			/* stylelint-disable */
			<?php echo esc_attr( $base_selector ); ?> a.uap-sidebar-upgrade-pro,
			<?php echo esc_attr( $base_selector ); ?>.current a.uap-sidebar-upgrade-pro {
				/**
				 * Make the item more prominent only if the user has Lite
				 */
				<?php if ( ! defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' ) ) { ?>

					/* Material dark scheme of #6BC45A */
					/* Primary color */
					background-color: #a5d396;
					/* On primary color */
					color: #11380c;

					margin-top: 3px;

				<?php } ?>

				font-weight: 500;

				display: flex;
				align-items: center;
				gap: 3px;
			}

			<?php echo esc_attr( $base_selector ); ?> span.dashicons {
				/* 0.9 of the original size */
				font-size: calc(20px * 0.9);

				/* Reset the width and height */
				width: auto;
				height: auto;
			}
			/* stylelint-enable */
		</style>

		<?php

		// Get the styles
		$styles = ob_get_clean();

		// Ensure styles are properly escaped for HTML output
		echo wp_kses(
			$styles,
			array(
				'style' => array(),
			)
		);
	}

	/**
	 * @return void
	 */
	public function pro_upgrade_view() {
		include_once 'view-pro-upsell.php';
	}
}
