<?php
/**
 * Per-action and per-integration availability for third-party MCP clients.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools;

/**
 * Stores which Automator actions, and which whole integrations, a site owner
 * has switched off for OAuth clients.
 *
 * Only what is switched off is stored, so new actions and integrations stay
 * available. A switched-off integration covers every action in it, including
 * actions registered later. First-party Uncanny Agent credentials never
 * consult this setting; see Action_Availability_Policy.
 *
 * Every read goes to the option, which WordPress already caches. An instance
 * can outlive the request it was built in (a registered ability keeps its
 * tool, and the tool keeps its policy and these settings), so a memo would
 * keep answering with a switch the owner has since changed.
 */
final class Action_Availability_Settings {

	/**
	 * Option holding the two sorted lists.
	 */
	public const OPTION_NAME = 'automator_mcp_action_availability';

	/**
	 * Integration codes switched off for third-party clients.
	 *
	 * @return string[]
	 */
	public function get_disabled_integrations(): array {
		return $this->load()['integrations'];
	}

	/**
	 * Action codes switched off for third-party clients.
	 *
	 * @return string[]
	 */
	public function get_disabled_actions(): array {
		return $this->load()['actions'];
	}

	/**
	 * Whether a whole integration has been switched off.
	 *
	 * @param string $code Integration code.
	 * @return bool
	 */
	public function is_integration_disabled( string $code ): bool {
		return in_array( $code, $this->get_disabled_integrations(), true );
	}

	/**
	 * Whether one action has been switched off.
	 *
	 * @param string $code Action code.
	 * @return bool
	 */
	public function is_action_disabled( string $code ): bool {
		return in_array( $code, $this->get_disabled_actions(), true );
	}

	/**
	 * Switch a whole integration off or on.
	 *
	 * @param string $code     Integration code.
	 * @param bool   $disabled True to switch it off.
	 * @return bool Whether the requested state is saved.
	 */
	public function set_integration_disabled( string $code, bool $disabled ): bool {
		$state = $this->load();

		return $this->set_disabled(
			$disabled ? array_merge( $state['integrations'], array( $code ) ) : array_diff( $state['integrations'], array( $code ) ),
			$state['actions']
		);
	}

	/**
	 * Switch one action off or on.
	 *
	 * @param string $code     Action code.
	 * @param bool   $disabled True to switch it off.
	 * @return bool Whether the requested state is saved.
	 */
	public function set_action_disabled( string $code, bool $disabled ): bool {
		$state = $this->load();

		return $this->set_disabled(
			$state['integrations'],
			$disabled ? array_merge( $state['actions'], array( $code ) ) : array_diff( $state['actions'], array( $code ) )
		);
	}

	/**
	 * Replace both lists.
	 *
	 * @param array $integrations Integration codes to switch off.
	 * @param array $actions      Action codes to switch off.
	 * @return bool Whether the requested state is saved.
	 */
	public function set_disabled( array $integrations, array $actions ): bool {
		$state = array(
			'integrations' => self::normalize_list( $integrations ),
			'actions'      => self::normalize_list( $actions ),
		);

		if ( get_option( self::OPTION_NAME, array() ) !== $state && ! update_option( self::OPTION_NAME, $state ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Read and normalise the stored option.
	 *
	 * @return array{integrations:string[],actions:string[]}
	 */
	private function load(): array {
		$stored = get_option( self::OPTION_NAME, array() );
		$stored = is_array( $stored ) ? $stored : array();

		return array(
			'integrations' => self::normalize_list( $stored['integrations'] ?? array() ),
			'actions'      => self::normalize_list( $stored['actions'] ?? array() ),
		);
	}

	/**
	 * Reduce a stored or submitted value to a sorted, unique list of codes.
	 *
	 * @param mixed $value Raw list.
	 * @return string[]
	 */
	private static function normalize_list( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$codes = array();

		foreach ( $value as $code ) {
			if ( ! is_string( $code ) ) {
				continue;
			}

			$code = trim( $code );

			if ( '' !== $code ) {
				$codes[ $code ] = true;
			}
		}

		$codes = array_keys( $codes );
		sort( $codes, SORT_STRING );

		return $codes;
	}
}
