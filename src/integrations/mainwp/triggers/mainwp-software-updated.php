<?php

namespace Uncanny_Automator\Integrations\Mainwp;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Mainwp_Software_Updated
 *
 * Trigger: {{A plugin, theme or translation}} is updated on a child site.
 *
 * MainWP fires the hook as soon as the child answers, before it reads the
 * result, and with a null answer when the Abilities API batch cron's request
 * fails. The trigger runs only when at least one item reports success, which is
 * MainWP's own per-item test.
 *
 * @property Mainwp_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 * @since 8.1.0
 */
class Mainwp_Software_Updated extends Trigger {

	/**
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'MAINWP_SOFTWARE_UPDATED', 'MAINWP' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'MAINWP_SOFTWARE_TYPE' )
			->hook( 'mainwp_after_plugin_theme_translation_update', 10, 4 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_sentence(
			sprintf(
				/* translators: %1$s: Plugin, theme or translation */
				esc_html_x( '{{A plugin, theme or translation:%1$s}} is updated on a child site', 'MainWP', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( '{{A plugin, theme or translation}} is updated on a child site', 'MainWP', 'uncanny-automator' ) );
	}

	/**
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code'           => $this->get_trigger_meta(),
				'label'                 => esc_html_x( 'Type', 'MainWP', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => $this->item_helpers->get_software_type_options( true ),
				'relevant_tokens'       => array(),
				'supports_custom_value' => false,
				'options_show_id'       => false,
			),
		);
	}

	/**
	 * @param array $trigger The trigger settings.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge(
			$tokens,
			Mainwp_Tokens::site_tokens(),
			array(
				array(
					'tokenId'   => 'UPDATE_TYPE',
					'tokenName' => esc_html_x( 'Type', 'MainWP', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'UPDATE_SLUGS',
					'tokenName' => esc_html_x( 'Slugs', 'MainWP', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'UPDATED_ITEMS',
					'tokenName' => esc_html_x( 'Updated items', 'MainWP', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
			)
		);
	}

	/**
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args `$information`, `$type`, `$slugs`, `$site`.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		$selected = (string) ( $trigger['meta'][ $this->get_trigger_meta() ] ?? '' );
		$type     = is_string( $hook_args[1] ?? null ) ? $hook_args[1] : '';

		if ( '' === $selected || '' === $type ) {
			return false;
		}

		if ( '-1' !== $selected && $selected !== $type ) {
			return false;
		}

		return array() !== $this->updated_items( $hook_args[0] ?? null );
	}

	/**
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args `$information`, `$type`, `$slugs`, `$site`.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$type   = is_string( $hook_args[1] ?? null ) ? $hook_args[1] : '';
		$labels = $this->item_helpers->get_software_type_labels();

		return array_merge(
			Mainwp_Tokens::hydrate_site_tokens( $hook_args[3] ?? null ),
			array(
				'UPDATE_TYPE'   => $labels[ $type ] ?? $type,
				'UPDATE_SLUGS'  => implode( ', ', $this->decode_slugs( $hook_args[2] ?? '' ) ),
				'UPDATED_ITEMS' => implode( ', ', $this->updated_items( $hook_args[0] ?? null ) ),
			)
		);
	}

	/**
	 * The items the child reports as updated.
	 *
	 * @param mixed $information The child's answer.
	 *
	 * @return string[]
	 */
	private function updated_items( $information ) {

		if ( ! is_array( $information ) || empty( $information['upgrades'] ) || ! is_array( $information['upgrades'] ) ) {
			return array();
		}

		return array_map( 'strval', array_keys( array_filter( $information['upgrades'] ) ) );
	}

	/**
	 * The comma-joined slug list. The dashboard's Update buttons send plugin slugs
	 * URL-encoded, so each item is decoded after the split.
	 *
	 * @param mixed $slugs The list as MainWP passes it.
	 *
	 * @return string[]
	 */
	private function decode_slugs( $slugs ) {

		if ( ! is_string( $slugs ) || '' === $slugs ) {
			return array();
		}

		return array_values( array_filter( array_map( 'trim', array_map( 'rawurldecode', explode( ',', $slugs ) ) ) ) );
	}
}
