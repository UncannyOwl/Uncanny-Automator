<?php

namespace Uncanny_Automator;

/**
 * Dynamic Tabs / Settings + Integrations API settings
 * @package Uncanny_Automator
 * @author  Saad
 * @version 2.4
 */

$active    = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'settings';
$tab       = isset( Admin_Menu::$tabs[ $active ] ) ? json_decode( json_encode( Admin_Menu::$tabs[ $active ] ), false ) : array();
$connected = isset( $_GET['connect'] ) ? sanitize_text_field( $_GET['connect'] ) : '';
if ( ! empty( $tab ) && 'settings' !== $active ) {
	?>
	<div class="wrap"> <!-- WP container -->
		<div class="uo-settings">
			<div class="uo-settings-content">
				<?php
				do_action_deprecated( 'uap_before_automator_settings_form', array(), '3.0', 'automator_before_settings_form' );
				do_action( 'automator_before_settings_form' );
				?>
				<form class="uo-settings-content-form" method="POST" action="options.php">
					<?php
					do_action_deprecated( 'uap_before_automator_settings', array(), '3.0', 'automator_before_settings' );
					do_action( 'automator_before_settings' );
					if ( $tab ) {
						if ( isset( $tab->settings_field ) ) {
							settings_fields( $tab->settings_field );
						}
						if ( isset( $tab->wp_nonce_field ) ) {
							wp_nonce_field( $tab->wp_nonce_field, $tab->wp_nonce_field );
						}
						?>
						<div class="uo-settings-content-top">
							<div class="uo-settings-content-info">
								<div class="uo-settings-content-title">
									<?php echo isset( $tab->title ) ? esc_html( $tab->title ) : ''; ?>
								</div>
								<div class="uo-settings-content-description">
									<?php echo isset( $tab->description ) ? $tab->description : ''; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</div>
								<?php if ( isset( $tab->is_pro ) && $tab->is_pro && ( ! defined( 'AUTOMATOR_PRO_FILE' ) || ! class_exists( '\Uncanny_Automator_Pro\InitializePlugin' ) ) ) { ?>
									<div class="uap-report-filters__pro-notice">
										<div class="uap-report-filters__pro-notice-text">
											<?php
											/* translators: 1. Trademarked term */
											echo wp_kses_post( sprintf( esc_attr__( 'Upgrade to %1$s to access this feature.', 'uncanny-automator' ), '<a href="https://automatorplugin.com/pricing/?utm_source=uncanny_automator&utm_medium=settings&utm_content=' . $active . '" target="_blank">Uncanny Automator Pro</a>' ) );
											?>
										</div>
									</div>
								<?php } else { ?>
									<?php if ( isset( $tab->is_expired ) && $tab->is_expired ) { ?>
										<div
											class='error-message'><?php esc_attr_e( 'Your application access has expired. Please connect again with new credentials.', 'uncanny-automator' ); ?></div>
									<?php } ?>
									<?php if ( ! empty ( $connected ) && '1' === $connected ) { ?>
										<div
											class='updated'><?php esc_attr_e( 'Your application connected successfully.', 'uncanny-automator' ); ?></div>
									<?php } elseif ( ! empty ( $connected ) && '2' === $connected ) { ?>
										<div
											class='error-message'><?php esc_attr_e( 'Something went wrong while connecting to application. Please try again.', 'uncanny-automator' ); ?></div>
									<?php } elseif ( ! empty ( $connected ) ) { ?>
										<div class='error-message'><?php echo $connected; ?></div>
									<?php } ?>
									<?php if ( isset( $tab->fields ) && $tab->fields ) { ?>
										<?php foreach ( $tab->fields as $field_id => $field_settings ) {
											$attributes = '';
											if ( isset( $field_settings->custom_atts ) ) {
												if ( is_object( $field_settings->custom_atts ) ) {
													foreach ( $field_settings->custom_atts as $attr => $val ) {
														$attributes .= " $attr=\"$val\"";
													}
												}
											}
											?>
											<div class="uo-settings-content-form">
												<label
													for="<?php echo $field_id ?>"><?php echo $field_settings->title; ?></label>
												<input id="<?php echo $field_id ?>"
													   name="<?php echo $field_id ?>"
													   type="<?php echo $field_settings->type ?>"
													   class="uo-admin-input <?php echo $field_settings->css_classes; ?>"
													   value="<?php echo get_option( $field_id, '' ); ?>"
													   placeholder="<?php echo $field_settings->placeholder ?>"
													<?php echo $attributes; ?>
													   <?php if ( $field_settings->required ){ ?>required="required"<?php } ?>>
											</div>
										<?php } ?>
									<?php } ?>
								<?php } ?>
								<?php
								$extra_content = apply_filters_deprecated(
									'uap_after_settings_extra_content',
									array(
										'',
										$active,
										$tab,
									),
									'3.0',
									'automator_after_settings_extra_content'
								);
								$extra_content = apply_filters( 'automator_after_settings_extra_content', $extra_content, $active, $tab );
								ob_start();
								echo $extra_content; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo ob_get_clean(); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								?>
							</div>
						</div>
						<div class="uo-settings-content-footer">
							<?php if ( isset( $tab->is_pro ) && $tab->is_pro && ( ! defined( 'AUTOMATOR_PRO_FILE' ) || ! class_exists( '\Uncanny_Automator_Pro\InitializePlugin' ) ) ) { ?>
							<?php } else { ?>
								<button type="submit"
										name="<?php echo isset( $tab->save_btn_name ) ? esc_html( $tab->save_btn_name ) : 'uap_btn_save'; ?>"
										class="uo-settings-btn uo-settings-btn--primary">
									<?php
									echo isset( $tab->save_btn_title ) ? esc_html( $tab->save_btn_title ) : esc_html__( 'Save', 'uncanny-automator' );
									?>
								</button>
							<?php } ?>
							<?php
							$extra_buttons = apply_filters_deprecated(
								'uap_after_settings_extra_buttons',
								array(
									'',
									$active,
									$tab,
								),
								'3.0',
								'automator_after_settings_extra_buttons'
							);
							$extra_buttons = apply_filters( 'automator_after_settings_extra_buttons', $extra_buttons, $active, $tab );
							ob_start();
							echo $extra_buttons; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo ob_get_clean(); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							?>
						</div>
						<?php
						do_action_deprecated( 'uap_after_automator_settings', array(), '3.0', 'automator_after_settings' );
						do_action( 'automator_after_settings' );
					}
					?>
				</form>
				<?php
				do_action_deprecated( 'uap_after_automator_settings_form', array(), '3.0', 'automator_after_settings_form' );
				do_action( 'automator_after_settings_form' );
				?>
			</div>
		</div>
	</div>
	<?php
} elseif ( 'settings' === $active ) {

	if ( ! $is_pro_active ) {

		?>
		<div class="wrap"> <!-- WP container -->
			<div class="uo-settings">
				<div class="uo-settings-content">
					<form class="uo-settings-content-form" method="POST" action="options.php">
						<div class="uo-settings-content-form">
							<div class="uo-settings-content-top">
								<div class="uo-settings-content-info">
									<div class="uo-settings-content-title">
										<strong><?php esc_html_e( 'License', 'uncanny-automator' ); ?></strong></div>
									<div class="uo-settings-content-form">
										<?php if ( ! $is_connected ) { ?>
											<a href="<?php echo $connect_url; ?>"
											   class="uo-settings-btn uo-settings-btn--primary uo-connected-button">
												<?php esc_html_e( 'Connect your site', 'uncanny-automator' ); ?></a>
										<?php } else { ?>
											<div class="uo-settings-account-connected"><span
													class="dashicons dashicons-yes-alt"></span>
												<?php esc_html_e( 'Your account is connected!', 'uncanny-automator' ); ?>
											</div>
											<div></div>

											<a href="<?php echo $disconnect_account; ?>"
											   class="uo-settings-btn uo-settings-btn--secondary uo-connected-button">
												<?php esc_html_e( 'Disconnect your site', 'uncanny-automator' ); ?></a>
										<?php } ?>
									</div>
									<div class="uo-settings-content-description">
										<p><?php esc_html_e( 'Creating a free account gives you access to third-party integrations including Google Sheets, Slack, Facebook, MailChimp and more.', 'uncanny-automator' ); ?></p>
										<p><?php echo wp_kses_post( sprintf( '<strong>%s <a href="%s" target="_blank">%s</a>.</strong>', esc_html__( 'To unlock more than 3x the triggers and actions for your recipes and unlimited third-party actions, consider', 'uncanny-automator' ), self::$automator_connect_url . 'pricing/?utm_source=uncanny_automator&utm_medium=settings&utm_content=upgrade_to_pro_link', esc_html__( 'upgrading to Pro', 'uncanny-automator' ) ) ); ?></p>
									</div>
								</div>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>

		<div class="wrap"> <!-- WP container -->
			<div class="uo-settings">
				<div class="uo-settings-content">
					<form class="uo-settings-content-form" method="POST" action="options.php">
						<div class="uo-settings-content-form">
							<div class="uo-settings-content-top">
								<div class="uo-settings-content-info">
									<div class="uo-settings-content-title">
										<strong><?php esc_html_e( 'Upgrade to Uncanny Automator Pro and unlock even more value for your site!', 'uncanny-automator' ); ?></strong>
									</div>
									<div class="uo-settings-content-description">
										<ul>
											<li><?php esc_html_e( '3x the triggers and actions', 'uncanny-automator' ); ?></li>
											<li><?php esc_html_e( 'Unlimited third-party actions with no per-transaction fees', 'uncanny-automator' ); ?></li>
											<li><?php esc_html_e( 'Add schedules and delays to your actions', 'uncanny-automator' ); ?></li>
											<li><?php esc_html_e( 'Create users in recipes', 'uncanny-automator' ); ?></li>
											<li><?php esc_html_e( 'Premium help desk support', 'uncanny-automator' ); ?></li>
										</ul>
									</div>
									<div class="uo-settings-content-form">
										<?php
										echo wp_kses_post( sprintf( '<a href="%s" class="uo-settings-btn uo-settings-btn--primary uo-connected-button">%s</a>', self::$automator_connect_url . 'pricing/?utm_source=uncanny_automator&utm_medium=settings&utm_content=upgrade_to_pro_button', esc_html__( 'Upgrade to Uncanny Automator Pro', 'uncanny-automator' ) ) )
										?>
									</div>
								</div>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
		<div class="wrap"> <!-- WP container -->
			<div class="uo-settings">
				<div class="uo-settings-content">
					<form class="uo-settings-content-form" id="uo_tracking" method="POST" action="options.php">
						<div class="uo-settings-content-form">
							<div class="uo-settings-content-top">
								<div class="uo-settings-content-info">
									<div class="uo-settings-content-title">
										<strong><?php esc_html_e( 'Allow usage tracking', 'uncanny-automator' ); ?></strong>
									</div>
									<div class="uo-settings-content-form">
										<label>
											<input type="checkbox" name="uap_automator_allow_tracking"
												   id="uap_automator_allow_tracking" <?php if ( $uap_automator_allow_tracking ) {
												echo 'checked="checked"';
											} ?>
												   value="1">
											<?php esc_html_e( "By allowing us to anonymously track usage data, we'll have a better idea of which integrations are most popular and where we should focus our development effort, as well as which WordPress configurations, themes PHP versions we should test against.", 'uncanny-automator' ); ?>
										</label>
									</div>
									<div class="uo-settings-content-form" style="margin-top: 20px;">
										<button class="uo-settings-btn uo-settings-btn--primary"
												id="uap_automator_allow_tracking_button"
												type="button"><?php esc_html_e( 'Save', 'uncanny-automator' ); ?>
										</button>
									</div>
								</div>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	} else {
		include __DIR__ . '/admin-license.php';
	}
	do_action( 'automator_on_settings_page_metabox' );
	?>
	<div class="wrap"> <!-- WP container -->
		<div class="uo-settings">
			<div class="uo-settings-content">
				<?php
				do_action_deprecated( 'uap_before_automator_settings_form', array(), '3.0', 'automator_before_settings_form' );
				do_action( 'automator_before_settings_form' );
				?>
				<form class="uo-settings-content-form" method="POST" action="options.php">
					<?php
					do_action_deprecated( 'uap_before_automator_settings', array(), '3.0', 'automator_before_settings' );
					do_action( 'automator_before_settings' );
					if ( $tab ) {
						if ( isset( $tab->settings_field ) ) {
							settings_fields( $tab->settings_field );
						}
						if ( isset( $tab->wp_nonce_field ) ) {
							wp_nonce_field( $tab->wp_nonce_field, $tab->wp_nonce_field );
						}

						$header_content = apply_filters( 'automator_settings_header', '', $active, $tab );
						ob_start();
						echo $header_content; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						$header_content = ob_get_clean(); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

						?>

						<?php if ( ! empty( $header_content ) ) { ?>

							<div
								class="uo-settings-content-header<?php echo esc_attr( apply_filters( 'automator_content_header_css_class', '', $active, $tab ) ); ?>">
								<?php echo $header_content;  //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>

						<?php } ?>

						<div class="uo-settings-content-top">
							<div class="uo-settings-content-info">
								<div class="uo-settings-content-title">
									<?php echo isset( $tab->title ) ? esc_html( $tab->title ) : ''; ?>
								</div>
								<div class="uo-settings-content-description">
									<?php echo isset( $tab->description ) ? $tab->description : ''; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</div>
								<?php if ( isset( $tab->is_pro ) && $tab->is_pro && ( ! defined( 'AUTOMATOR_PRO_FILE' ) || ! class_exists( '\Uncanny_Automator_Pro\InitializePlugin' ) ) ) { ?>
									<div class="uap-report-filters__pro-notice">
										<div class="uap-report-filters__pro-notice-text">
											<?php
											/* translators: 1. Trademarked term */
											echo wp_kses_post( sprintf( esc_attr__( 'Upgrade to %1$s to access this feature.', 'uncanny-automator' ), '<a href="https://automatorplugin.com/pricing/?utm_source=uncanny_automator&utm_medium=settings&utm_content=' . $active . '" target="_blank">Uncanny Automator Pro</a>' ) );
											?>
										</div>
									</div>
								<?php } else { ?>
									<?php if ( isset( $tab->is_expired ) && $tab->is_expired ) { ?>
										<div
											class='error-message'><?php esc_attr_e( 'Your application access has expired. Please connect again with new credentials.', 'uncanny-automator' ); ?></div>
									<?php } ?>
									<?php if ( ! empty ( $connected ) && '1' === $connected ) { ?>
										<div
											class='updated'><?php esc_attr_e( 'Your application connected successfully.', 'uncanny-automator' ); ?></div>
									<?php } elseif ( ! empty ( $connected ) && '2' === $connected ) { ?>
										<div
											class='error-message'><?php esc_attr_e( 'Something went wrong while connecting to application. Please try again.', 'uncanny-automator' ); ?></div>
									<?php } ?>
									<?php if ( isset( $tab->fields ) && $tab->fields ) { ?>
										<?php foreach ( $tab->fields as $field_id => $field_settings ) {
											$attributes = '';
											if ( isset( $field_settings->custom_atts ) ) {
												if ( is_object( $field_settings->custom_atts ) ) {
													foreach ( $field_settings->custom_atts as $attr => $val ) {
														$attributes .= " $attr=\"$val\"";
													}
												}
											}
											?>
											<div class="uo-settings-content-form">
												<label
													for="<?php echo $field_id ?>"><?php echo $field_settings->title; ?></label>
												<input id="<?php echo $field_id ?>"
													   name="<?php echo $field_id ?>"
													   type="<?php echo $field_settings->type ?>"
													   class="uo-admin-input <?php echo $field_settings->css_classes; ?>"
													   value="<?php echo get_option( $field_id, '' ); ?>"
													   placeholder="<?php echo $field_settings->placeholder ?>"
													<?php echo $attributes; ?>
													   <?php if ( $field_settings->required ){ ?>required="required"<?php } ?>>
											</div>
										<?php } ?>
									<?php } ?>
								<?php } ?>
								<?php
								$extra_content = apply_filters_deprecated(
									'uap_after_settings_extra_content',
									array(
										'',
										$active,
										$tab,
									),
									'3.0',
									'automator_after_settings_extra_content'
								);
								$extra_content = apply_filters( 'automator_after_settings_extra_content', $extra_content, $active, $tab );
								ob_start();
								echo $extra_content; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo ob_get_clean(); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								?>
							</div>
						</div>
						<div class="uo-settings-content-footer">
							<?php if ( isset( $tab->is_pro ) && $tab->is_pro && ( ! defined( 'AUTOMATOR_PRO_FILE' ) || ! class_exists( '\Uncanny_Automator_Pro\InitializePlugin' ) ) ) { ?>
							<?php } else { ?>
								<button type="submit"
										name="<?php echo isset( $tab->save_btn_name ) ? esc_html( $tab->save_btn_name ) : 'uap_btn_save'; ?>"
										class="uo-settings-btn uo-settings-btn--primary">
									<?php
									echo isset( $tab->save_btn_title ) ? esc_html( $tab->save_btn_title ) : esc_html__( 'Save', 'uncanny-automator' );
									?>
								</button>
							<?php } ?>
							<?php
							$extra_buttons = apply_filters_deprecated(
								'uap_after_settings_extra_buttons',
								array(
									'',
									$active,
									$tab,
								),
								'3.0',
								'automator_after_settings_extra_buttons'
							);
							$extra_buttons = apply_filters( 'automator_after_settings_extra_buttons', $extra_buttons, $active, $tab );
							ob_start();
							echo $extra_buttons; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo ob_get_clean(); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							?>
						</div>
						<?php
						do_action_deprecated( 'uap_after_automator_settings', array(), '3.0', 'automator_after_settings' );
						do_action( 'automator_after_settings' );
					}
					?>
				</form>
				<?php
				do_action_deprecated( 'uap_after_automator_settings_form', array(), '3.0', 'automator_after_settings_form' );
				do_action( 'automator_after_settings_form' );
				?>
			</div>
		</div>
	</div>
	<?php
}
