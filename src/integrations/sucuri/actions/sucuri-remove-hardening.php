<?php

namespace Uncanny_Automator\Integrations\Sucuri;

use Uncanny_Automator\Recipe\Action;

/**
 * Class Sucuri_Remove_Hardening
 *
 * Reverts Sucuri's PHP-execution hardening on one of the three directories it
 * supports, via `SucuriScanHardening::unhardenDirectory( $path )`
 * (src/hardening.lib.php:160). Sucuri strips the `<FilesMatch>` deny rules from
 * the directory's .htaccess and truncates the file if nothing is left.
 *
 * Sucuri refuses to unharden a directory that is not currently hardened, and
 * reports that through throwException(), which returns plain false — so the
 * state is checked here to produce a usable log message.
 *
 * Unlike applying hardening, this is not gated on the web server. Sucuri's own
 * unhardenDirectory() carries no Nginx/IIS check, and a site migrated away from
 * Apache still has the rules on disk — removing them is the only cleanup path.
 *
 * @package Uncanny_Automator\Integrations\Sucuri
 *
 * @property Sucuri_Helpers $item_helpers
 */
class Sucuri_Remove_Hardening extends Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'SUCURI' );
		$this->set_requires_user( false );
		$this->set_action_code( 'SUCURI_REMOVE_HARDENING' );
		$this->set_action_meta( 'SUCURI_DIRECTORY' );
		$this->set_sentence(
			sprintf(
				/* translators: 1: Directory */
				esc_html_x( 'Remove hardening from {{a directory:%1$s}}', 'Sucuri Security', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Remove hardening from {{a directory}}', 'Sucuri Security', 'uncanny-automator' ) );
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
			'SUCURI_UNHARDENED_DIRECTORY' => array(
				'name' => esc_html_x( 'Unhardened directory', 'Sucuri Security', 'uncanny-automator' ),
				'type' => 'text',
			),
			'SUCURI_UNHARDENED_PATH'      => array(
				'name' => esc_html_x( 'Unhardened directory path', 'Sucuri Security', 'uncanny-automator' ),
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

		// The hardened state is the gate, not the server. Sucuri's own
		// unhardenDirectory() carries no server check for exactly this reason:
		// a site moved from Apache to Nginx still has the .htaccess rules on
		// disk, and stripping them is the only way to clean up. The server is
		// only worth mentioning when nothing is hardened, since that is the
		// expected state there rather than a mistake.
		if ( ! \SucuriScanHardening::isHardened( $path ) ) {

			if ( ! $this->item_helpers->server_supports_hardening() ) {
				$this->add_log_error( esc_html_x( 'Sucuri hardening writes .htaccess rules, which this server (Nginx or IIS) does not support, so no directory is hardened on it.', 'Sucuri Security', 'uncanny-automator' ) );
				return false;
			}

			$this->add_log_error(
				sprintf(
					/* translators: %s: The directory name, e.g. wp-content */
					esc_html_x( 'The %s directory is not hardened.', 'Sucuri Security', 'uncanny-automator' ),
					$directory
				)
			);
			return false;
		}

		if ( true !== \SucuriScanHardening::unhardenDirectory( $path ) ) {
			$this->add_log_error(
				sprintf(
					/* translators: %s: The directory name, e.g. wp-content */
					esc_html_x( 'Sucuri could not remove hardening from the %s directory.', 'Sucuri Security', 'uncanny-automator' ),
					$directory
				)
			);
			return false;
		}

		$this->hydrate_tokens(
			array(
				'SUCURI_UNHARDENED_DIRECTORY' => $directory,
				'SUCURI_UNHARDENED_PATH'      => $path,
			)
		);

		return true;
	}
}
