<?php

namespace Uncanny_Automator;

/**
 * Trait Automator_Tooltip_Trait
 *
 * Provides common functionality for tooltip notifications.
 */
trait Automator_Tooltip_Trait {

	/**
	 * Number of days before showing the notice again.
	 *
	 * @var int
	 */
	protected $days_before_reshow = 14;

	/**
	 * Tooltip ID.
	 *
	 * @var string
	 */
	protected $tooltip_id;

	/**
	 * Parent selector. The tooltip will be added as child of this element.
	 *
	 * @var string
	 */
	protected $parent_selector;

	/**
	 * Element position. The position where the tooltip will be added.
	 *
	 * 'beforebegin': Before the targetElement itself.
	 * 'afterbegin': Just inside the targetElement, before its first child.
	 * 'beforeend': Just inside the targetElement, after its last child.
	 * 'afterend': After the targetElement itself.
	 *
	 * @var string
	 */
	protected $element_position = 'afterbegin';

	/**
	 * Initialize the tooltip notification.
	 *
	 * @return void
	 */
	protected function init() {
		// Show the tooltip
		add_action( 'admin_footer', array( $this, 'maybe_show_tooltip' ) );
	}

	/**
	 * Determine whether to show the tooltip based on the current conditions.
	 */
	public function maybe_show_tooltip() {
		// Get the current user ID
		$user_id = get_current_user_id();

		// Check if the user is logged in
		if ( ! $user_id ) {
			return false;
		}

		// Check if the current user can manage options
		if ( ! current_user_can( automator_get_capability() ) ) {
			return false;
		}

		// Get the user's tooltips visibility from user meta
		$tooltips_visibility = get_user_meta( $user_id, 'automator_tooltips_visibility', true );

		if ( ! is_array( $tooltips_visibility ) ) {
			$tooltips_visibility = array();
		}

		$show_tooltip = isset( $tooltips_visibility[ $this->tooltip_id ] ) ? $tooltips_visibility[ $this->tooltip_id ] : 0;

		$can_show_tooltip = ! $show_tooltip || ( time() >= $show_tooltip + $this->days_before_reshow * DAY_IN_SECONDS );

		if ( ! $can_show_tooltip ) {
			return false;
		}

		// Check if the tooltip should be displayed based on additional logic
		if ( $this->should_display_tooltip() ) {
			$this->display_tooltip();
		}
	}

	/**
	 * Base logic for determining if the tooltip should be displayed.
	 *
	 * @return bool True if the tooltip should be shown, false otherwise.
	 */
	protected function should_display_tooltip() {
		// Here you would include any additional conditions or simply return true/false.
		// For example, return true if no additional checks are needed.
		return true; // Assuming no additional checks are necessary
	}

	/**
	 * Display the tooltip notification.
	 */
	protected function display_tooltip() {
		?>

		<div 
			class="uap-tooltip-notification uap-tooltip-notification--hidden" 
			data-id="<?php echo esc_attr( $this->tooltip_id ); ?>"
			data-target="<?php echo esc_attr( $this->parent_selector ); ?>"
			data-position="<?php echo esc_attr( $this->element_position ); ?>"
		>
			<?php

			echo wp_kses(
				$this->create_tooltip_template(
					array(
						'dashicon'    => 'megaphone',
						'title'       => esc_html__( 'Get started with Automator!', 'uncanny-automator' ),
						'description' => esc_html__( 'Create your first time-saving Automator recipe and put your site on autopilot!', 'uncanny-automator' ),
						'cta_label'   => esc_html__( 'Start automating!', 'uncanny-automator' ),
						'cta_url'     => esc_url( admin_url( 'post-new.php?post_type=uo-recipe' ) ),
					)
				),
				array(
					'div'    => array(
						'class' => array(),
					),
					'span'   => array(
						'class' => array(),
					),
					'p'      => array(
						'class' => array(),
					),
					'a'      => array(
						'class' => array(),
						'href'  => array(),
					),
					'button' => array(
						'class' => array(),
					),
				)
			);
			?>
		</div>

		<?php
	}

	/**
	 * Creates an HTML template for a tooltip notification.
	 *
	 * This function generates the HTML structure for a tooltip notification using the provided content.
	 * The tooltip includes a dashicon, a title, content, and a call-to-action button.
	 *
	 * @param array $content {
	 *     Optional. An associative array of content for the tooltip.
	 *
	 *     @type string $dashicon     The dashicon class suffix to use in the tooltip's icon. Default is an empty string.
	 *     @type string $title        The title of the tooltip. Default is an empty string.
	 *     @type string $description  The main content of the tooltip. Default is an empty string.
	 *     @type string $cta_label    The label for the call-to-action button. Default is an empty string.
	 *     @type string $cta_url      The URL for the call-to-action button. Default is an empty string.
	 * }
	 *
	 * @return string The HTML content for the tooltip notification.
	 */
	public function create_tooltip_template( $content = array() ) {
		$dashicon    = isset( $content['dashicon'] ) ? $content['dashicon'] : '';
		$title       = isset( $content['title'] ) ? $content['title'] : '';
		$description = isset( $content['description'] ) ? $content['description'] : '';
		$cta_label   = isset( $content['cta_label'] ) ? $content['cta_label'] : '';
		$cta_url     = isset( $content['cta_url'] ) ? $content['cta_url'] : '';

		ob_start();

		?>

		<div class="uap-tooltip-notification__wrapper">
			<div class="uap-tooltip-notification__container">
				<div class="uap-tooltip-notification__header">
					<span class="uap-tooltip-notification__icon">
						<span class="dashicons dashicons-<?php echo esc_attr( $dashicon ); ?>"></span>
					</span>
					<?php echo esc_html( $title ); ?>
					<span class="uap-tooltip-notification__close">
						<span class="dashicons dashicons-dismiss"></span>
					</span>
				</div>
				<div class="uap-tooltip-notification__content">
					<p class="uap-tooltip-notification__content-description">
						<?php echo esc_html( $description ); ?>
					</p>
					<p class="uap-tooltip-notification__content-button">
						<a 
							class="uap-tooltip-notification__button button button-primary" 
							href="<?php echo esc_url( $cta_url ); ?>"
						>
							<?php echo esc_html( $cta_label ); ?>
						</a>
					</p>
				</div>
			</div>
		</div>
	
		<?php

		return ob_get_clean();
	}
}
