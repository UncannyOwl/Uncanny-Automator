<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator;

/**
 * Class Add_Mec_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Mec_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Mec_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'MEC' );
		$this->set_name( 'M.E. Calendar' );
		$this->set_icon( 'modern-events-calendar-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'modern-events-calendar/mec.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'MEC_ABSPATH' );
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
