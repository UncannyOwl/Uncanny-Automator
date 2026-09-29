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
		'FACEBOOK_LEAD_ADS',
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
		$parameters   = array( AUTOMATOR_POST_TYPE_ACTION, 'publish', 'integration' );
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

		if ( empty( $app_actions ) ) {
			return array();
		}

		$post_parents = array_column( $app_actions, 'post_parent' );

		if ( empty( $post_parents ) ) {
			return array();
		}

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
		if ( empty( $loops_and_recipes ) ) {
			return array();
		}

		$recipe_ids = array();
		foreach ( $loops_and_recipes as $_post ) {
			$recipe_ids[] = AUTOMATOR_POST_TYPE_LOOP === $_post['post_type'] ? $_post['post_parent'] : $_post['ID'];
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
	 * Completed run counts for many recipes at once, keyed by recipe id.
	 *
	 * The per-recipe version below issued one COUNT per recipe. That was
	 * tolerable while the usage report went out weekly; it goes out DAILY from
	 * 7.7, and on a site with a few thousand app-using recipes it is a few
	 * thousand round trips every day.
	 *
	 * A recipe with no completed runs is absent from a GROUP BY, so callers
	 * must default a missing id to 0 — which is exactly what the per-recipe
	 * COUNT returned for it. Same numbers, one query.
	 *
	 * @param int[] $recipe_ids
	 *
	 * @return array<int, int>
	 */
	public function get_recipe_run_counts( $recipe_ids ) {

		$recipe_ids = array_filter( array_map( 'absint', (array) $recipe_ids ) );

		if ( empty( $recipe_ids ) ) {
			return array();
		}

		$placeholders = implode( ', ', array_fill( 0, count( $recipe_ids ), '%d' ) );

		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT automator_recipe_id, COUNT(run_number) AS total
				FROM {$this->db->prefix}uap_recipe_log
				WHERE completed = 1 AND automator_recipe_id IN ($placeholders)
				GROUP BY automator_recipe_id",
				$recipe_ids
			),
			ARRAY_A
		);

		$counts = array();

		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row['automator_recipe_id'] ] = absint( $row['total'] );
		}

		return $counts;
	}

	/**
	 * How many recipes use a credit-consuming integration.
	 *
	 * The system report wanted this number and got it by building the whole
	 * per-recipe array — titles, edit links, post meta, a run count each — and
	 * calling count() on it. That cost ~3 queries per recipe to produce one
	 * integer. Nothing downstream of the count needs the rest.
	 *
	 * @return int
	 */
	public function count_recipes_with_apps() {

		$app_actions        = $this->fetch_actions_from_specific_integrations();
		$loops_and_recipes  = $this->identify_loops_and_recipes_from_post_types( $app_actions );
		$recipes_determined = $this->determine_recipes_from( $loops_and_recipes );

		// Counting the ids directly would be one query cheaper and subtly
		// WRONG: determine_recipes_from() resolves a loop to its post_parent,
		// which can point at a recipe that no longer exists, and it repeats an
		// id once per app action the recipe owns. get_recipes_from() drops both
		// — it looks the ids up as posts, and IN() returns each row once — so
		// counting its result is identical to count( fetch() ) by construction.
		return count( $this->get_recipes_from( $recipes_determined ) );
	}

	/**
	 * Get the number of times a recipe has been executed.
	 *
	 * Kept for callers outside this class. fetch() now batches instead — see
	 * get_recipe_run_counts().
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

		$recipes = $this->get_recipes_from( $recipes_determined );
		if ( empty( $recipes ) ) {
			return array();
		}
		$recipe_items = array();

		$recipe_ids = array_map( 'absint', array_column( $recipes, 'ID' ) );

		// One query for every run count, instead of one per recipe.
		$run_counts = $this->get_recipe_run_counts( $recipe_ids );

		// Two more for every post and its meta. Without this, get_post_type()
		// and get_post_meta() below each fall through to their own query per
		// recipe — on a cold cron request, which is where the usage report
		// runs, that was the bulk of the cost.
		_prime_post_caches( $recipe_ids, false, true );

		foreach ( $recipes as $recipe ) {
			$recipe_id = $recipe['ID'];
			// translators: 1: Recipe ID
			$recipe_title = ! empty( $recipe['post_title'] ) ? $recipe['post_title'] : sprintf( esc_html__( 'ID: %s (no title)', 'uncanny-automator' ), $recipe_id );

			$recipe_edit_url                  = get_edit_post_link( $recipe_id );
			$recipe_type                      = get_post_type( $recipe_id );
			$recipe_allowed_completions_total = get_post_meta( $recipe_id, 'recipe_max_completions_allowed', true );
			// Absent from the GROUP BY means no completed runs, which is what
			// the per-recipe COUNT returned for such a recipe.
			$recipe_number_of_runs            = isset( $run_counts[ (int) $recipe_id ] ) ? $run_counts[ (int) $recipe_id ] : 0;

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
