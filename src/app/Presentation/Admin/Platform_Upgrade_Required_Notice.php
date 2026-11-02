<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Registration_Outcomes;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Action;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Message;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Screen;

/**
 * "Update Uncanny Automator" notice, shown when the platform refused this
 * plugin version as too old (PLUGIN_UPGRADE_REQUIRED).
 *
 * It exists in the FIRST /v3 client so that a platform protocol version can one
 * day be retired: the oldest install in the field already knows how to hear the
 * refusal and tell its admin, instead of failing in silence.
 *
 * State is {@see Registration_Outcomes::OPTION_UPGRADE_REQUIRED}, keyed to
 * the refused version — so the notice clears itself the moment the plugin is
 * updated, with nothing to delete. Shown by the notice center; no dismiss.
 *
 * @since 8.0.0
 */
final class Platform_Upgrade_Required_Notice extends Notice {

	/**
	 * @var Registration_Outcomes
	 */
	private $outcomes;

	/**
	 * @param Registration_Outcomes|null $outcomes Says which version the platform refused.
	 */
	public function __construct( ?Registration_Outcomes $outcomes = null ) {

		$this->outcomes = $outcomes ?? new Registration_Outcomes();
	}

	/**
	 * @return string
	 */
	public function id(): string {

		return 'platform-upgrade-required';
	}

	/**
	 * @return string
	 */
	public function capability(): string {

		return 'update_plugins';
	}

	/**
	 * First of all: every app connection is down until the plugin is updated.
	 *
	 * @return int
	 */
	public function priority(): int {

		return 10;
	}

	/**
	 * @param Notice_Screen $screen The screen.
	 *
	 * @return Notice_Message|null
	 */
	public function message( Notice_Screen $screen ): ?Notice_Message {

		if ( AUTOMATOR_PLUGIN_VERSION !== $this->outcomes->upgrade_required_version() ) {
			return null;
		}

		return $this->upgrade();
	}

	/**
	 * @return Notice_Message[]
	 */
	public function previews(): array {

		return array( $this->upgrade() );
	}

	/**
	 * @return Notice_Message
	 */
	private function upgrade(): Notice_Message {

		return Notice_Message::error( esc_html_x( 'Update Uncanny Automator', 'Admin notice', 'uncanny-automator' ) )
			->with_paragraph( esc_html__( 'This version of Uncanny Automator is too old to connect to your apps. Update the plugin to restore your app connections.', 'uncanny-automator' ) )
			->with_action( Notice_Action::link( esc_html_x( 'Go to Plugins', 'Admin notice', 'uncanny-automator' ), admin_url( 'plugins.php' ) )->primary() );
	}
}
