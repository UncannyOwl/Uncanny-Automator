<?php

namespace Uncanny_Automator;

/**
 * Class Twitter_Helpers
 * @package Uncanny_Automator
 */
class Twitter_Helpers {

	/**
	 * @var Twitter_Helpers
	 */
	public $options;

	/**
	 * @var Twitter_Helpers
	 */
	public $setting_tab;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Twitter_Helpers constructor.
	 */
	public function __construct() {
		global $uncanny_automator;
		$this->load_options = $uncanny_automator->helpers->recipe->maybe_load_trigger_options( __CLASS__ );

		$this->setting_tab   = 'twitter_api';
		$this->automator_api = 'https://api.automatorplugin.com/twitter/';

		add_filter( 'uap_settings_tabs', array( $this, 'add_twitter_api_settings' ), 15 );
		add_action( 'init', array( $this, 'capture_oauth_tokens' ), 100, 3 );
		add_filter( 'uap_after_settings_extra_buttons', array( $this, 'twitter_connect_html' ), 10, 3 );

	}

	/**
	 * @param Twitter_Helpers $options
	 */
	public function setOptions( Twitter_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 *
	 * @return array $tokens
	 */
	public function get_client() {
		$tokens = get_option( '_uncannyowl_twitter_settings', array() );

		if ( empty( $tokens ) ) {
			return false;
		}

		return $tokens;
	}


	/**
	 * @param $tabs
	 *
	 * @return mixed
	 */
	public function add_twitter_api_settings( $tabs ) {

		$tab_url                    = admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-settings&tab=' . $this->setting_tab;
		$tabs[ $this->setting_tab ] = array(
			'name'           => __( 'Twitter', 'uncanny-automator' ),
			'title'          => __( 'Twitter account settings', 'uncanny-automator' ),
			'description'    => sprintf( '<p>%s</p>', __( 'Connecting to Twitter requires signing into your account to link it to Automator. To get started, click the "Connect an account" button below or the "Disconnect account" button if you need to disconnect or connect a new account. Uncanny Automator can only connect to a single Twitter account at one time. (It is not possible to set some recipes up under one account and then switch accounts, all recipes are mapped to the account selected on this page and existing recipes may break if they were set up under another account.)', 'uncanny-automator' ) ),
			'settings_field' => 'uap_automator_twitter_api_settings',
			'wp_nonce_field' => 'uap_automator_twitter_api_nonce',
			'save_btn_name'  => 'uap_automator_twitter_api_save',
			'save_btn_title' => __( 'Save settings', 'uncanny-automator' ),
			'fields'         => array(),
		);

		return $tabs;
	}

	/**
	 * @param $content
	 * @param $active
	 * @param $tab
	 *
	 * @return false|mixed|string
	 */
	public function twitter_connect_html( $content, $active, $tab ) {

		if ( 'twitter_api' === $active ) {

			$tab_url = admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-settings&tab=' . $this->setting_tab;

			$twitter_client = $this->get_client();

			if ( $twitter_client ) {
				$button_text  = __( 'Disconnect account', 'uncanny-automator' );
				$button_class = 'uo-disconnect-button';
				$button_url   = $tab_url . '&disconnect=1';
			} else {
				$nonce        = wp_create_nonce( 'automator_twitter_api_authentication' );
				$plugin_ver   = InitializePlugin::PLUGIN_VERSION;
				$api_ver      = '1.0';

				$action       = 'twitter_authorization_request';
				$redirect_url = urlencode( $tab_url );
				$button_url   = "https://api.automatorplugin.com/twitter/?action={$action}&redirect_url={$redirect_url}&nonce={$nonce}&api_ver={$api_ver}&plugin_ver={$plugin_ver}";
				$button_text  = __( 'Connect an account', 'uncanny-automator' );
				$button_class = 'uo-connect-button';
			}

			ob_start();
			?>

			<a href="<?php echo $button_url; ?>"
			   class="uo-settings-btn uo-settings-btn--secondary <?php echo $button_class; ?>">
				<?php
				echo $button_text;
				?>
			</a>

			<style>
				button[name="uap_automator_twitter_api_save"] {
					display: none;
				}

				.uo-connect-button {
					color: #fff;
					background-color: #4fb840;
				}

				.uo-disconnect-button {
					color: #fff;
					background-color: #f58933;
				}
			</style>
			<?php
			$content = ob_get_contents();
			ob_end_clean();
		}

		return $content;
	}

	/**
	 * @param string $option_code
	 * @param string $label
	 * @param bool $tokens
	 * @param string $type
	 * @param string $default
	 * @param bool
	 * @param string $description
	 * @param string $placeholder
	 *
	 * @return mixed
	 */
	public function textarea_field( $option_code = 'TEXT', $label = null, $tokens = true, $type = 'text', $default = null, $required = true, $description = '', $placeholder = null, $max_length = null ) {

		if ( ! $label ) {
			$label = __( 'Text', 'uncanny-automator' );
		}

		if ( ! $description ) {
			$description = '';
		}

		if ( ! $placeholder ) {
			$placeholder = '';
		}

		$option = array(
			'option_code'      => $option_code,
			'label'            => $label,
			'description'      => $description,
			'placeholder'      => $placeholder,
			'input_type'       => $type,
			'supports_tokens'  => $tokens,
			'required'         => $required,
			'default_value'    => $default,
			'supports_tinymce' => false,
			'max_length'       => $max_length,
		);

		return apply_filters( 'uap_option_text_field', $option );
	}

	/**
	 * Capture tokens returned by Automator API.
	 *
	 * @return mixed
	 */
	public function capture_oauth_tokens() {
		if ( isset( $_REQUEST['tab'] ) && $this->setting_tab == $_REQUEST['tab'] ) {

			if ( ! empty( $_GET['automator_api_message'] ) ) {
				$tokens = Automator_Helpers_Recipe::automator_api_decode_message( $_GET['automator_api_message'], wp_create_nonce( 'automator_twitter_api_authentication' ) );
				if ( $tokens ) {
					update_option( '_uncannyowl_twitter_settings', $tokens );
					wp_safe_redirect( admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-settings&tab=' . $this->setting_tab . '&connect=1' ) );
					die;
				} else {
					wp_safe_redirect( admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-settings&tab=' . $this->setting_tab . '&connect=2' ) );
					die;
				}
			} elseif ( ! empty( $_GET['disconnect'] ) ) {
				delete_option( '_uncannyowl_twitter_settings' );
				wp_safe_redirect( admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-settings&tab=' . $this->setting_tab ) );
				die;
			}
		}
	}

}

