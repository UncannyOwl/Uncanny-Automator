<?php
/**
 * Embedded Page Builder compatibility decision.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Components\Page_Builder;

/**
 * Evaluates runtime facts without loading a Page Builder class.
 *
 * @since 7.4.1
 */
class Compatibility {

	/**
	 * Resolve one exact handover outcome.
	 *
	 * @param array $environment Runtime facts collected by the host.
	 * @return array{status:string,detail:string}
	 */
	public function check( array $environment ): array {
		if ( ! empty( $environment['disabled'] ) ) {
			return $this->result( 'disabled', 'Embedded Page Builder ownership is disabled by the operator.' );
		}

		if ( version_compare( (string) $environment['php_version'], '8.1', '<' ) ) {
			return $this->result( 'php_incompatible', 'Page Builder requires PHP 8.1 or newer.' );
		}

		if ( version_compare( (string) $environment['wordpress_version'], '6.3', '<' ) ) {
			return $this->result( 'wordpress_incompatible', 'Page Builder requires WordPress 6.3 or newer.' );
		}

		if ( ! empty( $environment['ownership_marker_defined'] ) ) {
			return $this->result(
				! empty( $environment['owns_runtime'] ) ? 'already_owned' : 'ownership_marker_conflict',
				'The Page Builder ownership marker was defined before the Automator host ran.'
			);
		}

		if ( ! empty( $environment['legacy_runtime_constants'] ) ) {
			return $this->result(
				'standalone_runtime_active',
				'An early standalone Page Builder runtime is already active. Update or deactivate it before Automator takes ownership.'
			);
		}

		$bridge_version = isset( $environment['bridge_version'] )
			? (string) $environment['bridge_version']
			: '';
		if (
			'' !== $bridge_version
			&& version_compare( $bridge_version, Module::MINIMUM_BRIDGE_VERSION, '<' )
		) {
			return $this->result(
				'standalone_bridge_incompatible',
				'The active standalone Page Builder bridge is too old to hand over safely.'
			);
		}

		return $this->result( 'ready', 'The embedded Page Builder runtime may claim ownership.' );
	}

	/**
	 * Build a consistent decision result.
	 *
	 * @param string $status Status code.
	 * @param string $detail Operator detail.
	 * @return array{status:string,detail:string}
	 */
	private function result( string $status, string $detail ): array {
		return array(
			'status' => $status,
			'detail' => $detail,
		);
	}
}
