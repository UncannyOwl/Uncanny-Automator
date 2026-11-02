<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin;

use Uncanny_Automator\App\Presentation\Admin\Notices\Notice;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Action;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Message;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Screen;

/**
 * Uncanny Automator Pro is older than 8.0.0. It boots (the deprecated
 * Loop_Processor shim keeps it from fataling) but registers no block
 * processors, so every filter, delay and loop records a system error instead
 * of running. Without this the site looks healthy while recipes quietly stop
 * doing that work. For admins who can update plugins; no dismiss.
 *
 * @deprecated 8.0.0 Remove in 8.1 with the Loop_Processor shim.
 *
 * @since 8.0.0
 */
final class Pro_Version_Notice extends Notice {

	/**
	 * The first Pro version that runs blocks.
	 */
	private const MINIMUM = '8.0.0';

	/**
	 * @var string
	 */
	private $pro_version;

	/**
	 * @param string|null $pro_version The installed Pro version; read from Pro when not given ('' when Pro is absent).
	 */
	public function __construct( ?string $pro_version = null ) {

		$this->pro_version = $pro_version ?? ( defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' ) ? (string) AUTOMATOR_PRO_PLUGIN_VERSION : '' );
	}

	/**
	 * @return string
	 */
	public function id(): string {

		return 'pro-version-too-old';
	}

	/**
	 * @return string
	 */
	public function capability(): string {

		return 'update_plugins';
	}

	/**
	 * Second: filters, delays and loops record an error until Pro is updated.
	 *
	 * @return int
	 */
	public function priority(): int {

		return 20;
	}

	/**
	 * @param Notice_Screen $screen The screen.
	 *
	 * @return Notice_Message|null
	 */
	public function message( Notice_Screen $screen ): ?Notice_Message {

		if ( '' === $this->pro_version || version_compare( $this->pro_version, self::MINIMUM, '>=' ) ) {
			return null;
		}

		return $this->too_old( $this->pro_version );
	}

	/**
	 * @return Notice_Message[]
	 */
	public function previews(): array {

		return array( $this->too_old( '7.9.0' ) );
	}

	/**
	 * @param string $pro_version The Pro version installed.
	 *
	 * @return Notice_Message
	 */
	private function too_old( string $pro_version ): Notice_Message {

		return Notice_Message::error( esc_html_x( 'Update Uncanny Automator Pro', 'Uncanny Automator', 'uncanny-automator' ) )
			->with_paragraph(
				sprintf(
					/* translators: 1: installed Uncanny Automator Pro version, 2: required Uncanny Automator Pro version */
					esc_html_x( 'Uncanny Automator Pro %1$s is too old for this version of Uncanny Automator. Update Uncanny Automator Pro to %2$s or later. Until you do, any recipe that uses a filter, delay or loop will record an error instead of running those steps.', 'Uncanny Automator', 'uncanny-automator' ),
					esc_html( $pro_version ),
					esc_html( self::MINIMUM )
				)
			)
			->with_action( Notice_Action::link( esc_html_x( 'Go to Plugins', 'Admin notice', 'uncanny-automator' ), admin_url( 'plugins.php' ) )->primary() );
	}
}
