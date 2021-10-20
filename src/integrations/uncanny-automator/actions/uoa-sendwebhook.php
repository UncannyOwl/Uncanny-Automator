<?php

namespace Uncanny_Automator;

use WP_Error;

/**
 * Class UOA_SENDWEBHOOK
 * @package Uncanny_Automator
 */
class UOA_SENDWEBHOOK {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'UOA';
	public static $number_of_keys;
	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code    = 'WPSENDWEBHOOK';
		$this->action_meta    = 'WPWEBHOOK';
		self::$number_of_keys = 7;
		$this->define_action();
		add_filter( 'automator_api_setup', [ $this, 'legacy_meta_data' ] );

	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {


		$action = array(
			'author'             => Automator()->get_author_name( $this->action_code ),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'knowledge-base/send-data-to-a-webhook' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			'requires_user'      => false,
			/* translators: Action - Uncanny Automator */
			'sentence'           => sprintf( esc_attr__( 'Send data to {{a webhook:%1$s}}', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - Uncanny Automator */
			'select_option_name' => esc_attr__( 'Send data to {{a webhook}}', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'send_webhook' ),
			'options_group'      => [
				$this->action_meta => [
					// Webhook URL
					[
						'input_type' => 'url',

						'option_code' => 'WEBHOOK_URL',
						'label'       => esc_attr__( 'URL', 'uncanny-automator' ),

						'supports_tokens' => true,
						'required'        => true,
					],
					// Action event
					[
						'input_type' => 'select',

						'option_code' => 'ACTION_EVENT',
						/* translators: HTTP request method */
						'label'       => esc_attr__( 'Request method', 'uncanny-automator' ),
						'description' => esc_attr__( 'Select the HTTP request method supported by the webhook destination. If you are unsure, leave this value unchanged unless you are experiencing issues.', 'uncanny-automator' ),

						'required' => true,

						'default_value' => 'POST',
						'options'       => [
							'GET'    => 'GET',
							'PUT'    => 'PUT',
							'POST'   => 'POST',
							'DELETE' => 'DELETE',
							'HEAD'   => 'HEAD',
						],
					],
					// Header
					[
						'input_type' => 'repeater',

						'option_code' => 'WEBHOOK_HEADERS',

						'label'       => esc_attr__( 'Headers', 'uncanny-automator' ),
						'description' => esc_attr__( 'Add any HTTP request headers required by the webhook destination.', 'uncanny-automator' ),

						'required'          => false,
						'fields'            => [
							[
								'input_type' => 'text',

								'option_code' => 'NAME',
								'label'       => esc_attr__( 'Name', 'uncanny-automator' ),

								'supports_tokens' => true,
								'required'        => true,
							],
							[
								'input_type' => 'text',

								'option_code' => 'VALUE',
								'label'       => esc_attr__( 'Value', 'uncanny-automator' ),

								'supports_tokens' => true,
								'required'        => true,
							],
						],

						/* translators: Non-personal infinitive verb */
						'add_row_button'    => esc_attr__( 'Add header', 'uncanny-automator' ),
						/* translators: Non-personal infinitive verb */
						'remove_row_button' => esc_attr__( 'Remove header', 'uncanny-automator' ),
					],
					// Fields
					[
						'input_type' => 'repeater',

						'option_code' => 'WEBHOOK_FIELDS',

						'label' => esc_attr__( 'Fields', 'uncanny-automator' ),

						'required'          => true,
						'fields'            => [
							[
								'input_type' => 'text',

								'option_code' => 'KEY',
								'label'       => esc_attr__( 'Key', 'uncanny-automator' ),

								'supports_tokens' => true,
								'required'        => true,
							],
							[
								'input_type' => 'text',

								'option_code' => 'VALUE',
								'label'       => esc_attr__( 'Value', 'uncanny-automator' ),

								'supports_tokens' => true,
								'required'        => true,
							],
						],

						/* translators: Non-personal infinitive verb */
						'add_row_button'    => esc_attr__( 'Add pair', 'uncanny-automator' ),
						/* translators: Non-personal infinitive verb */
						'remove_row_button' => esc_attr__( 'Remove pair', 'uncanny-automator' ),
					],
				],
			],
			'buttons'            => [
				[
					'show_in'     => $this->action_meta,
					'text'        => esc_attr__( 'Documentation', 'uncanny-automator' ),
					'css_classes' => 'uap-btn uap-btn--transparent',
					'on_click'    => 'function(){ window.open( "https://automatorplugin.com", "_blank" ); }',
				],
				[
					'show_in'     => $this->action_meta,
					/* translators: Non-personal infinitive verb */
					'text'        => esc_attr__( 'Send test', 'uncanny-automator' ),
					'css_classes' => 'uap-btn uap-btn--red',
					'on_click'    => $this->send_test_js(),
					'modules'     => [ 'markdown' ],
				],
			],
		);

		Automator()->register->action( $action );
	}

	/**
	 * Anonymous JS function invoked as callback when clicking
	 * the custom button "Send test". The JS function requires
	 * the JS module "markdown". Make sure it's included in
	 * the "modules" array
	 *
	 * @return string The JS code
	 */

	public function send_test_js() {
		// Start output
		ob_start();

		// It's optional to add the <script> tags
		// This must have only one anonymous function
		?>

		<script>

			// Do when the user clicks on send test
			function ($button, data, modules) {
				// Add loading animation to the button
				$button.addClass('uap-btn--loading uap-btn--disabled');

				// Get the data we're going to send to the AJAX request
				let dataToBeSent = {
					action: 'sendtest_uoa_webhook',
					nonce: UncannyAutomator.nonce,

					integration_id: data.item.integrationCode,
					item_id: data.item.id,
					values: data.values
				}

				// Do AJAX
				jQuery.ajax({
					method: 'POST',
					dataType: 'json',
					url: ajaxurl,
					data: dataToBeSent,

					success: function (response) {
						// Remove loading animation from the button
						$button.removeClass('uap-btn--loading uap-btn--disabled');

						// Create notice
						// But first check if the message is defined
						if (typeof response.message !== 'undefined') {
							// Get notice type
							let noticeType = typeof response.type !== 'undefined' ? response.type : 'gray';

							// Parse message using markdown
							let markdown = new modules.Markdown(response.message);

							// Create notice
							let $notice = jQuery('<div/>', {
								'class': 'item-options__notice item-options__notice--' + noticeType
							});

							// Get markdown HTML
							let $message = markdown.getHTML();

							// Add message to the notice container
							$notice.html($message);

							// Get the notices container
							let $noticesContainer = jQuery('.item[data-id="' + data.item.id + '"] .item-options__notices');

							// Add notice
							$noticesContainer.html($notice);
						}
					},

					statusCode: {
						403: function () {
							location.reload();
						}
					},

					fail: function (response) {
					}
				});
			}

		</script>

		<?php

		// Get output
		$output = ob_get_clean();

		// Return output
		return $output;
	}

	/**
	 * Support legacy data structure.
	 */
	public function legacy_meta_data( $recipe_data ) {
		if ( ! empty( $recipe_data['recipes_object'] ) ) {
			foreach ( $recipe_data['recipes_object'] as $recipe_key => $recipe ) {
				if ( 'trash' !== $recipe['post_status'] ) {
					if ( ! empty( $recipe['actions'] ) ) {
						foreach ( $recipe['actions'] as $action_key => $action ) {
							if ( $this->action_code === $action['meta']['code'] ) {
								if ( isset( $action['meta']['WEBHOOKURL'] ) && ! isset( $action['meta']['WEBHOOK_URL'] ) ) {
									$recipe_data['recipes_object'][ $recipe_key ]['actions'][ $action_key ]['meta']['WEBHOOK_URL'] = $action['meta']['WEBHOOKURL'];
								}
								if ( isset( $action['meta']['KEY1'] ) && ! isset( $action['meta']['WEBHOOK_FIELDS'] ) ) {
									$webhook_field = array();
									for ( $i = 1; $i <= self::$number_of_keys; $i ++ ) {
										if ( isset( $action['meta'][ 'KEY' . $i ] ) && ! empty( $action['meta'][ 'KEY' . $i ] ) ) {
											$webhook_field[] = [
												'KEY'   => $action['meta'][ 'KEY' . $i ],
												'VALUE' => $action['meta'][ 'VALUE' . $i ],
											];
										}
									}
									$recipe_data['recipes_object'][ $recipe_key ]['actions'][ $action_key ]['meta']['WEBHOOK_FIELDS'] = json_encode( $webhook_field );
								}
								if ( ! isset( $action['meta']['ACTION_EVENT'] ) ) {
									$recipe_data['recipes_object'][ $recipe_key ]['actions'][ $action_key ]['meta']['ACTION_EVENT'] = 'POST';
								}
							}
						}
					}
				}
			}
		}

		return $recipe_data;
	}

	/**
	 * Validation function when the action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 */
	public function send_webhook( $user_id, $action_data, $recipe_id, $args ) {


		$key_values   = array();
		$headers      = array();
		$request_type = 'POST';
		$webhook_url  = null;
		if ( isset( $action_data['meta']['WEBHOOKURL'] ) ) {
			$webhook_url = Automator()->parse->text( $action_data['meta']['WEBHOOKURL'], $recipe_id, $user_id, $args );

			for ( $i = 1; $i <= UOA_SENDWEBHOOK::$number_of_keys; $i ++ ) {

				$key                = Automator()->parse->text( $action_data['meta'][ 'KEY' . $i ], $recipe_id, $user_id, $args );
				$value              = Automator()->parse->text( $action_data['meta'][ 'VALUE' . $i ], $recipe_id, $user_id, $args );
				$key_values[ $key ] = $value;
			}

		} elseif ( isset( $action_data['meta']['WEBHOOK_URL'] ) ) {
			$webhook_url = Automator()->parse->text( $action_data['meta']['WEBHOOK_URL'], $recipe_id, $user_id, $args );

			$fields = json_decode( $action_data['meta']['WEBHOOK_FIELDS'], true );

			for ( $i = 0; $i < count( $fields ); $i ++ ) {
				$key                = Automator()->parse->text( $fields[ $i ]['KEY'], $recipe_id, $user_id, $args );
				$value              = Automator()->parse->text( $fields[ $i ]['VALUE'], $recipe_id, $user_id, $args );
				$key_values[ $key ] = $value;
			}

			if ( isset( $action_data['meta']['WEBHOOK_HEADERS'] ) ) {
				$header_meta = json_decode( $action_data['meta']['WEBHOOK_HEADERS'], true );
				if ( ! empty( $header_meta ) ) {
					for ( $i = 0; $i <= count( $header_meta ); $i ++ ) {
						$key = isset( $header_meta[ $i ]['NAME'] ) ? Automator()->parse->text( $header_meta[ $i ]['NAME'], $recipe_id, $user_id, $args ) : null;
						// remove colon if user added in NAME
						$key   = str_replace( ':', '', $key );
						$value = isset( $header_meta[ $i ]['VALUE'] ) ? Automator()->parse->text( $header_meta[ $i ]['VALUE'], $recipe_id, $user_id, $args ) : null;
						if ( ! is_null( $key ) && ! is_null( $value ) ) {
							$headers[ $key ] = $value;
						}
					}
				}
			}

			if ( 'POST' === (string) $action_data['meta']['ACTION_EVENT'] || 'CUSTOM' === (string) $action_data['meta']['ACTION_EVENT'] ) {
				$request_type = 'POST';
			} elseif ( 'GET' === (string) $action_data['meta']['ACTION_EVENT'] ) {
				$request_type = 'GET';
			} elseif ( 'PUT' === (string) $action_data['meta']['ACTION_EVENT'] ) {
				$request_type = 'PUT';
			} elseif ( 'DELETE' === (string) $action_data['meta']['ACTION_EVENT'] ) {
				$request_type = 'DELETE';
			} elseif ( 'HEAD' === (string) $action_data['meta']['ACTION_EVENT'] ) {
				$request_type = 'HEAD';
			} elseif ( 'automator_custom_value' === (string) $action_data['meta']['ACTION_EVENT'] && isset( $action_data['meta']['ACTION_EVENT_custom'] ) ) {
				$request_type = $action_data['meta']['ACTION_EVENT_custom'];
			}
		}

		if ( $key_values && ! is_null( $webhook_url ) ) {
			$args = array(
				'method'   => $request_type,
				'body'     => $key_values,
				'timeout'  => '30',
				'blocking' => false,
			);

			if ( ! empty( $headers ) ) {
				$args['headers'] = $headers;
			}

			$response = wp_remote_request( $webhook_url, $args );

			if ( $response instanceof WP_Error ) {
				/* translators: 1. Webhook URL */
				$error_message = sprintf( esc_attr__( 'An error was found in the webhook (%1$s) response.', 'uncanny-automator' ), $webhook_url );
				Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message );

				return;
			}

			Automator()->complete_action( $user_id, $action_data, $recipe_id );
		}
	}
}
