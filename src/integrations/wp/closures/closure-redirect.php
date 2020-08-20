<?php

namespace Uncanny_Automator;

/**
 * Class CLOSURE_REDIRECT
 * @package Uncanny_Automator
 */
class Closure_Redirect {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'WP';

	private $closure_code;
	private $closure_meta;

	/**
	 * Set up Automator closure constructor.
	 */
	public function __construct() {
		$this->closure_code = 'REDIRECT';
		$this->closure_meta = 'REDIRECTURL';
		$this->define_closure();
	}

	/**
	 * Define and register the closure by pushing it into the Automator object
	 */
	public function define_closure() {

		global $uncanny_automator;

		$closure = array(
			'author'             => $uncanny_automator->get_author_name( $this->closure_code ),
			'support_link'       => $uncanny_automator->get_author_support_link( $this->closure_code ),
			'integration'        => self::$integration,
			'code'               => $this->closure_code,
			/* translators: Closure - WordPress */
			'sentence'           => sprintf(  esc_attr__( 'Redirect to {{a link:%1$s}} when recipe is completed', 'uncanny-automator' ), $this->closure_meta ),
			/* translators: Closure - WordPress */
			'select_option_name' =>  esc_attr__( 'Redirect when recipe is completed', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'redirect' ),
			'options'            => [
				$uncanny_automator->helpers->recipe->get_redirect_url(),
			],
		);

		$uncanny_automator->register->closure( $closure );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 * @param $closure_data
	 * @param $recipe_id
	 * @param $args
	 */
	public function redirect( $user_id, $closure_data, $recipe_id, $args ) {

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			$redirect_url = $closure_data['meta'][ $this->closure_meta ];

			global $uncanny_automator;
			$redirect_url = $uncanny_automator->parse->url( $redirect_url, $recipe_id, $args );
			update_option( 'UO_REDIRECTURL_' . $user_id, $redirect_url );

			return;
		} else {
			$redirect_url = $closure_data['meta'][ $this->closure_meta ];

			global $uncanny_automator;
			$redirect_url = $uncanny_automator->parse->url( $redirect_url, $recipe_id, $args );
			?>
            <script type="text/javascript">
                var t = setTimeout(function () {
                    document.location.href = '<?php echo $redirect_url ?>'
                }, 200)
            </script>
			<?php
			exit;
		}
	}
}
