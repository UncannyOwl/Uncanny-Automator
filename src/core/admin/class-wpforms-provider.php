<?php

namespace Uncanny_Automator;

use WPForms_Provider;

/**
 * Uncanny Automator integration.
 *
 * @since 3.2
 */
class WPForms_Uncanny_Automator extends WPForms_Provider {

	/**
	 * Configuration.
	 *
	 * @since 1.5.7
	 *
	 * @var array
	 */
	private $config = array(
		'plugin'       => 'uncanny-automator/uncanny-automator.php',
		'wporg_url'    => 'https://wordpress.org/plugins/uncanny-automator/',
		'download_url' => 'https://downloads.wordpress.org/plugin/uncanny-automator.zip',
		'new_recipe'   => 'post-new.php?post_type=uo-recipe&action=add-new-trigger',
	);

	/**
	 * @var
	 */
	private $configured_recipes = array();

	/**
	 * Initialize.
	 *
	 * @since 1.7.0
	 */
	public function init() {

		$this->version  = '1.7.0';
		$this->name     = 'Uncanny Automator';
		$this->slug     = 'uncanny-automator';
		$this->priority = 19;
		$this->icon     = Utilities::automator_get_asset( 'external/wpforms/wpforms-automator-icon.png' );

		if ( is_admin() ) {
			add_action( 'wpforms_admin_page', array( $this, 'learn_more_page' ) );

			// Check if we are on the correct page. It's too early to use any WP functions for that.
			if ( $this->is_integrations_page() || $this->is_marketing_page() ) {
				add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
			}
		}

		if ( wp_doing_ajax() ) {
			add_action(
				'wp_ajax_wpforms_uncanny_automator_check_plugin_status',
				array(
					$this,
					'ajax_check_plugin_status',
				)
			);
		}

	}

	/**
	 * Check if the user is on the integrations page.
	 *
	 * @return bool
	 * @since 1.7.0
	 */
	public function is_integrations_page() {
		return automator_filter_has_var( 'page' ) && 'wpforms-settings' === automator_filter_input( 'page' );
	}

	/**
	 * Check if the user is on the marketing page.
	 *
	 * @return bool
	 * @since 1.7.0
	 */
	public function is_marketing_page() {
		return automator_filter_has_var( 'page' ) && 'wpforms-builder' === automator_filter_input( 'page' );
	}

