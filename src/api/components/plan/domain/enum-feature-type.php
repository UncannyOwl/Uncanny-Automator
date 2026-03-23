<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Plan\Domain;

/**
 * Defines the types of features that can be gated by a plan.
 *
 * @package Uncanny_Automator\Api\Components\Plan\Domain
 * @since 7.0.0
 */
interface Feature_Type {
	const TRIGGER          = 'trigger';
	const ACTION           = 'action';
	const ACTION_CONDITION = 'action_condition';
	const LOOP             = 'loop';
}
