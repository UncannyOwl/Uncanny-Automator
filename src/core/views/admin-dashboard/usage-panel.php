<?php
/**
 * Dashboard "Usage" panel partial.
 *
 * Expects `$dashboard` populated by `Admin_Menu::get_dashboard_details()`.
 *
 * @package Uncanny_Automator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_connected = (bool) $dashboard->has_site_connected;
$is_unlimited = (bool) $dashboard->is_pro;
$plan_label   = $is_unlimited ? esc_html_x( 'Pro', 'Dashboard usage panel', 'uncanny-automator' ) : '';

$app_used  = $is_connected ? absint( $dashboard->paid_usage_count ) : 0;
$app_limit = $is_unlimited ? null : absint( $dashboard->usage_limit );
$app_pct   = ( $app_limit && $app_limit > 0 ) ? min( 100, (int) round( ( $app_used / $app_limit ) * 100 ) ) : 0;

if ( ! $is_connected ) {
	$app_state    = 'over';
	$app_pill     = esc_html__( 'Not connected', 'uncanny-automator' );
	$app_pill_cls = 'uap-dashboard-usage__pill--over';
} elseif ( $is_unlimited ) {
	$app_state    = 'unlimited';
	$app_pill     = $plan_label;
	$app_pill_cls = 'uap-dashboard-usage__pill--plan';
} elseif ( $app_pct >= 100 ) {
	$app_state    = 'over';
	$app_pill     = esc_html__( '100% used', 'uncanny-automator' );
	$app_pill_cls = 'uap-dashboard-usage__pill--over';
} elseif ( $app_pct >= 80 ) {
	$app_state    = 'near-limit';
	$app_pill     = esc_html__( 'Near limit', 'uncanny-automator' );
	$app_pill_cls = 'uap-dashboard-usage__pill--near';
} else {
	$app_state    = 'healthy';
	$app_pill     = $app_pct . '%';
	$app_pill_cls = 'uap-dashboard-usage__pill--healthy';
}

$llm       = $dashboard->llm_credits;
$llm_ready = $is_connected && ! empty( $llm->has_data );
$agent_pct = $llm_ready ? max( 0, min( 100, (int) $llm->usage_percent ) ) : 0;

if ( ! $is_connected ) {
	$agent_state    = 'over';
	$agent_pill     = esc_html__( 'Not connected', 'uncanny-automator' );
	$agent_pill_cls = 'uap-dashboard-usage__pill--over';
} elseif ( $agent_pct >= 100 ) {
	$agent_state    = 'over';
	$agent_pill     = esc_html__( 'Over', 'uncanny-automator' );
	$agent_pill_cls = 'uap-dashboard-usage__pill--over';
} elseif ( $agent_pct >= 80 ) {
	$agent_state    = 'near-limit';
	$agent_pill     = esc_html__( 'Near limit', 'uncanny-automator' );
	$agent_pill_cls = 'uap-dashboard-usage__pill--near';
} else {
	$agent_state    = 'healthy';
	$agent_pill     = $agent_pct . '%';
	$agent_pill_cls = 'uap-dashboard-usage__pill--healthy';
}

$agent_allocation_name = $llm_ready ? (string) $llm->allocation_name : '';
$agent_expires_on      = $llm_ready ? (string) $llm->expires_on : '';

$account_url     = defined( 'AUTOMATOR_STORE_URL' ) ? AUTOMATOR_STORE_URL : 'https://automatorplugin.com/';
$app_learn_url   = 'https://automatorplugin.com/knowledge-base/what-are-credits/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=learn_more_about_credits';
$agent_usage_url = $account_url . 'my-account/usage/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=usage_agent';
$agent_more_url  = 'https://automatorplugin.com/get-more-done-with-uncanny-agent/?utm_source=uncanny_automator&utm_medium=dashboard&utm_content=get_more_agent';

$recipes = Automator()->get->fetch_recipe_with_apps();
?>

<div id="uap-dashboard-usage" class="uap-dashboard-section uap-dashboard-usage">
	<div class="uap-dashboard-section__title">
		<?php esc_html_e( 'Usage', 'uncanny-automator' ); ?>
	</div>

	<div class="uap-dashboard-section__content uap-dashboard-usage__content">

		<div class="uap-dashboard-box uap-dashboard-usage__panes">
			<div class="uap-dashboard-usage__panes-grid">

				<div class="uap-dashboard-usage__pane" data-state="<?php echo esc_attr( $app_state ); ?>">
					<div class="uap-dashboard-usage__pane-header">
						<span class="uap-dashboard-usage__eyebrow">
							<?php esc_html_e( 'App credits', 'uncanny-automator' ); ?>
						</span>
						<span class="uap-dashboard-usage__pill <?php echo esc_attr( $app_pill_cls ); ?>">
							<?php echo esc_html( $app_pill ); ?>
						</span>
					</div>

					<?php if ( $is_connected ) : ?>
						<div class="uap-dashboard-usage__big">
							<span class="uap-dashboard-usage__big-number">
								<?php echo esc_html( number_format_i18n( $app_used ) ); ?>
							</span>
							<span class="uap-dashboard-usage__big-suffix">
								<?php
								if ( $is_unlimited ) {
									esc_html_e( 'used · no limit', 'uncanny-automator' );
								} else {
									printf(
										/* translators: %s — formatted limit. */
										esc_html__( 'of %s used', 'uncanny-automator' ),
										esc_html( number_format_i18n( (int) $app_limit ) )
									);
								}
								?>
							</span>
						</div>
					<?php endif; ?>

					<uo-legacy-progress-linear
						type="<?php echo $is_unlimited ? 'unlimited' : 'determinate'; ?>"
						<?php if ( ! $is_unlimited ) : ?>
							value="<?php echo esc_attr( $app_pct ); ?>"
						<?php endif; ?>
						role="progressbar"
						<?php if ( $is_unlimited ) : ?>
							aria-valuetext="<?php esc_attr_e( 'Unlimited', 'uncanny-automator' ); ?>"
						<?php else : ?>
							aria-valuemin="0"
							aria-valuemax="100"
							aria-valuenow="<?php echo esc_attr( $app_pct ); ?>"
						<?php endif; ?>
					></uo-legacy-progress-linear>

					<div class="uap-dashboard-usage__caption">
						<?php
						if ( ! $is_connected ) {
							esc_html_e( 'Site not connected', 'uncanny-automator' );
						} elseif ( $is_unlimited ) {
							esc_html_e( 'Unlimited app credits with', 'uncanny-automator' ); ?> <uo-pro-tag></uo-pro-tag> <?php
						}
						?>
					</div>

					<div class="uap-dashboard-usage__links">
						<?php if ( ! $is_connected ) : ?>
							<uo-button href="<?php echo esc_url( $setup_wizard_link ); ?>">
								<?php esc_html_e( 'Connect your site', 'uncanny-automator' ); ?>
							</uo-button>
						<?php else : ?>
							<a class="uap-dashboard-usage__link" href="<?php echo esc_url( $app_learn_url ); ?>" target="_blank" rel="noopener noreferrer">
								<?php esc_html_e( 'Learn more about App Credits', 'uncanny-automator' ); ?>
								<uo-icon id="external-link"></uo-icon>
								<span class="screen-reader-text"><?php esc_html_e( '(opens in new tab)', 'uncanny-automator' ); ?></span>
							</a>
						<?php endif; ?>
					</div>
				</div>

				<div class="uap-dashboard-usage__pane" data-state="<?php echo esc_attr( $agent_state ); ?>">
					<div class="uap-dashboard-usage__pane-header">
						<span class="uap-dashboard-usage__eyebrow">
							<?php esc_html_e( 'Uncanny Agent', 'uncanny-automator' ); ?>
						</span>
						<span class="uap-dashboard-usage__pill <?php echo esc_attr( $agent_pill_cls ); ?>">
							<?php echo esc_html( $agent_pill ); ?>
						</span>
					</div>

					<?php if ( $is_connected ) : ?>
						<div class="uap-dashboard-usage__big">
							<span class="uap-dashboard-usage__big-number">
								<?php echo esc_html( $agent_pct . '%' ); ?>
							</span>
							<span class="uap-dashboard-usage__big-suffix">
								<?php
								if ( '' !== $agent_allocation_name ) {
									printf(
										/* translators: %s — allocation name. */
										esc_html__( 'of %s used', 'uncanny-automator' ),
										esc_html( $agent_allocation_name )
									);
								} else {
									esc_html_e( 'used', 'uncanny-automator' );
								}
								?>
							</span>
						</div>
					<?php endif; ?>

					<uo-legacy-progress-linear
						type="determinate"
						value="<?php echo esc_attr( $agent_pct ); ?>"
						role="progressbar"
						aria-valuemin="0"
						aria-valuemax="100"
						aria-valuenow="<?php echo esc_attr( $agent_pct ); ?>"
					></uo-legacy-progress-linear>

					<div class="uap-dashboard-usage__caption">
						<?php
						if ( ! $is_connected ) {
							esc_html_e( 'Site not connected', 'uncanny-automator' );
						} elseif ( '' !== $agent_expires_on ) {
							$expires_ts = strtotime( $agent_expires_on );
							if ( false !== $expires_ts ) {
								printf(
									/* translators: %s — formatted expiry date. */
									esc_html__( 'Expires %s', 'uncanny-automator' ),
									esc_html( wp_date( get_option( 'date_format' ), $expires_ts ) )
								);
							}
						}
						?>
					</div>

					<div class="uap-dashboard-usage__links">
						<?php if ( ! $is_connected ) : ?>
							<uo-button href="<?php echo esc_url( $setup_wizard_link ); ?>">
								<?php esc_html_e( 'Connect your site', 'uncanny-automator' ); ?>
							</uo-button>
						<?php else : ?>
							<a class="uap-dashboard-usage__link" href="<?php echo esc_url( $agent_usage_url ); ?>" target="_blank" rel="noopener noreferrer">
								<?php esc_html_e( 'View usage details', 'uncanny-automator' ); ?>
								<uo-icon id="external-link"></uo-icon>
								<span class="screen-reader-text"><?php esc_html_e( '(opens in new tab)', 'uncanny-automator' ); ?></span>
							</a>
							<a class="uap-dashboard-usage__link" href="<?php echo esc_url( $agent_more_url ); ?>" target="_blank" rel="noopener noreferrer">
								<?php esc_html_e( 'Get more Uncanny Agent usage', 'uncanny-automator' ); ?>
								<uo-icon id="external-link"></uo-icon>
								<span class="screen-reader-text"><?php esc_html_e( '(opens in new tab)', 'uncanny-automator' ); ?></span>
							</a>
						<?php endif; ?>
					</div>
				</div>

			</div>
		</div>

		<div class="uap-dashboard-usage__row">

			<div class="uap-dashboard-box uap-dashboard-usage__faq">
				<div class="uap-dashboard-box-header">
					<div class="uap-dashboard-box-header__title">
						<?php esc_html_e( 'FAQ', 'uncanny-automator' ); ?>
					</div>
				</div>
				<div class="uap-dashboard-box-content">
					<uo-accordion>
						<?php foreach ( $dashboard->faq_items as $faq_item ) : ?>
							<uo-accordion-item>
								<div slot="summary"><?php echo esc_html( $faq_item['question'] ); ?></div>
								<?php echo esc_html( $faq_item['answer'] ); ?>
							</uo-accordion-item>
						<?php endforeach; ?>
					</uo-accordion>
				</div>
			</div>

			<div class="uap-dashboard-box uap-dashboard-usage__recipes">
				<div class="uap-dashboard-box-header">
					<div class="uap-dashboard-box-header__title">
						<?php esc_html_e( 'Recipes using app credits', 'uncanny-automator' ); ?>
					</div>
				</div>
				<div class="uap-dashboard-box-content uap-dashboard-box-content--top uap-dashboard-box-content--has-scroll">
					<?php if ( empty( $recipes ) ) : ?>
						<div class="uap-dashboard-usage__recipes-empty">
							<span class="uap-text-secondary">
								<span class="uap-icon uap-icon--circle-info"></span>
								<?php esc_html_e( 'No recipes using app credits on this site', 'uncanny-automator' ); ?>
							</span>
						</div>
					<?php else : ?>
						<div class="uap-dashboard-box-content-scroll">
							<table class="uap-dashboard-box-content-table uap-dashboard-usage__recipes-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Recipe', 'uncanny-automator' ); ?></th>
										<th><?php esc_html_e( 'Completions allowed', 'uncanny-automator' ); ?></th>
										<th><?php esc_html_e( 'Completed runs', 'uncanny-automator' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $recipes as $recipe ) : ?>
										<?php
										$title         = isset( $recipe['title'] ) ? (string) $recipe['title'] : '';
										$has_title     = '' !== $title && 0 !== strpos( $title, 'ID: ' );
										$allowed       = isset( $recipe['allowed_completions_total'] ) && '' !== $recipe['allowed_completions_total']
											? (int) $recipe['allowed_completions_total']
											: -1;
										$allowed_label = -1 === $allowed
											? esc_html__( 'Total: Unlimited', 'uncanny-automator' )
											/* translators: %s — formatted total. */
											: sprintf( esc_html__( 'Total: %s', 'uncanny-automator' ), number_format_i18n( $allowed ) );
										?>
										<tr>
											<td class="uap-dashboard-usage__recipes-name<?php echo $has_title ? '' : ' is-untitled'; ?>">
												<a href="<?php echo esc_url( $recipe['url'] ); ?>" target="_blank" rel="noopener noreferrer">
													<?php echo esc_html( $title ); ?>
												</a>
											</td>
											<td><?php echo esc_html( $allowed_label ); ?></td>
											<td><?php echo esc_html( number_format_i18n( (int) $recipe['completed_runs'] ) ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</div>
			</div>

		</div>

	</div>
</div>
