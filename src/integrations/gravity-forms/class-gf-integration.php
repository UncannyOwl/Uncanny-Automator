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

		$this->set_plugin_file_path( 'gravityforms/gravityforms.php' );
		$this->set_developer_name( 'Gravity Forms' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'commercial' );
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
}
