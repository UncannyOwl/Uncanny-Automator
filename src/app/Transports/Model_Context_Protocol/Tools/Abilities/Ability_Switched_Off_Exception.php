<?php
/**
 * A switched-off ability stopped on WordPress before 7.1.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abilities;

/**
 * Thrown from `wp_before_execute_ability` when an MCP tool call reaches an
 * ability the owner switched off; the tool call catches it and answers with
 * the refusal.
 *
 * @since 8.0.0
 */
final class Ability_Switched_Off_Exception extends \RuntimeException {
}
