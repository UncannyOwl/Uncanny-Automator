<?php

namespace Uncanny_Automator\Integrations\The_Events_Calendar;

use Uncanny_Automator\Recipe\Trigger_Definition;

/**
 * Class EC_EVENT_LINKED
 *
 * Trigger: An event is linked to a related post (organizer, venue, or
 * any third-party linked-post type).
 *
 * @property The_Events_Calendar_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 */
class EC_EVENT_LINKED extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Opt into the lazy trigger loading path.
	 *
	 * @return Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'EC_EVENT_LINKED', 'EC' )
			->trigger_meta( 'ECEVENTS' )
			->trigger_type( 'anonymous' )
			->hook( 'tribe_events_link_post', 10, 2 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type / hook are auto-applied from definition().

		$this->set_is_login_required( false );
		$this->set_support_link( \Automator()->get_author_support_link( $this->get_trigger_code(), 'integration/the-events-calendar/' ) );

		/* translators: %1$s is the event field token, %2$s is the related-post-type sub-select */
		$this->set_sentence( sprintf( esc_html_x( '{{An event:%1$s}} is linked to {{a related post:%2$s}}', 'The Events Calendar', 'uncanny-automator' ), $this->get_trigger_meta(), 'EC_LINKED_POST_TYPE:' . $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_html_x( '{{An event}} is linked to {{a related post}}', 'The Events Calendar', 'uncanny-automator' ) );
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function options() {
		return array(
			array(
				'input_type'            => 'select',
				'option_code'           => $this->get_trigger_meta(),
				'label'                 => esc_html_x( 'Event', 'The Events Calendar', 'uncanny-automator' ),
				'required'              => true,
				'options'               => array(),
				'supports_custom_value' => false,
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'events' ),
			),
			array(
				'input_type'  => 'select',
				'option_code' => 'EC_LINKED_POST_TYPE',
				'label'       => esc_html_x( 'Related post type', 'The Events Calendar', 'uncanny-automator' ),
				'required'    => true,
				'options'     => array(
					array( 'text' => esc_html_x( 'Any', 'The Events Calendar', 'uncanny-automator' ), 'value' => '-1' ),
					array( 'text' => esc_html_x( 'Organizer', 'The Events Calendar', 'uncanny-automator' ), 'value' => 'tribe_organizer' ),
					array( 'text' => esc_html_x( 'Venue', 'The Events Calendar', 'uncanny-automator' ), 'value' => 'tribe_venue' ),
					array( 'text' => esc_html_x( 'Custom', 'The Events Calendar', 'uncanny-automator' ), 'value' => 'custom' ),
				),
			),
		);
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		if ( ! isset( $hook_args[0], $hook_args[1] ) ) {
			return false;
		}

		$target  = absint( $hook_args[0] );
		$subject = absint( $hook_args[1] );

		if ( 0 === $target || 0 === $subject ) {
			return false;
		}

		$selected_event = isset( $trigger['meta'][ $this->get_trigger_meta() ] )
			? (string) $trigger['meta'][ $this->get_trigger_meta() ]
			: '';

		if ( '' === $selected_event ) {
			return false;
		}

		if ( '-1' !== $selected_event && absint( $selected_event ) !== $target ) {
			return false;
		}

		$selected_type = isset( $trigger['meta']['EC_LINKED_POST_TYPE'] )
			? (string) $trigger['meta']['EC_LINKED_POST_TYPE']
			: '-1';

		if ( '-1' === $selected_type ) {
			return true;
		}

		$actual_type = (string) get_post_type( $subject );

		if ( 'custom' === $selected_type ) {
			return ! in_array( $actual_type, array( 'tribe_organizer', 'tribe_venue' ), true );
		}

		return $actual_type === $selected_type;
	}

	/**
	 * @param array $trigger
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge(
			$tokens,
			$this->item_helpers->tokens()->event_tokens( $this->get_trigger_meta() ),
			array(
				array(
					'tokenId'   => 'LINKED_POST_ID',
					'tokenName' => esc_html_x( 'Linked post ID', 'The Events Calendar', 'uncanny-automator' ),
					'tokenType' => 'int',
				),
				array(
					'tokenId'   => 'LINKED_POST_TYPE',
					'tokenName' => esc_html_x( 'Linked post type', 'The Events Calendar', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'LINKED_POST_TITLE',
					'tokenName' => esc_html_x( 'Linked post title', 'The Events Calendar', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
			)
		);
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array<string,string>
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$target  = isset( $hook_args[0] ) ? absint( $hook_args[0] ) : 0;
		$subject = isset( $hook_args[1] ) ? absint( $hook_args[1] ) : 0;

		$event_tokens = $this->item_helpers->tokens()->hydrate_event_tokens( $target, $this->get_trigger_meta() );

		$subject_post = $subject ? get_post( $subject ) : null;

		// Resolve the auto-registered EC_LINKED_POST_TYPE field-token. Without an
		// explicit value here the framework falls back to the trigger_meta's
		// hydrated value, which surfaces the event title under the "Related post
		// type" label in the action's token list.
		$selected_type = isset( $trigger['meta']['EC_LINKED_POST_TYPE'] )
			? (string) $trigger['meta']['EC_LINKED_POST_TYPE']
			: '-1';
		$type_labels   = array(
			'-1'              => esc_html_x( 'Any related post', 'The Events Calendar', 'uncanny-automator' ),
			'tribe_organizer' => esc_html_x( 'Organizer', 'The Events Calendar', 'uncanny-automator' ),
			'tribe_venue'     => esc_html_x( 'Venue', 'The Events Calendar', 'uncanny-automator' ),
			'custom'          => esc_html_x( 'Custom', 'The Events Calendar', 'uncanny-automator' ),
		);

		return array_merge(
			$event_tokens,
			array(
				'EC_LINKED_POST_TYPE' => $type_labels[ $selected_type ] ?? $selected_type,
				'LINKED_POST_ID'      => (string) $subject,
				'LINKED_POST_TYPE'    => $subject_post instanceof \WP_Post ? (string) $subject_post->post_type : '',
				'LINKED_POST_TITLE'   => $subject_post instanceof \WP_Post ? (string) $subject_post->post_title : '',
			)
		);
	}
}
