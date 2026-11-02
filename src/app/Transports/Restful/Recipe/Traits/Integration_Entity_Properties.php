<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Recipe\Traits;

/**
 * Integration-aware property extension for recipe entities that ARE tied
 * to an integration registry entry — items (triggers, actions)
 * and block-modifiers (filter conditions, loop filters).
 *
 * Carries the `code` (integration item code, e.g. `WP_USER_LOGIN`) and
 * `integration_code` (e.g. `WP`, `GEN`) properties.
 *
 * Hydration is the caller's job, via the store that owns the entity —
 * items key these off `code`/`integration`, block modifiers off
 * `code`/`integration_code`, so one shared reader cannot serve both.
 *
 * Blocks (filter / loop / delay_schedule) do NOT mix this in — they
 * aren't integration-registered, so the `code` + `integration_code`
 * pair would be meaningless on a block.
 */
trait Integration_Entity_Properties {

	/**
	 * The integration-item code (e.g. `WP_USER_LOGIN`,
	 * `TOKEN_MEETS_CONDITION`). Identifies which catalog row the entity
	 * was created from.
	 *
	 * @var string
	 */
	private string $code = '';

	/**
	 * The integration code (e.g. `WP`, `GEN`, `WC`).
	 *
	 * @var string
	 */
	private string $integration_code = '';

	/**
	 * Record which item of its integration this entity is, such as `WP_USER_LOGIN`.
	 *
	 * @param string $code
	 * @return void
	 */
	protected function set_code( string $code ): void {
		$this->code = $code;
	}

	/**
	 * Which item of its integration this entity is, such as `WP_USER_LOGIN`.
	 *
	 * @return string
	 */
	protected function get_code(): string {
		return $this->code;
	}

	/**
	 * Record which integration this entity belongs to, such as `WP` or `GEN`.
	 *
	 * @param string $integration_code
	 * @return void
	 */
	protected function set_integration_code( string $integration_code ): void {
		$this->integration_code = $integration_code;
	}

	/**
	 * Which integration this entity belongs to, such as `WP` or `GEN`.
	 *
	 * @return string
	 */
	protected function get_integration_code(): string {
		return $this->integration_code;
	}
}
