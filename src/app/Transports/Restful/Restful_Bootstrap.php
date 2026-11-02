<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Transports\Restful;

use Uncanny_Automator\App\Transports\Restful\Recipe\Recipe_Rest_Controller;
use Uncanny_Automator\App\Transports\Restful\Integrations\Integrations_Rest_Controller;
use Uncanny_Automator\App\Transports\Restful\Blocks\Blocks_Rest_Controller;
use Uncanny_Automator\App\Transports\Restful\Remote_Data\Remote_Data_Rest_Controller;
use Uncanny_Automator\App\Transports\Restful\Post_Content\Post_Content_Rest_Controller;
use Uncanny_Automator\App\Transports\Restful\Post_Meta\Post_Meta_Rest_Controller;
use Uncanny_Automator\App\Transports\Restful\Recipe_Log\Recipe_Log_Rest_Controller;
use Uncanny_Automator\App\Transports\Restful\Template_Library\Template_Library_Rest_Controller;
use Uncanny_Automator\App\Transports\Restful\License\License_Rest_Controller;
use Uncanny_Automator\App\Transports\Restful\Addons\Addons_Rest_Controller;
use Uncanny_Automator\App\Transports\Restful\Ai\Ai_Rest_Controller;
use Uncanny_Automator\App\Transports\Restful\Site\Site_Rest_Controller;
use Uncanny_Automator\App\Transports\Restful\Account\Account_Rest_Controller;
use Uncanny_Automator\App\Transports\Restful\News\News_Rest_Controller;
use Uncanny_Automator\App\Transports\Restful\Usage\Usage_Rest_Controller;
use Uncanny_Automator\App\Transports\Restful\User_Preferences\User_Preferences_Rest_Controller;

/**
 * RESTful API Bootstrap.
 *
 * Initializes all REST API controllers.
 *
 * @since 7.0
 */
class Restful_Bootstrap {

	/**
	 * Constructor.
	 *
	 * @since 7.0
	 *
	 * @return void
	 */
	public function __construct() {
	}

	/**
	 * Initialize REST API transport layer.
	 *
	 * @since 7.0
	 *
	 * @return void
	 */
	public function init() {
		// Register REST routes.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Register all REST API routes.
	 *
	 * @since 7.0
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		// Register recipe routes.
		Recipe_Rest_Controller::register_routes();

		// Register integrations routes.
		Integrations_Rest_Controller::register_routes();

		// Register blocks routes.
		Blocks_Rest_Controller::register_routes();

		// Register remote-data routes.
		Remote_Data_Rest_Controller::register_routes();

		// Register recipe-log routes.
		Recipe_Log_Rest_Controller::register_routes();

		// Register template-library routes.
		Template_Library_Rest_Controller::register_routes();

		// Register license routes.
		License_Rest_Controller::register_routes();

		// Register addons routes.
		Addons_Rest_Controller::register_routes();

		// Register AI Dashboard routes.
		Ai_Rest_Controller::register_routes();

		// Register site status routes.
		Site_Rest_Controller::register_routes();

		// Register account summary routes.
		Account_Rest_Controller::register_routes();

		// Register usage summary routes.
		Usage_Rest_Controller::register_routes();

		// Register latest news routes.
		News_Rest_Controller::register_routes();

		// Register user preference routes.
		User_Preferences_Rest_Controller::register_routes();

		( new Post_Content_Rest_Controller() )->register_routes();
		( new Post_Meta_Rest_Controller() )->register_routes();
	}
}
