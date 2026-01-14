<?php

namespace Uncanny_Automator\Integrations\Telegram;

use Uncanny_Automator\Migrations\Migration;

/**
 * Class Telegram_Chat_Option_Migration.
 *
 * Migrates Telegram chat text to option data if custom values are being used.
 *
 * @package Uncanny_Automator
 */
class Telegram_Chat_Option_Migration extends Migration {

	/**
	 * Is app connected.
	 *
	 * @var bool
	 */
	private $is_app_connected;

	/**
	 * __construct
	 *
	 * @param string $name
	 * @return void
	 */
	public function __construct( $name, $is_app_connected ) {
		$this->name             = $name;
		$this->is_app_connected = $is_app_connected;
		add_action( 'shutdown', array( $this, 'maybe_run_migration' ) );
	}

	/**
	 * conditions_met
	 *
	 * Check if migration should run.
	 *
	 * @return bool
	 */
	public function conditions_met() {
		// Allow it to run, will be stopped if already run.
		return true;
	}

	/**
	 * migrate
	 *
	 * Update 'CHAT_ID' meta data.
	 *
	 * @return void
	 */
	public function migrate() {

		// If not connected flag as already completed.
		if ( ! $this->is_app_connected ) {
			$this->complete();
			return;
		}

		global $wpdb;

		// Get all SEND_MESSAGE Actions post IDs that are using 'CHAT_ID' meta key with a token.
		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm1.post_id 
				FROM {$wpdb->postmeta} pm1
				INNER JOIN {$wpdb->postmeta} pm2 
					ON pm1.post_id = pm2.post_id 
					AND pm2.meta_key = %s 
					AND pm2.meta_value = %s
				INNER JOIN {$wpdb->postmeta} pm3 
					ON pm1.post_id = pm3.post_id 
					AND pm3.meta_key = %s 
					AND pm3.meta_value LIKE %s
				WHERE pm1.meta_key = %s 
					AND pm1.meta_value = %s",
				'integration',
				'TELEGRAM',
				'CHAT_ID',
				'%{{%',
				'code',
				'SEND_MESSAGE'
			)
		);

		// Loop and adjust meta.
		foreach ( $post_ids as $post_id ) {
			// Get the 'CHAT_ID' meta value.
			$chat_id_value = get_post_meta( $post_id, 'CHAT_ID', true );
			if ( false !== strpos( $chat_id_value, '{{' ) ) {
				// Update required meta values for custom value select.
				update_post_meta( $post_id, 'CHAT_ID_custom', $chat_id_value );
				update_post_meta( $post_id, 'CHAT_ID_readable', esc_html__( 'Use a token/custom value', 'uncanny-automator' ) ); // phpcs:ignore Uncanny_Automator.Strings
				update_post_meta( $post_id, 'CHAT_ID', 'automator_custom_value' );
			}
		}

		// Mark migration as complete
		$this->complete();
	}
}
