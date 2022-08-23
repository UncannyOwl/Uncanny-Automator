<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

use Uncanny_Automator\Recipe\Actions;

/**
 * Class WHATSAPP_SEND_MESSAGE_TEMPLATE
 *
 * @package Uncanny_Automator
 */
class WHATSAPP_SEND_MESSAGE_TEMPLATE {

	use Actions;

	/**
	 * The prefix for the action fields.
	 *
	 * @var string
	 */
	const PREFIX = 'WHATSAPP_SEND_MESSAGE_TEMPLATE';

	public function __construct() {

		// Set the action status to await.
		add_filter( 'automator_get_action_completed_status', array( $this, 'set_completed_status' ), 10, 7 );

		// Set the action status to error.
		add_filter( 'automator_get_action_error_message', array( $this, 'set_error_message' ), 10, 7 );

		// Set the action status to `Completed, pending response`
		add_filter( 'automator_pro_get_action_completed_labels', array( $this, 'set_action_completed_label' ), 10, 1 );

		// Persist the wamid.id.
		add_action( 'automator_action_created', array( $this, 'action_meta_persist_wamid_data' ), 10, 1 );

		// No response closure.
		add_action( 'automator_whatsapp_webhook_noresponse_closure', array( $this, 'noresponse_closure' ), 10, 3 );

		// Load addition JS scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts' ) );

		$this->setup_action();

	}

	/**
	 * Load scripts.
	 *
	 * @param string $hook
	 *
	 * @return void
	 */
	public function load_scripts( $hook ) {

		if ( 'post.php' !== $hook ) {
			return;
		}

		if ( 'uo-recipe' !== get_current_screen()->post_type ) {
			return;
		}

		$script_uri = plugin_dir_url( __FILE__ ) . '../settings/scripts/fields-renderer.js';

		wp_enqueue_script( 'whatsapp-field-renderer', $script_uri, array( 'jquery' ), '1.0', true );

	}

	public function noresponse_closure( $response ) {

		if ( ! empty( $response['data']['messages'][0]['id'] ) ) {

			$helper = Automator()->helpers->recipe->whatsapp->options;

			$action_data = $helper->get_action_data_by_wamid( $response['data']['messages'][0]['id'] );

			$error_message = esc_html__( 'No response was received from Meta after 1 minute. Make sure you have set-up your webhook configuration correctly.' );

			$recipe_error_message = Automator()->db->action->get_error_message( $action_data['recipe_log_id'] );

			if ( ! empty( $recipe_error_message ) && 10 === intval( $recipe_error_message->completed ) ) {

				Automator()->db->action->mark_complete( $action_data['action_id'], $action_data['recipe_log_id'], 1, $error_message );

				Automator()->db->recipe->mark_complete( $action_data['recipe_log_id'], 1 );

			}
		}

	}

	/**
	 * Persist the WAMID after the action creation.
	 *
	 * @param array $entry
	 *
	 * @return void
	 */
	public function action_meta_persist_wamid_data( $action_arguments = array() ) {

		// Check if action has `await` argument.
		if ( empty( $action_arguments['args']['await'] ) ) {
			return;
		}

		// Add `whatsapp_meta` to {uap_action_log_meta}.
		Automator()->db->action->add_meta(
			$action_arguments['user_id'],
			$action_arguments['action_log_id'],
			$action_arguments['action_id'],
			'whatsapp_meta',
			wp_json_encode( $action_arguments['args'] )
		);

		// Add `whatsapp_wamid` to {uap_action_log_meta}.
		Automator()->db->action->add_meta(
			$action_arguments['user_id'],
			$action_arguments['action_log_id'],
			$action_arguments['action_id'],
			'whatsapp_wamid',
			$action_arguments['args']['await']['whatsapp_response']['data']['messages'][0]['id']
		);

	}

	public function set_action_completed_label( $labels = array() ) {

		$labels[10] = __( 'Completed, pending response', 'uncanny-automator' );

		return $labels;

	}

	public function set_error_message( $message, $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args ) {

		// Only filter this action
		if ( 'WHATSAPP_SEND_MESSAGE_TEMPLATE_CODE' !== $action_data['meta']['code'] ) {
			return $message;
		}

		if ( key_exists( 'await', $args ) ) {
			// Completed is stored as tiny int. Maybe update it to enum?
			$message = __( 'Message template sent. Waiting for response. The status will be updated once the response is received.', 'uncanny-automatar' );
		}

		return $message;

	}

	public function set_completed_status( $completed, $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args ) {

		// Only filter this action
		if ( 'WHATSAPP_SEND_MESSAGE_TEMPLATE_CODE' !== $action_data['meta']['code'] ) {
			return $completed;
		}

		if ( key_exists( 'await', $args ) ) {
			// Completed is stored as tiny int. Maybe update it to enum?
			$completed = 10;
		}

		return $completed;

	}

