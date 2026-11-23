<?php

namespace Uncanny_Automator\Integrations\Sportspress;

use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Sportspress_Helpers
 *
 * Remote-data segments, option builders and readers for the SportsPress
 * integration.
 *
 * IDs from fields, tokens and SportsPress meta are cast with (int) and kept
 * only when they are above 0: SportsPress stores -1 for an empty team slot,
 * and absint( '-1' ) is 1 (R15).
 *
 * @package Uncanny_Automator\Integrations\Sportspress
 */
class Sportspress_Helpers extends Abstract_Helpers {

	/**
	 * "Any" sentinel for trigger selections.
	 */
	const ANY = '-1';

	/**
	 * Event post type.
	 */
	const EVENT = 'sp_event';

	/**
	 * Team post type.
	 */
	const TEAM = 'sp_team';

	/**
	 * Player post type.
	 */
	const PLAYER = 'sp_player';

	/**
	 * League taxonomy.
	 */
	const TAX_LEAGUE = 'sp_league';

	/**
	 * Season taxonomy.
	 */
	const TAX_SEASON = 'sp_season';

	/**
	 * Venue taxonomy.
	 */
	const TAX_VENUE = 'sp_venue';

	/**
	 * Position taxonomy.
	 */
	const TAX_POSITION = 'sp_position';

	/**
	 * Upper bound on eagerly loaded dropdown rows.
	 */
	const OPTIONS_LIMIT = 500;

	/**
	 * @var Sportspress_Event_Tokens|null
	 */
	private $event_tokens = null;

	/**
	 * @var Sportspress_Selections|null
	 */
	private $selections = null;

	// -------------------------------------------------------------------------
	// Remote data: taxonomy terms.
	// -------------------------------------------------------------------------

	/**
	 * @param mixed $request
	 *
	 * @return array
	 */
	protected function remote_data_get_leagues( $request ): array {
		return $this->remote_data_success( $this->term_options( self::TAX_LEAGUE, esc_html_x( 'Any league', 'SportsPress', 'uncanny-automator' ) ) );
	}

	/**
	 * @param mixed $request
	 *
	 * @return array
	 */
	protected function remote_data_get_leagues_strict( $request ): array {
		return $this->remote_data_success( $this->term_options( self::TAX_LEAGUE ) );
	}

	/**
	 * @param mixed $request
	 *
	 * @return array
	 */
	protected function remote_data_get_seasons( $request ): array {
		return $this->remote_data_success( $this->term_options( self::TAX_SEASON, esc_html_x( 'Any season', 'SportsPress', 'uncanny-automator' ) ) );
	}

	/**
	 * @param mixed $request
	 *
	 * @return array
	 */
	protected function remote_data_get_seasons_strict( $request ): array {
		return $this->remote_data_success( $this->term_options( self::TAX_SEASON ) );
	}

	/**
	 * Venues for the actions' optional Venue field. The empty first option keeps
	 * the builder from selecting a venue nobody chose.
	 *
	 * @param mixed $request
	 *
	 * @return array
	 */
	protected function remote_data_get_venues_strict( $request ): array {

		$options = array_merge(
			array(
				array(
					'text'  => esc_html_x( 'No venue', 'SportsPress', 'uncanny-automator' ),
					'value' => '',
				),
			),
			$this->term_options( self::TAX_VENUE )
		);

		return $this->remote_data_success( $options );
	}

	/**
	 * @param mixed $request
	 *
	 * @return array
	 */
	protected function remote_data_get_positions( $request ): array {
		return $this->remote_data_success( $this->term_options( self::TAX_POSITION, esc_html_x( 'Any position', 'SportsPress', 'uncanny-automator' ) ) );
	}

	/**
	 * @param mixed $request
	 *
	 * @return array
	 */
	protected function remote_data_get_positions_strict( $request ): array {
		return $this->remote_data_success( $this->term_options( self::TAX_POSITION ) );
	}

	// -------------------------------------------------------------------------
	// Remote data: entity posts (searchable).
	// -------------------------------------------------------------------------

	/**
	 * @param mixed $request
	 *
	 * @return array
	 */
	protected function remote_data_get_events( $request ): array {
		return $this->remote_data_success( $this->post_options( self::EVENT, $this->search_query( $request ), esc_html_x( 'Any event', 'SportsPress', 'uncanny-automator' ) ) );
	}

	/**
	 * @param mixed $request
	 *
	 * @return array
	 */
	protected function remote_data_get_events_strict( $request ): array {
		return $this->remote_data_success( $this->post_options( self::EVENT, $this->search_query( $request ) ) );
	}

