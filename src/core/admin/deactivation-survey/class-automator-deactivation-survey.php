<?php

if ( ! class_exists( 'Automator_Deactivation_Survey' ) ) {
	/**
	 * Awesome Motive Deactivation Survey.
	 *
	 * This prompts the user for more details when they deactivate the plugin.
	 *
	 * @version    1.3.0
	 * @author     Jared Atchison and Chris Christoff
	 * @package    AwesomeMotive
	 * @license    GPL-2.0+
	 * @copyright  Copyright (c) 2018
	 */
	class Automator_Deactivation_Survey {
		/**
		 * The API URL we are calling. This value is set as a constant
		 * in the plugin for which it's being used.
		 *
		 * If `AUTOMATOR_DEV_MODE` is set to true then
		 *
		 * @since 1.0.0
		 * @var string
		 */
		public $api_url;

		/**
		 * Name for this plugin.
		 *
		 * @since 1.0.0
		 * @var string
		 */
		public $name;

		/**
		 * Unique slug for this plugin.
		 *
		 * @since 1.0.0
		 * @var string
		 */
		public $plugin;

		/**
		 * Primary class constructor.
		 *
		 * @param string $name Plugin name.
		 * @param string $plugin Plugin slug.
		 *
		 * @since 1.0.0
		 */
		public function __construct( $name = '', $plugin = '' ) {

			$this->name   = $name;
			$this->plugin = $plugin;

			$this->api_url =
				$this->has_deactivation_url() ?
					AUTOMATOR_DEACTIVATION_SURVEY_URL :
					'';

			// Don't run deactivation survey on dev sites.
			if ( $this->is_dev_url() ) {
				return;
			}

			add_action( 'admin_print_scripts', array( $this, 'js' ), 20 );
			add_action( 'admin_print_scripts', array( $this, 'css' ) );
			add_action( 'admin_footer', array( $this, 'modal' ) );
		}

		/**
		 * Determines if the current site has a deactivation URL set.
		 *
		 * Deactivation URLs can still be used in development sites if a
		 * development endpoint is set, so the functionality between
		 * deactivation URL and development URL is separated.
		 *
		 * @return bool True if the constant is set; otherwise, it's false.
		 * @since  1.3.0
		 *
		 */
		public function has_deactivation_url() {
			return (
				defined( 'AUTOMATOR_DEACTIVATION_SURVEY_URL' ) &&
				'' !== trim( AUTOMATOR_DEACTIVATION_SURVEY_URL ) &&
				filter_var( AUTOMATOR_DEACTIVATION_SURVEY_URL, FILTER_VALIDATE_URL )
			);
		}

		/**
		 * Checks if current site is a development one.
		 *
		 * @return bool
		 * @since 1.2.0
		 */
		public function is_dev_url() {
			// If it is an AM dev site, return false, so we can see them on our dev sites.
			if ( defined( 'AUTOMATOR_DEV_MODE' ) && AUTOMATOR_DEV_MODE ) {
				return false;
			}

			$url          = network_site_url( '/' );
			$is_local_url = false;

			// Trim it up
			$url = strtolower( trim( $url ) );

			// Need to get the host...so let's add the scheme so we can use parse_url
			if ( false === strpos( $url, 'http://' ) && false === strpos( $url, 'https://' ) ) {
				$url = 'http://' . $url;
			}
			$url_parts = parse_url( $url );
			$host      = ! empty( $url_parts['host'] ) ? $url_parts['host'] : false;
			if ( ! empty( $url ) && ! empty( $host ) ) {
				if ( false !== ip2long( $host ) ) {
					if ( ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
						$is_local_url = true;
					}
				} elseif ( 'localhost' === $host ) {
					$is_local_url = true;
				}

				$tlds_to_check = array( '.dev', '.local', ':8888' );
				foreach ( $tlds_to_check as $tld ) {
					if ( false !== strpos( $host, $tld ) ) {
						$is_local_url = true;
						continue;
					}
				}
				if ( substr_count( $host, '.' ) > 1 ) {
					$subdomains_to_check = array( 'dev.', '*.staging.', 'beta.', 'test.' );
					foreach ( $subdomains_to_check as $subdomain ) {
						$subdomain = str_replace( '.', '(.)', $subdomain );
						$subdomain = str_replace( array( '*', '(.)' ), '(.*)', $subdomain );
						if ( preg_match( '/^(' . $subdomain . ')/', $host ) ) {
							$is_local_url = true;
							continue;
						}
					}
				}
			}

			return $is_local_url;
		}

		/**
		 * Checks if current admin screen is the plugins page.
		 *
		 * @return bool
		 * @since 1.0.0
		 */
		public function is_plugin_page() {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : false;
			if ( empty( $screen ) ) {
				return false;
			}

			return ( ! empty( $screen->id ) && in_array( $screen->id, array( 'plugins', 'plugins-network' ), true ) );
		}

		/**
		 * Survey javascript.
		 *
		 * @since 1.0.0
		 */
		public function js() {

			if ( ! $this->is_plugin_page() ) {
				return;
			}
			?>
			<script type="text/javascript">
				(function ($) {
					$(function () {
						var $deactivateLink = $('#the-list').find('[data-slug="<?php echo $this->plugin; //phpcs:ignore ?>"] span.deactivate a'),
							$overlay = $('#ua-deactivate-survey-<?php echo $this->plugin; //phpcs:ignore ?>'),
							$form = $overlay.find('form'),
							formOpen = false;

						/* For backwards compatibility, we'll need to check to see if
						 * we're able to get the deactivation link in the traditional
						 * way.
						 *
						 * If not, we'll do it this way.
						 */
						if (0 === $deactivateLink.length) {
							$deactivateLink = $('#deactivate-<?php echo $this->plugin; //phpcs:ignore ?>');
						}

						// Plugin listing table deactivate link.
						$deactivateLink.on('click', function (event) {
							event.preventDefault();
							$overlay.css('display', 'table');
							formOpen = true;
							$form.find('.ua-deactivate-survey-option:first-of-type input[type=radio]').focus();
						});
						// Survey radio option selected.
						$form.on('change', 'input[type=radio]', function (event) {
							event.preventDefault();
							$form.find('input[type=text], .error').hide();
							$form.find('.ua-deactivate-survey-option').removeClass('selected');
							$(this).closest('.ua-deactivate-survey-option').addClass('selected').find('input[type=text]').show();
						});
						// Survey Skip & Deactivate.
						$form.on('click', '.ua-deactivate-survey-deactivate', function (event) {
							event.preventDefault();
							location.href = $deactivateLink.attr('href');
						});
						// Survey submit.
						$form.submit(function (event) {
							event.preventDefault();
							if (!$form.find('input[type=radio]:checked').val()) {
								$form.find('.ua-deactivate-survey-footer').prepend('<span class="error"><?php echo esc_js( __( 'Please select an option', 'uncanny-automator' ) ); ?></span>');
								return;
							}

							var data = {
								code: $form.find('.selected input[type=radio]').val(),
								reason: $form.find('.selected .ua-deactivate-survey-option-reason').text(),
								details: $form.find('.selected input[type=text]').val(),
								feedback: $form.find('#additional-feedback').val(),
								//site: '<?php echo esc_url( home_url() ); ?>',
								plugin: '<?php echo sanitize_key( $this->name ); ?>'
							}

							var submitSurvey = $.post(
								'<?php echo $this->api_url; //phpcs:ignore ?>',
								data
							);
							submitSurvey.always(function () {
								location.href = $deactivateLink.attr('href');
							});
						});
						// Exit key closes survey when open.
						$(document).keyup(function (event) {
							if (27 === event.keyCode && formOpen) {
								$overlay.hide();
								formOpen = false;
								$deactivateLink.focus();
							}
						});
					});
				})(jQuery);
			</script>
			<?php
		}

		/**
		 * Survey CSS.
		 *
		 * @since 1.0.0
		 */
		public function css() {

			if ( ! $this->is_plugin_page() ) {
				return;
			}
			?>
			<style type="text/css">
				.ua-deactivate-survey-modal {
					display: none;
					table-layout: fixed;
					position: fixed;
					z-index: 9999;
					width: 100%;
					height: 100%;
					text-align: center;
					font-size: 14px;
					top: 0;
					left: 0;
					background: rgba(0, 0, 0, 0.8);
				}

				.ua-deactivate-survey-wrap {
					display: table-cell;
					vertical-align: middle;
				}

				.ua-deactivate-survey {
					background-color: #fff;
					max-width: 550px;
					margin: 0 auto;
					padding: 30px;
					text-align: left;
				}

				.ua-deactivate-survey .error {
					display: block;
					color: red;
					margin: 0 0 10px 0;
				}

				.ua-deactivate-survey-title {
					display: block;
					font-size: 18px;
					font-weight: 700;
					text-transform: uppercase;
					border-bottom: 1px solid #ddd;
					padding: 0 0 18px 0;
					margin: 0 0 18px 0;
				}

				.ua-deactivate-survey-title span {
					color: #999;
					margin-right: 10px;
				}

				.ua-deactivate-survey-desc {
					display: block;
					font-weight: 600;
					margin: 0 0 18px 0;
				}

				.ua-deactivate-survey-option {
					margin: 0 0 10px 0;
				}

				.ua-deactivate-survey-option-input {
					margin-right: 10px !important;
				}

				.ua-deactivate-survey-option-details {
					display: none;
					width: 90%;
					margin: 10px 0 0 30px;
				}

				.ua-deactivate-survey-footer {
					margin-top: 18px;
				}

				.ua-deactivate-survey-deactivate {
					float: right;
					font-size: 13px;
					color: #ccc;
					text-decoration: none;
					padding-top: 7px;
				}

				.wp-core-ui .ua-deactivate-survey-submit:focus {
					box-shadow: 0 0 0 1px #fff, 0 0 0 3px #0790e9;
				}

				.wp-core-ui .ua-deactivate-survey-submit {
					background-color: #0790e9;
					border-color: #0790e9;
				}

				.ua-deactivate-survey-option-textarea {
					width: 100%;
					clear: both;
				}

				.wp-core-ui .ua-deactivate-survey-submit:active,
				.wp-core-ui .ua-deactivate-survey-submit:focus,
				.wp-core-ui .ua-deactivate-survey-submit:hover {
					background-color: #0B75B9;
					border-color: #0B75B9;
				}

				.ua-deactivate-survey-options input[type=text]:focus,
				.ua-deactivate-survey-options textarea:focus,
				.ua-deactivate-survey-options input[type=radio]:focus {
					border-color: #0790e9;
					box-shadow: 0 0 0 1px #0790e9;
				}
			</style>
			<?php
		}

		/**
		 * Survey modal.
		 *
		 * @since 1.0.0
		 */
		public function modal() {

			if ( ! $this->is_plugin_page() ) {
				return;
			}

			$options = array(
				1 => array(
					'title' => esc_html__( "It's a temporary deactivation", 'uncanny-automator' ),
				),
				2 => array(
					'title'   => esc_html__( "It can't do something I need", 'uncanny-automator' ),
					'details' => esc_html__( 'What feature(s) do you need?', 'uncanny-automator' ),
				),
				3 => array(
					'title'   => esc_html__( 'I found a plugin that works better for me', 'uncanny-automator' ),
					'details' => esc_html__( 'Which plugin worked better for you?', 'uncanny-automator' ),
				),
				4 => array(
					'title' => esc_html__( "It's too difficult to use", 'uncanny-automator' ),
				),
				5 => array(
					'title'   => esc_html__( 'Other', 'uncanny-automator' ),
					'details' => esc_html__( 'Provide details', 'uncanny-automator' ),
				),
			);
			?>
			<div class="ua-deactivate-survey-modal" id="ua-deactivate-survey-<?php echo $this->plugin; //phpcs:ignore ?>">
				<div class="ua-deactivate-survey-wrap">
					<form class="ua-deactivate-survey" method="post">
						<span class="ua-deactivate-survey-title"><span
								class="dashicons dashicons-testimonial"></span><?php echo ' ' . esc_html__( 'Quick Feedback', 'uncanny-automator' ); //phpcs:ignore ?></span>
						<span
							class="ua-deactivate-survey-desc"><?php echo sprintf( esc_html__( 'If you have a moment, please share why you are deactivating %s:', 'uncanny-automator' ), $this->name ); //phpcs:ignore ?></span>
						<div class="ua-deactivate-survey-options">
							<?php foreach ( $options as $id => $option ) : ?>
								<div class="ua-deactivate-survey-option">
									<label
										for="ua-deactivate-survey-option-<?php echo $this->plugin; //phpcs:ignore ?>-<?php echo $id; //phpcs:ignore ?>"
										class="ua-deactivate-survey-option-label">
										<input
											id="ua-deactivate-survey-option-<?php echo $this->plugin; //phpcs:ignore ?>-<?php echo $id; //phpcs:ignore ?>"
											class="ua-deactivate-survey-option-input" type="radio" name="code"
											value="<?php echo $id; //phpcs:ignore ?>"/>
										<span
											class="ua-deactivate-survey-option-reason"><?php echo $option['title']; //phpcs:ignore ?></span>
									</label>
									<?php if ( ! empty( $option['details'] ) ) : ?>
										<input class="ua-deactivate-survey-option-details" type="text"
											   placeholder="<?php echo $option['details']; //phpcs:ignore ?>"/>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
							<label
								for="ua-deactivate-survey-option-uncanny-automator-additional-feedback"
								class="ua-deactivate-survey-option-label">
										<span
											class="ua-deactivate-survey-option-uncanny-automator-additional-feedback"><?php _e( 'Additional feedback', 'uncanny-automator' ); //phpcs:ignore ?></span>
							</label>
							<br/><textarea rows="2" class="ua-deactivate-survey-option-textarea"
										   name="additional-feedback" id="additional-feedback"></textarea>
						</div>
						<div class="ua-deactivate-survey-footer">
							<button type="submit"
									class="ua-deactivate-survey-submit button button-primary button-large"><?php echo sprintf( esc_html__( 'Submit %s Deactivate', 'uncanny-automator' ), '&amp;' ); ?></button>
							<a href="#"
							   class="ua-deactivate-survey-deactivate"><?php echo sprintf( esc_html__( 'Skip %s Deactivate', 'uncanny-automator' ), '&amp;' ); ?></a>
						</div>
					</form>
				</div>
			</div>
			<?php
		}
	}
} // End if().
