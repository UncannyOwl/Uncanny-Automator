<?php
namespace Uncanny_Automator\Services;

/**
 * Every call Automator and Pro make into Action Scheduler goes through here.
 *
 * Automator bundles Action Scheduler, and the newest copy on the site is the one that loads. When none has loaded
 * (a build without vendor/, a copy that failed to initialize, a call made before plugins_loaded), each method returns
 * what Action Scheduler itself returns when there is nothing to schedule, find or change, instead of a fatal error.
 *
 * @since 8.0.0
 */
class Action_Scheduler {

	/**
	 * Action statuses, the same values as ActionScheduler_Store's constants, usable without that class.
	 */
	const STATUS_COMPLETE = 'complete';
	const STATUS_PENDING  = 'pending';
	const STATUS_RUNNING  = 'in-progress';
	const STATUS_FAILED   = 'failed';
	const STATUS_CANCELED = 'canceled';

	/**
	 * Whether Action Scheduler has loaded.
	 *
	 * @return bool
	 */
	public static function is_available() {
		return function_exists( 'as_schedule_single_action' ) && class_exists( 'ActionScheduler', false );
	}

	/**
	 * Whether a value is an action Action Scheduler actually holds.
	 *
	 * Action Scheduler answers an ID it can't find with ActionScheduler_NullAction, which extends
	 * ActionScheduler_Action and has an empty hook, empty args and no date. It never leaves this class.
	 *
	 * @since 8.0.0
	 *
	 * @param mixed $value
	 *
	 * @return bool
	 */
	public static function is_action( $value ) {
		return $value instanceof \ActionScheduler_Action && ! $value instanceof \ActionScheduler_NullAction;
	}

	/**
	 * When an action runs next.
	 *
	 * @since 8.0.0
	 *
	 * @param \ActionScheduler_Action $action An action this class returned.
	 *
	 * @return int|null The Unix timestamp, or null when its schedule has no date.
	 */
	public static function get_scheduled_timestamp( $action ) {
		$schedule = $action->get_schedule();
		$date     = $schedule instanceof \ActionScheduler_Schedule ? $schedule->get_date() : null;

		return $date instanceof \DateTime ? $date->getTimestamp() : null;
	}

	/**
	 * How often an action repeats.
	 *
	 * @since 8.0.0
	 *
	 * @param \ActionScheduler_Action $action An action this class returned.
	 *
	 * @return int|null Seconds between runs, or null when it isn't on an interval schedule (single, cron or none).
	 */
	public static function get_interval( $action ) {
		$schedule = $action->get_schedule();

		return $schedule instanceof \ActionScheduler_IntervalSchedule ? (int) $schedule->get_recurrence() : null;
	}

	/**
	 * The version of the copy of Action Scheduler that is running.
	 *
	 * @since 8.0.0
	 *
	 * @return string The version, or '' when Action Scheduler has not loaded.
	 */
	public static function version() {
		if ( ! self::is_available() ) {
			return '';
		}

		return (string) \ActionScheduler_Versions::instance()->latest_version();
	}

	/**
	 * Where the running copy of Action Scheduler was loaded from.
	 *
	 * @since 8.0.0
	 *
	 * @return string Its directory, or '' when Action Scheduler has not loaded.
	 */
	public static function source_path() {
		if ( ! self::is_available() ) {
			return '';
		}

		return (string) \ActionScheduler::plugin_path( '' );
	}

	/**
	 * Loads the lock Action Scheduler takes at shutdown, while its files are certainly there.
	 *
	 * On admin requests, Action Scheduler's shutdown hook takes a lock before it starts the async queue, and the lock's
	 * classes are otherwise loaded only then. Updating or deleting the plugin that ships the active copy removes those
	 * files while other requests are still running, so the lock would fatal. Loaded here, shutdown never reads the disk.
	 *
	 * @return void
	 */
	public static function load_shutdown_lock() {
		if ( is_admin() && class_exists( 'ActionScheduler', false ) ) {
			\ActionScheduler::lock();
		}
	}

