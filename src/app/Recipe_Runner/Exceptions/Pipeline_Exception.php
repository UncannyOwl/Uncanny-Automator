<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Exceptions;

/**
 * Exception for recipe execution pipeline errors.
 *
 * Thrown by pipeline stages and the runner when execution cannot
 * proceed (invalid action data, missing integrations, recipe
 * already completed, etc.).
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Exceptions
 * @since   7.3
 */
class Pipeline_Exception extends \Exception {
}
