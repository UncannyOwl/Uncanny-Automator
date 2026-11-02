<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Background;

/**
 * The "Background app actions" switch: while it is on, a capable app action's write is sent as a
 * platform job instead of waiting for the vendor. On by default. Its switch is on its own Advanced tab
 * (Presentation\Admin\Background_App_Actions_Tab), saved through a settings group of its own.
 */
final class Background_Actions_Setting {

	/**
	 * The option key.
	 *
	 * @var string
	 */
	const OPTION = 'automator_background_app_actions';

	/**
	 * The settings group its own Advanced tab saves through; no other form writes it.
	 *
	 * @var string
	 */
	const GROUP = 'automator_background_app_actions';

	/**
	 * Whether background app actions are on: unless the admin turned them off.
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		return '1' === (string) automator_get_option( self::OPTION, '1' );
	}

	/**
	 * Register the option with its settings group so options.php saves it. Background_Bootstrap binds this to `admin_init`.
	 *
	 * @return void
	 */
	public static function register_option(): void {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( self::class, 'sanitize' ),
			)
		);
	}

	/**
	 * Store `'1'` or `'0'`, nothing else.
	 *
	 * @param mixed $value The submitted value.
	 *
	 * @return string
	 */
	public static function sanitize( $value ): string {
		return in_array( (string) $value, array( '1', 'on', 'true' ), true ) ? '1' : '0';
	}
}
