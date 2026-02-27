<?php
/**
 * Loop Token Sentence Composer.
 *
 * Canonical token loop sentence HTML generator for core API loop tools.
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Presentation\Loop;

/**
 * Loop_Token_Sentence_Composer.
 */
class Loop_Token_Sentence_Composer {

	/**
	 * Compose encoded token sentence HTML for loop backup.
	 *
	 * @param string $token_name Display name for the token.
	 * @param string $icon_url   Optional token/integration icon URL.
	 *
	 * @return string HTML entity encoded sentence HTML.
	 */
	public function compose( string $token_name, string $icon_url = '' ): string {
		$icon_html = '';
		if ( '' !== trim( $icon_url ) ) {
			$icon_html = sprintf(
				'<span class="uap-token__icon"><img src="%s"></span>',
				esc_url( $icon_url )
			);
		}

		$sentence_html = sprintf(
			'<span class="sentence sentence--standard"><span class="sentence-pill" size="small" filled=""><span class="sentence-pill-value"><span class="uap-token">%s<span class="uap-token__name">%s</span></span></span></span></span>',
			$icon_html,
			esc_html( $token_name )
		);

		return htmlentities( $sentence_html, ENT_QUOTES );
	}
}
