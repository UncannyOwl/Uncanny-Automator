<?php

namespace Uncanny_Automator\Integrations\Uncanny_Ceus;

use Uncanny_Automator\Recipe\Trigger;
use Uncanny_Automator\Recipe\Trigger_Definition;

/**
 * Class UNCANNYCEUS_EARNSCEUS
 *
 * Fires when a user's total CEU balance reaches or exceeds the configured number.
 *
 * @package Uncanny_Automator\Integrations\Uncanny_Ceus
 *
 * @property Uncanny_Ceus_Helpers $item_helpers
 */
class UNCANNYCEUS_EARNSCEUS extends Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 *
	 * @return Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'EARNSCEUS', 'UNCANNYCEUS' )
			->trigger_meta( 'AMOUNTSCEUS' )
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

		// translators: %1$s is the credit designation label (plural), %2$s is the option code.
		$this->set_sentence( sprintf( esc_html_x( 'The total number of %1$s earned by a user is greater than or equal to {{a specific number:%2$s}}', 'Uncanny CEUs', 'uncanny-automator' ), $credit_designation_label_plural, $this->get_trigger_meta() ) );
		// translators: %1$s is the credit designation label (plural).
		$this->set_readable_sentence( sprintf( esc_html_x( 'The total number of %1$s earned by a user is greater than or equal to {{a specific number}}', 'Uncanny CEUs', 'uncanny-automator' ), $credit_designation_label_plural ) );
	}

	/**
	 * Trigger options.
	 *
	 * @return array[]
	 */
	public function options() {

		// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
		$credit_designation_label_plural = get_option( 'credit_designation_label_plural', __( 'CEUs', 'uncanny-ceu' ) );

		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				// translators: %1$s is the credit designation label (plural).
				'label'           => sprintf( esc_html_x( 'Number of %1$s', 'Uncanny CEUs', 'uncanny-automator' ), $credit_designation_label_plural ),
				'input_type'      => 'float',
				'validation_type' => 'integer',
				'required'        => true,
			),
		);
	}

	/**
	 * Validate the trigger against the hook arguments.
	 *
	 * Compares the user's total CEU balance (via CeuShortcodes::uo_ceu_total)
	 * against the number entered in the recipe.
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

		if ( ! class_exists( '\\uncanny_ceu\\Utilities' ) ) {
			return false;
		}

		$current_user = $hook_args[0] ?? null;
		$ceu_value    = (float) ( $hook_args[6] ?? 0 );

		if ( ! is_object( $current_user ) || empty( $current_user->ID ) ) {
			return false;
		}

		if ( 0.0 === $ceu_value ) {
			return false;
		}

		$ceu_shortcodes = \uncanny_ceu\Utilities::get_class_instance( 'CeuShortcodes' );

		if ( ! is_object( $ceu_shortcodes ) ) {
			return false;
		}

		$total_ceus = (float) $ceu_shortcodes->uo_ceu_total( array( 'user-id' => $current_user->ID ) );

		if ( 0.0 === $total_ceus ) {
			return false;
		}

		$required = (float) $trigger['meta'][ $this->get_trigger_meta() ];

		return $total_ceus >= $required;
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
		return array_merge( $tokens, $this->item_helpers->tokens()->ceus_award_tokens() );
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
		return $this->item_helpers->tokens()->hydrate_ceus_award_tokens( $hook_args );
	}
}
