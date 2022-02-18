<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Api_Server;
use Uncanny_Automator_Pro\Twilio_Pro_Helpers;

/**
 * Class Twilio_Helpers
 * @package Uncanny_Automator
 */
class Twilio_Helpers {

	/**
	 * @var Twilio_Helpers
	 */
	public $options;

	/**
	 * @var Twilio_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var string
	 */
	public $setting_tab;
	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Twilio_Helpers constructor.
	 */
	public function __construct() {
		// Selectively load options
		if ( method_exists( '\Uncanny_Automator\Automator_Helpers_Recipe', 'maybe_load_trigger_options' ) ) {
			global $uncanny_automator;
			$this->load_options = $uncanny_automator->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
		}

		$this->setting_tab = 'twilio_api';
		$this->automator_api = AUTOMATOR_API_URL . 'v2/twilio';

		add_filter( 'automator_settings_tabs', array( $this, 'add_twilio_api_settings' ), 15 );

		add_action( 'update_option_uap_automator_twilio_api_auth_token', array( $this, 'twilio_setting_update' ), 100, 3 );
		add_action( 'update_option_uap_automator_twilio_api_account_sid', array( $this, 'twilio_setting_update' ), 100, 3 );

		// Add twillio disconnect action.
		add_action( 'wp_ajax_automator_twillio_disconnect', array( $this, 'automator_twillio_disconnect' ), 100 );
	}