	/**
	 * @param mixed $request
	 *
	 * @return array
	 */
	protected function remote_data_get_teams( $request ): array {
		return $this->remote_data_success( $this->post_options( self::TEAM, $this->search_query( $request ), esc_html_x( 'Any team', 'SportsPress', 'uncanny-automator' ) ) );
	}

	/**
	 * @param mixed $request
	 *
	 * @return array
	 */
	protected function remote_data_get_teams_strict( $request ): array {
		return $this->remote_data_success( $this->post_options( self::TEAM, $this->search_query( $request ) ) );
	}

	/**
	 * @param mixed $request
	 *
	 * @return array
	 */
	protected function remote_data_get_players_strict( $request ): array {
		return $this->remote_data_success( $this->post_options( self::PLAYER, $this->search_query( $request ) ) );
	}

	/**
	 * The results repeater of "Update the results of {{an event}}": its Team
	 * select offers the chosen event's participants, and it starts with one row
	 * per participant, as SportsPress's inline results editor does.
	 *
	 * An event given as a token has no participants to offer yet, so the Team
	 * select then takes IDs or tokens only.
	 *
	 * @param mixed $request
	 *
	 * @return array
	 */
	protected function remote_data_get_event_teams_strict( $request ): array {

		$event_id = $this->positive_id( $this->request_field_value( $request, 'SP_EVENT' ) );
		$options  = array();
		$rows     = array();

		if ( null !== $this->get_entity( $event_id, self::EVENT ) ) {
			foreach ( $this->event_team_ids( $event_id ) as $team_id ) {
				$options[] = array(
					'text'  => esc_html( get_post_field( 'post_title', $team_id ) ),
					'value' => (string) $team_id,
				);
				$rows[]    = array(
					'SP_RESULT_TEAM'  => (string) $team_id,
					'SP_RESULT_SCORE' => '',
				);
			}
		}

		if ( empty( $rows ) ) {
			$rows[] = array(
				'SP_RESULT_TEAM'  => '',
				'SP_RESULT_SCORE' => '',
			);
		}

		$response         = $this->remote_data_success( array( 'fields' => $this->result_row_fields( $options ) ), 'field_properties' );
		$response['rows'] = $rows;

		return $response;
	}

