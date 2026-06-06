<?php

namespace Uncanny_Automator\Integrations\Gravity_Forms;

/**
 * Class Gravity_Forms_Integration
 *
 * @package Uncanny_Automator
 */
class Gravity_Forms_Integration extends \Uncanny_Automator\Integration {

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'GF' );
		$this->set_name( 'Gravity Forms' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/gravity-forms-icon.svg' );

		// Helpers MUST exist by the end of setup(): the Integration
		// constructor registers $instances_by_code ONLY when helpers are
		// non-null at construction, and Trigger_Late_Resolver feeds
		// Integration::helpers_for('GF') to lazily-fired triggers as their
		// sole dependency. Building this in load_shared_hooks() (after the
		// registration window) left helpers_for('GF') null forever — lazy
		// GF triggers constructed with no deps, $this->gf stayed null, and
		// define_tokens() fataled mid-queue ("form_tokens() on null"),
		// sticking recipes In Progress.
		$this->build_dependencies();

		// Register admin notice check
		add_action( 'admin_init', array( $this, 'check_pro_compatibility_notice' ) );
	}

	/**
	 * Build the shared $gf dependency container (tokens + helpers).
	 *
	 * Idempotent — called from setup() (registration window) and kept in
	 * load_shared_hooks() as a belt-and-braces for any path that invokes
	 * shared hooks on an instance whose setup was bypassed.
	 *
	 * @return void
	 */
	private function build_dependencies() {

		if ( null !== $this->helpers ) {
			return;
		}

		$gf          = new \stdClass();
		$gf->tokens  = new Gravity_Forms_Tokens();
		$gf->helpers = new \Uncanny_Automator\Gravity_Forms_Helpers();

		$this->helpers = $gf;
	}

	/**
	 * Shared dependencies for triggers/actions.
	 *
	 * The container is built in setup() — see build_dependencies(). Kept
	 * here so targeted mode keeps its contract (get_load_arguments() passes
	 * $this->helpers to individual items).
	 *
	 * @return void
	 */
	protected function load_shared_hooks() {
		$this->build_dependencies();
	}

	/**
	 * load
	 *
	 * @return void
	 */
	protected function load() {
		$this->load_shared_hooks();

		new ANON_GF_FORM_ENTRY_UPDATED( $this->helpers );
		new ANON_GF_SUBFORM( $this->helpers );
		new GF_SUBFORM( $this->helpers );
		new GF_SUBFORM_CODES( $this->helpers );
		new GF_SUBFORM_GROUPS( $this->helpers );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		// Check if Gravity Forms is active
		if ( ! defined( 'GF_PLUGIN_BASENAME' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check conditions for Pro compatibility notice
	 *
	 * @return void
	 */
	public function check_pro_compatibility_notice() {
		// Show notice if Pro is installed, not compatible version, and has GF recipes
		if ( defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' ) && ! $this->is_pro_compatible() && $this->has_active_gf_recipes() ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			add_action( 'automator_show_internal_admin_notice', array( $this, 'automator_compatibility_notice' ) );
		}
	}

	/**
	 * Check if installed Pro version is compatible with new Gravity Forms integration
	 *
	 * @return bool
	 */
	private function is_pro_compatible() {
		// No Pro installed, no problem
		if ( ! defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' ) ) {
			return true;
		}

		// Check if Pro has the updated Gravity Forms integration
		return version_compare( AUTOMATOR_PRO_PLUGIN_VERSION, '7.0.0', '>=' );
	}

	/**
	 * Check if user has any active Gravity Forms recipes
	 *
	 * @return bool
	 */
	private function has_active_gf_recipes() {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) 
				 FROM {$wpdb->postmeta} 
				 WHERE meta_value = %s 
				 AND meta_key = %s",
				'GF',
				'integration'
			)
		);

		return (int) $count > 0;
	}

	/**
	 * Display compatibility notice for Automator internal pages
	 */
	public function automator_compatibility_notice() {

		// Only add padding 0 if editing a recipe
		$style = Automator()->helpers->recipe->is_edit_page() ? 'padding:0;' : '';
		?>
		<div class="uap notice notice-error" style="<?php echo esc_attr( $style ); ?>">
			<uo-alert type="error" no-radius>
				<p><strong>
					<?php
					echo wp_kses(
						sprintf(
							/* translators: %s: The link to update the Pro plugin */
							esc_html_x( 'Gravity Forms integration requires Uncanny Automator and Uncanny Automator Pro to be updated to version 7.0.0 or higher. Please %s to restore Gravity Forms functionality.', 'Gravity Forms', 'uncanny-automator' ),
							sprintf(
								// translators: %s: The url to the plugins page, and the text "update the Pro plugin".
								'<a href="%s">%s</a>',
								esc_url( admin_url( 'plugins.php?s=uncanny+automator+pro' ) ),
								esc_html_x( 'update the Pro plugin', 'Admin notice link text for updating Pro plugin', 'uncanny-automator' )
							)
						),
						array(
							'a' => array(
								'href' => array(),
							),
						)
					);
					?>
				</strong></p>
			</uo-alert>
		</div>
		<?php
	}


	/**
	 * Check if current page is an Automator admin page
	 *
	 * @return bool
	 */
	private function is_automator_page() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		return strpos( $screen->id, 'uncanny-automator' ) !== false ||
			   strpos( $screen->base, 'automator' ) !== false;
	}
}