	/**
	 * @see as_enqueue_async_action()
	 *
	 * @param string $hook
	 * @param array  $args
	 * @param string $group
	 * @param bool   $unique
	 * @param int    $priority
	 *
	 * @return int The action ID, or 0 when it was not scheduled.
	 */
	public static function enqueue_async_action( $hook, $args = array(), $group = '', $unique = false, $priority = 10 ) {
		if ( ! self::is_available() ) {
			return 0;
		}

		return as_enqueue_async_action( $hook, $args, $group, $unique, $priority );
	}

	/**
	 * @see as_schedule_single_action()
	 *
	 * @param int    $timestamp
	 * @param string $hook
	 * @param array  $args
	 * @param string $group
	 * @param bool   $unique
	 * @param int    $priority
	 *
	 * @return int The action ID, or 0 when it was not scheduled.
	 */
	public static function schedule_single_action( $timestamp, $hook, $args = array(), $group = '', $unique = false, $priority = 10 ) {
		if ( ! self::is_available() ) {
			return 0;
		}

		return as_schedule_single_action( $timestamp, $hook, $args, $group, $unique, $priority );
	}

	/**
	 * @see as_schedule_recurring_action()
	 *
	 * @param int    $timestamp
	 * @param int    $interval_in_seconds
	 * @param string $hook
	 * @param array  $args
	 * @param string $group
	 * @param bool   $unique
	 * @param int    $priority
	 *
	 * @return int The action ID, or 0 when it was not scheduled.
	 */
	public static function schedule_recurring_action( $timestamp, $interval_in_seconds, $hook, $args = array(), $group = '', $unique = false, $priority = 10 ) {
		if ( ! self::is_available() ) {
			return 0;
		}

		return as_schedule_recurring_action( $timestamp, $interval_in_seconds, $hook, $args, $group, $unique, $priority );
	}

	/**
	 * @see as_schedule_cron_action()
	 *
	 * @param int    $timestamp
	 * @param string $schedule
	 * @param string $hook
	 * @param array  $args
	 * @param string $group
	 * @param bool   $unique
	 * @param int    $priority
	 *
	 * @return int The action ID, or 0 when it was not scheduled.
	 */
	public static function schedule_cron_action( $timestamp, $schedule, $hook, $args = array(), $group = '', $unique = false, $priority = 10 ) {
		if ( ! self::is_available() ) {
			return 0;
		}

		return as_schedule_cron_action( $timestamp, $schedule, $hook, $args, $group, $unique, $priority );
	}

	/**
	 * @see as_unschedule_action()
	 *
	 * @param string $hook
	 * @param array  $args
	 * @param string $group
	 *
	 * @return int|null The cancelled action's ID, or null when nothing was cancelled.
	 */
	public static function unschedule_action( $hook, $args = array(), $group = '' ) {
		if ( ! self::is_available() ) {
			return null;
		}

		return as_unschedule_action( $hook, $args, $group );
	}

	/**
	 * @see as_unschedule_all_actions()
	 *
	 * @param string $hook
	 * @param array  $args
	 * @param string $group
	 *
	 * @return void
	 */
	public static function unschedule_all_actions( $hook, $args = array(), $group = '' ) {
		if ( ! self::is_available() ) {
			return;
		}

		as_unschedule_all_actions( $hook, $args, $group );
	}

	/**
	 * @see as_next_scheduled_action()
	 *
	 * @param string     $hook
	 * @param array|null $args
	 * @param string     $group
	 *
	 * @return int|bool The next run's timestamp, true when it is running or async, false when none is scheduled.
	 */
	public static function next_scheduled_action( $hook, $args = null, $group = '' ) {
		if ( ! self::is_available() ) {
			return false;
		}

		return as_next_scheduled_action( $hook, $args, $group );
	}

