<?php

namespace Uncanny_Automator;

/**
 * Class Development_Ready_functions
 * @package Uncanny_Automator
 */
class Automator_Functions {


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
	public $recipe_types = [ 'user' ];

	/**
	 * Collection of all integrations
	 *
	 * @since    1.0.0
	 * @access   public
	 */
	public $integrations = [];

	/**
	 * Collection of all triggers
	 *
	 * @since    1.0.0
	 * @access   public
	 */
	public $triggers = [];

	/**
	 * Collection of all actions
	 *
	 * @since    1.0.0
	 * @access   public
	 */
	public $actions = [];

	/**
	 * Collection of all closures
	 *
	 * @since    1.0.0
	 * @access   public
	 */
	public $closures = [];

	/**
	 * Triggers and actions for each recipe with data
	 *
	 * @since    1.0.0
	 * @access   public
	 */
	public $recipes_data = [];

	/**
	 * Collection of all localized strings
	 *
	 * @since    1.0.0
	 * @access   public
	 */
	public $i18n = [];

	/**
	 * Composite Class of pre-defined Automator options
	 *
	 * @since    1.0.0
	 * @access   public
	 * @var Automator_Options
	 * @deprecated deprecated since v2.1.0. Use @var $this->helpers
	 */
	public $options;

	/**
	 * @var Automator_Recipe_Process
	 * @since    2.1
	 * @access   public
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
	 * Initializes all development helper classes and variables via class composition
	 */
	public function __construct() {

		// Automator integration, trigger, action and closure registration
		require_once $this->get_composite_class_dir() . 'automator-registration.php';
		$this->register = new Automator_Registration();

		// Automator integration, trigger, action and closure process
		require_once $this->get_composite_class_dir() . 'automator-recipe-process.php';
		require_once $this->get_composite_class_dir() . 'automator-recipe-process-user.php';
		$this->process = new Automator_Recipe_Process();

		// Automator integration, trigger, action and closure process
		require_once $this->get_composite_class_dir() . 'automator-recipe-process-complete.php';
		$this->complete = new Automator_Recipe_Process_Complete();

		// Load pre-defined options for triggers, actions, and closures
		require_once $this->get_composite_class_dir() . 'automator-options.php';
		require_once $this->get_composite_class_dir() . 'automator-helpers.php';
		require_once $this->get_composite_class_dir() . 'automator-recipe-helpers.php';
		require_once $this->get_composite_class_dir() . 'automator-recipe-helpers-field.php';
		$this->options = new Automator_Options();
		$this->helpers = new Automator_Helpers();

		// Load plugin status checks
		require_once $this->get_composite_class_dir() . 'automator-integrations-status.php';
		$this->plugin_status = new Automator_Integrations_Status();

		// Load plugin status checks
		require_once $this->get_composite_class_dir() . 'automator-error-messages.php';
		$this->error_message = new Automator_Error_Messages();

		// Load plugin status checks
		require_once $this->get_composite_class_dir() . 'automator-input-parser.php';
		$this->parse = new Automator_Input_Parser();

		// Load plugin translated strings
		require_once $this->get_composite_class_dir() . 'automator-translations.php';
		$this->i18n = new Automator_Translations();

		// Load plugin translated strings
		require_once $this->get_composite_class_dir() . 'automator-utilities.php';
		$this->utilities = new Automator_Utilities();

		// Load plugin translated strings
		require_once $this->get_composite_class_dir() . 'automator-get-data.php';
		$this->get = new Automator_Get_Data();
	}

	/**
	 * Get the directory for composite classes
	 *
	 * return string
	 */
	public function get_composite_class_dir() {
		return dirname( AUTOMATOR_BASE_FILE ) . '/src/core/mu-classes/composite-classes/';
	}

	/**
	 * Returns a recipe types for automator
	 *
	 * @return array
	 */
	public function get_recipe_types() {
		$recipe_types = apply_filters( 'uap_recipe_types', $this->recipe_types );

		return $recipe_types;
	}

	/**
	 * Returns a filtered set on automator integrations
	 *
	 * @return array
	 */
	public function get_integrations() {
		$integrations = apply_filters( 'uap_integrations', $this->integrations );

		return $integrations;
	}

