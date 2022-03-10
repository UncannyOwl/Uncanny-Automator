<?php

namespace Uncanny_Automator;

/**
 * Reviews
 * Settings > General > Improve Automator > Review
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 * @author  Daniela R. & Agustin B.
 */

?>

<div class="uap-settings-panel-content-subtitle">
    <?php esc_html_e( 'Is Automator useful to you?', 'uncanny-automator' ); ?>
</div>

<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
    <?php

        printf(
            /* translators: 1. Trademarked term */
            esc_html__( 'Reviews of %1$s help to expand its reach and mean a lot to our team. If you find %1$s adding value to your site, please consider leaving a review.', 'uncanny-automator' ),
            'Uncanny Automator'
        );

    ?>
</div>

<div class="uap-settings-panel-content-paragraph">
    
    <uo-button
        href="https://wordpress.org/support/plugin/uncanny-automator/reviews/?filter=5#new-post"
        target="_blank"
    >
        <?php esc_html_e( 'Add review', 'uncanny-automator' ); ?>
    </uo-button>

</div>
