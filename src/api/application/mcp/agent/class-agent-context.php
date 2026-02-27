<?php
/**
 * Agent Context — builds the ModelContext payload.
 *
 * Produces the context array matching the three-way contract
 * (PHP → TypeScript/Zod, Python/Pydantic).
 *
 * @since 7.1.0
 * @see /Curiosity/front.end/context.ts  Zod schema (source of truth).
 * @see app/api/schemas/context/          Pydantic models (Python consumer).
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Application\Mcp\Agent;

use Uncanny_Automator\Api\Services\Integration\Integration_Registry_Service;
use Uncanny_Automator\Api\Services\Plan\Plan_Service;
use WP_Post;
use WP_Screen;
use WP_Term;

/**
 * Builds a ModelContext payload for the MCP agent.
 *
 * Usage:
 *   $context = new Agent_Context();
 *   $payload = $context->build();
 *
 * @since 7.1.0
 */
class Agent_Context {

	/**
	 * Schema version.
	 */
	const VERSION = '1.0';

	/**
	 * Build the full ModelContext array.
	 *
	 * @return array<string, mixed>
	 */
	public function build(): array {

		$context = array(
			'version'      => self::VERSION,
			'account'      => $this->build_account(),
			'userIdentity' => $this->build_user_identity(),
			'WordPress'    => $this->build_wordpress(),
			'Automator'    => $this->build_automator(),
		);

		$metadata = $this->build_metadata();

		if ( '' !== $metadata ) {
			$context['metaData'] = $metadata;
		}

		return $context;
	}

	// ------------------------------------------------------------------
	// Account
	// ------------------------------------------------------------------

	/**
	 * Build account (site-level plan) block.
	 *
	 * @return array{id: string, name: string, scheduledActions: bool, filterConditions: bool, loops: bool}
	 */
	private function build_account(): array {

		$plan_service = new Plan_Service();
		$plan_id      = $plan_service->get_current_plan_id();

		return array(
			'id'               => $plan_id,
			'name'             => ucwords( str_replace( '-', ' ', $plan_id ) ),
			'scheduledActions' => 'lite' !== $plan_id,
			'filterConditions' => 'lite' !== $plan_id,
			'loops'            => 'lite' !== $plan_id,
		);
	}

	// ------------------------------------------------------------------
	// User Identity
	// ------------------------------------------------------------------

	/**
	 * Build user identity block.
	 *
	 * @return array{name: string, email: string, role: string}
	 */
	private function build_user_identity(): array {

		$user = wp_get_current_user();

		$roles = $user->roles ?? array();

		return array(
			'name'  => $user->display_name ?? '',
			'email' => $user->user_email ?? '',
			'role'  => ! empty( $roles ) ? reset( $roles ) : '',
		);
	}

	// ------------------------------------------------------------------
	// WordPress
	// ------------------------------------------------------------------

	/**
	 * Build WordPress admin context block.
	 *
	 * @return array{currentScreen: array, currentPost: array|false, currentTaxonomy: array|false}
	 */
	private function build_wordpress(): array {

		return array(
			'currentScreen'   => $this->build_current_screen(),
			'currentPost'     => $this->build_current_post(),
			'currentTaxonomy' => $this->build_current_taxonomy(),
		);
	}

	/**
	 * Build currentScreen from get_current_screen().
	 *
	 * @return array{id: string, title: string, url: string}
	 */
	private function build_current_screen(): array {

		$screen = $this->get_current_screen();

		$screen_id = '';
		$title     = '';

		if ( $screen instanceof WP_Screen ) {
			$screen_id = $screen->id ?? '';

			// get_current_screen() doesn't have a title property.
			// Use the admin page title from the global.
			$title = $this->get_admin_page_title();
		}

		return array(
			'id'    => sanitize_text_field( $screen_id ),
			'title' => sanitize_text_field( $title ),
			'url'   => $this->get_current_admin_url(),
		);
	}

	/**
	 * Build currentPost context.
	 *
	 * @return array{id: int, type: string, title: string}|false
	 */
	private function build_current_post() {

		$post = $this->get_current_post();

		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		return array(
			'id'    => $post->ID,
			'type'  => $post->post_type,
			'title' => sanitize_text_field( $post->post_title ),
		);
	}