	/**
	 * Returns a filtered set on automator triggers
	 *
	 * @return array
	 */
	public function get_triggers() {
		$triggers = apply_filters( 'uap_triggers', $this->triggers );

		return $triggers;
	}

	/**
	 * Returns a filtered set on automator actions
	 *
	 * @return array
	 */
	public function get_actions() {
		$uap_actions = apply_filters( 'uap_actions', $this->actions );

		return $uap_actions;
	}

	/**
	 * Returns a filtered set on automator closures
	 *
	 * @return array
	 */
	public function get_closures() {
		$uap_actions = apply_filters( 'uap_closures', $this->closures );

		return $uap_actions;
	}

	/**
	 * @param string $code
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
		$author_name = apply_filters( $filter, 'Uncanny Owl' );

		return $author_name;
	}

	/**
	 * @param string $code
	 *
	 * @return mixed
	 */
	public function get_author_support_link( $code = '' ) {
		if ( ! empty( $code ) ) {
			$code   = strtolower( $code );
			$filter = "automator_{$code}_author_support_link";
		} else {
			$filter = 'automator_author_support_link';
		}

		$author_support_link = apply_filters( $filter, 'https://automatorplugin.com/knowledge-base/' );

		return $author_support_link;
	}

	/**
	 * Register a new recipe type and creates a type if defined and the type does not exist
	 *
	 * @param null $recipe_type
	 * @param array $recipe_details
	 *
	 * @return null|                 |true
	 */
	public function register_recipe_type( $recipe_type = null, $recipe_details = [] ) {

		return $this->register->recipe_type( $recipe_type, $recipe_details );
	}

	/**
	 * Register a new trigger and creates a type if defined and the type does not exist
	 *
	 * @param $trigger          null||array
	 * @param $integration_code null|string
	 * @param $integration      null||array
	 *
	 * @return null|                 |true
	 * @deprecated v2.1
	 * @use $uncanny_automator->register->trigger
	 */
	public function register_trigger( $trigger = null, $integration_code = null, $integration = null ) {
		return $this->register->trigger( $trigger, $integration_code, $integration );
	}

	/**
	 * Register a new uap action and creates a type if defined and the type does not exist
	 *
	 * @param $uap_action       null||array
	 * @param $integration_code null|string
	 * @param $integration      null||array
	 *
	 * @return null|                 |true
	 * @deprecated v2.1
	 * @use $uncanny_automator->register->action
	 */
	public function register_action( $uap_action = null, $integration_code = null, $integration = null ) {
		return $this->register->action( $uap_action, $integration_code, $integration );
	}

	/**
	 * Registers a new closure and creates a type if defined and the type does not exist
	 *
	 * @param $closure          null||array
	 * @param $integration_code null|string
	 * @param $integration      null||array
	 *
	 * @return null|                 |true
	 * @deprecated v2.1
	 * @use $uncanny_automator->register->closure
	 */
	public function register_closure( $closure = null, $integration_code = null, $integration = null ) {
		return $this->register->closure( $closure, $integration_code, $integration );
	}

	/**
	 * Add a new integration
	 *
	 * @param $integration_code null||string
	 * @param $integration      null||array
	 *
	 * @return null|                 |bool
	 * @deprecated v2.1
	 * @use $uncanny_automator->register->integration
	 */
	public function register_integration( $integration_code = null, $integration = null ) {
		return $this->register->integration( $integration_code, $integration );
	}

