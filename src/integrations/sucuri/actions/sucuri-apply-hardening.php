<?php

namespace Uncanny_Automator\Integrations\Sucuri;

use Uncanny_Automator\Recipe\Action;

/**
 * Class Sucuri_Apply_Hardening
 *
 * Applies Sucuri's PHP-execution hardening to one of the three directories it
 * supports, via `SucuriScanHardening::hardenDirectory( $path )`
 * (src/hardening.lib.php:120). Sucuri appends `<FilesMatch>` deny rules to the
 * directory's .htaccess.
 *
 * Apache only. Sucuri rejects Nginx and IIS, and it reports every failure by
 * calling throwException(), which returns plain false unless the
 * SUCURISCAN_THROW_EXCEPTIONS constant is defined — so the reason never reaches
 * the caller. The pre-checks below exist to produce a usable log message.
 *
 * @package Uncanny_Automator\Integrations\Sucuri
 *
 * @property Sucuri_Helpers $item_helpers
 */
class Sucuri_Apply_Hardening extends Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'SUCURI' );
		$this->set_requires_user( false );
		$this->set_action_code( 'SUCURI_APPLY_HARDENING' );
		$this->set_action_meta( 'SUCURI_DIRECTORY' );
		$this->set_sentence(
			sprintf(
				/* translators: 1: Directory */
				esc_html_x( 'Apply hardening to {{a directory:%1$s}}', 'Sucuri Security', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Apply hardening to {{a directory}}', 'Sucuri Security', 'uncanny-automator' ) );
	}

	/**
	 * Options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'           => $this->get_action_meta(),
				'label'                 => esc_html_x( 'Directory', 'Sucuri Security', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options_show_id'       => false,
				'supports_custom_value' => false,
				'options'               => array(),
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'hardening_directories_strict' ),
				'description'           => esc_html_x( 'Hardening writes .htaccess rules and is supported on Apache only.', 'Sucuri Security', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Define output tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'SUCURI_HARDENED_DIRECTORY' => array(
				'name' => esc_html_x( 'Hardened directory', 'Sucuri Security', 'uncanny-automator' ),
				'type' => 'text',
			),
			'SUCURI_HARDENED_PATH'      => array(
				'name' => esc_html_x( 'Hardened directory path', 'Sucuri Security', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Process action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action data.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        The args.
	 * @param array $parsed      The parsed options.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		if ( ! class_exists( '\SucuriScanHardening' ) ) {
			$this->add_log_error( esc_html_x( 'Sucuri Security is not active.', 'Sucuri Security', 'uncanny-automator' ) );
			return false;
		}

		$directory = trim( (string) ( $parsed[ $this->get_action_meta() ] ?? '' ) );
		$path      = $this->item_helpers->resolve_directory( $directory );

		if ( '' === $path ) {
			$this->add_log_error(
				sprintf(
					/* translators: %s: The directory value saved on the action */
					esc_html_x( 'Unsupported directory: [%s].', 'Sucuri Security', 'uncanny-automator' ),
					$directory
				)
			);
			return false;
		}

		if ( ! $this->item_helpers->server_supports_hardening() ) {
			$this->add_log_error( esc_html_x( 'Sucuri hardening writes .htaccess rules, which this server (Nginx or IIS) does not support.', 'Sucuri Security', 'uncanny-automator' ) );
			return false;
		}

		if ( ! is_dir( $path ) || ! wp_is_writable( $path ) ) {
			$this->add_log_error(
				sprintf(
					/* translators: %s: Absolute path to the directory */
					esc_html_x( 'The directory is missing or not writable: [%s].', 'Sucuri Security', 'uncanny-automator' ),
					$path
				)
			);
			return false;
		}

		if ( \SucuriScanHardening::isHardened( $path ) ) {
			$this->add_log_error(
				sprintf(
					/* translators: %s: The directory name, e.g. wp-content */
					esc_html_x( 'The %s directory is already hardened.', 'Sucuri Security', 'uncanny-automator' ),
					$directory
				)
			);
			return false;
		}

		if ( true !== \SucuriScanHardening::hardenDirectory( $path ) ) {
			$this->add_log_error(
				sprintf(
					/* translators: %s: The directory name, e.g. wp-content */
					esc_html_x( 'Sucuri could not harden the %s directory.', 'Sucuri Security', 'uncanny-automator' ),
					$directory
				)
			);
			return false;
		}

		$this->hydrate_tokens(
			array(
				'SUCURI_HARDENED_DIRECTORY' => $directory,
				'SUCURI_HARDENED_PATH'      => $path,
			)
		);

		return true;
	}
}