	/**
	 * Build currentTaxonomy context.
	 *
	 * @return array{taxonomy: string, term: array{id: int, name: string}|false}|false
	 */
	private function build_current_taxonomy() {

		$screen = $this->get_current_screen();

		if ( ! $screen instanceof WP_Screen || empty( $screen->taxonomy ) ) {
			return false;
		}

		$taxonomy = sanitize_text_field( $screen->taxonomy );

		// Check if editing a specific term.
		// phpcs:ignore WordPress.Security.NonceVerification -- Reading only; no state change.
		$tag_id = isset( $_GET['tag_ID'] ) ? absint( $_GET['tag_ID'] ) : 0;

		$term = false;

		if ( $tag_id > 0 ) {
			$wp_term = get_term( $tag_id, $taxonomy );

			if ( $wp_term instanceof WP_Term ) {
				$term = array(
					'id'   => $wp_term->term_id,
					'name' => sanitize_text_field( $wp_term->name ),
				);
			}
		}

		return array(
			'taxonomy' => $taxonomy,
			'term'     => $term,
		);
	}

	// ------------------------------------------------------------------
	// Automator
	// ------------------------------------------------------------------

	/**
	 * Build Automator context block.
	 *
	 * @return array{version: string, activeIntegrations: array, currentRecipe: array|false}
	 */
	private function build_automator(): array {

		return array(
			'version'            => defined( 'AUTOMATOR_PLUGIN_VERSION' ) ? AUTOMATOR_PLUGIN_VERSION : '',
			'activeIntegrations' => $this->build_active_integrations(),
			'currentRecipe'      => $this->build_current_recipe(),
		);
	}

	/**
	 * Build active integrations list.
	 *
	 * @return array<int, array{code: string, name: string}>
	 */
	private function build_active_integrations(): array {

		$registry = Integration_Registry_Service::get_instance();

		return $registry->get_active_integrations();
	}

	/**
	 * Build currentRecipe context.
	 *
	 * Only populated on recipe editor screens.
	 *
	 * @return array{id: int, title: string, type: string}|false
	 */
	private function build_current_recipe() {

		$post = $this->get_current_post();

		if ( ! $post instanceof WP_Post || 'uo-recipe' !== $post->post_type ) {
			return false;
		}

		$recipe = Automator()->get_recipe_object( $post->ID, ARRAY_A );

		if ( empty( $recipe ) || ! is_array( $recipe ) ) {
			return false;
		}

		return array(
			'id'    => $post->ID,
			'title' => sanitize_text_field( $post->post_title ),
			'type'  => sanitize_text_field( $recipe['recipe_type'] ?? '' ),
		);
	}

	// ------------------------------------------------------------------
	// Metadata
	// ------------------------------------------------------------------

	/**
	 * Build optional metadata string (base64-encoded JSON).
	 *
	 * @return string Base64 JSON or empty string.
	 */
	private function build_metadata(): string {

		$data = array(
			'plugin_version'  => defined( 'AUTOMATOR_PLUGIN_VERSION' ) ? AUTOMATOR_PLUGIN_VERSION : '',
			'php_version'     => PHP_VERSION,
			'wp_version'      => get_bloginfo( 'version' ),
			'server_software' => sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ?? '' ) ),
		);

		$json = wp_json_encode( $data );

		if ( false === $json ) {
			return '';
		}

		return base64_encode( $json ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Not obfuscation; structured metadata encoding.
	}

	// ------------------------------------------------------------------
	// WordPress helpers (seams for testing)
	// ------------------------------------------------------------------

	/**
	 * Get the current WP_Screen.
	 *
	 * @return WP_Screen|null
	 */
	protected function get_current_screen(): ?WP_Screen {
		$screen = get_current_screen();

		return $screen instanceof WP_Screen ? $screen : null;
	}

	/**
	 * Get the current post being edited.
	 *
	 * @return WP_Post|null
	 */
	protected function get_current_post(): ?WP_Post {

		// phpcs:ignore WordPress.Security.NonceVerification -- Reading only; no state change.
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

		if ( 0 === $post_id ) {
			global $post;
			return $post instanceof WP_Post ? $post : null;
		}

		$wp_post = get_post( $post_id );

		return $wp_post instanceof WP_Post ? $wp_post : null;
	}

	/**
	 * Get admin page title from global $title.
	 *
	 * @return string
	 */
	protected function get_admin_page_title(): string {
		global $title;

		return is_string( $title ) ? $title : '';
	}

	/**
	 * Get the current admin URL.
	 *
	 * @return string
	 */
	protected function get_current_admin_url(): string {

		$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );

		if ( '' === $request_uri ) {
			return admin_url();
		}

		return home_url( $request_uri );
	}
}