	/**
	 * Get data for all recipe objects
	 *
	 * @param null $force_new_data_load
	 * @param null $recipe_id
	 * @param null $match_trigger_code
	 *
	 * @return array
	 */
	public function get_recipes_data( $force_new_data_load = null, $recipe_id = null, $match_trigger_code = null ) {
		if ( null === $force_new_data_load ) {
			if ( ! empty( $this->recipes_data ) ) {
				return $this->recipes_data;
			}
		}

		//$performance_setting = get_option( 'uap_automator_performance_version', 0 );
		//Always enabled
		$performance_setting = 1;
		//Force performance version
		if ( 1 === absint( $performance_setting ) && is_null( $recipe_id ) ) {
			return $this->get_recipes_data_performance();
		}

		$args = array(
			'numberposts' => 9999,
			'post_type'   => 'uo-recipe',
			'post_status' => 'any',
		);

		if ( is_numeric( $recipe_id ) && ! is_null( $recipe_id ) ) {
			$args['post__in'] = [ $recipe_id ];
			$recipe           = [];
			$recipes          = get_posts( $args );
			//$recipes = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_status FROM $wpdb->posts WHERE post_type = %s AND post_status IN ( 'publish', 'draft' ) AND ID = %d", 'uo-recipe', $recipe_id ) );
			if ( $recipes ) {
				foreach ( $recipes as $key => $recipe_d ) {

					$recipe_id                 = $recipe_d->ID;
					$completed_by_current_user = $this->is_recipe_completed( $recipe_id );

					$recipe[ $key ]['ID']          = $recipe_id;
					$recipe[ $key ]['post_status'] = $recipe_d->post_status;
					$recipe[ $key ]['recipe_type'] = $this->utilities->get_recipe_type( $recipe_id );

					$triggers_array             = []; //= is_array( $children_array ) && key_exists( 'uo-trigger', $children_array[ $recipe_id ] ) ? $children_array[ $recipe_id ]['uo-trigger'] : [];
					$triggers                   = $this->get_recipe_data( 'uo-trigger', $recipe_id, $triggers_array );
					$recipe[ $key ]['triggers'] = $triggers;

					$action_array              = []; //= is_array( $children_array ) && key_exists( 'uo-action', $children_array[ $recipe_id ] ) ? $children_array[ $recipe_id ]['uo-action'] : [];
					$actions                   = $this->get_recipe_data( 'uo-action', $recipe_id, $action_array );
					$recipe[ $key ]['actions'] = $actions;

					$closure_array              = []; //= is_array( $children_array ) && key_exists( 'uo-closure', $children_array[ $recipe_id ] ) ? $children_array[ $recipe_id ]['uo-action'] : [];
					$closures                   = $this->get_recipe_data( 'uo-closure', $recipe_id, $closure_array );
					$recipe[ $key ]['closures'] = $closures;

					$recipe[ $key ]['completed_by_current_user'] = $completed_by_current_user;
				}
			}

			return $recipe;
		} else {

			global $uncanny_automator;

			$recipes = get_posts( $args );
			if ( $recipes ) {
				foreach ( $recipes as $key => $recipe ) {

					$recipe_id = $recipe->ID;

					$triggers_array                         = []; //= is_array( $children_array ) && ! empty( $children_array ) && key_exists( 'uo-trigger', $children_array[ $recipe_id ] ) ? $children_array[ $recipe_id ]['uo-trigger'] : [];
					$triggers                               = $this->get_recipe_data( 'uo-trigger', $recipe_id, $triggers_array, $match_trigger_code );
					$this->recipes_data[ $key ]['triggers'] = $triggers;
					//No recipes matched trigger code.. Continue
					if ( empty( $triggers ) ) {
						//unset( $this->recipes_data[ $key ] );
						continue;
					}

					$this->recipes_data[ $key ]['ID']          = $recipe_id;
					$this->recipes_data[ $key ]['post_status'] = $recipe->post_status;
					$this->recipes_data[ $key ]['recipe_type'] = $uncanny_automator->utilities->get_recipe_type( $recipe_id );


					$action_array                          = []; //= is_array( $children_array ) && ! empty( $children_array ) && key_exists( 'uo-action', $children_array[ $recipe_id ] ) ? $children_array[ $recipe_id ]['uo-action'] : [];
					$actions                               = $this->get_recipe_data( 'uo-action', $recipe_id, $action_array );
					$this->recipes_data[ $key ]['actions'] = $actions;

					$closure_array                          = []; //= is_array( $children_array ) && ! empty( $children_array ) && key_exists( 'uo-closure', $children_array[ $recipe_id ] ) ? $children_array[ $recipe_id ]['uo-closure'] : [];
					$closures                               = $this->get_recipe_data( 'uo-closure', $recipe_id, $closure_array );
					$this->recipes_data[ $key ]['closures'] = $closures;

					$this->recipes_data[ $key ]['completed_by_current_user'] = $this->is_recipe_completed( $recipe_id );
				}
			}

			return $this->recipes_data;
		}
	}

