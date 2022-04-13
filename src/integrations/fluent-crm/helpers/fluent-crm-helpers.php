<?php

namespace Uncanny_Automator;

use FluentCrm\App\Models\Lists;
use FluentCrm\App\Models\SubscriberPivot;
use FluentCrm\App\Models\Tag;
use Uncanny_Automator_Pro\Fluent_Crm_Pro_Helpers;

/**
 * Class Fluent_Crm_Helpers
 *
 * @package Uncanny_Automator
 */
class Fluent_Crm_Helpers {

	/**
	 * @var bool
	 */
	public static $has_run = false;

	/**
	 * @var Fluent_Crm_Helpers
	 */
	public $options;

	/**
	 * @var Fluent_Crm_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Wp_Fluent_Forms_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );

		$fluent_crm_targetted_actions = array(
			'fluentcrm_subscriber_status_to_subscribed',
			'fluentcrm_subscriber_status_to_pending',
			'fluentcrm_subscriber_status_to_unsubscribed',
			'fluentcrm_subscriber_status_to_bounced',
			'fluentcrm_subscriber_status_to_complained',
		);

		foreach ( $fluent_crm_targetted_actions as $status_action ) {
			add_action( $status_action, array( $this, 'do_fluent_crm_actions' ), 2, 99 );
		}

	}

	/**
	 * @param Fluent_Crm_Helpers $options
	 */
	public function setOptions( Fluent_Crm_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Fluent_Crm_Pro_Helpers $pro
	 */
	public function setPro( Fluent_Crm_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * Our callback function to attach the trigger 'automator_fluentcrm_status_update'.
	 *
	 * @param mixed $subscriber The accepted subscriber object from status_action.
	 * @param string $old_status The old status.
	 *
	 * @return void
	 */
	public function do_fluent_crm_actions( $subscriber, $old_status ) {
		// Make sure to only trigger once. For some reason, Fluent CRM is triggering this twice.
		if ( ! self::$has_run ) {
			do_action( 'automator_fluentcrm_status_update', $subscriber, $old_status );
			self::$has_run = true;
		}
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function fluent_crm_lists( $label = null, $option_code = 'FCRMLIST', $args = array() ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr_x( 'List', 'FluentCRM', 'uncanny-automator' );
		}

		$token                    = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax                  = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$is_any                   = key_exists( 'is_any', $args ) ? $args['is_any'] : false;
		$is_required              = key_exists( 'is_required', $args ) ? $args['is_required'] : true;
		$target_field             = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point                = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$supports_multiple_values = key_exists( 'supports_multiple_values', $args ) ? $args['supports_multiple_values'] : false;

		$options = array();

		if ( false !== $is_any ) {
			$options['0'] = esc_attr_x( 'Any list', 'FluentCRM', 'uncanny-automator' );
		}

		if ( Automator()->helpers->recipe->load_helpers ) {

			$lists = Lists::orderBy( 'title', 'DESC' )->get();

			if ( ! empty( $lists ) ) {
				foreach ( $lists as $list ) {

					$options[ $list->id ] = esc_html( $list->title );
				}
			}
		}

		$type = 'select';

		$option = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => $type,
			'supports_multiple_values' => $supports_multiple_values,
			'required'                 => $is_required,
			'supports_tokens'          => $token,
			'is_ajax'                  => $is_ajax,
			'fill_values_in'           => $target_field,
			'endpoint'                 => $end_point,
			'options'                  => $options,
		);

		return $option;

	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function fluent_crm_tags( $label = null, $option_code = 'FCRMTAG', $args = array() ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr_x( 'Tag', 'FluentCRM', 'uncanny-automator' );
		}

		$token                    = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax                  = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$is_required              = key_exists( 'is_required', $args ) ? $args['is_required'] : true;
		$is_any                   = key_exists( 'is_any', $args ) ? $args['is_any'] : false;
		$target_field             = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point                = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$supports_multiple_values = key_exists( 'supports_multiple_values', $args ) ? $args['supports_multiple_values'] : false;

		$options = array();

		if ( $is_any ) {
			$options['0'] = esc_attr_x( 'Any tag', 'FluentCRM', 'uncanny-automator' );
		}

		if ( Automator()->helpers->recipe->load_helpers ) {

			$tags = Tag::orderBy( 'title', 'DESC' )->get();

			if ( ! empty( $tags ) ) {
				foreach ( $tags as $tag ) {
					$options[ $tag->id ] = esc_html( $tag->title );
				}
			}
		}

		$type = 'select';

		$option = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => $type,
			'supports_multiple_values' => $supports_multiple_values,
			'required'                 => $is_required,
			'supports_tokens'          => $token,
			'is_ajax'                  => $is_ajax,
			'fill_values_in'           => $target_field,
			'endpoint'                 => $end_point,
			'options'                  => $options,
		);

		return $option;

	}

