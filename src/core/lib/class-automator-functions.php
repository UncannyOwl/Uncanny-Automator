<?php

namespace Uncanny_Automator;

/**
 * Class Automator_Functions
 * 
 * Development ready functions.
 *
 * @package Uncanny_Automator
 */
class Automator_Functions {

	/**
	 * @var
	 */
	public static $instance;
	/**
	 * Composite Class of integration, trigger, action, and closure registration functions
	 *
	 * @since    1.0.0
	 * @access   public
	 * @var Automator_Registration
	 */
	public $register;
	/**
	 * Collection of all recipe types
	 *
	 * @since    2.0.0
	 * @access   public
	 */
	public $recipe_types = array( 'user', 'anonymous' );
	/**
	 * Collection of all integrations
	 *
	 * @since    1.0.0
	 * @access   public
	 */
	public $integrations = array();
	/**
	 * Collection of all triggers
	 *
	 * @since    1.0.0
	 * @access   public
	 */
	public $triggers = array();
	/**
	 * Collection of all actions
	 *
	 * @since    1.0.0
	 * @access   public
	 */
	public $actions = array();
	/**
	 * Collection of all closures
	 *
	 * @since    1.0.0
	 * @access   public
	 */
	public $closures = array();
	/**
	 * Triggers and actions for each recipe with data
	 *
	 * @since    1.0.0
	 * @access   public
	 */
	public $recipes_data = array();
	/**
	 * Collection of all localized strings
	 *
	 * @since    1.0.0
	 * @access   public
	 */
	public $i18n = array();
	/**
	 * @since    2.1
	 * @access   public
	 * @var Automator_Recipe_Process
	 */
	public $process;
	/**
	 *
	 * @since    2.1
	 * @access   public
	 * @var Automator_Recipe_Process_Complete
	 */
	public $complete;
	/**
	 * Composite Class of pre-defined Automator helper functions
	 *
	 * @since    2.1.0
	 * @access   public
	 * @var Automator_Helpers
	 */
	public $helpers;
	/**
	 * Composite Class of pre-defined Automator utilities
	 *
	 * @since    1.0.0
	 * @access   public
	 * @var Automator_Utilities
	 */
	public $utilities;
	/**
	 * Composite Class of data collection functions
	 *
	 * @since    1.0.0
	 * @access   public
	 * @var Automator_Get_Data
	 */
	public $get;
	/**
	 * Composite Class of pre-defined Automator tokens
	 *
	 * @since    1.0.0
	 * @access   public
	 */
	public $tokens;
	/**
	 * Composite Class that checks plugin status
	 *
	 * @since    1.0.0
	 * @access   public
	 */
	public $plugin_status;
	/**
	 * Composite Class that returns common error messages
	 *
	 * @since    1.0.0
	 * @access   public
	 */
	public $error_message;
	/**
	 * Composite Class that returns an input that needs to have tokens replaced
	 *
	 * @since    1.0.0
	 * @access   public
	 */
	public $parse;
	/**
	 * Collection of all Automator Email Variables
	 *
	 * @since    1.0.0
	 * @access   public
	 */
	public $defined_variables;

	/**
	 * System report
	 *
	 * @since    3.0.0
	 * @access   public
	 * @var Automator_System_Report
	 */
	public $system_report;

	/**
	 * @var Automator_WP_Error
	 */
	public $error;

	/**
	 * @var Automator_WP_Error
	 */
	public $exception;

	/**
	 * @var Automator_DB_Handler
	 */
	public $db;

	/**
	 * @var Automator_Cache_Handler
	 */
	public $cache;

	/**
	 * @var Automator_Send_Webhook
	 */
	public $send_webhook;

	/**
	 * Initializes all development helper classes and variables via class composition
	 */
	public function __construct() {
		// Automator Cache Handler
		require_once __DIR__ . '/helpers/class-automator-cache-handler.php';
		$this->cache = Automator_Cache_Handler::get_instance();

		// Automator DB Handler
		require_once __DIR__ . '/utilities/db/class-automator-db-handler-tokens.php';
		require_once __DIR__ . '/utilities/db/class-automator-db-handler-closures.php';
		require_once __DIR__ . '/utilities/db/class-automator-db-handler-actions.php';
		require_once __DIR__ . '/utilities/db/class-automator-db-handler-triggers.php';
		require_once __DIR__ . '/utilities/db/class-automator-db-handler-recipes.php';
		require_once __DIR__ . '/utilities/db/class-automator-db-handler.php';
		$this->db = Automator_DB_Handler::get_instance();

		// Automator WP_Error Handler
		require_once __DIR__ . '/utilities/error/class-automator-wp-error.php';
		$this->error = Automator_WP_Error::get_instance();

		// Automator_Exception Handler
		require_once __DIR__ . '/utilities/error/class-automator-exception.php';
		$this->exception = Automator_Exception::get_instance();

		// Automator integration, trigger, action and closure registration
		require_once __DIR__ . '/utilities/class-automator-registration.php';
		$this->register = Automator_Registration::get_instance();

		// Automator integration, trigger, action and closure process
		require_once __DIR__ . '/process/class-automator-recipe-process.php';
		require_once __DIR__ . '/process/class-automator-recipe-process-user.php';
		//require_once __DIR__ . '/process/class-automator-recipe-process-anon.php';
		$this->process = Automator_Recipe_Process::get_instance();

		// Automator integration, trigger, action and closure process
		require_once __DIR__ . '/process/class-automator-recipe-process-complete.php';
		$this->complete = Automator_Recipe_Process_Complete::get_instance();

		// Load pre-defined options for triggers, actions, and closures
		require_once __DIR__ . '/helpers/class-automator-helpers.php';
		require_once __DIR__ . '/helpers/class-automator-email-helpers.php';
		require_once __DIR__ . '/helpers/class-automator-recipe-helpers.php';
		require_once __DIR__ . '/helpers/class-automator-recipe-helpers-field.php';
		require_once __DIR__ . '/helpers/class-automator-trigger-condition-helpers.php';
		$this->helpers = Automator_Helpers::get_instance();

		// Load plugin status checks
		require_once __DIR__ . '/utilities/class-automator-integrations-status.php';
		$this->plugin_status = Automator_Integrations_Status::get_instance();

		// Load plugin status checks
		require_once __DIR__ . '/utilities/error/class-automator-error-messages.php';
		$this->error_message = Automator_Error_Messages::get_instance();

		// Load plugin status checks
		require_once UA_ABSPATH . 'src/core/lib/recipe-parts/trait-tokens.php';
		require_once __DIR__ . '/recipe-parts/tokens/class-automator-tokens.php';
		$this->tokens = Automator_Tokens::get_instance();

		// Load plugin status checks
		require_once __DIR__ . '/utilities/class-automator-input-parser.php';
		$this->parse = Automator_Input_Parser::get_instance();

		// Load plugin translated strings
		require_once __DIR__ . '/utilities/class-automator-translations.php';
		$this->i18n = Automator_Translations::get_instance();

		// Load plugin translated strings
		require_once __DIR__ . '/utilities/class-automator-utilities.php';
		$this->utilities = Automator_Utilities::get_instance();

		// Load plugin translated strings
		require_once __DIR__ . '/utilities/class-automator-get-data.php';
		$this->get = Automator_Get_Data::get_instance();

		// Load System report
		require_once __DIR__ . '/utilities/class-automator-system-report.php';
		$this->system_report = Automator_System_Report::get_instance();

		// Load Webhook files
		require_once __DIR__ . '/webhooks/class-automator-send-webhook.php';
		$this->send_webhook = Automator_Send_Webhook::get_instance();
	}

