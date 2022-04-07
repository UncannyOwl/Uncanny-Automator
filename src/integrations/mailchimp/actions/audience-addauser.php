<?php

namespace Uncanny_Automator;

/**
 * Class AUDIENCE_ADDAUSER
 *
 * @package Uncanny_Automator
 */
class AUDIENCE_ADDAUSER {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'MAILCHIMP';

	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'MCHIMPAUDIENCEADDAUSER';
		$this->action_meta = 'AUDIENCEADDAUSER';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name( $this->action_code ),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'knowledge-base/mailchimp/' ),
			'is_pro'             => false,
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			// translators: the selected Mailchimp's audience name
			'sentence'           => sprintf( __( 'Add the user to {{an audience:%1$s}}', 'uncanny-automator' ), $this->action_meta ),
			'select_option_name' => __( 'Add the user to {{an audience}}', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'add_update_audience_member' ),
			'options_callback'   => array( $this, 'load_options' ),
			'buttons'            => array(
				array(
					'show_in'     => $this->action_meta,
					'text'        => __( 'Load fields', 'uncanny-automator' ),
					'css_classes' => 'uap-btn uap-btn--red',
					'on_click'    => $this->get_samples_js(),
					'modules'     => array( 'modal', 'markdown' ),
				),
			),
		);

		Automator()->register->action( $action );
	}

	/**
	 * load_options
	 *
	 * @return void
	 */
	public function load_options() {
		return array(
			'options_group' => array(
				$this->action_meta => array(
					Automator()->helpers->recipe->mailchimp->options->get_all_lists(
						__( 'Audience', 'uncanny-automator' ),
						'MCLIST',
						array(
							'is_ajax'      => true,
							'target_field' => 'MCLISTGROUPS',
							'endpoint'     => 'select_mcgroupslist_from_mclist',
						)
					),
					Automator()->helpers->recipe->mailchimp->options->get_double_opt_in(
						__( 'Double opt-in', 'uncanny-automator' ),
						'MCDOUBLEOPTIN',
						array(
							'description' => __( 'When set to "yes", a confirmation email will be sent before the user is added to the selected audience.', 'uncanny-automator' ),
						)
					),
					Automator()->helpers->recipe->mailchimp->options->get_double_opt_in(
						__( 'Update existing', 'uncanny-automator' ),
						'MCUPDATEEXISTING',
						array(
							'description' => __( 'If this is set to Yes, the information provided will be used to update the existing user. Fields that are left blank will not be updated.', 'uncanny-automator' ),
						)
					),
					Automator()->helpers->recipe->mailchimp->options->get_double_opt_in(
						__( 'Change groups?', 'uncanny-automator' ),
						'MCCHANGEGROUPS',
						array(
							'options'     => array(
								array(
									'value' => 'replace-all',
									'text'  => __( 'Replace all', 'uncanny-automator' ),
								),
								array(
									'value' => 'add-only',
									'text'  => __( 'Add only', 'uncanny-automator' ),
								),
								array(
									'value' => 'replace-matching',
									'text'  => __( 'Remove matching', 'uncanny-automator' ),
								),
							),
							'description' => __( "Add only: The group(s) specified below will be added to the subscriber's existing groups/interests. Replace All: All of the subscriber's existing groups will be cleared, and replaced with the groups selected below. \n Remove Matching: Clears any existing group selections only for the groups specified below.", 'uncanny-automator' ),
						)
					),
					Automator()->helpers->recipe->mailchimp->options->get_list_groups(
						__( 'Groups', 'uncanny-automator' ),
						'MCLISTGROUPS',
						array(
							'required' => false,
						)
					),
					Automator()->helpers->recipe->field->text_field( 'MCLANGUAGECODE', __( 'Language code', 'uncanny-automator' ), true, 'text', null, false ),
					array(
						'option_code'       => 'MERGE_FIELDS',
						'input_type'        => 'repeater',
						'label'             => __( 'Merge fields', 'uncanny-automator' ),
						/* translators: 1. Button */
						'description'       => '',
						'required'          => true,
						'fields'            => array(
							array(
								'option_code' => 'FIELD_NAME',
								'label'       => __( 'Field', 'uncanny-automator' ),
								'input_type'  => 'text',
								'required'    => true,
								'read_only'   => true,
								'options'     => array(),
							),
							Automator()->helpers->recipe->field->text_field( 'FIELD_VALUE', __( 'Value', 'uncanny-automator' ), true, 'text', '', false ),
						),
						'add_row_button'    => __( 'Add pair', 'uncanny-automator' ),
						'remove_row_button' => __( 'Remove pair', 'uncanny-automator' ),
						'hide_actions'      => true,
					),
				),
			),
		);
	}

	public function get_samples_js() {
		// Start output
		ob_start();

		// It's optional to add the <script> tags
		// This must have only one anonymous function
		?>

		<script>

			// Do when the user clicks on send test
			function ($button, data, modules) {

				// Create a configuration object
				let config = {
					// In milliseconds, the time between each call
					timeBetweenCalls: 1 * 1000,
					// In milliseconds, the time we're going to check for samples
					checkingTime: 60 * 1000,
					// Links
					links: {
						noResultsSupport: 'https://automatorplugin.com/knowledge-base/google-sheets/'
					},
					// i18n
					i18n: {
						checkingHooks: "<?php /* translators: Number of seconds */ printf( esc_attr__( "We're checking for columns. We'll keep trying for %s seconds.", 'uncanny-automator' ), '{{time}}' ); ?>",
						noResultsTrouble: "<?php esc_attr_e( 'We had trouble finding columns.', 'uncanny-automator' ); ?>",
						noResultsSupport: "<?php esc_attr_e( 'See more details or get help', 'uncanny-automator' ); ?>",
						samplesModalTitle: "<?php esc_attr_e( "Here is the data we've collected", 'uncanny-automator' ); ?>",
						samplesModalWarning: "<?php /* translators: 1. Button */ printf( esc_attr__( 'Clicking on \"%1$s\" will remove your current fields and will use the ones on the table above instead.', 'uncanny-automator' ), '{{confirmButton}}' ); ?>",
						samplesTableValueType: "<?php esc_attr_e( 'Value type', 'uncanny-automator' ); ?>",
						samplesTableReceivedData: "<?php esc_attr_e( 'Received data', 'uncanny-automator' ); ?>",
						samplesModalButtonConfirm: "<?php esc_attr_e( 'Use these fields', 'uncanny-automator' ); ?>",
						samplesModalButtonCancel: "<?php esc_attr_e( 'Do nothing', 'uncanny-automator' ); ?>",
					}
				}

				// Create the variable we're going to use to know if we have to keep doing calls
				let foundResults = false

				// Get the date when this function started
				let startDate = new Date()

				// Create array with the data we're going to send
				let dataToBeSent = {
					action: 'get_mailchimp_audience_fields',
					nonce: UncannyAutomator.nonce,

					recipe_id: UncannyAutomator.recipe.id,
					item_id: data.item.id,
					audience: data.values.MCLIST
				}

				// Add notice to the item
				// Create notice
				let $notice = jQuery('<div/>', {
					'class': 'item-options__notice item-options__notice--warning'
				})

				// Add notice message
				$notice.html(config.i18n.checkingHooks.replace('{{time}}', parseInt(config.checkingTime / 1000)))

				// Get the notices container
				let $noticesContainer = jQuery('.item[data-id="' + data.item.id + '"] .item-options__notices')

				// Add notice
				$noticesContainer.html($notice)

				// Create the function we're going to use recursively to
				// do check for the samples
				var getSamples = function () {
					// Do AJAX call
					jQuery.ajax({
						method: 'POST',
						dataType: 'json',
						url: ajaxurl,
						data: dataToBeSent,

						// Set the checking time as the timeout
						timeout: config.checkingTime,

						success: function (response) {
							// Get new date
							let currentDate = new Date()

							// Define the default value of foundResults
							let foundResults = false

							// Check if the response was successful
							if (response.success) {
								// Check if we got the rows from a sample
								if (response.samples.length > 0) {
									// Update foundResults
									foundResults = true
								}
							}

							// Check if we have to do another call
							let shouldDoAnotherCall = false

							// First, check if we don't have results
							if (!foundResults) {
								// Check if we still have time left
								if ((currentDate.getTime() - startDate.getTime()) <= config.checkingTime) {
									// Update result
									shouldDoAnotherCall = true
								}
							}

							if (shouldDoAnotherCall) {
								// Wait and do another call
								setTimeout(function () {
									// Invoke this function again
									getSamples()
								}, config.timeBetweenCalls)
							} else {
								// Add loading animation to the button
								$button.removeClass('uap-btn--loading uap-btn--disabled')
								// Iterate samples and create an array with the rows
								let rows = []
								let keys = {}
								jQuery.each(response.samples, function (index, sample) {
									// Iterate keys
									jQuery.each(sample, function (index, row) {
										// Check if the we already added this key
										if (typeof keys[row.key] !== 'undefined') {
											// Then just append the value
											// rows[ keys[ row.key ] ].data = rows[ keys[ row.key ] ].data + ', ' + row.data;
										} else {
											// Add row and save the index
											keys[row.key] = rows.push(row)
										}
									})
								})
								// Get the field with the fields (WEBHOOK_DATA)

								let mergeFields = data.item.options.AUDIENCEADDAUSER.fields[6]

								// Remove all the current fields
								mergeFields.fieldRows = []

								// Add new rows. Iterate rows from the sample
								jQuery.each(rows, function (index, row) {
									// Add row
									mergeFields.addRow({
										FIELD_NAME: row.key
									}, false)
								})

								// Render again
								mergeFields.reRender()

								// Check if it has results
								if (foundResults) {
									// Remove notice
									$notice.remove()

								} else {
									// Change the notice type
									$notice.removeClass('item-options__notice--warning').addClass('item-options__notice--error')

									// Create a new notice message
									let noticeMessage = config.i18n.noResultsTrouble

									// Change the notice message
									$notice.html(noticeMessage + ' ')

									// Add help link
									let $noticeHelpLink = jQuery('<a/>', {
										target: '_blank',
										href: config.links.noResultsSupport
									}).text(config.i18n.noResultsSupport)
									$notice.append($noticeHelpLink)
								}
							}
						},

						statusCode: {
							403: function () {
								location.reload()
							}
						},

						fail: function (response) {
						}
					})
				}

				// Add loading animation to the button
				$button.addClass('uap-btn--loading uap-btn--disabled')

				// Try to get samples
				getSamples()
			}

		</script>

		<?php

		// Get output
		$output = ob_get_clean();

		// Return output
		return $output;
	}

	/**
	 * Validation function when the action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 */
	public function add_update_audience_member( $user_id, $action_data, $recipe_id, $args ) {

		$helpers = Automator()->helpers->recipe->mailchimp->options;

		try {

			$list_id         = $action_data['meta']['MCLIST'];
			$double_optin    = $action_data['meta']['MCDOUBLEOPTIN'];
			$update_existing = $action_data['meta']['MCUPDATEEXISTING'];
			$change_groups   = $action_data['meta']['MCCHANGEGROUPS'];
			$groups_list     = json_decode( $action_data['meta']['MCLISTGROUPS'] );
			$lang_code       = Automator()->parse->text( $action_data['meta']['MCLANGUAGECODE'], $recipe_id, $user_id, $args );

			$merge_fields = $action_data['meta']['MERGE_FIELDS'];
			$fields       = json_decode( $merge_fields, true );
			$key_values   = array();

			$field_count = count( $fields );

			for ( $i = 0; $i < $field_count; $i ++ ) {
				$key   = $fields[ $i ]['FIELD_NAME'];
				$value = Automator()->parse->text( $fields[ $i ]['FIELD_VALUE'], $recipe_id, $user_id, $args );
				if ( strpos( $key, '_addr1' ) || strpos( $key, '_addr2' ) || strpos( $key, '_city' ) || strpos( $key, '_state' ) || strpos( $key, '_zip' ) || strpos( $key, '_country' ) ) {
					$key_split = explode( '_', $key, 2 );
					if ( 2 === count( $key_split ) ) {
						$key_values[ $key_split[0] ][ $key_split[1] ] = $value;
					}
				} else {
					$key_values[ $key ] = $value;
				}
			}

			// get current user email
			$user           = get_userdata( $user_id );
			$user_hash      = md5( strtolower( trim( $user->user_email ) ) );
			$user_interests = array();

			$existing_user = $helpers->get_list_user( $list_id, $user_hash );

			// If the user already exists in this list
			if ( false !== $existing_user ) {

				if ( 'no' === $update_existing ) {
					throw new \Exception( __( 'User already subscribed to the list.', 'uncanny-automator' ) );
				}

				$user_interests = $helpers->compile_user_interests( $existing_user, $change_groups, $groups_list ); 
			}

			// Now create an audience
			$status = 'subscribed';

			if ( 'yes' === $double_optin ) {
				$status = 'pending';
			}
				
			$user_data = array(
				'email_address' => $user->user_email,
				'status'        => $status,
				'merge_fields'  => $key_values,
				'language'      => $lang_code,
				'interests'     => $user_interests,
			);
			
			if ( 'yes' === $update_existing ) {
				$user_data['status_if_new'] = $status;
			}

			if ( empty( $user_data['interests'] ) ) {
				unset( $user_data['interests'] );
			}

			$request_params = array(
				'action'    => 'add_subscriber',
				'list_id'   => $list_id,
				'user_hash' => $user_hash,
				'user_data' => wp_json_encode( $user_data ),
			);

			$response = $helpers->api_request( $request_params, $action_data );

			Automator()->complete_action( $user_id, $action_data, $recipe_id );

			return;
			
		} catch ( \Exception $e ) {
			$helpers->complete_with_error( $e->getMessage(), $user_id, $action_data, $recipe_id );
		}
	}
}
