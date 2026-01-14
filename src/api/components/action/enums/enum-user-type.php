<?php
namespace Uncanny_Automator\Api\Components\Action\Enums;

/**
 * User Type Enum.
 *
 * Represents the type of user for a recipe.
 *
 * Upgrade to PHP 8.1 enum in the future.
 *
 * @since 7.0.0
 */
class User_Type {

	/**
	 * User user type.
	 *
	 * @var string
	 */
	const USER = 'user';

	/**
	 * Anonymous user type.
	 *
	 * @var string
	 */
	const ANONYMOUS = 'anonymous';
}
