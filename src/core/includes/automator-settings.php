<?php

namespace Uncanny_Automator;

/**
 * Dynamic Tabs / Settings + Integrations API settings
 * @package Uncanny_Automator
 * @author  Saad
 * @version 2.4
 */

$active    = automator_filter_has_var( 'tab' ) ? automator_filter_input( 'tab' ) : 'settings';
$tab       = isset( Admin_Menu::$tabs[ $active ] ) ? json_decode( json_encode( Admin_Menu::$tabs[ $active ] ), false ) : array();
$connected = automator_filter_has_var( 'connect' ) ? automator_filter_input( 'connect' ) : '';
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
										<div class='error-message'><?php echo esc_attr( $connected ); ?></div>
									<?php } ?>
									<?php if ( isset( $tab->fields ) && $tab->fields ) { ?>
										<?php foreach ( $tab->fields as $field_id => $field_settings ) {
											$attributes = '';
											if ( isset( $field_settings->custom_atts ) ) {
												if ( is_object( $field_settings->custom_atts ) ) {
													foreach ( $field_settings->custom_atts as $attr => $val ) {
														$attributes .= " $attr=\"$val\"";
													}
												} else {
													$attributes .= $field_settings->custom_atts;
												}
											}
											?>
											<div class="uo-settings-content-form">
												<label
													for="<?php echo esc_attr( $field_id ) ?>"><?php echo esc_attr( $field_settings->title ); ?></label>
												<input id="<?php echo esc_attr( $field_id ) ?>"
													   name="<?php echo esc_attr( $field_id ) ?>"
													   type="<?php echo esc_attr( $field_settings->type ) ?>"
													   class="uo-admin-input <?php echo esc_attr( $field_settings->css_classes ); ?>"

													   <?php 
													   	
													   $value = '';
													   $default = '';
													   if ( isset( $field_settings->default ) && ! empty( $field_settings->default ) ) {
														   $default = $field_settings->default;
													   }

														if ( 'checkbox' === $field_settings->type ) {
															$on_off_value = get_option( $field_id, $default );
															
															if ( 'on' === $on_off_value ) {
																$value = 'checked';
															}
														} else {
															$value = 'value="' . get_option( $field_id, $default ) . '"';
														}

													   echo wp_kses_post( $value ); ?> 
													   placeholder="<?php echo esc_attr( $field_settings->placeholder ) ?>"
													<?php echo wp_kses_post( $attributes ); ?>
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
													for="<?php echo esc_attr( $field_id ) ?>"><?php echo esc_attr( $field_settings->title ); ?></label>
												<input id="<?php echo esc_attr( $field_id ) ?>"
													   name="<?php echo esc_attr( $field_id ) ?>"
													   type="<?php echo esc_attr( $field_settings->type ) ?>"
													   class="uo-admin-input <?php echo esc_attr( $field_settings->css_classes ); ?>"
													   value="<?php echo wp_kses_post( get_option( $field_id, '' ) ); ?>"
													   placeholder="<?php echo esc_attr( $field_settings->placeholder ) ?>"
													<?php echo wp_kses_post( $attributes ); ?>
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
