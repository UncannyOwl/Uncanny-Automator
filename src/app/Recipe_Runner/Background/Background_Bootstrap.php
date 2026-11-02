<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Background;

use Uncanny_Automator\App\Recipe_Runner\Cron\Background_Job_Poll;

/**
 * Wires background app actions: the setting, the completion subscriber, the recovery poll and the site's own retry
 * of site-originated jobs. The one line
 * Application_Bootstrap calls for this layer, and the only file in it that registers WordPress hooks.
 */
final class Background_Bootstrap {

	/**
	 * Register everything. Every add_action() / add_filter() of this layer lives here; the setting, the subscriber
	 * and the poll only expose the callbacks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_init', array( Background_Actions_Setting::class, 'register_option' ) );

		add_action( 'automator_platform_async_completed', array( new Background_Job_Completion_Subscriber(), 'on_completion' ), 10, 6 );

		$poll = new Background_Job_Poll();
		add_filter( 'cron_schedules', array( $poll, 'add_cron_interval' ) );
		add_action( 'init', array( $poll, 'schedule_event' ) );
		add_action( Background_Job_Poll::CRON_HOOK, array( $poll, 'poll' ) );

		$site_retry = new Background_Site_Retry();
		add_action( Background_Site_Retry::CRON_HOOK, array( $site_retry, 'retry' ) );
		add_action( 'init', array( $site_retry, 'schedule_sweep' ) );
		add_action( Background_Site_Retry::SWEEP_HOOK, array( $site_retry, 'sweep' ) );
	}
}
