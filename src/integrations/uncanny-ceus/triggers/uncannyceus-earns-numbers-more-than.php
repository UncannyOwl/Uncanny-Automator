<?php

namespace Uncanny_Automator\Integrations\Uncanny_Ceus;

use Uncanny_Automator\Recipe\Trigger;
use Uncanny_Automator\Recipe\Trigger_Definition;

/**
 * Class UNCANNYCEUS_EARNS_NUMBERS_MORE_THAN
 *
 * Fires when a single CEU award is greater than or equal to the configured number.
 *
 * @package Uncanny_Automator\Integrations\Uncanny_Ceus
 *
 * @property Uncanny_Ceus_Helpers $item_helpers
 */
class UNCANNYCEUS_EARNS_NUMBERS_MORE_THAN extends Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 *
	 * @return Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'EARNS_NUMBERS_MORE_THAN', 'UNCANNYCEUS' )
			->trigger_meta( 'CEUS_EARN_NUMBERS' )
			->hook( 'ceus_after_updated_user_ceu_record', 20, 7 );
	}

	/**
	 * Skip registration when the host plugin doesn't expose the award hook.
	 *
	 * @return bool
	 */
	public function requirements_met() {

		if ( ! class_exists( '\\uncanny_ceu\\Utilities' ) ) {
			return false;
		}

		$version = \uncanny_ceu\Utilities::get_version();

		return version_compare( $version, '3.0.6', '>' );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type / hook are auto-applied from definition().

		// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
		$credit_designation_label_plural = get_option( 'credit_designation_label_plural', __( 'CEUs', 'uncanny-ceu' ) );

		// translators: %1$s is the option code, %2$s is the credit designation label (plural).
		$this->set_sentence( sprintf( esc_html_x( 'A user earns {{number:%1$s}} or more %2$s', 'Uncanny CEUs', 'uncanny-automator' ), $this->get_trigger_meta(), $credit_designation_label_plural ) );
		// translators: %1$s is the credit designation label (plural).
		$this->set_readable_sentence( sprintf( esc_html_x( 'A user earns {{a number of}} or more %1$s', 'Uncanny CEUs', 'uncanny-automator' ), $credit_designation_label_plural ) );
	}

	/**
	 * Trigger options.
	 *
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'input_type'      => 'float',
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Number', 'Uncanny CEUs', 'uncanny-automator' ),
				'validation_type' => 'float',
				'required'        => true,
				'relevant_tokens' => array(),
			),
		);
	}

	/**
	 * Validate the trigger against the hook arguments.
	 *
	 * @param array $trigger   The trigger configuration.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		if ( ! isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ) {
			return false;
		}

		$numbers_entered = (float) $trigger['meta'][ $this->get_trigger_meta() ];
		$ceu_value       = (float) ( $hook_args[6] ?? 0 );

		return $ceu_value >= $numbers_entered;
	}

	/**
	 * Define tokens emitted by this trigger.
	 *
	 * @param array $trigger The trigger configuration.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge( $tokens, $this->item_helpers->tokens()->ceus_threshold_tokens() );
	}

	/**
	 * Hydrate tokens from the hook arguments.
	 *
	 * @param array $trigger   The trigger configuration.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		return $this->item_helpers->tokens()->hydrate_ceus_threshold_tokens( $hook_args );
	}
}