	/**
	 * @param null $match_trigger_code
	 *
	 * @return array
	 */
	public function get_recipes_data_performance() {
		global $uncanny_automator;
		global $wpdb;
		//if ( is_admin() || DOING_AJAX || isset( $_REQUEST['doing_rest'] ) ) {
		$qry = $wpdb->prepare( "SELECT ID, post_title, post_type, post_status, post_parent FROM $wpdb->posts WHERE post_type = %s ORDER BY ID DESC LIMIT 0, 99999", 'uo-recipe' );

		//echo $qry;
		$recipes = $wpdb->get_results( $qry );
		//Extract Recipe IDs
		$recipe_ids = array_column( (array) $recipes, 'ID' );
		//Collective array of recipes triggers, actions, closures
		$recipe_data = $this->pre_fetch_recipe_metas( $recipes );
		//Collective array of users recipes completed status
		$recipes_completed = $this->are_recipes_completed( null, $recipe_ids );
		$recipes_completed = empty( $recipes_completed ) ? [] : $recipes_completed;
		if ( $recipes ) {
			$key = 0;
			foreach ( $recipes as $recipe ) {
				$recipe_id = $recipe->ID;
				if ( key_exists( $recipe_id, $recipe_data ) && is_array( $recipe_data[ $recipe_id ] ) && key_exists( 'triggers', $recipe_data[ $recipe_id ] ) ) {
					if ( $recipe_data[ $recipe_id ]['triggers'] ) {
						//Grab tokens for each of trigger
						foreach ( $recipe_data[ $recipe_id ]['triggers'] as $t_id => $tr ) {
							$tokens                                                   = $this->get->recipe_trigger_tokens( $tr['meta'], $recipe_id );
							$recipe_data[ $recipe_id ]['triggers'][ $t_id ]['tokens'] = $tokens;
						}
					}
					$triggers = $recipe_data[ $recipe_id ]['triggers']; //$this->get_recipe_data( 'uo-trigger', $recipe_id, $triggers_array );
				} else {
					$triggers = [];
				}
				/*if ( empty( $triggers ) ) {
					continue;
				}*/
				$this->recipes_data[ $key ]['ID']          = $recipe_id;
				$this->recipes_data[ $key ]['post_status'] = $recipe->post_status;
				$this->recipes_data[ $key ]['recipe_type'] = $uncanny_automator->utilities->get_recipe_type( $recipe_id );

				$this->recipes_data[ $key ]['triggers'] = $triggers;

				if ( key_exists( $recipe_id, $recipe_data ) && is_array( $recipe_data[ $recipe_id ] ) && key_exists( 'actions', $recipe_data[ $recipe_id ] ) ) {
					$actions = $recipe_data[ $recipe_id ]['actions']; //$this->get_recipe_data( 'uo-action', $recipe_id, $action_array );
				} else {
					$actions = [];
				}
				$this->recipes_data[ $key ]['actions'] = $actions;

				if ( key_exists( $recipe_id, $recipe_data ) && is_array( $recipe_data[ $recipe_id ] ) && key_exists( 'closures', $recipe_data[ $recipe_id ] ) ) {
					$closures = $recipe_data[ $recipe_id ]['closures']; //$this->get_recipe_data( 'uo-closure', $recipe_id, $closure_array );
				} else {
					$closures = [];
				}
				$this->recipes_data[ $key ]['closures'] = $closures;

				$this->recipes_data[ $key ]['completed_by_current_user'] = key_exists( $recipe_id, $recipes_completed ) ? $recipes_completed[ $recipe_id ] : false;
				$key ++;
			}
		}

		return $this->recipes_data;
	}