	/**
	 * @return \Uncanny_Automator\Automator_Functions
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * @param $integration_code
	 * @param $integration
	 */
	public function set_integrations( $integration_code, $integration ) {
		$this->integrations[ $integration_code ] = $integration;
	}

	/**
	 * @param $trigger
	 */
	public function set_triggers( $trigger ) {
		$this->triggers[] = $trigger;
	}

	/**
	 * @param $action
	 */
	public function set_actions( $action ) {
		$this->actions[] = $action;
	}

	/**
	 * @param $closure
	 */
	public function set_closures( $closure ) {
		$this->closures[] = $closure;
	}

	/**
	 * @param $recipe_type
	 * @param $details
	 */
	public function set_recipe_type( $recipe_type, $details ) {
		$this->recipe_types[ $recipe_type ] = $details;
	}

	/**
	 * Returns a filtered set on automator integrations
	 *
	 * @return array
	 */
	public function get_integrations() {
		$this->integrations = apply_filters_deprecated( 'uap_integrations', array( $this->integrations ), '3.0', 'automator_integrations' );

		return apply_filters( 'automator_integrations', $this->integrations );
	}

	/**
	 * Returns a filtered set on automator triggers
	 *
	 * @return array
	 */
	public function get_triggers() {
		$this->triggers = apply_filters_deprecated( 'uap_triggers', array( $this->triggers ), '3.0', 'automator_triggers' );

		return apply_filters( 'automator_triggers', $this->triggers );
	}

	/**
	 * Returns a filtered set on automator actions
	 *
	 * @return array
	 */
	public function get_actions() {
		$this->actions = apply_filters_deprecated( 'uap_actions', array( $this->actions ), '3.0', 'automator_actions' );

		return apply_filters( 'automator_actions', $this->actions );
	}

	/**
	 * Returns a filtered set on automator closures
	 *
	 * @return array
	 */
	public function get_closures() {
		$this->closures = apply_filters_deprecated( 'uap_closures', array( $this->closures ), '3.0', 'automator_closures' );

		return apply_filters( 'automator_closures', $this->closures );
	}

	/**
	 * Returns a recipe types for automator
	 *
	 * @return array
	 */
	public function get_recipe_types() {
		$this->recipe_types = apply_filters_deprecated( 'uap_recipe_types', array( $this->recipe_types ), '3.0', 'automator_recipe_types' );

		$this->recipe_types = apply_filters( 'automator_recipe_types', $this->recipe_types );

		return $this->recipe_types;
	}

	/**
	 * @param $code
	 *
	 * @return mixed
	 */
	public function get_author_name( $code = '' ) {
		if ( ! empty( $code ) ) {
			$code   = strtolower( $code );
			$filter = "automator_{$code}_author_name";
		} else {
			$filter = 'automator_author_name';
		}

		return apply_filters( $filter, 'Uncanny Owl' );
	}

	/**
	 * @param $code
	 * @param $link
	 *
	 * @return mixed
	 */
	public function get_author_support_link( $code = '', $link = '' ) {
		$url = 'https://automatorplugin.com/';
		if ( ! empty( $code ) ) {
			$code   = strtolower( $code );
			$filter = "automator_{$code}_author_support_link";
		} else {
			$filter = 'automator_author_support_link';
		}
		if ( ! empty( $link ) ) {
			$url .= $link;
		}

		return apply_filters( $filter, $url );
	}