	/**
	 * @param null|array|string $to_match
	 * @param null $match_type
	 * @param null $recipes
	 * @param null $trigger_meta
	 * @param null $trigger_code
	 *
	 * @return array
	 */
	public function match_single_condition( $to_match = null, $match_type = 'int', $trigger_meta = null, $trigger_code = null ) {

		$recipe_ids = array();

		if (
			null === $to_match ||
			null === $trigger_meta ||
			null === $trigger_code
		) {
			// Sanity check
			return $recipe_ids;
		}

		$matched_recipe_ids = array();

		// Normalize $to_match as array
		if ( ! is_array( $to_match ) ) {
			$to_match = array( $to_match );
		}

		$recipes = Automator()->get->recipes_from_trigger_code( $trigger_code );

		foreach ( $recipes as $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				if ( key_exists( $trigger_meta, $trigger['meta'] ) ) {

					$trigger_value = $trigger['meta'][ $trigger_meta ];

					foreach ( $to_match as $match ) {

						switch ( $match_type ) {
							case 'int':
								$trigger_value = (int) $trigger_value;
								$match         = (int) $match;
								break;
							case 'text':
								$trigger_value = (string) $trigger_value;
								$match         = (string) $match;
								break;
							case 'absint':
								$trigger_value = absint( $trigger_value );
								$match         = absint( $match );
								break;
						}

						if (
							$trigger_value === $match ||
							0 === $trigger_value || // handle "any" selection
							'0' === $trigger_value // handle "any" selection
						) {
							$matched_recipe_ids[] = (object) array(
								'recipe_id'     => $recipe['ID'],
								'trigger_value' => $trigger_value,
								'matched_value' => $to_match,
							);
							break;
						}
					}//end foreach
				}//end if
			}//end foreach
		}//end foreach