	protected function setup_action() {

		$this->set_integration( 'WHATSAPP' );

		$this->set_action_code( self::PREFIX . '_CODE' );

		$this->set_action_meta( self::PREFIX . '_META' );

		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/whatsapp/' ) );

		$this->set_is_pro( false );

		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: Action sentence */
				esc_attr__( 'Send a WhatsApp {{message template:%1$s}} to {{a number:%2$s}}', 'uncanny-automator' ),
				$this->get_action_meta(),
				'PHONE_NUMBER'
			)
		);

		/* translators: Action - WordPress */
		$this->set_readable_sentence( esc_attr__( 'Send a WhatsApp {{message template}} to {{a number}}', 'uncanny-automator' ) );

		$this->set_options_callback( array( $this, 'load_options' ) );

		$this->set_buttons(
			array(
				array(
					'show_in'     => $this->get_action_meta(),
					'text'        => __( 'Get variables', 'uncanny-automator' ),
					'css_classes' => 'uap-btn uap-btn--red',
					'on_click'    => 'uap_whatsapp_render_fields',
					'modules'     => array( 'modal', 'markdown' ),
				),
			)
		);

		$this->register_action();

	}

	public function load_options() {

		$options = array(
			'options_group' => array(
				$this->get_action_meta() => array(
					array(
						'option_code'           => $this->get_action_meta(),
						'label'                 => esc_attr__( 'Message template', 'uncanny-automator' ),
						'description'           => esc_attr__( "Select a message template and click Get variables to retrieve the template's dynamic variables.", 'uncanny-automator' ),
						'input_type'            => 'select',
						'required'              => true,
						'supports_token'        => false,
						'supports_custom_value' => false,
						'is_ajax'               => true,
						'endpoint'              => 'automator_whatsapp_list_message_templates',
						'options_show_id'       => false,
					),
					array(
						'option_code'  => 'HEADER_VARIABLES',
						'label'        => esc_attr__( 'Header', 'uncanny-automator' ),
						'input_type'   => 'repeater',
						'hide_actions' => true,
						'fields'       => array(
							array(
								'option_code' => 'HEADER_VARIABLE_FORMAT',
								'input_type'  => 'text',
								'read_only'   => true,
								'required'    => false,
								'label'       => __( 'Type', 'uncanny-automator' ),
							),
							array(
								'option_code' => 'HEADER_VARIABLE_VALUE',
								'input_type'  => 'text',
								'placeholder' => '{1}',
								'required'    => false,
								'label'       => __( 'Value', 'uncanny-automator' ),
							),
						),
					),
					array(
						'option_code'  => 'BODY_VARIABLES',
						'label'        => esc_attr__( 'Body variables', 'uncanny-automator' ),
						'input_type'   => 'repeater',
						'hide_actions' => true,
						'fields'       => array(
							array(
								'input_type'  => 'text',
								'option_code' => 'BODY_VARIABLE',
								'placeholder' => '{}',
								'required'    => false,
								'label'       => __( 'Value', 'uncanny-automator' ),
							),
						),
					),

					array(
						'option_code'  => 'BUTTON_VARIABLES',
						'label'        => esc_attr__( 'Buttons', 'uncanny-automator' ),
						'input_type'   => 'repeater',
						'hide_actions' => true,
						'fields'       => array(
							array(
								'input_type'  => 'text',
								'option_code' => 'BUTTON_FORMAT',
								'placeholder' => '{N/A}',
								'read_only'   => true,
								'required'    => false,
								'label'       => __( 'Format', 'uncanny-automator' ),
							),
							array(
								'input_type'  => 'text',
								'option_code' => 'BUTTON_VARIABLE',
								'placeholder' => '{1}',
								'required'    => false,
								'label'       => __( 'Value', 'uncanny-automator' ),
							),
						),
					),
				),
			),
			'options'       => array(
				array(
					'option_code'           => 'PHONE_NUMBER',
					'label'                 => esc_attr__( 'To', 'uncanny-automator' ),
					'input_type'            => 'text',
					'placeholder'           => esc_attr__( '+1 123 345 6789', 'uncanny-automator' ),
					'required'              => true,
					'supports_token'        => true,
					'supports_custom_value' => true,
				),

			),
		);

		$options = Automator()->utilities->keep_order_of_options( $options );

		return $options;
	}

	/**
	 * Get formatted code.
	 *
	 * @param  string $option_code The option code.
	 *
	 * @return string The prefix underscore option code string.
	 */
	protected function get_formatted_code( $option_code = '' ) {

		return sprintf( '%1$s_%2$s', self::PREFIX, $option_code );

	}


	/**
	 * Process the action.
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

		$helper = Automator()->helpers->recipe->whatsapp->options;

		$to = isset( $parsed['PHONE_NUMBER'] ) ? sanitize_text_field( $parsed['PHONE_NUMBER'] ) : null;

		$template = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_textarea_field( $parsed[ $this->get_action_meta() ] ) : null;

		list( $template_name, $locale ) = explode( '|', $template );

		try {

			$body = array(
				'action'               => 'send_template',
				'to'                   => $to,
				'template'             => $template_name,
				'template_composition' => wp_json_encode( $parsed ),
				'language'             => $locale,
				'phone_id'             => $helper->get_phone_number_id(),
				'access_token'         => $helper->get_access_token(),
			);

			$response = $helper->api_call( $body, $action_data );

			$action_data['args']['await'] = array(
				'whatsapp_response' => $response,
			);

			wp_schedule_single_event( time() + 60, 'automator_whatsapp_webhook_noresponse_closure', array( $response ) );

			Automator()->complete->action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {

			$action_data['complete_with_errors'] = true;

			Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );

			return;

		}

	}

}
