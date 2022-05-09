<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

/**
 * Class OPTINMONSTER_SHOW_CAMPAIGN
 *
 * @package Uncanny_Automator
 */
class OPTINMONSTER_SHOW_CAMPAIGN {

	use Recipe\Actions;

	public $prefix = '';

	public function __construct() {

		$this->prefix = 'OPTINMONSTER_SHOW_CAMPAIGN';

		$this->setup_action();

		add_action( 'wp_loaded', array( $this, 'add_script' ) );

	}

	/**
	 * Setup Action.
	 *
	 * @return void.
	 */
	protected function setup_action() {

		$this->set_integration( 'OPTINMONSTER' );
		$this->set_action_code( 'SHOW_CAMPAIGN' );
		$this->set_action_meta( 'CAMPAIGN' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );

		/* translators: Action - WordPress */
		$this->set_sentence( sprintf( esc_attr__( 'Show {{a campaign:%1$s}}', 'uncanny-automator' ), $this->get_action_meta() ) );

		/* translators: Action - WordPress */
		$this->set_readable_sentence( esc_attr__( 'Show {{a campaign}}', 'uncanny-automator' ) );

		$this->set_options_callback( array( $this, 'load_options' ) );

		$this->register_action();

	}

	/**
	 * add_script
	 *
	 * @return void
	 */
	public function add_script() {

		if ( is_admin() ) {
			return;
		}

		if ( ! $this->optinmonster_action_exists() ) {
			return;
		}

		$script_uri = plugin_dir_url( __FILE__ ) . '../scripts/show-campaign.js';

		wp_enqueue_script( 'automator-optinmonster', $script_uri, array( 'jquery' ), InitializePlugin::PLUGIN_VERSION, true );

	}

	/**
	 * optinmonster_action_exists
	 *
	 * Checks if there is a recipe with an active OptinMonster action
	 *
	 * @return boolean
	 */
	public function optinmonster_action_exists() {

		$recipes_data = Automator()->get_recipes_data();

		// Loop through all actions
		foreach ( $recipes_data as $recipe ) {

			if ( 'publish' !== $recipe['post_status'] ) {
				continue;
			}

			foreach ( $recipe['actions'] as $action ) {

				if ( 'publish' !== $action['post_status'] ) {
					continue;
				}

				if ( $this->get_action_code() === $action['meta']['code'] && $this->get_integration() === $action['meta']['integration'] ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * load_options
	 *
	 * @return void
	 */
	public function load_options() {

		$options_group = array(
			$this->get_action_meta() => $this->get_fields(),
		);

		return array( 'options_group' => $options_group );
	}

	/**
	 * Proccess our action.
	 *
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param $parsed
	 *
	 * @return void.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$campaign = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : '';

		if ( ! Automator()->helpers->recipe->optinmonster->campaign_is_active( $campaign ) ) {

			$error_message                       = __( 'The campaign is not active', 'uncanny-automator' );
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}

		$this->set_cookie( $campaign );

		Automator()->complete->action( $user_id, $action_data, $recipe_id );

	}

	/**
	 * set_cookie
	 *
	 * Send the cookie to the UI
	 *
	 * @param  string $campaign_id
	 * @return void
	 */
	public function set_cookie( $campaign_id ) {

		$cookie_name     = 'ua_show_campaign';
		$cookie_lifetime = time() + ( 86400 * 30 ); // 86400 = 1 day
		setcookie( $cookie_name, $campaign_id, $cookie_lifetime, '/' );

	}

	/**
	 * Get the action fields.
	 */
	public function get_fields() {

		$fields = array();

		// List of available campaigns
		$fields[] = array(
			'input_type'            => 'select',
			'option_code'           => $this->get_action_meta(),
			/* translators: HTTP request method */
			'label'                 => esc_attr__( 'Campaign', 'uncanny-automator' ),
			'description'           => esc_attr__( 'Inline campaigns are not supported as they are always displayed.', 'uncanny-automator' ),
			'required'              => true,
			'supports_custom_value' => false,
			'options'               => Automator()->helpers->recipe->optinmonster->get_campaigns(),
		);

		return $fields;

	}

}