	/**
	 * @see as_has_scheduled_action()
	 *
	 * @param string     $hook
	 * @param array|null $args
	 * @param string     $group
	 *
	 * @return bool
	 */
	public static function has_scheduled_action( $hook, $args = null, $group = '' ) {
		if ( ! self::is_available() ) {
			return false;
		}

		return as_has_scheduled_action( $hook, $args, $group );
	}

	/**
	 * @see as_get_scheduled_actions()
	 *
	 * @param array  $args
	 * @param string $return_format OBJECT, ARRAY_A or 'ids'.
	 *
	 * @return array In OBJECT format, actions keyed by ID, leaving out any that is_action() rejects.
	 */
	public static function get_scheduled_actions( $args = array(), $return_format = OBJECT ) {
		if ( ! self::is_available() ) {
			return array();
		}

		$actions = as_get_scheduled_actions( $args, $return_format );

		if ( OBJECT === $return_format ) {
			return array_filter( $actions, array( self::class, 'is_action' ) );
		}

		return $actions;
	}

	/**
	 * @param int|string $action_id
	 *
	 * @return \ActionScheduler_Action|null Null when Action Scheduler has not loaded or has no action with that ID.
	 */
	public static function fetch_action( $action_id ) {
		$action_id = (int) $action_id;

		if ( 1 > $action_id || ! self::is_available() ) {
			return null;
		}

		$action = \ActionScheduler::store()->fetch_action( $action_id );

		return self::is_action( $action ) ? $action : null;
	}

	/**
	 * @param int $action_id
	 *
	 * @return string One of the STATUS_* values, or '' when Action Scheduler has not loaded or has no action with that ID.
	 */
	public static function get_status( $action_id ) {
		if ( ! self::is_available() ) {
			return '';
		}

		// The database store throws for an unknown ID; the hybrid store used while Action Scheduler migrates returns null.
		try {
			return (string) \ActionScheduler::store()->get_status( $action_id );
		} catch ( \InvalidArgumentException $e ) {
			return '';
		}
	}

	/**
	 * Does nothing when Action Scheduler has no action with that ID.
	 *
	 * @param int $action_id
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException When Action Scheduler can't change an action it has.
	 */
	public static function cancel_action( $action_id ) {
		if ( ! self::is_available() ) {
			return;
		}

		try {
			\ActionScheduler::store()->cancel_action( $action_id );
		} catch ( \InvalidArgumentException $e ) {
			if ( '' !== self::get_status( $action_id ) ) {
				throw $e;
			}
		}
	}

	/**
	 * @param string $group
	 *
	 * @return void
	 */
	public static function cancel_actions_by_group( $group ) {
		if ( ! self::is_available() ) {
			return;
		}

		\ActionScheduler::store()->cancel_actions_by_group( $group );
	}

	/**
	 * Does nothing when Action Scheduler has no action with that ID.
	 *
	 * @param int $action_id
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException When Action Scheduler can't change an action it has.
	 */
	public static function delete_action( $action_id ) {
		if ( ! self::is_available() ) {
			return;
		}

		try {
			\ActionScheduler::store()->delete_action( $action_id );
		} catch ( \InvalidArgumentException $e ) {
			if ( '' !== self::get_status( $action_id ) ) {
				throw $e;
			}
		}
	}

	/**
	 * Does nothing when Action Scheduler has no action with that ID.
	 *
	 * @param int $action_id
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException When Action Scheduler can't change an action it has.
	 */
	public static function mark_failure( $action_id ) {
		if ( ! self::is_available() ) {
			return;
		}

		try {
			\ActionScheduler::store()->mark_failure( $action_id );
		} catch ( \InvalidArgumentException $e ) {
			if ( '' !== self::get_status( $action_id ) ) {
				throw $e;
			}
		}
	}

	/**
	 * Adds a line to the action's log on Tools → Scheduled Actions.
	 *
	 * @param int    $action_id
	 * @param string $message
	 *
	 * @return void
	 */
	public static function log( $action_id, $message ) {
		if ( ! self::is_available() ) {
			return;
		}

		\ActionScheduler_Logger::instance()->log( $action_id, $message );
	}
}
