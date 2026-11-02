<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol;

/**
 * Capability and discovery guidance shared by both MCP connection paths.
 */
final class Server_Instructions {

	/**
	 * Model-facing instructions returned during server discovery or initialization.
	 */
	public const TEXT = <<<'INSTRUCTIONS'
Uncanny Automator lets users manage and automate their WordPress site with the AI they already use. Help them run their site in plain English, get answers from site data, and act across installed plugins and connected apps. It also lets AI build editable workflows that run on site events or schedules after the chat ends, without an AI deciding each step unless the workflow explicitly includes AI actions.

Start from the user's site-management goal: manage content, users, orders, comments, menus, plugins and settings; investigate records and logs; enroll learners; update CRM contacts; or work with other connected services. Page creation and design, including reusable headers, footers and navigation, are part of this broader scope. Choose examples and next steps that fit the user's request. Users can connect their preferred AI or use the built-in Uncanny Agent. Available operations depend on this site's installed providers, connected accounts, Automator features and granted access.

Discover capabilities before choosing an implementation. The visible MCP tools are entry points into a larger catalog: individual operations usually do not have their own named MCP tool. Use rest_discover to find native Automator abilities and enabled abilities supplied by WordPress and plugins. Discover automator/search and call it with type="action" for Automator integration operations or type="ability" for enabled third-party abilities. If search results are unhelpful, browse the relevant catalog with mode="list" and inspect availability. For example, plugin installation is an action in the WP_ADMIN integration. Do not infer that an operation is unsupported simply because its name is absent from the top-level tool list.

Inspect component inputs through the discovered automator/get-component-schema ability, resolve dropdown choices through automator/get-field-options where applicable, and execute through automator/run-action. This runs one integration action without creating a recipe. Automator abilities use rest_request with the discovered method and path: GET/DELETE use query:{input:{...}}, POST uses body:{input:{...}}. Send input:{} for empty arguments. Automator operation names written with underscores in field help identify the corresponding automator/<hyphenated-name> ability on native-capable sites. Owner-enabled third-party abilities can also use the root run_ability tool with their discovered name and input schema.

For reviewed native WordPress REST operations, including content and supported builder workflows, use rest_discover to find reviewed routes. Inspect a selected path and method for its live arguments and schema_hash, then call rest_request with a concrete site-relative path, query and body. If discovery returns schema_complete=false, retrieve all schema_chunk fragments using schema_offset and schema_hash, concatenate their content in order and decode the complete JSON before using its inputs. Pass schema_hash to detect changed inputs before running. Native WordPress validation and permissions still apply; operations that make changes require write access. This runner does not expose every registered REST route. Use this same native discovery/request path for Automator recipes and integration actions. If an Automator operation is advertised as a root tool, use that tool and its live schema instead: its trusted native bridge may be unavailable, including on older WordPress without the Abilities API. Never invent unavailable routes.

For a small change inside authored post content, use the discovered POST /automator/v1/posts/{id}/content-edit operation through rest_request. Here post includes blog posts, pages and supported custom post types. Use rest_request GET /automator/v1/posts/{id}/content-edit first for content_hash and the source; content over 64 KiB returns an outline, so read only what you need with view=search or view=range. Then call rest_request on the discovered POST path with expected_content_hash and ordered edits in body, using its schema_hash. The post ID belongs in the path. Quote unique literal source in search, with replace for replacement, content for insert_before/insert_after, or action=delete. To duplicate or reorder whole blocks without resending them, use copy_range or move_range with offset, length and to from the outline. WordPress prepares the changes without making the model rewrite the whole article. A missing, ambiguous or stale match refuses the edit without saving. Preserve Gutenberg block markup and unrelated text. Page Builder-owned content uses its own edit_part path. Inspect verification and the change excerpts; native sanitizers can change content. Do not blindly retry an uncertain write.

Build repeatable automations by creating or updating native Automator recipes. A recipe connects manual or event-based triggers to actions. Users can inspect and edit it in Automator. Once enabled, it runs on its configured triggers without needing the conversation or a fresh model decision for each step; workflows can include explicit AI actions. Combine integrations, use conditions to decide which actions run, delays or schedules to control timing, and loops to process matching users, posts, or items. Use discovered tokens to pass values between steps.

Understand and troubleshoot the site. Inspect existing recipes and their execution logs to explain behavior, diagnose failures, and maintain workflows. Use dedicated site-data tools to find records and identifiers. When database access is available, use the read-only MySQL tools to investigate WordPress, plugin, and custom-table data, join related records, and calculate summaries. Inspect tables and columns before querying.

Follow each tool's schema and availability information. Verify saved configuration and execution results; when starting a manual recipe, use its logs to establish completion.
INSTRUCTIONS;
}
