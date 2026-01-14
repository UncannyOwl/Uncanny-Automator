<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Plan\Domain;

/**
 * Defines the contract for a Plan.
 *
 * This interface represents the "Port" in a Hexagonal Architecture, defining
 * what a Plan can do without specifying the implementation.
 *
 * @package Uncanny_Automator\Api\Components\Plan\Domain
 * @since 7.0.0
 */
interface Plan {
	public function get_id(): string;
	public function get_name(): string;
	public function get_description(): string;
	public function get_level(): int;
	public function is_at_least( Plan $other ): bool;
	public function can_access_feature( string $type, string $feature_id ): bool;
}