	/**
	 * Get data for all recipe objects
	 *
	 * @param $force_new_data_load
	 * @param null $recipe_id
	 *
	 * @return array
	 */
	public function get_recipes_data( $force_new_data_load = false, $recipe_id = null ) {
		if ( ( false === $force_new_data_load ) && ! empty( $this->recipes_data ) && null === $recipe_id ) {
			return $this->recipes_data;
		}

		$recipes_data = Automator()->cache->get( $this->cache->recipes_data );
		if ( ! empty( $recipes_data ) && false === $force_new_data_load && null === $recipe_id ) {
			return $recipes_data;
		}

		// Accidentally sent recipe post instead of id?
		if ( $recipe_id instanceof \WP_Post && 'uo-recipe' === (string) $recipe_id->post_type ) {
			$recipe_id = $recipe_id->ID;
		}

		if ( null !== $recipe_id && is_numeric( $recipe_id ) ) {
			return $this->get_recipe_data_by_recipe_id( $recipe_id );
		}

		global $wpdb;

		$recipes = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_type, post_status, post_parent FROM $wpdb->posts WHERE post_type = %s AND post_status NOT LIKE %s ORDER BY ID DESC LIMIT 0, 99999", 'uo-recipe', 'trash' ) );
		if ( empty( $recipes ) ) {
			return array();
		}
		$cached = Automator()->cache->get( 'get_recipe_type' );
		//Extract Recipe IDs
		$recipe_ids = array_column( (array) $recipes, 'ID' );
		//Collective array of recipes triggers, actions, closures
		$recipe_data = $this->pre_fetch_recipe_metas( $recipes );
		//Collective array of users recipes completed status
		$recipes_completed = $this->are_recipes_completed( null, $recipe_ids );
		$recipes_completed = empty( $recipes_completed ) ? array() : $recipes_completed;

		foreach ( $recipes as $recipe ) {
			$recipe_id = $recipe->ID;
			if ( array_key_exists( $recipe_id, $recipe_data ) && is_array( $recipe_data[ $recipe_id ] ) && array_key_exists( 'triggers', $recipe_data[ $recipe_id ] ) ) {
				if ( $recipe_data[ $recipe_id ]['triggers'] ) {
					//Grab tokens for each of trigger
					foreach ( $recipe_data[ $recipe_id ]['triggers'] as $t_id => $tr ) {
						$tokens = $this->tokens->trigger_tokens( $tr['meta'], $recipe_id );

						$recipe_data[ $recipe_id ]['triggers'][ $t_id ]['tokens'] = $tokens;
					}
				}
				$triggers = $recipe_data[ $recipe_id ]['triggers'];
			} else {
				$triggers = array();
			}

			$this->recipes_data[ $recipe_id ]['ID']          = $recipe_id;
			$this->recipes_data[ $recipe_id ]['post_status'] = $recipe->post_status;
			$this->recipes_data[ $recipe_id ]['recipe_type'] = isset( $cached[ $recipe_id ] ) ? $cached[ $recipe_id ] : Automator()->utilities->get_recipe_type( $recipe_id );

			$this->recipes_data[ $recipe_id ]['triggers'] = $triggers;

			if ( array_key_exists( $recipe_id, $recipe_data ) && is_array( $recipe_data[ $recipe_id ] ) && array_key_exists( 'actions', $recipe_data[ $recipe_id ] ) ) {
				$actions = $recipe_data[ $recipe_id ]['actions'];
			} else {
				$actions = array();
			}
			$this->recipes_data[ $recipe_id ]['actions'] = $actions;

			if ( array_key_exists( $recipe_id, $recipe_data ) && is_array( $recipe_data[ $recipe_id ] ) && array_key_exists( 'closures', $recipe_data[ $recipe_id ] ) ) {
				$closures = $recipe_data[ $recipe_id ]['closures'];
			} else {
				$closures = array();
			}
			$this->recipes_data[ $recipe_id ]['closures'] = $closures;

			$this->recipes_data[ $recipe_id ]['completed_by_current_user'] = array_key_exists( $recipe_id, $recipes_completed ) ? $recipes_completed[ $recipe_id ] : false;
		}

		$this->recipes_data = apply_filters( 'automator_get_recipes_data', $this->recipes_data, $recipe_id );

		Automator()->cache->set( $this->cache->recipes_data, $this->recipes_data );

		return $this->recipes_data;
	}

	/**
	 * @param null $recipe_id
	 *
	 * @return array
	 */
	public function get_recipe_data_by_recipe_id( $recipe_id = null ) {
		if ( null === $recipe_id ) {
			return array();
		}
		$key    = 'automator_recipe_data_of_' . $recipe_id;
		$recipe = Automator()->cache->get( $key );
		if ( ! empty( $recipe ) ) {
			return $recipe;
		}
		$recipe  = array();
		$recipes = get_post( $recipe_id );
		if ( ! $recipes ) {
			return array();
		}
		$cached = Automator()->cache->get( 'get_recipe_type' );

		$is_recipe_completed           = $this->is_recipe_completed( $recipe_id );
		$key                           = $recipe_id;
		$recipe[ $key ]['ID']          = $recipe_id;
		$recipe[ $key ]['post_status'] = $recipes->post_status;
		$recipe[ $key ]['recipe_type'] = isset( $cached[ $recipe_id ] ) ? $cached[ $recipe_id ] : $this->utilities->get_recipe_type( $recipe_id );

		$triggers_array             = array();
		$triggers                   = $this->get_recipe_data( 'uo-trigger', $recipe_id, $triggers_array );
		$recipe[ $key ]['triggers'] = $triggers;

		$action_array              = array();
		$actions                   = $this->get_recipe_data( 'uo-action', $recipe_id, $action_array );
		$recipe[ $key ]['actions'] = $actions;

		$closure_array              = array();
		$closures                   = $this->get_recipe_data( 'uo-closure', $recipe_id, $closure_array );
		$recipe[ $key ]['closures'] = $closures;

		$recipe[ $key ]['completed_by_current_user'] = $is_recipe_completed;

		$recipe[ $key ]['extra_options'] = $this->load_extra_options( $recipe[ $key ] );

		$recipe = apply_filters( 'automator_get_recipe_data_by_recipe_id', $recipe, $key );

		Automator()->cache->set( $key, $recipe );

		return $recipe;
	}

