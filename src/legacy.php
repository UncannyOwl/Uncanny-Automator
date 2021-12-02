<?php
/**
 * InitializePlugin
 *
 * This class used to be in uncanny-automator < 3.0. Some of the plugins hooked in to InitializePlugin::PLUGIN_VERSION
 * to grab the version of Automator. We have moved away from this in 3.0 to CONSTANT, see
 * uncanny-automator/src/globals.php.
 */

namespace Uncanny_Automator;

/**
 * Class InitializePlugin
 *
 * @package    Uncanny_Automator
 * @deprecated 3.0
 * @use        AUTOMATOR_PLUGIN_VERSION
 */
class InitializePlugin {

	/**
	 * The plugin name
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string
	 */
	const PLUGIN_NAME = 'Uncanny Automator';

	/**
	 * The plugin name acronym
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string
	 */
	const PLUGIN_PREFIX = 'uap';

	/**
	 * Min PHP Version
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string
	 */
	const MIN_PHP_VERSION = '7.0';

	/**
	 * The plugin version number
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string
	 */
	const PLUGIN_VERSION = AUTOMATOR_PLUGIN_VERSION;

	/**
	 * The database version number
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string
	 */
	const DATABASE_VERSION = AUTOMATOR_DATABASE_VERSION;

	/**
	 * The database views version number
	 *
	 * @since    2.5.1
	 * @access   private
	 * @var      string
	 */
	const DATABASE_VIEWS_VERSION = AUTOMATOR_DATABASE_VIEWS_VERSION;

	/**
	 * The full path and filename
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string
	 */
	const MAIN_FILE = __FILE__;

	/**
	 * Allows the debugging scripts to initialize and log them in a file
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string
	 */
	private $log_debug_messages = false;

	/**
	 * The instance of the class
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Object
	 */
	private static $instance = null;

	/**
	 * Creates singleton instance of class
	 *
	 * @return InitializePlugin $instance The InitializePlugin Class
	 * @since 1.0.0
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * class constructor
	 */
	private function __construct() {
	}
}

// Let's run it
InitializePlugin::get_instance();