	/**
	 * The results repeater's row fields.
	 *
	 * @param array $team_options
	 *
	 * @return array
	 */
	public function result_row_fields( $team_options ) {
		return array(
			array(
				'option_code'           => 'SP_RESULT_TEAM',
				'label'                 => esc_html_x( 'Team', 'SportsPress', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'supports_custom_value' => true,
				'options'               => $team_options,
			),
			array(
				'option_code' => 'SP_RESULT_SCORE',
				'label'       => esc_html_x( 'Result', 'SportsPress', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
				'tokens'      => true,
			),
		);
	}

	// -------------------------------------------------------------------------
	// Option builders.
	// -------------------------------------------------------------------------

	/**
	 * Options for a taxonomy, children indented under their parent.
	 *
	 * @param string $taxonomy
	 * @param string $any_label Empty string omits "Any".
	 *
	 * @return array
	 */
	public function term_options( $taxonomy, $any_label = '' ) {

		$options = $this->any_option( $any_label );

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => self::OPTIONS_LIMIT,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return $options;
		}

		$by_parent = array();

		foreach ( $terms as $term ) {
			$by_parent[ (int) $term->parent ][] = $term;
		}

		return array_merge( $options, $this->flatten_terms( $by_parent, 0, 0 ) );
	}

	/**
	 * Options for a SportsPress post type.
	 *
	 * @param string $post_type
	 * @param string $search
	 * @param string $any_label Empty string omits "Any".
	 *
	 * @return array
	 */
	public function post_options( $post_type, $search = '', $any_label = '' ) {

		$options = $this->any_option( $any_label );

		$args = array(
			'post_type'      => $post_type,
			'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'posts_per_page' => self::OPTIONS_LIMIT,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		);

		if ( '' !== (string) $search ) {
			$args['s'] = (string) $search;
		}

		foreach ( get_posts( $args ) as $post ) {
			$options[] = array(
				'text'  => esc_html( $post->post_title ),
				'value' => (string) $post->ID,
			);
		}

		return $options;
	}

	/**
	 * Event formats as select options, from SportsPress's own list, which
	 * includes Tournament when SportsPress Pro's Tournaments module is on.
	 *
	 * @return array
	 */
	public function format_options() {

		$options = array();

		foreach ( $this->get_event_formats() as $value => $label ) {
			$options[] = array(
				'text'  => esc_html( $label ),
				'value' => (string) $value,
			);
		}

		return $options;
	}

	/**
	 * Event formats, value => label.
	 *
	 * @return array<string, string>
	 */
	public function get_event_formats() {

		if ( ! function_exists( 'SP' ) || ! is_object( SP()->formats ) ) {
			return array();
		}

		return (array) SP()->formats->event;
	}

	/**
	 * WordPress post statuses with SportsPress's labels for Published and
	 * Scheduled.
	 *
	 * @return array<string, string>
	 */
	public function post_status_labels() {
		return array(
			'publish' => esc_html_x( 'Published', 'SportsPress', 'uncanny-automator' ),
			'future'  => esc_html_x( 'Scheduled', 'SportsPress', 'uncanny-automator' ),
			'draft'   => esc_html_x( 'Draft', 'SportsPress', 'uncanny-automator' ),
			'pending' => esc_html_x( 'Pending', 'SportsPress', 'uncanny-automator' ),
			'private' => esc_html_x( 'Private', 'SportsPress', 'uncanny-automator' ),
		);
	}

	/**
	 * Static options for the given post statuses, in the given order.
	 *
	 * @param string[] $statuses
	 * @param string   $any_label Empty string omits "Any".
	 *
	 * @return array
	 */
	public function post_status_options( $statuses, $any_label = '' ) {

		$labels  = $this->post_status_labels();
		$options = $this->any_option( $any_label );

		foreach ( (array) $statuses as $status ) {
			if ( isset( $labels[ $status ] ) ) {
				$options[] = array(
					'text'  => $labels[ $status ],
					'value' => $status,
				);
			}
		}

		return $options;
	}

	/**
	 * A post status's label, or the raw value.
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	public function post_status_label( $status ) {

		$labels = $this->post_status_labels();
		$status = (string) $status;

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/**
	 * SportsPress's countries, lower-case three-letter code => name.
	 *
	 * @return array<string, string>
	 */
	public function get_countries() {

		if ( ! function_exists( 'SP' ) || ! is_object( SP()->countries ) ) {
			return array();
		}

		return (array) SP()->countries->countries;
	}

	// -------------------------------------------------------------------------
	// Readers.
	// -------------------------------------------------------------------------

	/**
	 * @return bool
	 */
	public function plugin_loaded() {
		return defined( 'SP_PLUGIN_FILE' );
	}

	/**
	 * A positive integer ID, or 0.
	 *
	 * @param mixed $value
	 *
	 * @return int
	 */
	public function positive_id( $value ) {

		if ( ! is_scalar( $value ) ) {
			return 0;
		}

		$id = (int) $value;

		return $id > 0 ? $id : 0;
	}

	/**
	 * A list of positive integer IDs from an array, a JSON array or a comma list.
	 *
	 * @param mixed $value
	 *
	 * @return int[]
	 */
	public function to_id_list( $value ) {

		if ( ! is_array( $value ) ) {
			$decoded = json_decode( (string) $value, true );
			$value   = is_array( $decoded ) ? $decoded : explode( ',', (string) $value );
		}

		$ids = array();

		foreach ( $value as $item ) {
			$id = $this->positive_id( is_string( $item ) ? trim( $item ) : $item );
			if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * @param array  $parsed
	 * @param string $option_code
	 *
	 * @return array{ids: int[], invalid: string[], custom: bool}
	 */
	public function selected_ids( $parsed, $option_code ) {
		return $this->selections()->selected_ids( $parsed, $option_code );
	}

	/**
	 * @param array  $parsed
	 * @param string $option_code
	 * @param string $post_type
	 * @param string $label
	 *
	 * @return array{ids: int[], error: string}
	 */
	public function selected_posts( $parsed, $option_code, $post_type, $label ) {
		return $this->selections()->selected_posts( $parsed, $option_code, $post_type, $label );
	}

	/**
	 * @param array  $parsed
	 * @param string $option_code
	 * @param string $taxonomy
	 * @param string $label
	 *
	 * @return array{ids: int[], error: string}
	 */
	public function selected_terms( $parsed, $option_code, $taxonomy, $label ) {
		return $this->selections()->selected_terms( $parsed, $option_code, $taxonomy, $label );
	}

	/**
	 * A post of the expected type, or null.
	 *
	 * @param mixed  $post_id
	 * @param string $post_type
	 *
	 * @return \WP_Post|null
	 */
	public function get_entity( $post_id, $post_type ) {

		$post_id = $this->positive_id( $post_id );

		if ( 0 === $post_id ) {
			return null;
		}

		$post = get_post( $post_id );

		return ( $post instanceof \WP_Post && $post_type === $post->post_type ) ? $post : null;
	}

	/**
	 * Term IDs attached to a post.
	 *
	 * @param int    $post_id
	 * @param string $taxonomy
	 *
	 * @return int[]
	 */
	public function term_ids( $post_id, $taxonomy ) {

		$terms = wp_get_post_terms( (int) $post_id, $taxonomy, array( 'fields' => 'ids' ) );

		return is_wp_error( $terms ) ? array() : array_map( 'intval', $terms );
	}

	/**
	 * Whether a post carries the selected term. "Any" matches; a missing
	 * selection fails closed.
	 *
	 * @param int         $post_id
	 * @param string      $taxonomy
	 * @param string|null $selected
	 *
	 * @return bool
	 */
	public function matches_term( $post_id, $taxonomy, $selected ) {

		if ( null === $selected ) {
			return false;
		}

		if ( self::ANY === (string) $selected ) {
			return true;
		}

		$term_id = $this->positive_id( $selected );

		return $term_id > 0 && in_array( $term_id, $this->term_ids( $post_id, $taxonomy ), true );
	}

	/**
	 * An event's participants: team IDs, or player IDs in player mode, in the
	 * event's order. Empty slots are dropped.
	 *
	 * @param int $event_id
	 *
	 * @return int[]
	 */
	public function event_team_ids( $event_id ) {

		if ( ! function_exists( 'sp_get_teams' ) ) {
			return array();
		}

		return $this->to_id_list( (array) sp_get_teams( (int) $event_id ) );
	}

	/**
	 * A player's current team IDs.
	 *
	 * @param int $player_id
	 *
	 * @return int[]
	 */
	public function player_current_team_ids( $player_id ) {

		if ( ! class_exists( '\SP_Player' ) ) {
			return array();
		}

		return $this->to_id_list( (array) ( new \SP_Player( (int) $player_id ) )->current_teams() );
	}

	/**
	 * Names and IDs of a list of terms, in the shared list format: names joined
	 * with ", " and IDs joined with ",".
	 *
	 * @param \WP_Term[]|false|\WP_Error $terms
	 *
	 * @return array{names: string, ids: string}
	 */
	public function term_lists( $terms ) {

		$names = array();
		$ids   = array();

		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( $term instanceof \WP_Term && (int) $term->term_id > 0 ) {
				$names[] = $term->name;
				$ids[]   = (int) $term->term_id;
			}
		}

		return array(
			'names' => implode( ', ', $names ),
			'ids'   => implode( ',', $ids ),
		);
	}

	/**
	 * Titles and IDs of a list of post IDs, in the shared list format.
	 *
	 * @param mixed $post_ids
	 *
	 * @return array{names: string, ids: string}
	 */
	public function post_lists( $post_ids ) {

		$ids   = $this->to_id_list( (array) $post_ids );
		$names = array();

		// One name per ID, so the two lists stay in step.
		foreach ( $ids as $id ) {
			$title   = (string) get_post_field( 'post_title', $id );
			$names[] = '' === $title ? esc_html_x( '(no title)', 'SportsPress', 'uncanny-automator' ) : $title;
		}

		return array(
			'names' => implode( ', ', $names ),
			'ids'   => implode( ',', $ids ),
		);
	}

	/**
	 * A required field's saved value, or null when it is missing or empty, so
	 * the caller fails closed instead of widening to "Any".
	 *
	 * @param array  $trigger
	 * @param string $option_code
	 *
	 * @return string|null
	 */
	public function required_meta_value( $trigger, $option_code ) {

		if ( ! isset( $trigger['meta'][ $option_code ] ) ) {
			return null;
		}

		$value = (string) $trigger['meta'][ $option_code ];

		return '' === $value ? null : $value;
	}

	// -------------------------------------------------------------------------
	// Writers.
	// -------------------------------------------------------------------------

	/**
	 * Insert a SportsPress post with the submitted form set aside.
	 *
	 * SportsPress's admin savers on save_post (the meta boxes, the player quick
	 * edit) write from $_POST. An action that runs while another SportsPress
	 * post is being saved would otherwise give the new post that form's teams,
	 * results and details.
	 *
	 * @param array $args wp_insert_post() arguments.
	 *
	 * @return int|\WP_Error
	 */
	public function insert_post( $args ) {

		$form  = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- set aside, not read.
		$_POST = array();

		try {
			return wp_insert_post( $args, true );
		} finally {
			$_POST = $form;
		}
	}

	/**
	 * Add one non-unique meta row per value, as SportsPress does.
	 *
	 * @param int    $post_id
	 * @param string $key
	 * @param array  $values
	 *
	 * @return void
	 */
	public function add_multi_meta( $post_id, $key, $values ) {

		$values = array_values(
			array_filter(
				(array) $values,
				function ( $value ) {
					return is_scalar( $value ) && '' !== trim( (string) $value );
				}
			)
		);

		if ( empty( $values ) || ! function_exists( 'sp_add_post_meta_recursive' ) ) {
			return;
		}

		sp_add_post_meta_recursive( (int) $post_id, $key, $values );
	}

	/**
	 * Assign terms by ID. Integers only: a numeric string would create a new
	 * term named after the number.
	 *
	 * @param int    $post_id
	 * @param string $taxonomy
	 * @param int[]  $term_ids
	 *
	 * @return void
	 */
	public function set_terms( $post_id, $taxonomy, $term_ids ) {

		$term_ids = $this->to_id_list( $term_ids );

		if ( empty( $term_ids ) ) {
			return;
		}

		wp_set_object_terms( (int) $post_id, $term_ids, $taxonomy, false );
	}

	// -------------------------------------------------------------------------
	// Tokens.
	// -------------------------------------------------------------------------

	/**
	 * @param string[] $groups Any of core, status, details, relations.
	 *
	 * @return array
	 */
	public function event_token_definitions( $groups ) {
		return $this->event_tokens()->definitions( $groups );
	}

	/**
	 * @param int      $event_id
	 * @param string[] $groups
	 *
	 * @return array
	 */
	public function hydrate_event_tokens( $event_id, $groups ) {
		return $this->event_tokens()->hydrate( $event_id, $groups );
	}

	/**
	 * A trigger token definition.
	 *
	 * @param string $id
	 * @param string $name
	 * @param string $type
	 *
	 * @return array
	 */
	public function token_def( $id, $name, $type ) {
		return array(
			'tokenId'   => $id,
			'tokenName' => $name,
			'tokenType' => $type,
		);
	}

	/**
	 * Every token ID in a definition list mapped to an empty string.
	 *
	 * @param array $definitions
	 *
	 * @return array
	 */
	public function empty_keyset( $definitions ) {
		return array_fill_keys( wp_list_pluck( $definitions, 'tokenId' ), '' );
	}

	// -------------------------------------------------------------------------
	// Internals.
	// -------------------------------------------------------------------------

	/**
	 * @return Sportspress_Event_Tokens
	 */
	private function event_tokens() {

		if ( null === $this->event_tokens ) {
			$this->event_tokens = new Sportspress_Event_Tokens( $this );
		}

		return $this->event_tokens;
	}

	/**
	 * @return Sportspress_Selections
	 */
	private function selections() {

		if ( null === $this->selections ) {
			$this->selections = new Sportspress_Selections( $this );
		}

		return $this->selections;
	}

	/**
	 * @param string $any_label
	 *
	 * @return array
	 */
	private function any_option( $any_label ) {

		if ( '' === $any_label ) {
			return array();
		}

		return array(
			array(
				'text'  => $any_label,
				'value' => self::ANY,
			),
		);
	}

	/**
	 * Depth-first flatten of terms grouped by parent into indented options.
	 *
	 * @param array $by_parent
	 * @param int   $parent_id
	 * @param int   $depth
	 *
	 * @return array
	 */
	private function flatten_terms( $by_parent, $parent_id, $depth ) {

		if ( ! isset( $by_parent[ $parent_id ] ) || $depth > 10 ) {
			return array();
		}

		$options = array();

		foreach ( $by_parent[ $parent_id ] as $term ) {
			$options[] = array(
				'text'  => esc_html( str_repeat( '— ', $depth ) . $term->name ),
				'value' => (string) $term->term_id,
			);
			$options   = array_merge( $options, $this->flatten_terms( $by_parent, (int) $term->term_id, $depth + 1 ) );
		}

		return $options;
	}

	/**
	 * A field's value on a remote-data request, unwrapping a custom value.
	 *
	 * @param mixed  $request
	 * @param string $option_code
	 *
	 * @return string
	 */
	private function request_field_value( $request, $option_code ) {

		if ( ! is_object( $request ) || ! method_exists( $request, 'get_field_value' ) ) {
			return '';
		}

		$value = (string) $request->get_field_value( $option_code );

		if ( 'automator_custom_value' === $value ) {
			$value = (string) $request->get_field_value( $option_code . '_custom' );
		}

		return $value;
	}

	/**
	 * @param mixed $request
	 *
	 * @return string
	 */
	private function search_query( $request ) {

		if ( ! is_object( $request ) || ! method_exists( $request, 'get_search_query' ) ) {
			return '';
		}

		return (string) $request->get_search_query();
	}
}
