<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Integrations\WP\Tokens\Loopable\Universal\Post_Categories;
use Uncanny_Automator\Integrations\WP\Tokens\Loopable\Universal\Post_Tags;

/**
 * Class Add_Wp_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Wp_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Wp_Integration constructor.
	 */
	public function __construct() {
		add_filter( 'cron_schedules', array( $this, 'every_minute_cron_schedules' ) );

		$this->setup();

		$this->setup_cron();
	}

	/**
	 * @param $schedules
	 *
	 * @return mixed
	 */
	public function every_minute_cron_schedules( $schedules ) {
		// Check if the "every_minute" schedule already exists
		if ( ! isset( $schedules['every_minute'] ) ) {
			// Add the "every_minute" schedule
			$schedules['every_minute'] = array(
				'interval' => 60, // Interval in seconds (60 seconds = 1 minute)
				'display'  => __( 'Every minute', 'uncanny-automator' ),
			);
		}

		return $schedules;
	}

	/**
	 * Explicitly return true because WordPress is always active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return true;
	}

	/**
	 * Creates a set of loopable tokens.
	 *
	 * @return array{mixed[]}
	 */
	public function create_loopable_tokens() {
		return array(
			'WP_POST_TAGS'       => Post_Tags::class,
			'WP_POST_CATEGORIES' => Post_Categories::class,
		);
	}

	/**
	 * Setup Integration
	 */
	protected function setup() {
		$this->set_integration( 'WP' );
		$this->set_name( 'WordPress' );
		$this->set_icon( __DIR__ . '/img/wordpress-icon.svg' );
		$this->set_plugin_file_path( '' );
		$this->set_loopable_tokens( $this->create_loopable_tokens() );
	}

	/**
	 * @return void
	 */
	public function setup_cron() {
		// A post is published in a taxonomy.
		if ( ! wp_next_scheduled( 'automator_wp_post_published_in_taxonomy_posts_published' ) ) {
			wp_schedule_event( time(), 'every_minute', 'automator_wp_post_published_in_taxonomy_posts_published' );
		}

		// A user creates a post.
		if ( ! wp_next_scheduled( 'automator_userspost_posts_published' ) ) {
			wp_schedule_event( time(), 'every_minute', 'automator_userspost_posts_published' );
		}
	}
}