	/**
	 * @param Twilio_Helpers $options
	 */
	public function setOptions( Twilio_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Twilio_Helpers $pro
	 */
	public function setPro( Twilio_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 *
	 * @param string $to
	 * @param string $message
	 * @param string $user_id
	 *
	 * @return array
	 * @throws ConfigurationException
	 * @throws TwilioException
	 */
	public function send_sms( $to, $body, $user_id ) {

		$client = $this->get_client();

		if ( ! $client ) {
			return array(
				'result'  => false,
				'message' => __( 'Twilio credentails are missing or expired.', 'uncanny-automator' ),
			);
		}

		$from = trim( get_option( 'uap_automator_twilio_api_phone_number', '' ) );

		if ( empty( $from ) ) {
			return array(
				'result'  => false,
				'message' => __( 'Twilio number is missing.', 'uncanny-automator' ),
			);
		}

		$to = self::validate_phone_number( $to );

		if ( ! $to ) {
			return array(
				'result'  => false,
				'message' => __( 'To number is not valid.', 'uncanny-automator' ),
			);
		}

		$request['action'] = 'send_sms';
		$request['account_sid'] = $client['account_sid'];
		$request['auth_token'] = $client['auth_token'];


		$request['from'] = $from;
		$request['to'] = $to;
		$request['body'] = $body;

		try {
			$response = Api_Server::api_call( 'v2/twilio', $request );
		} catch ( \Exception $th ) {
			return array(
				'result'  => false,
				'message' => $th->getMessage(),
			);
		}

		update_user_meta( $user_id, '_twilio_sms_', $response );

		return array(
			'result'  => true,
			'message' => '',
		);

	}

	/**
	 * @param $phone
	 *
	 * @return false|mixed|string|string[]
	 */
	private function validate_phone_number( $phone ) {
		// Allow +, - and . in phone number
		$filtered_phone_number = filter_var( $phone, FILTER_SANITIZE_NUMBER_INT );
		// Remove "-" from number
		$phone_to_check = str_replace( '-', '', $filtered_phone_number );

		// Check the lenght of number
		// This can be customized if you want phone number from a specific country
		if ( strlen( $phone_to_check ) < 10 || strlen( $phone_to_check ) > 14 ) {
			return false;
		} else {
			return $phone_to_check;
		}
	}

	/**
	 * get_client
	 *
	 * @return void|bool
	 */
	public function get_client() {

		$sid      = get_option( 'uap_automator_twilio_api_account_sid' );
		$token    = get_option( 'uap_automator_twilio_api_auth_token' );

		if ( empty( $sid ) || empty( $token ) ) {
			return false;
		}

		return array('account_sid' => $sid, 'auth_token' => $token );

	}

	/**
	 * Check if the settings tab should display.
	 *
	 * @return boolean.
	 */
	public function display_settings_tab() {

		if ( Automator()->utilities->has_valid_license() ) {
			return true;
		}

		if ( Automator()->utilities->is_from_modal_action() ) {
			return true;
		}

		return ! empty( $this->get_client() );
	}

	/**
	 * @param $tabs
	 *
	 * @return mixed
	 */
	public function add_twilio_api_settings( $tabs ) {

		if ( ! $this->display_settings_tab() ) {
			return $tabs;
		}

		$tab_url                               = admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-settings&tab=' . $this->setting_tab;
		$tabs[ $this->setting_tab ]            = array(
			'name'           => __( 'Twilio', 'uncanny-automator' ),
			'title'          => __( 'Twilio API settings', 'uncanny-automator' ),
			'description'    => sprintf(
									'<p>%1$s</p>',
									sprintf(
										__( "To view API credentials visit %1\$s. It's really easy, we promise! Visit %2\$s for simple instructions.", 'uncanny-automator' ),

										'<a href="' . automator_utm_parameters( 'https://www.twilio.com/console/', 'settings', 'twilio-credentials' ) . '" target="_blank">https://www.twilio.com/console/</a>',

										'<a href="' . automator_utm_parameters( 'https://automatorplugin.com/knowledge-base/twilio/', 'settings', 'twilio-kb_article' ) . '" target="_blank">https://automatorplugin.com/knowledge-base/twilio/</a>'
									)
								) . $this->get_user_info(),
			'is_pro'         => false,
			'settings_field' => 'uap_automator_twilio_api_settings',
			'wp_nonce_field' => 'uap_automator_twilio_api_nonce',
			'save_btn_name'  => 'uap_automator_twilio_api_save',
			'save_btn_title' => __( 'Save API details', 'uncanny-automator' ),
			'fields'         => array(
				'uap_automator_twilio_api_account_sid'  => array(
					'title'       => __( 'Account SID:', 'uncanny-automator' ),
					'type'        => 'text',
					'css_classes' => '',
					'placeholder' => '',
					'default'     => '',
					'required'    => true,
					'custom_atts' => array( 'autocomplete' => 'off' ),
				),
				'uap_automator_twilio_api_auth_token'   => array(
					'title'       => __( 'Auth token:', 'uncanny-automator' ),
					'type'        => 'text',
					'css_classes' => '',
					'placeholder' => '',
					'default'     => '',
					'required'    => true,
					'custom_atts' => array( 'autocomplete' => 'off' ),
				),
				'uap_automator_twilio_api_phone_number' => array(
					'title'       => __( 'Twilio number:', 'uncanny-automator' ),
					'type'        => 'text',
					'css_classes' => '',
					'placeholder' => '+15017122661',
					'default'     => '',
					'required'    => true,
					'custom_atts' => array( 'autocomplete' => 'off' ),
				),
			),
		);

		return $tabs;
	}

	/**
	 * Returns the html of the user connected in Twillio API.
	 *
	 * @return string The html of the user.
	 */
	public function get_user_info() {

		$accounts = $this->get_twillio_accounts_connected();

		if ( ! empty( $accounts ) ) {
			return $this->get_user_html( $accounts );
		}

		return '';
	}

	/**
	 * Constructs the html of the user connected in Twillio API.
	 *
	 * @param array $account The account connected found in Twillio Response.
	 * @return string The complete html display of user info.
	 */
	public function get_user_html( $account = array() ) {
		ob_start();
		$this->get_inline_stylesheet();

		?>
		<?php if ( ! empty( $account ) ) : ?>
			<div class="uoa-twillio-user-info">
				<div class="uoa-twillio-user-info__item">
					<div class="uoa-twillio-user-info__item-name">
						<?php echo esc_html( $account['friendly_name'] ); ?>
					</div>
					<div class="uoa-twillio-user-info__item-type">
						<?php echo esc_html( $account['type'] ); ?>
					</div>
					<div class="uoa-twillio-user-info__item-status">
						<?php echo esc_html( $account['status'] ); ?>
					</div>
				</div>
			</div>
			<p>
				<?php
				$disconnect_uri = add_query_arg(
					array(
						'action' => 'automator_twillio_disconnect',
						'nonce'  => wp_create_nonce( 'automator_twillio_disconnect' ),
					),
					admin_url( 'admin-ajax.php' )
				);
				?>
				<a title="<?php esc_attr_e( 'Disconnect', 'uncanny-automator-pro' ); ?>" href="<?php echo esc_url( $disconnect_uri ); ?>" class="uo-settings-btn uo-settings-btn--error">
					<?php esc_html_e( 'Disconnect', 'uncanny-automator-pro' ); ?>
				</a>

			</p>
		<?php endif; ?>
		<?php
		return ob_get_clean();
	}

	/**
	 * Outputs an inline CSS to format our disconnect button and user info.
	 *
	 * @return void
	 */
	public function get_inline_stylesheet() {
		?>
		<style>
			.uo-settings-content-description a.uo-settings-btn--error {
				color: #e94b35;
			}
			.uo-settings-content-description a.uo-settings-btn--error:focus,
			.uo-settings-content-description a.uo-settings-btn--error:active,
			.uo-settings-content-description a.uo-settings-btn--error:hover {
				color: #fff;
			}

			.uoa-twillio-user-info {
				margin: 20px 0;
				color: #1f304c;
			}
			.uoa-twillio-user-info__item {
				margin-bottom: 10px;
				display: flex;
				flex-wrap: nowrap;
				align-items: center;
				justify-content: space-between;
				max-width: 285px;
			}
			.uoa-twillio-user-info__item-name {
				font-weight: 700;
			}
			.uoa-twillio-user-info__item-type,
			.uoa-twillio-user-info__item-status {
				border: 1px solid;
				border-radius: 20px;
				display: inline-block;
				font-size: 12px;
				padding: 2.5px 10px 3px;
				text-transform: capitalize;
			}
		</style>
		<?php
	}

	/**
	 * Get the Twillio Accounts connected using the account id and auth token.
	 * This functions sends an http request with Basic Authentication to Twillio API.
	 *
	 * @return array $twillio_accounts The twillio accounts connected.
	 */
	public function get_twillio_accounts_connected() {

		$client = $this->get_client();

		if ( empty( $client ) ) {
			return array();
		}

		// Return the transient if its available.
		$accounts_saved = get_transient( '_automator_twilio_account_info' );

		if ( false !== $accounts_saved ) {
			return $accounts_saved;
		}

		$body['action'] = 'account_info';
		$body['account_sid'] = $client['account_sid'];
		$body['auth_token'] = $client['auth_token'];

		try {
			$twillio_account = Api_Server::api_call( 'v2/twilio', $body );
		} catch ( \Exception $th ) {
			return array();
		}

		if ( empty( $twillio_account ) ) {
			return array();
		}

		// Update the transient.
		set_transient( '_automator_twilio_account_info', $twillio_account, DAY_IN_SECONDS );

		return $twillio_account;

	}

	/**
	 * Callback function to hook wp_ajax_automator_twillio_disconnect.
	 * Deletes all the option and transients then redirect the user back to the settings page.
	 *
	 * @return void.
	 */
	public function automator_twillio_disconnect() {

		if ( wp_verify_nonce( filter_input( INPUT_GET, 'nonce', FILTER_DEFAULT ), 'automator_twillio_disconnect' ) ) {

			// Remove option
			$option_keys = array(
				'_uncannyowl_twilio_settings',
				'_uncannyowl_twilio_settings_expired',
				'uap_automator_twilio_api_auth_token',
				'uap_automator_twilio_api_phone_number',
				'uap_automator_twilio_api_account_sid',
			);

			foreach ( $option_keys as $option_key ) {
				delete_option( $option_key );
			}

			// Remove transients.
			$transient_keys = array(
				'_uncannyowl_twilio_settings',
				'_automator_twilio_account_info',
				'uap_automator_twilio_api_accounts_response'
			);

			foreach ( $transient_keys as $transient_key ) {
				delete_transient( $transient_key );
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type' => 'uo-recipe',
					'page'      => 'uncanny-automator-settings',
					'tab'       => 'twilio_api',
				),
				admin_url( 'edit.php' )
			)
		);

		exit;
	}

	public function twilio_setting_update() {
		delete_transient( '_automator_twilio_account_info' );
	}
}
