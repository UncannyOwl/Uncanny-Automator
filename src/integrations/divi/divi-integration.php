<?php

namespace Uncanny_Automator\Integrations\Divi;

/**
 * Class Divi_Integration
 *
 * @package Uncanny_Automator\Integrations\Divi
 */
class Divi_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Integration setup.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Divi_Helpers();
		$this->set_integration( 'DIVI' );
		$this->set_name( 'Divi' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/divi-icon.svg' );

		// @deprecated 7.3 — Old Pro reaches Free via `Automator()->helpers->recipe->divi
		// ->options->all_divi_forms( ... )`. The singleton-chain surface lives on the
		// legacy \Uncanny_Automator\Divi_Helpers shim, not on the modern helper. New
		// code uses $this->get_item_helpers() / $this->item_helpers — never this chain.
		\Automator()->helpers->recipe->divi = new \Uncanny_Automator\Divi_Helpers();
	}

	/**
	 * Active only when the Divi theme is the current template.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		$theme = wp_get_theme();

		return 'Divi' === $theme->get_template();
	}

	/**
	 * Load triggers (full-load mode).
	 *
	 * @return void
	 */
	public function load() {
		$this->register_legacy_token_parser();

		new DIVI_SUBMIT_FORM( $this->helpers );
		new ANON_DIVI_SUBMIT_FORM( $this->helpers );
	}

	/**
	 * Shared hooks (targeted-load mode).
	 *
	 * The legacy token parser must register regardless of load mode — it's
	 * the parse-time bridge for the modern triggers' token IDs and is hit
	 * during recipe execution, not only at recipe-build time.
	 *
	 * @return void
	 */
	protected function load_shared_hooks() {
		$this->register_legacy_token_parser();
	}

	/**
	 * Register the legacy `\Uncanny_Automator\Divi_Tokens` parser.
	 *
	 * Bridges picker-format token IDs emitted at define time
	 * ({post_id}__{uid}__{idx}|field) to the dash-form keyspace modern
	 * hydrate_tokens stores under ({post_id}-{uid}|field). The parser's
	 * `'DIVIFORM' === $pieces[2]` gate keeps it a no-op for every other
	 * integration's tokens.
	 *
	 * @return void
	 */
	private function register_legacy_token_parser() {
		new \Uncanny_Automator\Divi_Tokens();
	}
}
