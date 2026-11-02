<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin;

use Uncanny_Automator\App\Presentation\Admin\Notices\Notice;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Dismissal;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Message;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Screen;

/**
 * "Automator cache flushed!", on the request that flushed Automator's cache
 * from the admin bar: {@see \Uncanny_Automator\Automator_Cache_Handler::remove_all_cache()}
 * fires `FLUSHED_ACTION` once it has. So it is shown once.
 *
 * @since 8.0.0
 */
final class Cache_Flushed_Notice extends Notice {

	/**
	 * Fired once the admin bar's "Flush cache" has flushed the cache.
	 */
	public const FLUSHED_ACTION = 'automator_cache_flushed_from_admin_bar';

	/**
	 * @return string
	 */
	public function id(): string {

		return 'cache-flushed';
	}

	/**
	 * @return string
	 */
	public function capability(): string {

		return automator_get_capability();
	}

	/**
	 * @return Notice_Dismissal
	 */
	public function dismissal(): Notice_Dismissal {

		return Notice_Dismissal::for_this_view();
	}

	/**
	 * @param Notice_Screen $screen The screen.
	 *
	 * @return Notice_Message|null
	 */
	public function message( Notice_Screen $screen ): ?Notice_Message {

		return 0 < did_action( self::FLUSHED_ACTION ) ? $this->flushed() : null;
	}

	/**
	 * @return Notice_Message[]
	 */
	public function previews(): array {

		return array( $this->flushed() );
	}

	/**
	 * @return Notice_Message
	 */
	private function flushed(): Notice_Message {

		return Notice_Message::success( esc_html_x( 'Automator cache flushed!', 'Admin notice', 'uncanny-automator' ) );
	}
}
