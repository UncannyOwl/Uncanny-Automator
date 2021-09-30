<?php

namespace Uncanny_Automator;

/**
 *
 */
class Prune_Logs {

	/**
	 * Class constructor
	 */
	public function __construct() {
		add_action( 'automator_on_settings_page_metabox', array( $this, 'add_purge_settings' ) );
		add_action( 'admin_init', array( $this, 'maybe_purge_logs' ) );
		add_action( 'admin_notices', array( $this, 'add_pruned_notice' ) );
	}

	public function add_pruned_notice() {
		if ( ! automator_filter_has_var( 'post_type' ) ) {
			return;
		}

		if ( ! automator_filter_has_var( 'page' ) ) {
			return;
		}

		if ( ! automator_filter_has_var( 'pruned' ) ) {
			return;
		}

		if ( 'uo-recipe' !== automator_filter_input( 'post_type' ) ) {
			return;
		}

		if ( 'uncanny-automator-settings' !== automator_filter_input( 'page' ) ) {
			return;
		}
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Activity logs successfully pruned.', 'uncanny-automator' ); ?></p>
		</div>
		<?php
	}

	/**
	 *
	 * Add values to settings tab
	 *
	 * @param $tabs
	 *
	 * @return mixed
	 */
	public function add_purge_settings() {
		?>
		<div class="wrap">
			<div class="uo-settings">
				<div class="uo-settings-content">
					<form class="uo-settings-content-form" method="POST" action="options.php">
						<?php
						wp_nonce_field( 'automator_manual_purge_days_nonce', 'automator_manual_purge_days_nonce' );
						$date  = get_option( 'automator_last_manual_prune_date', '' );
						$class = ! empty( $date ) ? ' uo-setting--active' : '';

						$section_header_content = '';

						if ( ! empty( $date ) ) {
							$section_header_content = esc_html( sprintf( '%s %s', esc_html__( 'Action performed on:', 'uncanny-automator' ), gmdate( get_option( 'date_format' ), $date ) ) );
						}

						?>

						<?php if ( ! empty( $section_header_content ) ){ ?> 

							<div class="uo-settings-content-header<?php echo esc_html( $class ); ?>">
								<?php echo $section_header_content; ?>
							</div>

						<?php } ?>
						
						<div class="uo-settings-content-top">
							<div class="uo-settings-content-info">
								<div class="uo-settings-content-title">
									<?php esc_html_e( 'Prune activity logs', 'uncanny-automator' ); ?>
								</div>
								<div class="uo-settings-content-description">
									<?php esc_html_e( 'Enter a number of days below to delete recipe log entries older than the specified number of days. Logs will only be deleted for recipes that are not In Progress.', 'uncanny-automator' ); ?>
								</div>
								<div class="uo-settings-content-form">
									<label
										for="automator_manual_purge_days"><?php esc_html_e( 'Enter value in days', 'uncanny-automator' ); ?></label>
									<input id="automator_manual_purge_days" name="automator_manual_purge_days"
										   type="number"
										   class="uo-admin-input"
										   value="<?php echo esc_attr( get_option( 'automator_manual_purge_days', '' ) ); ?>"
										   placeholder="10" min="0" max="365" step="1"
										   required="required"/>
								</div>
							</div>
						</div>
						<div class="uo-settings-content-footer">
							<button type="submit" name="uap_automator_purgedays_save"
									class="uo-settings-btn uo-settings-btn--primary">
								<?php esc_html_e( 'Delete logs', 'uncanny-automator' ); ?>
							</button>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	public function maybe_purge_logs() {
		if ( ! automator_filter_has_var( 'automator_manual_purge_days_nonce', INPUT_POST ) ) {
			return;
		}

		if ( ! wp_verify_nonce( automator_filter_input( 'automator_manual_purge_days_nonce', INPUT_POST ), 'automator_manual_purge_days_nonce' ) ) {
			return;
		}

		$prune_days_limit = automator_filter_input( 'automator_manual_purge_days', INPUT_POST );

		if ( empty( $prune_days_limit ) ) {
			return;
		}
		if ( intval( $prune_days_limit ) < 1 ) {
			return;
		}

		global $wpdb;

		$previous_time = gmdate( 'Y-m-d', strtotime( '-' . $prune_days_limit . ' days' ) );
		$recipes       = $wpdb->get_results( $wpdb->prepare( "SELECT `ID`, `automator_recipe_id` FROM {$wpdb->prefix}uap_recipe_log WHERE `date_time` < %s AND ( `completed` = %d OR `completed` = %d  OR `completed` = %d )", $previous_time, 1, 2, 9 ) );

		if ( empty( $recipes ) ) {
			update_option( 'automator_last_manual_prune_date', time() );
			$referrer = wp_get_referer();
			wp_safe_redirect( $referrer . '&pruned=1' );
			exit;
		}

		foreach ( $recipes as $recipe ) {
			$recipe_id               = absint( $recipe->automator_recipe_id );
			$automator_recipe_log_id = absint( $recipe->ID );

			// Prune recipe logs.
			automator_purge_recipe_logs( $recipe_id, $automator_recipe_log_id );

			// Prune trigger logs.
			automator_purge_trigger_logs( $recipe_id, $automator_recipe_log_id );

			// Prune action logs.
			automator_purge_action_logs( $recipe_id, $automator_recipe_log_id );

			// Prune closure logs.
			automator_purge_closure_logs( $recipe_id, $automator_recipe_log_id );
		}
		update_option( 'automator_last_manual_prune_date', time() );

		$referrer = wp_get_referer();
		wp_safe_redirect( $referrer . '&pruned=1' );
		exit;
	}
}