		return $matched_recipe_ids;
	}

	/**
	 * @param $subscriber
	 *
	 * @return int
	 */
	public function get_subscriber_user_id( $subscriber ) {

		/*
		 * The user can either get on a list by an admin
		 * or then can fill out a form with their own email.
		 *
		 * We do not trigger if the logged in user adds an email(to a form)
		 * that is not their own unless they are an admin adding it on behalf
		 * of the user
		 *
		 * If there is no wp user associated with the subscriber, we use the subscriber
		 * email to check if a wp user has the same email.
		 *
		 */

		$user_id = 0;

		// Get the user ID
		if ( absint( $subscriber->user_id ) !== 0 ) {
			// Subscriber already has an ID associated with them
			$user_id = $subscriber->user_id;
		} elseif ( ! empty( $subscriber->email ) ) {

			// Lets see if a WP user has an email associated with them
			$user = get_user_by_email( $subscriber->email );

			if ( false !== $user ) {
				// A user was found with the subscriber email
				$user_id = $user->ID;
			}
		}

		// Just return the user id if the user is not logged in.
		if ( ! is_user_logged_in() ) {
			return $user_id;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			if ( $user_id !== get_current_user_id() ) {
				// The user is not an admin and subscriber added to a list is not the current user
				return 0;
			}
		}

		return $user_id;

	}

	/**
	 * @param $attachedListIds
	 *
	 * @return array
	 */
	public function get_attached_list_ids( $attachedListIds ) {

		/*
		 * This action is triggered by three different processes and returns either list ids
		 * or pivot ids(table: wp_fc_subscriber_pivot)
		 */
		$list_ids     = array();
		$request_type = automator_filter_input( 'type', INPUT_POST );

		if ( ! empty( $request_type ) ) {
			// the $attachedListIds are actually pivot IDs
			$pivots = SubscriberPivot::whereIn( 'id', $attachedListIds )->get();
			if ( ! empty( $pivots ) ) {
				foreach ( $pivots as $pivot ) {
					$list_ids[] = $pivot->object_id;
				}
			}
		} else {
			$list_ids = $attachedListIds;
		}

		return $list_ids;
	}

	/**
	 * @param $attachedTagIds
	 *
	 * @return array
	 */
	public function get_attached_tag_ids( $attachedTagIds ) {

		/*
		 * This action is triggered by three different processes and returns either list ids
		 * or pivot ids(table: wp_fc_subscriber_pivot)
		 */
		$list_ids = array();

		// Just check to see if the user is logged in or not.
		if ( ! is_user_logged_in() ) {
			$list_ids = $attachedTagIds;
		}

		$request_type = automator_filter_input( 'type', INPUT_POST );

		if ( ! empty( $request_type ) ) {
			// the $attachedListIds are actually pivot IDs
			$pivots = SubscriberPivot::whereIn( 'id', $attachedTagIds )->get();
			if ( ! empty( $pivots ) ) {
				foreach ( $pivots as $pivot ) {
					$list_ids[] = $pivot->object_id;
				}
			}
		} else {
			$list_ids = $attachedTagIds;
		}

		return $list_ids;
	}

	/**
	 * Get all formatted statuses.
	 *
	 * @return array The list of subscribers statuses.
	 */
	public function get_subscriber_statuses( $any = true ) {

		if ( ! function_exists( 'fluentcrm_subscriber_statuses' ) ) {
			return array();
		}

		$statuses = fluentcrm_subscriber_statuses();

		$formatted_statues = array();

		$trans_maps = array(
			'subscribed'   => __( 'Subscribed', 'uncanny-automator' ),
			'pending'      => __( 'Pending', 'uncanny-automator' ),
			'unsubscribed' => __( 'Unsubscribed', 'uncanny-automator' ),
			'bounced'      => __( 'Bounced', 'uncanny-automator' ),
			'complained'   => __( 'Complained', 'uncanny-automator' ),
		);

		if ( true === $any ) {
			$formatted_statues['-1'] = esc_html__( 'Any status', 'uncanny-automator' );
		}

		foreach ( $statuses as $status ) {
			$formatted_statues[ $status ] = isset( $trans_maps[ $status ] ) ? $trans_maps[ $status ] : ucfirst( $status );
		}

		return $formatted_statues;

	}

	/**
	 * Add the wp user as a FluentCrm contact.
	 *
	 * @param object $user The WordPress user object returned by function get_userdata.
	 *
	 * @return mixed Returns false if not successful. Otherwise instance of \FluentCrm\App\Models\Subscriber.
	 */
	public function add_user_as_contact( $user, $tags = array(), $list = array() ) {

		if ( ! function_exists( 'FluentCrmApi' ) ) {
			return 0;
		}

		$contact_api = FluentCrmApi( 'contacts' );

		$data = array(
			'first_name' => isset( $user->first_name ) ? sanitize_text_field( $user->first_name ) : '',
			'last_name'  => isset( $user->last_name ) ? sanitize_text_field( $user->last_name ) : '',
			'email'      => $user->user_email,
			'status'     => 'subscribe',
		);

		// Update the tags if argument is supplied.
		if ( ! empty( $tags ) ) {
			$data['tags'] = $tags;
		}

		// Update the list if argument is supplied.
		if ( ! empty( $list ) ) {
			$data['lists'] = $list;
		}

		$contact = $contact_api->createOrUpdate( $data );

		return $contact;

	}

	/**
	 * @return array
	 */
	public function get_custom_field() {
		$custom_fields = fluentcrm_get_custom_contact_fields();

		$field_types = array(
			'text'         => 'text',
			'textarea'     => 'textarea',
			'checkbox'     => 'checkbox',
			'radio'        => 'radio',
			'date'         => 'date',
			'date_time'    => 'text',
			'select-multi' => 'select',
			'select-one'   => 'select',
			'number'       => 'int',
		);

		$placeholder = array(
			'date_time' => esc_html__( 'yyyy-mm-dd hh:mm:ss', 'uncanny-automator' ),
		);

		$fields = array();
		foreach ( $custom_fields as $k => $custom_field ) {
			$options                  = null;
			$supports_multiple_values = false;
			if ( 'select-multi' === $custom_field['type'] ) {
				$supports_multiple_values = true;
			}

			if (
				'select-multi' === $custom_field['type'] ||
				'select-one' === $custom_field['type'] ||
				'radio' === $custom_field['type']
			) {
				$options = array();
				foreach ( $custom_field['options'] as $option ) {
					$options[ $option ] = $option;
				}
			}

			if ( 'checkbox' === $custom_field['type'] ) {
				foreach ( $custom_field['options'] as $option ) {
					$fields[] = array(
						'input_type'  => $field_types[ $custom_field['type'] ],
						'option_code' => 'FLUENTCRM_CUSTOMFIELD_' . $k . '_' . $option,
						'options'     => $option,
						'required'    => false,
						'label'       => $custom_field['label'] . ' - ' . $option,
					);
				}
			} else {
				$fields[] = array(
					'input_type'               => $field_types[ $custom_field['type'] ],
					'option_code'              => 'FLUENTCRM_CUSTOMFIELD_' . $k,
					'options'                  => $options,
					'required'                 => false,
					'label'                    => $custom_field['label'],
					'supports_token'           => true,
					'placeholder'              => isset( $placeholder[ $custom_field['type'] ] ) ? $placeholder[ $custom_field['type'] ] : '',
					'supports_multiple_values' => $supports_multiple_values,
					'supports_custom_value'    => false,
				);
			}
		}

		return $fields;
	}
}
