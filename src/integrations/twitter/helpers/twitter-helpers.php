<?php

namespace Uncanny_Automator;

/**
 * Class Twitter_Helpers
 *
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
	 * The URL of the API for this integration
	 *
	 * @var String
	 */
	public static $automator_api;

	/**
	 * Twitter_Helpers constructor.
	 */
	public function __construct() {
		self::$automator_api = AUTOMATOR_API_URL . 'v2/twitter';

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
		$this->load_settings();
	}

	/**
	 * Load the settings
	 * 
	 * @return void
	 */
	private function load_settings() {
		include_once __DIR__ . '/../settings/settings-twitter.php';
	}

	/**
	 * @param Twitter_Helpers $options
	 */
	public function setOptions( Twitter_Helpers $options ) { // phpcs:ignore
		$this->options = $options;
	}

	/**
	 * Checks whether this integration is connected
	 *
	 * @return boolean True if it's connected
	 */
	public static function get_is_connected() {
		return ! empty( self::get_client() );
	}

	/**
	 *
	 * @return array $tokens
	 */
	public static function get_client() {
		$tokens = get_option( '_uncannyowl_twitter_settings', array() );

		if ( empty( $tokens ) ) {
			return false;
		}

		return $tokens;
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
}