	/**
	 * load_extra_options
	 *
	 * @param mixed $type
	 * @param mixed $item_code
	 *
	 * @return void
	 */
	public function load_extra_options( $recipe ) {

		// Get the extra options meta. This one should only exists during REST calls. In all other cases, this meta should nor exist
		$extra_options_meta = get_post_meta( $recipe['ID'], 'extra_options', true );

		// If the meta doesn't exist (initial recipe page load), replace it with an empty array
		$extra_options = empty( $extra_options_meta ) ? array() : $extra_options_meta;

		// We will loop through triggers and actions to see if any of them have extra optiosn to load
		$types_to_process = array( 'actions', 'triggers' );

		foreach ( $types_to_process as $type ) {
			foreach ( $recipe[ $type ] as $item ) {

				$item_code   = $item['meta']['code'];
				$integration = $item['meta']['integration'];

				// If extra options were already loaded for this item, bail
				if ( isset( $extra_options[ $integration ][ $item_code ] ) ) {
					continue;
				}

				// Otherwise, get the options callback from the integration definition
				if ( 'actions' === $type ) {
					$callback = Automator()->get->value_from_action_meta( $item_code, 'options_callback' );
				} elseif ( 'triggers' === $type ) {
					$callback = Automator()->get->value_from_trigger_meta( $item_code, 'options_callback' );
				}

				// If there is no callback found, bail
				if ( ! $callback ) {
					continue;
				}

				// If the callback is found, execute it
				$extra_options[ $integration ][ $item_code ] = call_user_func( $callback );
			}
		}

		// Store all the extra options in the post meta so that subsequent REST API calls won't need to load the options again
		update_post_meta( $recipe['ID'], 'extra_options', $extra_options );

		return $extra_options;
	}

	/**
	 * @param array $recipes
	 *
	 * @return array
	 */
	public function pre_fetch_recipe_metas( $recipes = array() ) {
		$metas    = array();
		$triggers = array();
		$actions  = array();
		$closures = array();
		if ( ! empty( $recipes ) ) {

			global $wpdb;
			// Fetch uo-trigger, uo-action, uo-closure.
			$recipe_children = $wpdb->get_results( "SELECT ID, post_status, post_type, menu_order FROM $wpdb->posts WHERE post_parent IN (SELECT ID FROM $wpdb->posts WHERE post_type = 'uo-recipe')" );

			if ( $recipe_children ) {
				foreach ( $recipe_children as $p ) {
					$child_id = $p->ID;
					$p_t      = $p->post_type;
					$p_s      = $p->post_status;
					$m_o      = $p->menu_order;
					switch ( $p_t ) {
						case 'uo-trigger':
							$triggers[ $child_id ] = array(
								'ID'          => $child_id,
								'post_status' => $p_s,
								'menu_order'  => $m_o,
							);
							break;
						case 'uo-action':
							$actions[ $child_id ] = array(
								'ID'          => $child_id,
								'post_status' => $p_s,
								'menu_order'  => $m_o,
							);
							break;
						case 'uo-closure':
							$closures[ $child_id ] = array(
								'ID'          => $child_id,
								'post_status' => $p_s,
								'menu_order'  => $m_o,
							);
							break;
					}
				}
			}

			// Fetch metas for uo-trigger, uo-action, uo-closure
			$related_metas = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT pm.post_id, pm.meta_key, pm.meta_value, p.post_parent, p.post_type, p.menu_order
FROM $wpdb->postmeta pm
    LEFT JOIN $wpdb->posts p
        ON p.ID = pm.post_id
WHERE pm.post_id
          IN (SELECT ID FROM $wpdb->posts WHERE post_parent IN (SELECT ID FROM $wpdb->posts WHERE post_type = %s))", 'uo-recipe'
				)
			);

			if ( $related_metas ) {
				foreach ( $related_metas as $p ) {
					$child_id = $p->post_id;
					$m_k      = $p->meta_key;
					$m_v      = $p->meta_value;
					if ( array_key_exists( $child_id, $triggers ) ) {
						$triggers[ $child_id ]['meta'][ $m_k ] = $m_v;
					} elseif ( array_key_exists( $child_id, $actions ) ) {
						$actions[ $child_id ]['meta'][ $m_k ] = $m_v;
					} elseif ( array_key_exists( $child_id, $closures ) ) {
						$closures[ $child_id ]['meta'][ $m_k ] = $m_v;
					}
				}
			}
			//Fix missing metas!
			if ( $triggers ) {
				foreach ( $triggers as $trigger_id => $array ) {
					if ( ! array_key_exists( 'meta', $array ) ) {
						$triggers[ $trigger_id ]['meta'] = array( 'code' => '' );
					} else {
						//Attempt to return Trigger ID for magic button
						foreach ( $array['meta'] as $mk => $mv ) {
							if ( 'code' === (string) trim( $mk ) && 'WPMAGICBUTTON' === (string) trim( $mv ) ) {
								$triggers[ $trigger_id ]['meta']['WPMAGICBUTTON'] = $trigger_id;
							}
						}
					}
				}
			}

