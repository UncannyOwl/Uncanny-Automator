<?php
/**
 * Feature-state gate for MCP presentation surfaces.
 *
 * @package Uncanny_Automator
 * @since 7.5.1.1
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Client;

use Uncanny_Automator\App\Feature_State\Application\Get_Feature_State;
use Uncanny_Automator\App\Feature_State\Domain\Feature_State;

/**
 * Applies the feature policy to MCP presentation without disabling transport.
 */
final class Feature_State_Presentation_Gate {

	/**
	 * Request-scoped feature-state query.
	 *
	 * @var Get_Feature_State|null
	 */
	private ?Get_Feature_State $feature_state;

	/**
	 * Create the presentation gate.
	 *
	 * @param Get_Feature_State|null $feature_state Request-scoped feature-state query.
	 */
	public function __construct( ?Get_Feature_State $feature_state ) {
		$this->feature_state = $feature_state;
	}

	/**
	 * Register the presentation filter.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'automator_mcp_should_render_surface', array( $this, 'should_render_surface' ), PHP_INT_MAX, 2 );
	}

	/**
	 * Apply matrix visibility to one known MCP presentation surface.
	 *
	 * Existing denials remain denied. Unknown surfaces retain the decision made by
	 * earlier filters so this release does not claim policy ownership over them.
	 *
	 * @param mixed $should_render Existing render decision.
	 * @param mixed $surface       MCP presentation surface.
	 *
	 * @return bool
	 */
	public function should_render_surface( $should_render, $surface ): bool {
		if ( ! $should_render ) {
			return false;
		}

		if ( ! is_string( $surface ) ) {
			return (bool) $should_render;
		}

		switch ( $surface ) {
			case 'admin_launcher':
				$state = $this->get_feature_state();
				if ( null === $state ) {
					return false;
				}
				return $state->is_visible( Feature_State::AGENT_LAUNCHER_TAB );

			case 'admin_bar_quicklink':
			case 'admin_bar_quicklink_styles':
				$state = $this->get_feature_state();
				if ( null === $state ) {
					return false;
				}
				return $state->is_visible( Feature_State::AGENT_LAUNCHER_TOP_BAR_LINK );

			case 'admin_sdk':
				// SDK loading supports two Axis surfaces; it is not a seventh feature.
				// Load it when either the launcher or top-bar link may render.
				$state = $this->get_feature_state();
				if ( null === $state ) {
					return false;
				}
				return $state->is_visible( Feature_State::AGENT_LAUNCHER_TAB )
					|| $state->is_visible( Feature_State::AGENT_LAUNCHER_TOP_BAR_LINK );

			default:
				return (bool) $should_render;
		}
	}

	/**
	 * Resolve feature state without allowing presentation policy to break MCP transport.
	 *
	 * @return Feature_State|null
	 */
	private function get_feature_state(): ?Feature_State {
		if ( null === $this->feature_state ) {
			return null;
		}

		try {
			return $this->feature_state->execute();
		} catch ( \Throwable $error ) {
			unset( $error );
			return null;
		}
	}
}
