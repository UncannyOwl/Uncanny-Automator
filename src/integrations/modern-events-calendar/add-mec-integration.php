<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator;

/**
 * Class Add_Mec_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Mec_Integration {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'MEC';

	/**
	 * Add_Integration constructor. Do nothing for now.
	 *
	 * @return self.
	 */
	public function __construct() {
		return $this;
	}

	/**
	 * Only load this integration and its triggers and actions if the related plugin is active
	 *
	 * @param $status
	 * @param $code
	 *
	 * @return bool True if MEC class exists. Otherwise, false.
	 */
	public function plugin_active( $status, $code ) {

		if ( self::$integration === $code ) {

			if ( class_exists( 'MEC' ) ) {
				$status = true;

				// Handling fatal error due to file structure changes (combined helpers).
				$old_helper_file  = str_replace( 'uncanny-automator', 'uncanny-automator-pro', plugin_dir_path( __DIR__ ) );
				$old_helper_file .= 'modern-events-calendar' . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'mec-pro-event-helpers.php';

				// If the old helper file exists. Disable this integration.
				if ( file_exists( $old_helper_file ) ) {
					// Check if Automator Pro is active.
					if ( in_array( 'uncanny-automator-pro/uncanny-automator-pro.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
						$status = false;
						add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );
						add_action( 'admin_init', array( $this, 'update_icon' ) );
					}
				}
			} else {
				$status = false;
			}
		}

		return $status;
	}

	/**
	 * Set the directories that the auto loader will run in
	 *
	 * @param $directory
	 *
	 * @return array The list of directories.
	 */
	public function add_integration_directory_func( $directory ) {

		$directory[] = dirname( __FILE__ ) . '/helpers';
		$directory[] = dirname( __FILE__ ) . '/triggers';
		$directory[] = dirname( __FILE__ ) . '/tokens';

		return $directory;
	}

	/**
	 * Register the integration by pushing it into the global automator object
	 *
	 * @return void.
	 */
	public function add_integration_func() {

		Automator()->register->integration(
			self::$integration,
			array(
				'name'     => 'M.E. Calendar',
				'icon_svg' => Utilities::automator_get_integration_icon( __DIR__ . '/img/modern-events-calendar-icon.svg' ),
			)
		);
	}

	/**
	 * Display some admin notices.
	 *
	 * @return void.
	 */
	public function display_admin_notices() {
		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<?php esc_html_e( 'A newer version Uncanny Automator Pro is required to use Uncanny Automator with Modern Events Calendar. Please update Uncanny Automator Pro to the latest version.', 'uncanny-automator' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Update Pro integration icon.
	 *
	 * @return void.
	 */
	public function update_icon() {
		if ( isset( Automator()->integrations['MEC']['icon_svg'] ) ) {
			Automator()->integrations['MEC']['icon_svg'] = Utilities::automator_get_integration_icon( __DIR__ . '/img/modern-events-calendar-icon.svg' );
		}
	}
}
