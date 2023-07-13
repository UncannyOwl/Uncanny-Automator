<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

/**
 * Class Telegram_Helpers
 *
 * @package Uncanny_Automator
 */
class Telegram_Helpers {

	public $functions;

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {

		$this->functions = new Telegram_Functions();

		$this->functions->register_hooks();

		new Telegram_Settings( $this );
	}
}
