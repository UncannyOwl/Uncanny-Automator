<?php
namespace Uncanny_Automator\Services\Dashboard;

use wpdb;

/**
 * Utility class for managing recipe usages and interactions with credits.
 */
class Recipe_Using_Credits_Utils {

	/**
	 * WordPress database global object.
	 *
	 * @var wpdb
	 */
	protected $db;

	/**
	 * Supported integrations for recipes.
	 *
	 * @var string[]
	 */
	protected $integrations = array(
		'ACTIVE_CAMPAIGN',
		'AWEBER',
		'BITLY',
		'BREVO',
		'CAMPAIGN_MONITOR',
		'CLICKUP',
		'CONSTANT_CONTACT',
		'CONVERTKIT',
		'DRIP',
		'FACEBOOK',
		'FACEBOOK_GROUPS',
		'GETRESPONSE',
		'GOOGLE_CALENDAR',
		'GOOGLE_CONTACTS',
		'GOOGLESHEET',
		'GTT',
		'GTW',
		'HELPSCOUT',
		'HUBSPOT',
		'INSTAGRAM',
		'LINKEDIN',
		'MAILCHIMP',
		'MAILERLITE',
		'MAUTIC',
		'MICROSOFT_TEAMS',
		'NOTION',
		'ONTRAPORT',
		'OPEN_AI',
		'SENDY',
		'SLACK',
		'TELEGRAM',
		'TRELLO',
		'TWILIO',
		'TWITTER',
		'WHATSAPP',
		'ZOHO_CAMPAIGNS',
		'ZOOM',
		'ZOOMWEBINAR',
	);

	/**
	 * Constructor to initialize the WordPress database object.
	 */
	public function __construct() {
		global $wpdb;
		$this->db = $wpdb;
	}

	/**
	 * Fetch actions from the database that match specific integrations.
	 *
	 * @return array
	 */
	public function fetch_actions_from_specific_integrations() {
		$placeholders = implode( ', ', array_fill( 0, count( $this->integrations ), '%s' ) );
		$parameters   = array( 'uo-action', 'publish', 'integration' );
		$args         = array_merge( $parameters, $this->integrations );

		$stmt = $this->db->prepare(
			"SELECT post.ID, post.post_parent, post.post_title, post.post_status
            FROM {$this->db->posts} AS post
            INNER JOIN {$this->db->postmeta} AS meta ON meta.post_id = post.ID
            WHERE post.post_type=%s AND post.post_status=%s AND meta.meta_key=%s AND meta.meta_value IN ($placeholders)",
			$args
		);

		return $this->db->get_results( $stmt, ARRAY_A );
	}

	/**
	 * Identify loops and recipes from post types.
	 *
	 * @param array $app_actions Actions fetched from specific integrations.
	 * @return array
	 */
	public function identify_loops_and_recipes_from_post_types( $app_actions ) {
		$post_parents = array_column( $app_actions, 'post_parent' );
		$placeholders = implode( ', ', array_fill( 0, count( $post_parents ), '%d' ) );

		$stmt = $this->db->prepare(
			"SELECT ID, post_parent, post_type, post_title FROM {$this->db->posts} WHERE ID IN($placeholders)",
			$post_parents
		);

		return (array) $this->db->get_results( $stmt, ARRAY_A );
	}

	/**
	 * Determine which recipes to fetch based on loops and other criteria.
	 *
	 * @param array $loops_and_recipes Results from identifying loops and recipes.
	 * @return array
	 */
	public function determine_recipes_from( $loops_and_recipes ) {
		$recipe_ids = array();
		foreach ( $loops_and_recipes as $_post ) {
			$recipe_ids[] = 'uo-loop' === $_post['post_type'] ? $_post['post_parent'] : $_post['ID'];
		}
		return (array) $recipe_ids;
	}

	/**
	 * Retrieve recipe details based on determined IDs.
	 *
	 * @param array $recipes_determined IDs of recipes to fetch.
	 * @return array
	 */
	public function get_recipes_from( $recipes_determined ) {

		// Bail if empty.
		if ( empty( $recipes_determined ) ) {
			return array();
		}

		$placeholders = implode( ', ', array_fill( 0, count( $recipes_determined ), '%d' ) );
		$results      = (array) $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$this->db->posts} WHERE ID IN($placeholders)",
				$recipes_determined
			),
			ARRAY_A
		);

		return $results;
	}

	/**
	 * Get the number of times a recipe has been executed.
	 *
	 * @param int $recipe_id Recipe ID to query.
	 * @return int Number of runs for the specified recipe.
	 */
	public function get_recipe_number_of_runs( $recipe_id ) {
		$stmt   = $this->db->prepare(
			"SELECT COUNT(run_number) FROM {$this->db->prefix}uap_recipe_log WHERE automator_recipe_id=%d AND completed = 1",
			$recipe_id
		);
		$result = $this->db->get_var( $stmt );
		return absint( $result );
	}

	/**
	 * Fetch full recipe details including runs and execution data.
	 *
	 * @return array Complete recipe details for UI display.
	 */
	public function fetch() {
		$app_actions        = $this->fetch_actions_from_specific_integrations();
		$loops_and_recipes  = $this->identify_loops_and_recipes_from_post_types( $app_actions );
		$recipes_determined = $this->determine_recipes_from( $loops_and_recipes );

		$recipes      = $this->get_recipes_from( $recipes_determined );
		$recipe_items = array();

		foreach ( $recipes as $recipe ) {
			$recipe_id    = $recipe['ID'];
			$recipe_title = ! empty( $recipe['post_title'] ) ? $recipe['post_title'] : sprintf( __( 'ID: %s (no title)', 'uncanny-automator' ), $recipe_id );

			$recipe_edit_url                  = get_edit_post_link( $recipe_id );
			$recipe_type                      = get_post_type( $recipe_id );
			$recipe_allowed_completions_total = get_post_meta( $recipe_id, 'recipe_max_completions_allowed', true );
			$recipe_number_of_runs            = $this->get_recipe_number_of_runs( $recipe_id );

			// Calculate specific data based on the recipe type.
			$recipe_times_per_user = '';
			if ( 'user' === $recipe_type ) {
				$recipe_times_per_user = get_post_meta( $recipe_id, 'recipe_completions_allowed', true );
			}

			$recipe_items[] = array(
				'id'                        => $recipe_id,
				'title'                     => $recipe_title,
				'url'                       => $recipe_edit_url,
				'type'                      => $recipe_type,
				'times_per_user'            => $recipe_times_per_user,
				'allowed_completions_total' => $recipe_allowed_completions_total,
				'completed_runs'            => $recipe_number_of_runs,
			);
		}

		return (array) $recipe_items;
	}

}
