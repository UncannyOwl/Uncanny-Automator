<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin;

use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Action;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Dismissal;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Message;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Screen;
use Uncanny_Automator\Automator_Review;
use Uncanny_Automator\Automator_System_Report;
use Uncanny_Automator\Prune_Logs;

/**
 * "Review your data management settings": U-bot, once the logs pass 1 GB
 * (`automator_display_log_size_notification_threshold_size_in_mb`), on the
 * screens the credits notice uses (Automator's, the Dashboard, Plugins,
 * Posts and Pages lists). Its X hides it for the whole site for 30 days
 * (`automator_display_log_size_notification_threshold_days`); the time of
 * the last dismissal stays in the option it always lived in.
 *
 * @since 8.0.0
 */
final class Log_Size_Notice extends Notice {

	/**
	 * When the site last dismissed it (Unix time).
	 */
	public const OPTION_LAST_DISMISSED = 'automator_dismiss_log_last_dismissed';

	/**
	 * @return string
	 */
	public function id(): string {

		return 'log-size';
	}

	/**
	 * For the site, for the threshold's days.
	 *
	 * @return Notice_Dismissal
	 */
	public function dismissal(): Notice_Dismissal {

		return Notice_Dismissal::per_site( $this->threshold_days() * DAY_IN_SECONDS );
	}

	/**
	 * @param Notice_Screen $screen The screen.
	 *
	 * @return Notice_Message|null
	 */
	public function message( Notice_Screen $screen ): ?Notice_Message {

		if ( ! in_array( $screen->id(), Automator_Review::get_allowed_page_for_credits_notif(), true ) ) {
			return null;
		}

		$threshold = (int) Dispatcher::filter( 'automator_display_log_size_notification_threshold_size_in_mb', 1024 );
		if ( $this->table_size() < $threshold || $this->dismissed_recently() ) {
			return null;
		}

		return $this->too_large( $threshold );
	}

	/**
	 * @return Notice_Message[]
	 */
	public function previews(): array {

		return array( $this->too_large( 1024 ) );
	}

	/**
	 * @param int $threshold The size the logs passed, in MB.
	 *
	 * @return Notice_Message
	 */
	private function too_large( int $threshold ): Notice_Message {

		$data_management = add_query_arg(
			array(
				'post_type' => AUTOMATOR_POST_TYPE_RECIPE,
				'page'      => 'uncanny-automator-config',
				'tab'       => 'general',
				'general'   => 'logs',
			),
			admin_url( 'edit.php' )
		);

		return Notice_Message::warning( esc_html_x( 'Review your data management settings', 'Reviews banner', 'uncanny-automator' ) )
			->with_mascot()
			->with_paragraph(
				sprintf(
					/* translators: %s: Size threshold (e.g., "1 GB") */
					esc_html_x( 'U-bot has been working hard on your recipes and noticed that the size of your logs now exceeds %s. Consider removing records you no longer need by clicking the button below to set up data management options.', 'Reviews banner', 'uncanny-automator' ),
					esc_html( Prune_Logs::format_number_in_kb_mb_gb( $threshold ) )
				)
			)
			->with_action( Notice_Action::link( esc_html_x( 'Data management', 'Reviews banner', 'uncanny-automator' ), $data_management )->primary() );
	}

	/**
	 * Keep the time of the dismissal where the site always kept it.
	 *
	 * @return void
	 */
	public function dismissed(): void {

		automator_update_option( self::OPTION_LAST_DISMISSED, time() );
	}

	/**
	 * The logs' size in MB, measured now when never measured.
	 *
	 * @return int
	 */
	private function table_size(): int {

		$size = (int) automator_get_option( 'automator_db_size', 0 );

		return 0 === $size ? (int) Automator_System_Report::refresh_cached_tables_size() : $size;
	}

	/**
	 * Whether the site dismissed it within the threshold's days.
	 *
	 * @return bool
	 */
	private function dismissed_recently(): bool {

		$last = (int) automator_get_option( self::OPTION_LAST_DISMISSED, 0 );

		return 0 !== $last && ( time() - $last ) < $this->threshold_days() * DAY_IN_SECONDS;
	}

	/**
	 * @return int
	 */
	private function threshold_days(): int {

		return max( 1, (int) Dispatcher::filter( 'automator_display_log_size_notification_threshold_days', 30 ) );
	}
}