	public function builder_sidebar() {

		if ( ! automator_filter_has_var( 'form_id' ) ) {
			return;
		}
		$form_id    = (int) automator_filter_input( 'form_id' );
		$configured = '';
		global $wpdb;

		$forms = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.post_parent
FROM $wpdb->postmeta pm
LEFT JOIN $wpdb->posts p
ON p.ID = pm.post_id AND p.post_type = %s
WHERE 1=1
AND ( pm.`meta_key` LIKE %s OR pm.`meta_key` LIKE %s OR pm.`meta_key` LIKE %s )
AND pm.`meta_value` LIKE %d
GROUP BY p.post_parent",
				'uo-trigger',
				'ANONWPFFORMS',
				'WPFFORMS',
				'ANONWPFSUBFORM',
				$form_id
			)
		);
		if ( ! empty( $forms ) ) {
			$configured               = 'configured';
			$this->configured_recipes = $forms;
		}
		echo '<a href="#" class="wpforms-panel-sidebar-section icon ' . esc_attr( $configured ) . ' wpforms-panel-sidebar-section-' . esc_attr( $this->slug ) . '" data-section="' . esc_attr( $this->slug ) . '">';

		echo '<img src="' . esc_url( $this->icon ) . '">';

		echo esc_html( $this->name );

		echo '<i class="fa fa-angle-right wpforms-toggle-arrow"></i>';

		if ( ! empty( $configured ) ) {
			echo '<i class="fa fa-check-circle-o"></i>';
		}

		echo '</a>';
	}

	/**
	 * Enqueue JS and CSS files.
	 *
	 * @since 1.7.0
	 */
	public function enqueue_assets() {

		wp_enqueue_style(
			'wpforms-admin-page-uncanny-automator',
			Utilities::automator_get_asset( 'external/wpforms/wpforms.css' ),
			null,
			AUTOMATOR_PLUGIN_VERSION
		);

		wp_enqueue_script(
			'wpforms-admin-page-uncanny-automator',
			Utilities::automator_get_asset( 'external/wpforms/wpforms.js' ),
			array(),
			AUTOMATOR_PLUGIN_VERSION
		);

		\wp_localize_script(
			'wpforms-admin-page-uncanny-automator',
			'wpforms_uncannyautomator',
			$this->get_js_strings()
		);
	}

	/**
	 * JS Strings.
	 *
	 * @return array Array of strings.
	 * @since 1.7.0
	 */
	protected function get_js_strings() {

		$error_could_not_install = sprintf(
			wp_kses( /* translators: %s - Lite plugin download URL. */
				__( 'Could not install plugin. Please <a href="%s">download</a> and install manually.', 'uncanny-automator' ),
				array(
					'a' => array(
						'href' => true,
					),
				)
			),
			esc_url( $this->config['download_url'] )
		);

		$error_could_not_activate = sprintf(
			wp_kses( /* translators: %s - Lite plugin download URL. */
				__( 'Could not activate plugin. Please activate from the <a href="%s">Plugins page</a>.', 'uncanny-automator' ),
				array(
					'a' => array(
						'href' => true,
					),
				)
			),
			esc_url( admin_url( 'plugins.php' ) )
		);

		return array(
			'nonce'                    => wp_create_nonce( 'wpforms-admin' ),
			'ajax_url'                 => admin_url( 'admin-ajax.php' ),
			'installing'               => esc_html__( 'Installing...', 'uncanny-automator' ),
			'activating'               => esc_html__( 'Activating...', 'uncanny-automator' ),
			'activated'                => esc_html__( 'Uncanny Automator Installed & Activated', 'uncanny-automator' ),
			'install_now'              => esc_html__( 'Install Now', 'uncanny-automator' ),
			'activate_now'             => esc_html__( 'Activate Now', 'uncanny-automator' ),
			'download_now'             => esc_html__( 'Download Now', 'uncanny-automator' ),
			'plugins_page'             => esc_html__( 'Go to Plugins page', 'uncanny-automator' ),
			'error_could_not_install'  => $error_could_not_install,
			'error_could_not_activate' => $error_could_not_activate,
			'manual_install_url'       => $this->config['download_url'],
			'manual_activate_url'      => admin_url( 'plugins.php' ),
		);
	}

	/**
	 * Wrap the builder content with the required markup.
	 * This one is here because we don't need the "Add New Connection" button for this provider.
	 *
	 * @since 1.7.0
	 */
	public function builder_output() {
		?>
		<div class="wpforms-panel-content-section wpforms-panel-content-section-<?php echo esc_attr( $this->slug ); ?>"
			 id="<?php echo esc_attr( $this->slug ); ?>-provider">

			<?php $this->builder_output_before(); ?>

			<div class="wpforms-panel-content-section-title">

				<?php echo esc_attr( $this->name ); ?>

			</div>

			<div class="wpforms-provider-connections-wrap wpforms-clear">

				<div class="wpforms-provider-connections">

					<?php $this->builder_content(); ?>

				</div>

			</div>

			<?php $this->builder_output_after(); ?>

		</div>
		<?php
	}

	/**
	 * Output content after the main builder output.
	 *
	 * @since 1.7.0
	 */
	public function builder_output_after() {
		$this_form_anyone = sprintf(
			__( 'When anyone submits %s form', 'uncanny-automator' ), //phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
			sprintf( '<span class="uap-wpf-integration-trigger__field">%s</span>', __( 'this', 'uncanny-automator' ) )
		);

		$field_form_anoynone = sprintf(
			__( 'When anyone submits %1$s form with %2$s in %3$s', 'uncanny-automator' ), //phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
			sprintf( '<span class="uap-wpf-integration-trigger__field">%s</span>', __( 'this', 'uncanny-automator' ) ),
			sprintf( '<span class="uap-wpf-integration-trigger__field">%s</span>', __( 'a specific value', 'uncanny-automator' ) ),
			sprintf( '<span class="uap-wpf-integration-trigger__field">%s</span>', __( 'a specific field', 'uncanny-automator' ) )
		);

		$triggers            = array(
			'ANONWPFSUBFORM'     => $this_form_anyone,
			'ANONWPFSUBMITFIELD' => $field_form_anoynone,
		);
		$nonce               = wp_create_nonce( 'Uncanny Automator' );
		$form_id             = automator_filter_input( 'form_id' );
		$form                = wpforms()->form->get( absint( $form_id ) );
		$new_recipe_url      = admin_url( $this->config['new_recipe'] ) . '&is_anon=yes&item_code=ANONWPFSUBFORM&nonce=' . $nonce;
		$new_anon_recipe_url = admin_url( $this->config['new_recipe'] ) . '&is_anon=yes&item_code=ANONWPFSUBMITFIELD&nonce=' . $nonce;

		$new_recipe_url          .= '&optionCode=ANONWPFFORMS&optionValue=' . $form_id . '&optionValue_readable=' . rawurlencode( $form->post_title );
		$new_anon_recipe_url     .= '&optionCode=ANONWPFFORMS&optionValue=' . $form_id . '&optionValue_readable=' . rawurlencode( $form->post_title );
		$is_automator_pro_active = defined( 'AUTOMATOR_PRO_FILE' );
		?>

		<div class="uap-wpf-integration">

			<img
				src="<?php echo esc_url( WPFORMS_PLUGIN_URL . 'assets/images/icon-provider-uncanny-automator.png' ); ?>"
				alt="Uncanny Automator logo"
				class="uap-wpf-integration__logo">

			<h2 class="uap-wpf-integration__title">
				<?php esc_attr_e( 'Connect Your Form to Powerful Automations', 'uncanny-automator' ); ?>
			</h2>

			<p class="uap-wpf-integration__description">
				<?php esc_attr_e( "Uncanny Automator can connect this form to your favorite WordPress plugins, sites and non-WordPress apps. Let's get started!", 'uncanny_automator' ); ?>
			</p>

			<div class="uap-wpf-integration-steps">

				<div class="uap-wpf-integration-flow-step">
					<div class="uap-wpf-integration-flow-step__left">
						<div class="uap-wpf-integration-flow-step__number">1</div>
					</div>

					<div class="uap-wpf-integration-flow-step__content">

						<div class="uap-wpf-integration-flow-step__title">
							<?php esc_attr_e( 'Choose when your recipe will run:', 'uncanny-automator' ); ?>
						</div>

						<div class="uap-wpf-integration-triggers">
							<?php
							foreach ( $triggers as $trigger_code => $trigger_sentence ) {
								$class    = ! $is_automator_pro_active && 'ANONWPFSUBMITFIELD' === $trigger_code ? ' uap-wpf-automator-pro-not-active' : '';
								$disabled = ! $is_automator_pro_active && 'ANONWPFSUBMITFIELD' === $trigger_code ? ' disabled="disabled"' : '';
								$checked  = 'ANONWPFSUBFORM' === $trigger_code ? ' checked="checked"' : '';
								?>
								<label class="uap-wpf-integration-recipe-trigger <?php echo esc_attr( $class ); ?>">
									<input type="radio" value="<?php echo esc_attr( $trigger_code ); ?>"
										   data-target="create_<?php echo esc_attr( $trigger_code ); ?>"
										   class="uap-wpf-integration-recipe-trigger-radio"
										<?php echo esc_attr( $disabled ); ?>
										<?php echo esc_attr( $checked ); ?>
									>
									<?php echo $trigger_sentence; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

									<?php if ( $disabled ) { ?>

										<span class="uap-wpf-integration-recipe-trigger-pro">
											<i class="fa fa-lock"></i>
											<?php echo wp_kses_post( sprintf( '%1$s <strong>%2$s</strong>', __( 'Requires', 'uncanny-automator' ), 'Uncanny Automator Pro' ) ); ?>
											<a href="https://automatorplugin.com/pricing/?utm_source=wpforms&utm_medium=form_marketing&utm_content=learn_more_pro_link&utm_r=wpforms"
											   target="_blank">
												<?php esc_html_e( 'Get it now', 'uncanny-automator' ); ?>
											</a>
										</span>
									<?php } ?>
								</label>
							<?php } ?>
						</div>

					</div>
				</div>

				<div class="uap-wpf-integration-flow-step">
					<div class="uap-wpf-integration-flow-step__left">
						<div class="uap-wpf-integration-flow-step__number">2</div>
					</div>

					<div class="uap-wpf-integration-flow-step__content">
						<a data-id="ANONWPFSUBFORM" href="<?php echo esc_url_raw( $new_recipe_url ); ?>" target="_blank"
						   class="uap-wpf-integration-create-recipe-btn wpforms-btn wpforms-btn-md wpforms-btn-orange">
							<?php esc_attr_e( 'Create automation', 'uncanny-automator' ); ?>
						</a>
						<a data-id="ANONWPFSUBMITFIELD" href="<?php echo esc_url_raw( $new_anon_recipe_url ); ?>"
						   target="_blank"
						   class="uap-wpf-integration-create-recipe-btn wpforms-btn wpforms-btn-md wpforms-btn-orange">
							<?php esc_attr_e( 'Create automation', 'uncanny-automator' ); ?>
						</a>
					</div>
				</div>

			</div>
			<?php if ( ! empty( $this->configured_recipes ) ) { ?>
				<p>&nbsp;</p>
				<p>&nbsp;</p>
				<h5 class="uap-wpf-integration__title">
					<?php echo esc_attr( _nx( 'Recipe using this form', 'Recipes using this form', count( $this->configured_recipes ), 'uncanny-automator' ) ); ?>
				</h5>
				<div class="uap-wpdf-connected-recipes">
					<ul>
						<?php foreach ( $this->configured_recipes as $recipe_id ) { ?>
							<li>
								<a href="<?php echo esc_url( get_edit_post_link( $recipe_id ) ); ?>"><?php echo esc_html( sprintf( '#%d- %s %s', $recipe_id, empty( get_the_title( $recipe_id ) ) ? __( 'no title', 'uncanny-automator' ) : get_the_title( $recipe_id ), 'draft' === get_post_status( $recipe_id ) ? __( '(Draft)', 'uncanny-automator' ) : '' ) ); ?></a>
							</li>
						<?php } ?>
					</ul>
				</div>
			<?php } ?>
		</div>

		<?php
	}

	/**
	 * Uncanny Automator "Learn More" admin page.
	 *
	 * @since 1.7.0
	 */
	public function learn_more_page() {
	}

	/*************************************************************************
	 * Integrations tab methods - these methods relate to the settings page. *
	 *************************************************************************/

	/**
	 * Add custom Uncanny Automator panel to the Settings Integrations tab.
	 *
	 * @param array $active
	 * @param array $settings
	 *
	 * @since 1.7.0
	 */
	public function integrations_tab_options( $active, $settings ) {

	}

	/**
	 * Output the insall/activate Uncanny Automator button.
	 *
	 * @param string $class Additonal button classes.
	 *
	 * @since 1.7.0
	 */
	public function install_button( $class = '' ) {

	}

	/**
	 * Returns whether Uncanny Automator plugin is installed or not.
	 *
	 * @return bool True if Uncanny Automator plugin is active.
	 * @since 1.7.0
	 */
	protected function installed() {
		return array_key_exists( $this->config['plugin'], get_plugins() );
	}

	/**
	 * Returns whether Uncanny Automator plugin is active or not.
	 *
	 * @return bool True if Uncanny Automator plugin is active.
	 * @since 1.7.0
	 */
	protected function active() {
		return defined( 'AUTOMATOR_BASE_FILE' ) && ( is_plugin_active( $this->config['plugin'] ) );
	}
}

new WPForms_Uncanny_Automator();

