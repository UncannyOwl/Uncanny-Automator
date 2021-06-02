<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator;

/**
 * Class CF_SUBFORM
 * @package Uncanny_Automator
 */
class CF_SUBFORM {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'CF';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {

		add_filter( 'wpcf_verify_nonce', '__return_true' );

		$this->trigger_code = 'CFSUBFORM';
		$this->trigger_meta = 'CFFORMS';
		$this->define_trigger();

	}


	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/caldera-forms/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - Caldera Forms */
			'sentence'            => sprintf( esc_attr__( 'A user submits {{a form:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - Caldera Forms */
			'select_option_name'  => esc_attr__( 'A user submits {{a form}}', 'uncanny-automator' ),
			'action'              => 'caldera_forms_submit_complete',
			'priority'            => 99,
			'accepted_args'       => 4,
			'validation_function' => array( $this, 'caldera_forms_submit' ),
			'options'             => array(
				Automator()->helpers->recipe->caldera_forms->options->list_caldera_forms_forms(),
				Automator()->helpers->recipe->options->number_of_times(),
			),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $form
	 * @param $referrer
	 */
	public function caldera_forms_submit( $form, $referrer, $process_id, $entryid ) {

		$user_id    = wp_get_current_user()->ID;
		$recipes    = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$conditions = $this->match_condition( $form, $recipes, $this->trigger_meta, $this->trigger_code );

		if ( ! $conditions ) {
			return;
		}

		if ( ! empty( $conditions ) ) {
			foreach ( $conditions['recipe_ids'] as $recipe_id ) {
				if ( ! Automator()->is_recipe_completed( $recipe_id, $user_id ) ) {
					$args = array(
						'code'            => $this->trigger_code,
						'meta'            => $this->trigger_meta,
						'recipe_to_match' => $recipe_id,
						'ignore_post_id'  => true,
						'user_id'         => $user_id,
					);

					Automator()->maybe_add_trigger_entry( $args );
				}
			}
		}
	}

	/**
	 * Matching Form id because its not an integer.
	 *
	 * @param array $form .
	 * @param array $recipes .
	 * @param string $trigger_meta .
	 * @param string $trigger_code .
	 *
	 * @return array|bool
	 */
	public function match_condition( $form, $recipes = null, $trigger_meta = null, $trigger_code = null ) {

		if ( null === $recipes ) {
			return false;
		}

		$recipe_ids     = array();
		$entry_to_match = $form['ID'];

		foreach ( $recipes as $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				if ( key_exists( $trigger_meta, $trigger['meta'] ) && (string) $trigger['meta'][ $trigger_meta ] === (string) $entry_to_match ) {
					$recipe_ids[ $recipe['ID'] ] = $recipe['ID'];
					break;
				}
			}
		}

		if ( ! empty( $recipe_ids ) ) {
			return array(
				'recipe_ids' => $recipe_ids,
				'result'     => true,
			);
		}

		return false;
	}
}