			//Build old recipe array style
			foreach ( $related_metas as $r ) {
				$recipe_id     = absint( $r->post_parent );
				$non_recipe_id = absint( $r->post_id );
				switch ( $r->post_type ) {
					case 'uo-trigger':
						if ( array_key_exists( $non_recipe_id, $triggers ) ) {
							$metas[ $recipe_id ]['triggers'][] = $triggers[ $non_recipe_id ];
							unset( $triggers[ $non_recipe_id ] );
						}
						break;
					case 'uo-action':
						if ( array_key_exists( $non_recipe_id, $actions ) ) {
							$metas[ $recipe_id ]['actions'][] = $actions[ $non_recipe_id ];
							unset( $actions[ $non_recipe_id ] );
						}
						break;
					case 'uo-closure':
						if ( array_key_exists( $non_recipe_id, $closures ) ) {
							$metas[ $recipe_id ]['closures'][] = $closures[ $non_recipe_id ];
							unset( $closures[ $non_recipe_id ] );
						}
						break;
				}
			}
		}

		return $metas;
	}

	/**
	 * Check if the recipe was completed
	 *
	 * @param null $user_id
	 * @param $recipe_ids
	 *
	 * @return array
	 */
	public function are_recipes_completed( $user_id = null, $recipe_ids = array() ) {

		if ( empty( $recipe_ids ) ) {
			Automator()->error->trigger( 'You are trying to check if a recipe is completed without providing a recipe_ids.' );

			return null;
		}

		// Set user ID
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		// No user id is available.
		if ( 0 === $user_id ) {
			Automator()->error->trigger( 'You are trying to check if a recipe is completed when a there is no logged in user.' );

			return null;
		}

		$completed = array();
		global $wpdb;
		$results = $wpdb->get_results(
			$wpdb->prepare( "SELECT COUNT(completed) AS completed, automator_recipe_id FROM {$wpdb->prefix}uap_recipe_log WHERE user_id = %d AND automator_recipe_id IN (" . join( ',', $recipe_ids ) . ') AND completed = 1 GROUP BY automator_recipe_id', $user_id ) //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		if ( $results ) {
			foreach ( $recipe_ids as $recipe_id ) {
				$complete = 0;
				$found    = false;
				foreach ( $results as $r ) {
					if ( $recipe_id === (int) $r->automator_recipe_id ) {
						$found    = true;
						$complete = $r->completed;
						break;
					} else {
						$found = false;
					}
				}

				if ( $found ) {
					$completed[ $recipe_id ] = $complete;
				} else {
					$completed[ $recipe_id ] = 0;
				}
			}
		} else {
			//Fallback to mark every recipe incomplete
			foreach ( $recipe_ids as $recipe_id ) {
				$completed[ $recipe_id ] = 0;
			}
		}

		return $this->utilities->recipes_number_times_completed( $recipe_ids, $completed );
	}

	/**
	 * Check if the recipe was completed
	 *
	 * @param null $recipe_id
	 * @param null $user_id
	 *
	 * @return null|bool
	 */
	public function is_recipe_completed( $recipe_id = null, $user_id = null ) {

		if ( null === $recipe_id || ! is_numeric( $recipe_id ) ) {
			Automator()->error->trigger( 'You are trying to check if a recipe is completed without providing a recipe_id.' );

			return null;
		}

		/**
		 * If recipe is completed maximum number of times, bail.
		 *
		 * @since 3.0
		 */
		if ( $this->is_recipe_completed_max_times( $recipe_id ) ) {
			return true;
		}

		if ( ! is_user_logged_in() ) {
			return null;
		}

		// Set user ID
		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		$results = $this->user_completed_recipe_number_times( $recipe_id, $user_id );

		return $this->utilities->recipe_number_times_completed( $recipe_id, $results );
	}

	/**
	 * @param $recipe_id
	 * @param $user_id
	 *
	 * @return false|int|string
	 */
	public function user_completed_recipe_number_times( $recipe_id, $user_id ) {
		global $wpdb;
		$results = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(completed) AS num_times_completed
						FROM {$wpdb->prefix}uap_recipe_log
						WHERE 1=1
						AND user_id = %d
						AND automator_recipe_id = %d
						AND completed = 1",
				$user_id,
				$recipe_id
			)
		);

		if ( 0 === $results ) {
			return false;
		}

		return empty( $results ) ? 0 : $results;
	}

	/**
	 * @param null $recipe_id
	 *
	 * @return bool|null
	 */
	public function is_recipe_completed_max_times( $recipe_id = null ) {

		if ( null === $recipe_id || ! is_numeric( $recipe_id ) ) {
			Automator()->error->add_error( 'is_recipe_completed', 'ERROR: You are trying to check if a recipe is completed without providing a recipe_id.', $this );

			return null;
		}

		global $wpdb;
		$results = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(completed) AS num_times_completed
						FROM {$wpdb->prefix}uap_recipe_log
						WHERE 1=1
						AND automator_recipe_id = %d
						AND completed = 1",
				$recipe_id
			)
		);

		if ( 0 === $results ) {
			return false;
		}
		$results = empty( $results ) ? 0 : $results;

		return $this->utilities->recipe_max_times_completed( $recipe_id, $results );
	}

	/**
	 * @param $recipe_id
	 * @param $type
	 *
	 * @return array|object|null
	 */
	public function get_recipe_children_query( $recipe_id, $type ) {
		global $wpdb;
		$q = $wpdb->prepare( "SELECT ID, post_status, menu_order FROM $wpdb->posts WHERE post_parent = %d AND post_type = %s", $recipe_id, $type );
		if ( 'uo-action' === $type ) {
			$q = "$q ORDER BY menu_order ASC";
		}
		$q = apply_filters_deprecated(
			'q_get_recipe_data',
			array(
				$q,
				$recipe_id,
				$type,
			),
			'3.0',
			'automator_get_recipe_data_query'
		);
		$q = apply_filters( 'automator_get_recipe_data_query', $q, $recipe_id, $type );

		return $wpdb->get_results( $q, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get saved data for recipe actions or triggers
	 *
	 * @param null $type
	 * @param null $recipe_id
	 * @param array $recipe_children
	 *
	 * @return null
	 */
	public function get_recipe_data( $type = null, $recipe_id = null, $recipe_children = array() ) {

		if ( null === $type ) {
			return null;
		}

		if ( ! in_array( $type, array( 'uo-trigger', 'uo-action', 'uo-closure' ), true ) ) {
			return null;
		}

		if ( ! is_numeric( $recipe_id ) ) {
			Automator()->error->trigger( 'You are trying to get recipe data without providing a recipe_id' );

			return null;
		}

		global $wpdb;
		if ( empty( $recipe_children ) ) {
			// All the triggers associated with the recipe
			$recipe_children = $this->get_recipe_children_query( $recipe_id, $type );
		}
		// All data for recipe triggers
		$recipe_children_data = array();
		if ( empty( $recipe_children ) ) {
			return $recipe_children_data;
		}
		// Check each trigger for set values
		foreach ( $recipe_children as $key => $child ) {
			// Collect all meta data for this trigger
			if ( ! array_key_exists( 'meta', $child ) ) {
				$child_meta = get_post_custom( $child['ID'] );
			} else {
				$child_meta = $child['meta'];
			}

			if ( ! $child_meta ) {
				continue;
			}
			// Get post custom return an array for each meta_key as there maybe more than one value per key.. we only store and need one value
			$child_meta_single = array();
			foreach ( $child_meta as $meta_key => $meta_value ) {
				$child_meta_single[ $meta_key ] = reset( $meta_value );
			}
			$code = array_key_exists( 'code', $child_meta_single ) ? $child_meta_single['code'] : '';

			/** Fix to show MAGIC BUTTON ID
			 *
			 * @since 3.0
			 * @package Uncanny_Automator
			 */
			if ( 'WPMAGICBUTTON' === (string) $code && ! array_key_exists( 'WPMAGICBUTTON', $child_meta_single ) ) {
				$child_meta_single['WPMAGICBUTTON'] = $child['ID'];
			}

			$item_not_found = $this->child_item_not_found_handle( $type, $code );
			if ( $item_not_found ) {
				//$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->posts SET post_status = 'draft' WHERE ID = %d", absint( $child['ID'] ) ) );
				//$child['post_status'] = 'draft';
			}

			// The trigger is create/stored automatically but may not have been saved. Delete if not saved!
			if ( empty( $child_meta ) && isset( $child['ID'] ) ) {
				continue;
			}

			$recipe_children_data[ $key ]['ID']          = absint( $child['ID'] );
			$recipe_children_data[ $key ]['post_status'] = $child['post_status'];
			$recipe_children_data[ $key ]['meta']        = $child_meta_single;

			if ( ! empty( $child['menu_order'] ) ) {
				$recipe_children_data[ $key ]['menu_order'] = $child['menu_order'];
			}

			if ( 'uo-trigger' === $type ) {
				$recipe_children_data[ $key ]['tokens'] = $this->tokens->trigger_tokens( $child_meta_single, $recipe_id );
			}
		}

		return apply_filters(
			'automator_recipe_children_data',
			$recipe_children_data,
			array(
				'type'            => $type,
				'recipe_id'       => $recipe_id,
				'recipe_children' => $recipe_children,
			)
		);
	}

	/**
	 * @param $type
	 * @param $code
	 *
	 * @return bool
	 */
	public function child_item_not_found_handle( $type, $code ) {
		$item_not_found = true;

		if ( 'uo-trigger' === $type ) {
			$system_triggers = $this->get_triggers();
			if ( ! empty( $system_triggers ) ) {
				foreach ( $system_triggers as $trigger ) {
					if ( $trigger['code'] === $code ) {
						$item_not_found = false;
					}
				}
			} else {
				$item_not_found = false;
			}
		}

		if ( 'uo-action' === $type ) {
			$system_actions = $this->get_actions();
			if ( ! empty( $system_actions ) ) {
				foreach ( $system_actions as $action ) {
					if ( $action['code'] === $code ) {
						$item_not_found = false;
					}
				}
			} else {
				$item_not_found = false;
			}
		}

		if ( 'uo-closure' === $type ) {
			$system_closures = $this->get_closures();
			if ( ! empty( $system_closures ) ) {
				foreach ( $system_closures as $closure ) {
					if ( $closure['code'] === $code ) {
						$item_not_found = false;
					}
				}
			} else {
				$item_not_found = false;
			}
		}

		return $item_not_found;
	}

	/**
	 * Added this function to directly fetch trigger data instead of looping thru
	 * recipe and it's triggers for parsing. Specially needed for multi-trigger
	 * parsing
	 *
	 * @param $recipe_id
	 * @param $trigger_id
	 *
	 * @return array|mixed
	 * @since  2.9
	 * @author Saad S.
	 */
	public function get_trigger_data( $recipe_id = 0, $trigger_id = 0 ) {
		$recipe_data = $this->get_recipe_data( 'uo-trigger', $recipe_id );
		if ( ! $recipe_data ) {
			return array();
		}
		foreach ( $recipe_data as $trigger_data ) {
			if ( absint( $trigger_id ) !== absint( $trigger_data['ID'] ) ) {
				continue;
			}

			return $trigger_data;
		}

		return array();
	}

	/**
	 * Complete the action for the user
	 *
	 * @param null $user_id
	 * @param null $action_data
	 * @param null $recipe_id
	 * @param $error_message
	 * @param null $recipe_log_id
	 *
	 * @param $args
	 *
	 * @return null
	 * @deprecated 3.0
	 */
	public function complete_action( $user_id = null, $action_data = null, $recipe_id = null, $error_message = '', $recipe_log_id = null, $args = array() ) {
		if ( defined( 'AUTOMATOR_DEBUG_MODE' ) && true === AUTOMATOR_DEBUG_MODE ) {
			_doing_it_wrong( 'complete_actions', 'Please use Automator()->complete->action() instead.', 3.0 );
		}

		return $this->complete->action( $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args );
	}

	/**
	 * Complete a recipe
	 *
	 * @param $recipe_id     null||int
	 * @param $user_id       null||int
	 * @param $recipe_log_id null||int
	 *
	 * @param $args
	 *
	 * @return null|bool
	 * @deprecated 3.0
	 */
	public function complete_recipe( $recipe_id = null, $user_id = null, $recipe_log_id = null, $args = array() ) {
		if ( defined( 'AUTOMATOR_DEBUG_MODE' ) && true === AUTOMATOR_DEBUG_MODE ) {
			_doing_it_wrong( 'complete_recipe', 'Please use Automator()->complete->recipe() instead.', 3.0 );
		}

		return $this->complete->recipe( $recipe_id, $user_id, $recipe_log_id, $args );
	}

	/**
	 * Complete all actions in recipe
	 *
	 * @param null $recipe_id
	 * @param null $user_id
	 * @param null $recipe_log_id
	 *
	 * @param $args
	 *
	 * @return bool
	 * @deprecated 3.0
	 */
	public function complete_actions( $recipe_id = null, $user_id = null, $recipe_log_id = null, $args = array() ) {
		if ( defined( 'AUTOMATOR_DEBUG_MODE' ) && true === AUTOMATOR_DEBUG_MODE ) {
			_doing_it_wrong( 'complete_actions', 'Please use Automator()->complete->complete_actions() instead.', 3.0 );
		}

		return $this->complete->complete_actions( $recipe_id, $user_id, $recipe_log_id, $args );
	}

	/**
	 * Complete all closures in recipe
	 *
	 * @param null $recipe_id
	 * @param null $user_id
	 * @param null $recipe_log_id
	 * @param $args
	 *
	 * @return bool
	 * @deprecated 3.0
	 *
	 */
	public function complete_closures( $recipe_id = null, $user_id = null, $recipe_log_id = null, $args = array() ) {
		if ( defined( 'AUTOMATOR_DEBUG_MODE' ) && true === AUTOMATOR_DEBUG_MODE ) {
			_doing_it_wrong( 'complete_closures', 'Please use Automator()->complete->closures() instead.', 3.0 );
		}

		return $this->complete->closures( $recipe_id, $user_id, $recipe_log_id, $args );
	}

	/**
	 * Insert the trigger for the user
	 *
	 * @param $args
	 *
	 * @return null
	 * @deprecated 3.0
	 */
	public function insert_trigger_meta( $args ) {
		if ( defined( 'AUTOMATOR_DEBUG_MODE' ) && true === AUTOMATOR_DEBUG_MODE ) {
			_doing_it_wrong( 'insert_trigger_meta', 'Please use Automator()->process->user->insert_trigger_meta() instead.', 3.0 );
		}

		return $this->process->user->insert_trigger_meta( $args );
	}

	/**
	 *
	 * Complete a trigger once all validation & trigger entry added
	 * and number of times met, complete the trigger
	 *
	 * @param $args
	 *
	 * @return bool
	 * @deprecated 3.0 Automator()->process->user->maybe_trigger_complete
	 */
	public function maybe_trigger_complete( $args ) {
		if ( defined( 'AUTOMATOR_DEBUG_MODE' ) && true === AUTOMATOR_DEBUG_MODE ) {
			_doing_it_wrong( 'maybe_trigger_complete', 'Please use Automator()->process->user->maybe_trigger_complete() instead.', 3.0 );
		}

		return $this->process->user->maybe_trigger_complete( $args );
	}

	/**
	 * Complete the trigger for the user
	 *
	 * @param $args
	 *
	 * @return null
	 * @deprecated 3.0
	 */
	public function complete_trigger( $args = array() ) {
		if ( defined( 'AUTOMATOR_DEBUG_MODE' ) && true === AUTOMATOR_DEBUG_MODE ) {
			_doing_it_wrong( 'complete_trigger', 'Please use Automator()->complete->trigger() instead.', 3.0 );
		}

		return $this->complete->trigger( $args );
	}

	/**
	 *
	 * Matches recipes against trigger meta/code. If a recipe is found and not completed,
	 * add a trigger entry in to the DB and matches number of times.
	 *
	 * @param      $args
	 * @param $mark_trigger_complete
	 *
	 * @return array|bool|int|null
	 * @deprecated 3.0 Use Automator()->process->user->
	 */
	public function maybe_add_trigger_entry( $args, $mark_trigger_complete = true ) {
		if ( defined( 'AUTOMATOR_DEBUG_MODE' ) && true === AUTOMATOR_DEBUG_MODE ) {
			_doing_it_wrong( 'maybe_add_trigger_entry', 'Please use Automator()->process->user->maybe_add_trigger_entry() instead.', 3.0 );
		}

		return $this->process->user->maybe_add_trigger_entry( $args, $mark_trigger_complete );
	}


	/**
	 * @param $recipe_id
	 * @param $user_id
	 * @param $create_recipe
	 * @param $args
	 * @param $maybe_simulate
	 * @param null $maybe_add_log_id
	 *
	 * @return array
	 * @since  2.0
	 * @deprecated 3.0
	 * @author Saad S. on Nov 15th, 2019
	 *
	 * Added $maybe_simulate in order to avoid unnecessary recipe logs in database.
	 * It'll return existing $recipe_log_id if there's one for a user & recipe, or
	 * simulate an ID for the next run.. The reason for simulate is to avoid unnecessary
	 * recipe_logs in the database since we insert recipe log first & check if trigger
	 * is valid after which means, recipe log is added and not used in this run.
	 * Once trigger is validated.. I pass $maybe_simulate ID to $maybe_add_log_id
	 * and insert recipe log at this point.
	 *
	 */
	public function maybe_create_recipe_log_entry( $recipe_id, $user_id, $create_recipe = true, $args = array(), $maybe_simulate = false, $maybe_add_log_id = null ) {
		if ( defined( 'AUTOMATOR_DEBUG_MODE' ) && true === AUTOMATOR_DEBUG_MODE ) {
			_doing_it_wrong( 'maybe_create_recipe_log_entry', 'Please use Automator()->process->user->maybe_create_recipe_log_entry() instead.', 3.0 );
		}

		return $this->process->user->maybe_create_recipe_log_entry( $recipe_id, $user_id, $create_recipe, $args, $maybe_simulate, $maybe_add_log_id );
	}

	/**
	 *
	 * Record an entry in to DB against a trigger
	 *
	 * @param      $user_id
	 * @param      $trigger_id
	 * @param      $recipe_id
	 * @param null $recipe_log_id
	 *
	 * @return array
	 * @deprecated 3.0
	 */
	public function maybe_get_trigger_id( $user_id, $trigger_id, $recipe_id, $recipe_log_id = null ) {
		if ( defined( 'AUTOMATOR_DEBUG_MODE' ) && true === AUTOMATOR_DEBUG_MODE ) {
			_doing_it_wrong( 'maybe_get_trigger_id', 'Please use Automator()->process->user->maybe_get_trigger_id() instead.', 3.0 );
		}

		return $this->process->user->maybe_get_trigger_id( $user_id, $trigger_id, $recipe_id, $recipe_log_id );
	}

	/**
	 * @param        $data
	 * @param $type
	 *
	 * @return string
	 * @deprecated 3.0
	 */
	public function uap_sanitize( $data, $type = 'text' ) {
		if ( defined( 'AUTOMATOR_DEBUG_MODE' ) && true === AUTOMATOR_DEBUG_MODE ) {
			_doing_it_wrong( 'uap_sanitize', 'Please use Automator()->utilities->automator_sanitize() instead.', 3.0 );
		}

		return $this->utilities->automator_sanitize( $data, $type );
	}

	/**
	 * @param $args
	 * @param $check
	 *
	 * @return bool
	 */
	public function is_user_signed_in( $args ) {
		$is_signed_in = array_key_exists( 'is_signed_in', $args ) ? $args['is_signed_in'] : false;
		/**
		 * v3.9.1 or 3.10.
		 * Globally set `is_signed_in` to true if trigger type is "user"
		 */
		if ( isset( $args['code'] ) && false === $is_signed_in ) {
			$is_signed_in = Automator()->is_trigger_type_user( $args['code'] );
		}

		return true === $is_signed_in ? true : is_user_logged_in();
	}

	/**
	 * Register a new recipe type and creates a type if defined and the type does not exist
	 *
	 * @param null $recipe_type
	 * @param $recipe_details
	 *
	 * @return null|bool
	 * @deprecated v2.1 Automator()->register->recipe_type
	 * @use Automator()->register->recipe_type
	 */
	public function register_recipe_type( $recipe_type = null, $recipe_details = array() ) {
		if ( defined( 'AUTOMATOR_DEBUG_MODE' ) && true === AUTOMATOR_DEBUG_MODE ) {
			_doing_it_wrong( 'register_recipe_type', 'Please use Automator()->register->recipe_type instead.', 3.0 );
		}

		return $this->register->recipe_type( $recipe_type, $recipe_details );
	}

	/**
	 * Register a new trigger and creates a type if defined and the type does not exist
	 *
	 * @param $trigger
	 * @param $integration_code
	 * @param $integration
	 *
	 * @return null|bool
	 * @deprecated v2.1 Automator()->register->trigger
	 * @use Automator()->register->trigger
	 */
	public function register_trigger( $trigger = null, $integration_code = null, $integration = null ) {
		if ( defined( 'AUTOMATOR_DEBUG_MODE' ) && true === AUTOMATOR_DEBUG_MODE ) {
			_doing_it_wrong( 'register_trigger', 'Please use Automator()->register->trigger instead.', 3.0 );
		}

		return $this->register->trigger( $trigger, $integration_code, $integration );
	}

	/**
	 * Register a new uap action and creates a type if defined and the type does not exist
	 *
	 * @param $uap_action
	 * @param $integration_code
	 * @param $integration
	 *
	 * @return null|bool
	 * @deprecated v2.1 use Automator()->register->action()
	 * @use Automator()->register->action
	 */
	public function register_action( $uap_action = null, $integration_code = null, $integration = null ) {
		if ( defined( 'AUTOMATOR_DEBUG_MODE' ) && true === AUTOMATOR_DEBUG_MODE ) {
			_doing_it_wrong( 'register_action', 'Please use Automator()->register->action instead.', 3.0 );
		}

		return $this->register->action( $uap_action, $integration_code, $integration );
	}

	/**
	 * Registers a new closure and creates a type if defined and the type does not exist
	 *
	 * @param $closure
	 * @param $integration_code
	 * @param $integration
	 *
	 * @return null|bool
	 * @deprecated v2.1 Automator()->register->closure
	 * @use Automator()->register->closure
	 */
	public function register_closure( $closure = null, $integration_code = null, $integration = null ) {
		if ( defined( 'AUTOMATOR_DEBUG_MODE' ) && true === AUTOMATOR_DEBUG_MODE ) {
			_doing_it_wrong( 'register_closure', 'Please use Automator()->register->closure instead.', 3.0 );
		}

		return $this->register->closure( $closure, $integration_code, $integration );
	}

	/**
	 * Add a new integration
	 *
	 * @param $integration_code
	 * @param $integration
	 *
	 * @return null|bool
	 * @deprecated v2.1 Automator()->register->integration
	 * @use Automator()->register->integration
	 */
	public function register_integration( $integration_code = null, $integration = null ) {
		if ( defined( 'AUTOMATOR_DEBUG_MODE' ) && true === AUTOMATOR_DEBUG_MODE ) {
			_doing_it_wrong( 'register_integration', 'Please use Automator()->register->integration instead.', 3.0 );
		}

		return $this->register->integration( $integration_code, $integration );
	}

	/**
	 * @param $trigger_code
	 *
	 * @return false|string
	 */
	public function get_trigger_type( $trigger_code = null ) {
		if ( null === $trigger_code ) {
			return false;
		}
		$triggers = $this->get_triggers();
		if ( empty( $triggers ) ) {
			return false;
		}
		foreach ( $triggers as $trigger ) {
			if ( ! isset( $trigger['code'] ) ) {
				continue;
			}
			if ( (string) $trigger_code !== (string) $trigger['code'] ) {
				continue;
			}
			if ( ! isset( $trigger['type'] ) ) {
				return 'anonymous';
			}

			return (string) $trigger['type'];
		}

		return false;
	}

	/**
	 * Determines whether the trigger type is a user.
	 * 
	 * @param string $trigger_code The trigger code.
	 *
	 * @return bool True if the trigger type is 'user'. Otherwise, false.
	 */
	public function is_trigger_type_user( $trigger_code = '' ) {

		return $this->is_trigger_type( 'user', $trigger_code );

	}

	/**
	 * Determines whether the trigger type is an anonymous.
	 * 
	 * @param string $trigger_code The trigger code.
	 * 
	 * @return bool True if the trigger type is 'anonymous'. Otherwise, false.
	 */
	public function is_trigger_type_anonymous( $trigger_code = '' ) {

		return $this->is_trigger_type( 'anonymous', $trigger_code );

	}

	/**
	 * Determines if the trigger type is equal to the given type.
	 * 
	 * @param string $type The type (anonymous, user) you want to compare against the trigger.
	 * @param string $trigger_code The trigger code of the trigger.
	 * 
	 * @return bool True if given type is equal to the type of the trigger. Otherwise, false.
	 */
	public function is_trigger_type( $type = '', $trigger_code = '' ) {

		return (string) $type === (string) $this->get_trigger_type( $trigger_code );

	}

}
