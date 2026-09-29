<?php

namespace Uncanny_Automator\Integrations\Code_Snippets;

use Uncanny_Automator\Integration;

/**
 * Class Code_Snippets_Integration
 *
 * @pacakge Uncanny_Automator
 */
class Code_Snippets_Integration extends Integration {
	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Code_Snippets_Helpers();
		$this->set_integration( 'CODE_SNIPPETS' );
		$this->set_name( 'Code Snippets' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/code-snippets-icon.svg' );

		$this->set_plugin_file_path( 'code-snippets/code-snippets.php' );
		$this->set_developer_name( 'Code Snippets Pro' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'wp_org' );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		// Load triggers.
		// Load ajax methods.

		// Load actions.
		new CODE_SNIPPETS_ACTIVATE_SNIPPET( $this->helpers );
		new CODE_SNIPPETS_DEACTIVATE_SNIPPET( $this->helpers );

	}

	/**
	 * Check if Plugin is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return function_exists( 'Code_Snippets\code_snippets' );
	}
}