	/**
	 * @param array $recipes
	 *
	 * @return array
	 */
	public function pre_fetch_recipe_metas( $recipes = [] ) {
		$metas    = [];
		$triggers = [];
		$actions  = [];
		$closures = [];
		if ( ! empty( $recipes ) ) {

			global $wpdb;
			//////////////////////
			//////////////////////
			//////////////////////
			/////Fetch uo-trigger, uo-action, uo-closure
			$recipe_children = $wpdb->get_results( "SELECT ID, post_status, post_type
													FROM $wpdb->posts
													WHERE post_parent IN (SELECT ID
													FROM $wpdb->posts
													WHERE post_type = 'uo-recipe')" );

			if ( $recipe_children ) {
				foreach ( $recipe_children as $p ) {
					$ID  = $p->ID;
					$p_t = $p->post_type;
					$p_s = $p->post_status;
					switch ( $p_t ) {
						case 'uo-trigger':
							$triggers[ $ID ] = [ 'ID' => $ID, 'post_status' => $p_s, ];
							break;
						case 'uo-action':
							$actions[ $ID ] = [ 'ID' => $ID, 'post_status' => $p_s, ];
							break;
						case 'uo-closure':
							$closures[ $ID ] = [ 'ID' => $ID, 'post_status' => $p_s, ];
							break;
					}

				}
			}

			///END
			//////////////////////
			//////////////////////

			//////////////////////
			//////////////////////
			//////////////////////
			/////Fetch metas for uo-trigger, uo-action, uo-closure
			$q             = $wpdb->prepare( "SELECT pm.post_id, pm.meta_key, pm.meta_value, p.post_parent, p.post_type
														FROM $wpdb->postmeta pm
														LEFT JOIN $wpdb->posts p
														ON p.ID = pm.post_id
														WHERE pm.post_id IN (SELECT ID
														FROM $wpdb->posts
														WHERE post_parent IN (SELECT ID
														FROM $wpdb->posts
														WHERE post_type = %s))", 'uo-recipe' );
			$related_metas = $wpdb->get_results( $q );

			if ( $related_metas ) {
				foreach ( $related_metas as $p ) {
					$ID  = $p->post_id;
					$m_k = $p->meta_key;
					$m_v = $p->meta_value;
					if ( key_exists( $ID, $triggers ) ) {
						$triggers[ $ID ]['meta'][ $m_k ] = $m_v;
					} elseif ( key_exists( $ID, $actions ) ) {
						$actions[ $ID ]['meta'][ $m_k ] = $m_v;
					} elseif ( key_exists( $ID, $closures ) ) {
						$closures[ $ID ]['meta'][ $m_k ] = $m_v;
					}
				}
			}
			//Fix missing metas!
			if ( $triggers ) {
				foreach ( $triggers as $trigger_ID => $array ) {
					if ( ! key_exists( 'meta', $array ) ) {
						$triggers[ $trigger_ID ]['meta'] = [ 'code' => '' ];
					} elseif ( key_exists( 'meta', $array ) ) {
						//Attempt to return Trigger ID for magic button
						foreach ( $array['meta'] as $mk => $mv ) {
							if ( 'code' === (string) trim( $mk ) && 'WPMAGICBUTTON' === (string) trim( $mv ) ) {
								$triggers[ $trigger_ID ]['meta']['WPMAGICBUTTON'] = $trigger_ID;
							}
						}
					}
				}
			}

			///END
			//////////////////////
			//////////////////////


			/////Build old recipe array style
			foreach ( $related_metas as $r ) {
				$recipe_id     = absint( $r->post_parent );
				$non_recipe_id = absint( $r->post_id );
				switch ( $r->post_type ) {
					case 'uo-trigger':
						if ( key_exists( $non_recipe_id, $triggers ) ) {
							$metas[ $recipe_id ]['triggers'][] = $triggers[ $non_recipe_id ];
							unset( $triggers[ $non_recipe_id ] );
						}
						break;
					case 'uo-action':
						if ( key_exists( $non_recipe_id, $actions ) ) {
							$metas[ $recipe_id ]['actions'][] = $actions[ $non_recipe_id ];
							unset( $actions[ $non_recipe_id ] );
						}
						break;
					case 'uo-closure':
						if ( key_exists( $non_recipe_id, $closures ) ) {
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
	 * @param $user_id   null||int
	 * @param array $recipe_ids
	 *
	 * @return array
	 */
	public function are_recipes_completed( $user_id = null, $recipe_ids = [] ) {

		if ( empty( $recipe_ids ) ) {
			Utilities::log( 'ERROR: You are trying to check if a recipe is completed without providing a recipe_id.', 'is_recipe_completed ERROR', false, 'uap-errors' );

			return null;
		}

		// Set user ID
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		// No user id is aviable.
		if ( 0 === $user_id ) {
			Utilities::log( 'ERROR: You are trying to check if a recipe is completed when a there is no logged in user.', 'is_recipe_completed ERROR', false, 'uap-errors' );

			return null;
		}

		$completed = [];
		global $wpdb;
		$table_name = $wpdb->prefix . 'uap_recipe_log';
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) === $table_name && ! empty( $recipe_ids ) ) {
			$results = $wpdb->get_results( "SELECT COUNT(completed) AS completed, automator_recipe_id FROM $table_name WHERE user_id = $user_id AND automator_recipe_id IN (" . join( ',', $recipe_ids ) . ") AND completed = 1 GROUP BY automator_recipe_id" );

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
		}

		return $this->utilities->recipes_number_times_completed( $recipe_ids, $completed );

	}

	/**
	 * Check if the recipe was completed
	 *
	 * @param $recipe_id null||int
	 * @param $user_id   null||int
	 *
	 * @return null|bool
	 */
	public function is_recipe_completed( $recipe_id = null, $user_id = null ) {

		if ( null === $recipe_id || ! is_numeric( $recipe_id ) ) {
			Utilities::log( 'ERROR: You are trying to check if a recipe is completed without providing a recipe_id.', 'is_recipe_completed ERROR', false, 'uap-errors' );

			return null;
		}

		if ( ! is_user_logged_in() ) {
			return null;
		}

		// Set user ID
		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'uap_recipe_log';
		$results    = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(completed) AS num_times_completed 
														FROM $table_name 
														WHERE 1=1 
														AND user_id = %d 
														AND automator_recipe_id = %d 
														AND completed = 1", $user_id, $recipe_id ) );

		if ( 0 === $results ) {
			return false;
		} else {
			$results = empty( $results ) ? 0 : $results;

			return $this->utilities->recipe_number_times_completed( $recipe_id, $results );
		}
	}

	/**
	 * Complete a recipe
	 *
	 * @param $recipe_id     null||int
	 * @param $user_id       null||int
	 * @param $recipe_log_id null||int
	 *
	 * @param array $args
	 *
	 * @return null|true
	 */
	public function complete_recipe( $recipe_id = null, $user_id = null, $recipe_log_id = null, $args = [] ) {

		return $this->complete->recipe( $recipe_id, $user_id, $recipe_log_id, $args );
	}

	/**
	 * Complete all actions in recipe
	 *
	 * @param null $recipe_id
	 * @param null $user_id
	 * @param null $recipe_log_id
	 *
	 * @param array $args
	 *
	 * @return bool
	 */
	public function complete_actions( $recipe_id = null, $user_id = null, $recipe_log_id = null, $args = [] ) {

		return $this->complete->complete_actions( $recipe_id, $user_id, $recipe_log_id, $args );
	}

	/**
	 * Complete all closures in recipe
	 *
	 * @param null $recipe_id
	 * @param null $user_id
	 * @param null $recipe_log_id
	 * @param array $args
	 *
	 * @return bool
	 *
	 */
	public function complete_closures( $recipe_id = null, $user_id = null, $recipe_log_id = null, $args = [] ) {

		return $this->complete->closures( $recipe_id, $user_id, $recipe_log_id, $args );
	}

	/**
	 * Get saved data for recipe actions or triggers
	 *
	 * @param       $type      null||int
	 * @param       $recipe_id null||int
	 * @param array $recipe_children
	 * @param null $matched_trigger
	 *
	 * @return null|                |array
	 */
	public function get_recipe_data( $type = null, $recipe_id = null, $recipe_children = [], $matched_trigger = null ) {

		if ( null === $type ) {
			return null;
		}

		if ( ! in_array( $type, array( 'uo-trigger', 'uo-action', 'uo-closure' ) ) ) {
			return null;
		}


		if ( is_null( $recipe_id ) || ! is_numeric( $recipe_id ) ) {
			Utilities::log( 'ERROR: You are trying to get recipe data without providing a recipe_id', 'get_recipe_data ERROR', false, 'uap-errors' );

			return null;
		}

		global $wpdb;
		if ( empty( $recipe_children ) ) {
			$q = apply_filters(
				'q_get_recipe_data',
				"Select ID, post_status FROM $wpdb->posts WHERE post_parent = $recipe_id AND post_type = '{$type}'",
				$recipe_id,
				$type );

			// All the triggers associated with the recipe
			$recipe_children = $wpdb->get_results( $q, ARRAY_A );
		}
		// All data for recipe triggers
		$recipe_children_data = [];
		if ( $recipe_children ) {
			// Check each trigger for set values
			foreach ( $recipe_children as $key => $child ) {
				// Collect all meta data for this trigger
				if ( ! key_exists( 'meta', $child ) ) {
					$child_meta = get_post_custom( $child['ID'] );
				} else {
					$child_meta = $child['meta'];
				}

				if ( ! $child_meta ) {
					continue;
				}
				// Get post custom return an array for each meta_key as there maybe more than one value per key.. we only store and need one value
				$child_meta_single = [];
				foreach ( $child_meta as $meta_key => $meta_value ) {
					$child_meta_single[ $meta_key ] = reset( $meta_value );
				}
				$code = key_exists( 'code', $child_meta_single ) ? $child_meta_single['code'] : '';

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


				if ( $item_not_found ) {

					$item = array(
						'ID'          => $child['ID'],
						'post_status' => 'draft',
					);

					//wp_update_post( $item );
					$wpdb->query( "UPDATE $wpdb->posts SET post_status = 'draft' WHERE ID = {$child['ID']}" );
					$child['post_status'] = 'draft';
				}

				// The trigger is create/stored automatically but may not have been saved. Delete if not saved!
				if ( empty( $child_meta ) && isset( $child['ID'] ) ) {
					//wp_delete_post( $child['ID'] );
					continue;
				}

				$recipe_children_data[ $key ]['ID']          = absint( $child['ID'] );
				$recipe_children_data[ $key ]['post_status'] = $child['post_status'];
				$recipe_children_data[ $key ]['meta']        = $child_meta_single;


				if ( 'uo-trigger' === $type ) {
					$recipe_children_data[ $key ]['tokens'] = $this->get->recipe_trigger_tokens( $child_meta_single, $recipe_id );
				}
			}
		}

		return $recipe_children_data;
	}

	/**
	 * Added this function to directly fetch trigger data instead of looping thru
	 * recipe and it's triggers for parsing. Specially needed for multi-trigger
	 * parsing
	 *
	 * @param int $recipe_id
	 * @param int $trigger_id
	 *
	 * @return array|mixed
	 * @since 2.9
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
	 * Insert trigger for the user
	 *
	 * @param $user_id
	 * @param $trigger_id    null||int
	 * @param $recipe_id     null||int
	 * @param $completed     bool
	 * @param $recipe_log_id null||bool
	 *
	 * @return int|null
	 */
	public function insert_trigger( $user_id = null, $trigger_id = null, $recipe_id = null, $completed = false, $recipe_log_id = null ) {

		return $this->insert_trigger( $user_id, $trigger_id, $recipe_id, $completed, $recipe_log_id );

	}

	/**
	 * Check if the trigger is completed
	 *
	 * @param $user_id       null||int
	 * @param $trigger_id    null||int
	 * @param $recipe_id     null||int
	 * @param $recipe_log_id null||int
	 * @param array $args
	 * @param bool $process_recipe
	 *
	 * @return null|bool
	 */
	public function is_trigger_completed( $user_id = null, $trigger_id = null, $recipe_id = null, $recipe_log_id = null, $args = [], $process_recipe = false ) {

		return $this->process->user->is_trigger_completed( $user_id, $trigger_id, $recipe_id, $recipe_log_id, $args, $process_recipe );
	}

	/**
	 * Complete the trigger for the user
	 *
	 * @param array $args
	 *
	 * @return null
	 */
	public function complete_trigger( $args = [] ) {

		return $this->complete->trigger( $args );
	}

	/**
	 * Complete the action for the user
	 *
	 * @param $user_id       null||int
	 * @param $action_data   null||int
	 * @param $recipe_id     null||int
	 * @param $error_message string
	 * @param $recipe_log_id null
	 *
	 * @param array $args
	 *
	 * @return null
	 */
	public function complete_action( $user_id = null, $action_data = null, $recipe_id = null, $error_message = '', $recipe_log_id = null, $args = [] ) {

		return $this->complete->action( $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args );
	}

	/**
	 * Are all triggers in the recipe completed
	 *
	 * @param int $recipe_id null||int
	 * @param int $user_id null||int
	 * @param int $recipe_log_id null||int
	 *
	 * @param array $args
	 *
	 * @return bool|null
	 */
	public function triggers_completed( $recipe_id = 0, $user_id = 0, $recipe_log_id = 0, $args = [] ) {

		return $this->complete->triggers_completed( $recipe_id, $user_id, $recipe_log_id, $args );
	}

	/**
	 * Insert the trigger for the user
	 *
	 * @param $args
	 *
	 * @return null||int
	 */
	public function insert_trigger_meta( $args ) {
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
	 */
	public function maybe_trigger_complete( $args ) {
		return $this->process->user->maybe_trigger_complete( $args );
	}

	/**
	 *
	 * Matches recipes against trigger meta/code. If a recipe is found and not completed,
	 * add a trigger entry in to the DB and matches number of times.
	 *
	 * @param      $args
	 * @param bool $mark_trigger_complete
	 *
	 * @return array|bool|int|null
	 */
	public function maybe_add_trigger_entry( $args, $mark_trigger_complete = true ) {
		return $this->process->user->maybe_add_trigger_entry( $args, $mark_trigger_complete );
	}


	/**
	 * @param int $recipe_id
	 * @param int $user_id
	 * @param bool $create_recipe
	 * @param array $args
	 * @param bool $maybe_simulate
	 * @param null $maybe_add_log_id
	 *
	 * @return array
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
	 * @since 2.0
	 */
	public function maybe_create_recipe_log_entry( $recipe_id, $user_id, $create_recipe = true, $args = [], $maybe_simulate = false, $maybe_add_log_id = null ) {
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
	 */
	public function maybe_get_trigger_id( $user_id, $trigger_id, $recipe_id, $recipe_log_id = null ) {
		return $this->process->user->maybe_get_trigger_id( $user_id, $trigger_id, $recipe_id, $recipe_log_id );
	}

	/**
	 * @param $data
	 * @param string $type
	 *
	 * @return string
	 */
	public function uap_sanitize( $data, $type = 'text' ) {
		//$before = $data;
		switch ( $type ) {
			case 'mixed':
			case 'array':
				if ( is_array( $data ) ) {
					$this->uap_sanitize_array( $data );
				} else {
					$data = sanitize_text_field( $data );
				}
				break;
			default:
				$data = sanitize_text_field( $data );
				break;
		}

		//Utilities::log( [ $before, $data, $type ], '', true, 'sanitize' );
		return $data;
	}

	/**
	 * Recursively calls itself if children has arrays as well
	 *
	 * @param $data
	 *
	 * @return mixed
	 */
	public function uap_sanitize_array( $data ) {
		foreach ( $data as $k => $v ) {
			$k = esc_attr( $k );
			if ( is_array( $v ) ) {
				$data[ $k ] = $this->uap_sanitize( $v, 'array' );
			} else {
				switch ( $k ) {
					case 'EMAILFROM':
					case 'EMAILTO':
					case 'EMAILCC':
					case 'EMAILBCC':
					case 'WPCPOSTAUTHOR':
						$regex = '/[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})/';
						if ( preg_match( $regex, $v, $email_is ) ) {
							$data[ $k ] = sanitize_email( $v );
						} else {
							$data[ $k ] = sanitize_text_field( $v );
						}
						break;
					case'EMAILBODY':
					case'WPCPOSTCONTENT':
						$data[ $k ] = wp_kses_post( $v );
						break;
					default:
						$data[ $k ] = sanitize_text_field( $v );
						break;
				}
			}
		}

		return $data;
	}
}
