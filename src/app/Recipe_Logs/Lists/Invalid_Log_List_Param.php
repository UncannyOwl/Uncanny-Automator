<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Lists;

use InvalidArgumentException;

/**
 * A list read's parameter the Logs page lists cannot take.
 *
 * Carries the parameter's name, so the REST reply can say which one.
 *
 * @since 8.1.0
 */
final class Invalid_Log_List_Param extends InvalidArgumentException {

	/**
	 * The parameter's name.
	 *
	 * @var string
	 */
	private $param;

	/**
	 * @param string $param   The parameter's name.
	 * @param string $message Why it was refused, for the REST reply.
	 */
	public function __construct( string $param, string $message ) {
		parent::__construct( $message );
		$this->param = $param;
	}

	/**
	 * The parameter's name.
	 *
	 * @return string
	 */
	public function get_param(): string {
		return $this->param;
	}
}
