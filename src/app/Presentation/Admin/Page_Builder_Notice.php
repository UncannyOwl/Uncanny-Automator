<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin;

use Uncanny_Automator\App\Components\Page_Builder\Module;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Dismissal;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Message;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Screen;

/**
 * "Uncanny Page Builder is not available right now", with the plain reason
 * and the technical detail, while the embedded Page Builder cannot run
 * ({@see Module::is_unavailable()}), for site managers, on the recipe,
 * global-part and Page Builder settings routes only (the editors are left
 * alone). A dismissal is per user and tied to the failure, so a different
 * failure shows again; one recorded before the notice center still holds.
 *
 * @since 8.0.0
 */
final class Page_Builder_Notice extends Notice {

	/**
	 * @var Module
	 */
	private $module;

	/**
	 * @param Module $module The Page Builder host.
	 */
	public function __construct( Module $module ) {

		$this->module = $module;
	}

	/**
	 * @return string
	 */
	public function id(): string {

		return 'page-builder-unavailable';
	}

	/**
	 * @return string
	 */
	public function capability(): string {

		return 'manage_options';
	}

	/**
	 * Per user, while the failure is the one dismissed.
	 *
	 * @return Notice_Dismissal
	 */
	public function dismissal(): Notice_Dismissal {

		return Notice_Dismissal::per_user( 0, $this->module->notice_fingerprint() );
	}

	/**
	 * @param Notice_Screen $screen The screen.
	 *
	 * @return Notice_Message|null
	 */
	public function message( Notice_Screen $screen ): ?Notice_Message {

		if ( ! $this->module->is_notice_route() || ! $this->module->is_unavailable() ) {
			return null;
		}

		if ( get_user_option( Module::DISMISSED_META_KEY, get_current_user_id() ) === $this->module->notice_fingerprint() ) {
			return null;
		}

		$status = $this->module->get_status();

		return $this->unavailable( $status['status'], $this->module->friendly_message(), $status['detail'] );
	}

	/**
	 * One for each reason Page Builder cannot run that reads differently.
	 *
	 * @return Notice_Message[]
	 */
	public function previews(): array {

		$previews = array();
		foreach ( Module::UNAVAILABLE_STATUSES as $status ) {
			$reason              = Module::friendly_message_for( $status );
			$previews[ $reason ] = $this->unavailable( $status, $reason, 'Embedded Page Builder status: ' . $status . '.' );
		}

		$engine              = Module::friendly_message_for( 'boot_failed', true );
		$previews[ $engine ] = $this->unavailable( 'boot_failed', $engine, 'Embedded Page Builder boot failed: a table does not use InnoDB.' );

		return array_values( $previews );
	}

	/**
	 * @param string $status The module status.
	 * @param string $reason The plain reason.
	 * @param string $detail The technical detail.
	 *
	 * @return Notice_Message
	 */
	private function unavailable( string $status, string $reason, string $detail ): Notice_Message {

		return Notice_Message::error( esc_html_x( 'Uncanny Page Builder is not available right now.', 'Page Builder module admin notice', 'uncanny-automator' ) )
			->with_state( $status )
			->with_paragraph( esc_html( $reason ) )
			->with_paragraph( esc_html( $detail ) );
	}
}
