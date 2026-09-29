<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api;

use UncannyPageBuilder\Application\NavigationMenuService;
use UncannyPageBuilder\Domain\ErrorMessage;
use UncannyPageBuilder\Domain\Exception\InvalidNavigationRequestException;
use UncannyPageBuilder\Domain\Exception\NavigationChangedConcurrentlyException;
use UncannyPageBuilder\Domain\Exception\NavigationItemTextNotSavableException;
use UncannyPageBuilder\Domain\Exception\NavigationMenuNotFoundException;
use UncannyPageBuilder\Domain\Exception\StaleNavigationMenuException;

final class AgentNavigationController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly NavigationMenuService $menus,
    ) {}

    public function registerRoutes(): void
    {
        register_rest_route('uncanny-page-builder/v1', '/agent/navigation', [
            'methods' => 'GET',
            'callback' => [$this, 'readNavigation'],
            'permission_callback' => [$this->permissions, 'canEdit'],
            'args' => [
                'operation' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'menu_id' => [
                    'required' => false,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        register_rest_route('uncanny-page-builder/v1', '/agent/navigation', [
            'methods' => 'POST',
            'callback' => [$this, 'manageNavigation'],
            'permission_callback' => [$this->permissions, 'canManage'],
        ]);
    }

    /**
     * GET is a read-only transport boundary. Keep this whitelist separate
     * from the consolidated POST dispatcher so editor-level callers cannot
     * reach navigation mutations by putting a write operation in the query.
     */
    public function readNavigation(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $operation = trim((string) ($request->get_param('operation') ?? ''));
        if (!in_array($operation, ['', 'list_locations', 'list_menus', 'read_menu'], true)) {
            return $this->textError(405, 'read_only_transport', [
                'OPERATION: ' . $operation,
                'NEXT STEP',
                'Use POST for create_menu, add_item, update_item, delete_item, move_item, replace_tree, or assign_location.',
            ]);
        }

        return $this->manageNavigation($request);
    }

    public function manageNavigation(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $operation = trim((string) ($request->get_param('operation') ?? ''));

        try {
            $permissionItemIds = $this->isWriteOperation($operation)
                ? $this->itemIdsForPermission($operation, $request)
                : [];
            if (
                $this->isWriteOperation($operation)
                && !$this->permissions->canManageNavigation(
                    $operation,
                    $this->positiveId($request->get_param('item_id'), 'item_id'),
                    $permissionItemIds,
                )
            ) {
                return $this->textError(403, 'native_navigation_capability_required', [
                    'OPERATION: ' . $operation,
                    'NEXT STEP',
                    'Use an account with the WordPress capability registered for this navigation operation.',
                ]);
            }

            return match ($operation) {
                '', 'list_locations' => $this->listLocations(),
                'list_menus' => $this->listMenus(),
                'read_menu' => $this->readMenu($this->positiveId($request->get_param('menu_id'), 'menu_id')),
                'create_menu' => $this->createMenu(trim((string) ($request->get_param('name') ?? ''))),
                'add_item' => $this->addItem($request),
                'update_item' => $this->updateItem($request),
                'delete_item' => $this->deleteItem($request),
                'move_item' => $this->moveItem($request, $this->positiveItemIds($permissionItemIds)),
                'replace_tree' => $this->replaceTree($request, $this->positiveItemIds($permissionItemIds)),
                'assign_location' => $this->assignLocation($request),
                default => AgentTextResponse::withStatus(implode("\n", [
                    'TOOL: manage_navigation',
                    'RESULT: error',
                    'ERROR_CODE: invalid_operation',
                    'OPERATION: ' . $operation,
                    'NEXT STEP',
                    'Retry with operation list_locations, list_menus, read_menu, create_menu, add_item, update_item, delete_item, move_item, replace_tree, or assign_location.',
                ]), 400),
            };
        } catch (NavigationMenuNotFoundException) {
            return ApiResponse::error(ErrorMessage::NavigationMenuNotFound);
        } catch (NavigationChangedConcurrentlyException $conflict) {
            $lines = [
                'OPERATION: ' . $operation,
                'PRESERVED_ITEM_IDS: ' . implode(', ', $conflict->itemIds()),
                'DETAIL: Someone else changed these items while this request ran. Their changes were kept. The other changes from this request were undone.',
            ];
            if ($conflict->unrestoredPositionItemIds() !== []) {
                $lines[] = 'ORDER_NOT_RESTORED_ITEM_IDS: ' . implode(', ', $conflict->unrestoredPositionItemIds());
                $lines[] = 'The menu order may differ from its order before this request.';
            }

            return $this->textError(409, 'navigation_changed_concurrently', [
                ...$lines,
                'NEXT STEP',
                'Call read_menu, review the current items, and retry only if the change is still needed.',
            ]);
        } catch (NavigationItemTextNotSavableException $refusal) {
            return $this->textError(422, 'navigation_text_not_savable', [
                'OPERATION: ' . $operation,
                'ITEM_IDS: ' . implode(', ', $refusal->itemIds()),
                'DETAIL: WordPress would remove markup from these items because this account cannot save it. Nothing was saved.',
                'NEXT STEP',
                'Ask a user who can save unfiltered HTML to make this change. Do not remove markup from these items unless a person asks for it.',
            ]);
        } catch (StaleNavigationMenuException) {
            return $this->textError(409, 'stale_navigation_menu', [
                'OPERATION: ' . $operation,
                'DETAIL: The navigation menu changed after authorization. Nothing was saved.',
                'NEXT STEP',
                'Call read_menu, review the current items, and retry with the new menu state.',
            ]);
        } catch (InvalidNavigationRequestException $e) {
            return $this->textError(400, 'invalid_navigation_request', [
                'DETAIL: ' . $e->getMessage(),
                'NEXT STEP',
                'Fix the request fields and retry the navigation operation.',
            ]);
        } catch (\Throwable $failure) {
            return $this->unexpectedOperationFailure(
                $operation,
                absint($request->get_param('menu_id') ?? 0),
                $failure,
            );
        }
    }

    private function unexpectedOperationFailure(string $operation, int $menuId, \Throwable $failure): \WP_REST_Response
    {
        \error_log(sprintf(
            '[Uncanny Page Builder] Navigation operation "%s" failed for menu %d (%s).',
            $operation,
            $menuId,
            $failure::class,
        ));

        $lines = [
            'OPERATION: ' . $operation,
        ];

        if (in_array($operation, ['', 'list_locations', 'list_menus', 'read_menu'], true)) {
            $lines[] = 'NEXT STEP';
            $lines[] = 'Retry the read operation. If the error continues, review the WordPress error log.';

            return $this->textError(500, 'navigation_operation_failed', $lines);
        }

        $lines[] = 'RETRY_SAFETY: The write result is uncertain. Do not retry blindly.';
        $lines[] = 'NEXT STEP';
        $lines[] = 'Call list_menus. For an existing menu, call read_menu. Confirm the current state before another write.';

        return $this->textError(500, 'navigation_operation_failed', $lines);
    }

    private function listLocations(): \WP_REST_Response
    {
        $locations = $this->menus->listLocations();
        $lines = [
            'TOOL: manage_navigation',
            'RESULT: success',
            'OPERATION: list_locations',
            '',
            'LOCATIONS',
        ];

        if ($locations === []) {
            $lines[] = 'none';
        }

        foreach ($locations as $location) {
            $lines[] = '- SLUG: ' . (string) ($location['slug'] ?? '');
            $lines[] = '  LABEL: ' . (string) ($location['label'] ?? '');
            $lines[] = '  ASSIGNED_MENU_ID: ' . (string) ($location['assigned_menu_id'] ?? 0);
        }

        $lines[] = '';
        $lines[] = 'NEXT STEP';
        $lines[] = 'Use read_menu with an ASSIGNED_MENU_ID to inspect one menu before attempting navigation writes.';

        return AgentTextResponse::ok(implode("\n", $lines));
    }

    private function listMenus(): \WP_REST_Response
    {
        $menus = $this->menus->listMenus();
        $lines = [
            'TOOL: manage_navigation',
            'RESULT: success',
            'OPERATION: list_menus',
            '',
            'MENUS',
        ];

        if ($menus === []) {
            $lines[] = 'none';
        }

        foreach ($menus as $menu) {
            $lines[] = '- MENU_ID: ' . (string) ($menu['id'] ?? 0);
            $lines[] = '  NAME: ' . (string) ($menu['name'] ?? '');
            $lines[] = '  ITEM_COUNT: ' . count((array) ($menu['items'] ?? []));
        }

        $lines[] = '';
        $lines[] = 'NEXT STEP';
        $lines[] = 'Use read_menu with a MENU_ID before attempting navigation writes.';

        return AgentTextResponse::ok(implode("\n", $lines));
    }

    private function readMenu(int $menuId): \WP_REST_Response|\WP_Error
    {
        if ($menuId <= 0) {
            return AgentTextResponse::withStatus(implode("\n", [
                'TOOL: manage_navigation',
                'RESULT: error',
                'ERROR_CODE: missing_menu_id',
                'NEXT STEP',
                'Retry with operation read_menu and a valid menu_id.',
            ]), 400);
        }

        $menu = $this->menus->readMenu($menuId);
        if ($menu === null) {
            return ApiResponse::error(ErrorMessage::NavigationMenuNotFound);
        }

        $lines = [
            'TOOL: manage_navigation',
            'RESULT: success',
            'OPERATION: read_menu',
            'MENU_ID: ' . (string) ($menu['id'] ?? 0),
            'NAME: ' . (string) ($menu['name'] ?? ''),
            '',
            'ITEMS',
        ];

        $items = (array) ($menu['items'] ?? []);
        if ($items === []) {
            $lines[] = 'none';
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $lines[] = '- ITEM_ID: ' . (string) ($item['id'] ?? 0);
            $lines[] = '  LABEL: ' . (string) ($item['label'] ?? '');
            $lines[] = '  TYPE: ' . (string) ($item['type'] ?? '');
            $lines[] = '  OBJECT_TYPE: ' . (string) ($item['object_type'] ?? '');
            $lines[] = '  OBJECT_ID: ' . (string) ($item['object_id'] ?? 0);
            $lines[] = '  URL: ' . (string) ($item['url'] ?? '');
            $lines[] = '  PARENT_ID: ' . (string) ($item['parent_id'] ?? 0);
            $lines[] = '  POSITION: ' . (string) ($item['position'] ?? 0);
            $lines[] = '  TARGET: ' . (string) ($item['target'] ?? '');
            $lines[] = '  CLASSES: ' . implode(', ', array_map('strval', (array) ($item['classes'] ?? [])));
        }

        $lines[] = '';
        $lines[] = 'NEXT STEP';
        $lines[] = 'Use the ITEM_ID values from this response when updating, moving, or deleting items.';

        return AgentTextResponse::ok(implode("\n", $lines));
    }

    // Write operations.
    private function createMenu(string $name): \WP_REST_Response
    {
        $menu = $this->menus->createMenu($name);

        return AgentTextResponse::ok(implode("\n", [
            'TOOL: manage_navigation',
            'RESULT: success',
            'OPERATION: create_menu',
            'MENU_ID: ' . (string) ($menu['id'] ?? 0),
            'NAME: ' . (string) ($menu['name'] ?? ''),
            'ITEM_COUNT: ' . count((array) ($menu['items'] ?? [])),
            ...$this->writeImpactLines(),
            '',
            'NEXT STEP',
            'Use MENU_ID from this response when adding the first menu item.',
        ]));
    }

    private function addItem(\WP_REST_Request $request): \WP_REST_Response
    {
        $result = $this->menus->addItem(
            menuId: $this->positiveId($request->get_param('menu_id'), 'menu_id'),
            input: $this->itemInput($request, false),
        );

        return $this->itemMutationSuccess('add_item', $result['menu'], $result['item_id']);
    }

    private function updateItem(\WP_REST_Request $request): \WP_REST_Response
    {
        $itemId = $this->positiveId($request->get_param('item_id'), 'item_id');
        $menu = $this->menus->updateItem(
            menuId: $this->positiveId($request->get_param('menu_id'), 'menu_id'),
            itemId: $itemId,
            input: $this->itemInput($request, true),
        );

        return $this->itemMutationSuccess('update_item', $menu, $itemId);
    }

    private function deleteItem(\WP_REST_Request $request): \WP_REST_Response
    {
        $itemId = $this->positiveId($request->get_param('item_id'), 'item_id');
        $menu = $this->menus->deleteItem(
            menuId: $this->positiveId($request->get_param('menu_id'), 'menu_id'),
            itemId: $itemId,
        );

        return AgentTextResponse::ok(implode("\n", [
            'TOOL: manage_navigation',
            'RESULT: success',
            'OPERATION: delete_item',
            'MENU_ID: ' . (string) ($menu['id'] ?? 0),
            'DELETED_ITEM_ID: ' . $itemId,
            'ITEM_COUNT: ' . count((array) ($menu['items'] ?? [])),
            ...$this->writeImpactLines(),
            '',
            'NEXT STEP',
            'Call read_menu to confirm the updated menu.',
        ]));
    }

    /** @param int[] $expectedItemIds */
    private function moveItem(\WP_REST_Request $request, array $expectedItemIds): \WP_REST_Response
    {
        $itemId = $this->positiveId($request->get_param('item_id'), 'item_id');
        $menu = $this->menus->moveItem(
            menuId: $this->positiveId($request->get_param('menu_id'), 'menu_id'),
            itemId: $itemId,
            parentId: $this->nonNegativeId($request->get_param('parent_id'), 'parent_id'),
            position: (int) ($request->get_param('position') ?? 0),
            expectedItemIds: $expectedItemIds,
        );

        return $this->itemMutationSuccess('move_item', $menu, $itemId);
    }

    /** @param int[] $expectedItemIds */
    private function replaceTree(\WP_REST_Request $request, array $expectedItemIds): \WP_REST_Response
    {
        if (!$this->isTruthy($request->get_param('replace_tree'))) {
            return $this->textError(400, 'invalid_navigation_request', [
                'DETAIL: replace_tree must be true for operation replace_tree.',
                'NEXT STEP',
                'Set replace_tree=true and send the full item list.',
            ]);
        }

        $menu = $this->menus->replaceTree(
            menuId: $this->positiveId($request->get_param('menu_id'), 'menu_id'),
            items: $this->treeItems($request->get_param('items')),
            expectedItemIds: $expectedItemIds,
        );

        return AgentTextResponse::ok(implode("\n", [
            'TOOL: manage_navigation',
            'RESULT: success',
            'OPERATION: replace_tree',
            'MENU_ID: ' . (string) ($menu['id'] ?? 0),
            'ITEM_COUNT: ' . count((array) ($menu['items'] ?? [])),
            ...$this->writeImpactLines(),
            '',
            'NEXT STEP',
            'Call read_menu to confirm the updated menu.',
        ]));
    }

    private function assignLocation(\WP_REST_Request $request): \WP_REST_Response
    {
        $location = $this->menus->assignLocation(
            locationSlug: (string) ($request->get_param('location_slug') ?? ''),
            menuId: $this->positiveId($request->get_param('menu_id'), 'menu_id'),
        );

        return AgentTextResponse::ok(implode("\n", [
            'TOOL: manage_navigation',
            'RESULT: success',
            'OPERATION: assign_location',
            'LOCATION_SLUG: ' . (string) ($location['slug'] ?? ''),
            'ASSIGNED_MENU_ID: ' . (string) ($location['assigned_menu_id'] ?? 0),
            ...$this->writeImpactLines(),
            '',
            'NEXT STEP',
            'Use list_locations or read_menu to confirm the assigned location.',
        ]));
    }

    /**
     * @return array{
     *   type?: string,
     *   object_type?: string,
     *   object_id?: int,
     *   label?: string,
     *   url?: string,
     *   parent_id?: int,
     *   target?: string,
     *   classes?: string[]
     * }
     */
    private function itemInput(\WP_REST_Request $request, bool $allowPartial): array
    {
        $input = [];

        foreach (['type', 'object_type', 'label', 'url', 'target'] as $field) {
            $value = $request->get_param($field);
            if ($value !== null && ($allowPartial || trim((string) $value) !== '')) {
                $input[$field] = (string) $value;
            }
        }

        foreach (['object_id', 'parent_id'] as $field) {
            $value = $request->get_param($field);
            if ($value !== null) {
                $id = $this->nonNegativeId($value, $field);
                if ($allowPartial || $id > 0 || $field === 'parent_id') {
                    $input[$field] = $id;
                }
            }
        }

        $classes = $request->get_param('classes');
        if (is_array($classes)) {
            $input['classes'] = array_values(array_map('strval', $classes));
        }

        return $input;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function treeItems(mixed $items): array
    {
        if (!is_array($items)) {
            throw new InvalidNavigationRequestException('items must be an array.');
        }

        $validated = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new InvalidNavigationRequestException('Each items entry must be an object.');
            }

            if (array_key_exists('item_id', $item)) {
                $item['item_id'] = $this->nonNegativeId($item['item_id'], 'items.item_id');
            }
            if (array_key_exists('parent_id', $item)) {
                $item['parent_id'] = $this->nonNegativeId($item['parent_id'], 'items.parent_id');
            }
            if (array_key_exists('object_id', $item)) {
                $item['object_id'] = $this->nonNegativeId($item['object_id'], 'items.object_id');
            }

            $validated[] = $item;
        }

        return $validated;
    }

    /**
     * @param array{id: int, name: string, items: array<int, array<string, mixed>>} $menu
     */
    private function itemMutationSuccess(string $operation, array $menu, int $itemId): \WP_REST_Response
    {
        return AgentTextResponse::ok(implode("\n", [
            'TOOL: manage_navigation',
            'RESULT: success',
            'OPERATION: ' . $operation,
            'MENU_ID: ' . (string) ($menu['id'] ?? 0),
            'ITEM_ID: ' . $itemId,
            'ITEM_COUNT: ' . count((array) ($menu['items'] ?? [])),
            ...$this->writeImpactLines(),
            '',
            'NEXT STEP',
            'Call read_menu to confirm the updated menu.',
        ]));
    }

    /**
     * Navigation is WordPress-global state, not a Page Builder draft lane.
     * Keep both consequences explicit so the Agent cannot report a write as
     * safely isolated merely because the selected page artifact stayed put.
     *
     * @return list<string>
     */
    private function writeImpactLines(): array
    {
        return [
            '',
            'PUBLIC IMPACT: WordPress navigation changed immediately and may affect visitors wherever this menu is rendered.',
            'PAGE BUILDER LIVE ARTIFACT: unchanged',
        ];
    }

    private function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes'], true);
    }

    private function isWriteOperation(string $operation): bool
    {
        return in_array($operation, [
            'create_menu',
            'add_item',
            'update_item',
            'delete_item',
            'move_item',
            'replace_tree',
            'assign_location',
        ], true);
    }

    /**
     * @return int[]
     */
    private function treeItemIdsForPermission(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        $ids = [];
        foreach (array_values($items) as $item) {
            if (!is_array($item)) {
                throw new InvalidNavigationRequestException('Each items entry must be an object.');
            }

            $ids[] = $this->nonNegativeId($item['item_id'] ?? null, 'items.item_id');
        }

        return $ids;
    }

    /**
     * A move rewrites the complete normalized tree, so it needs object-level
     * permission for every existing item that the repository will save.
     *
     * @return int[]
     */
    private function itemIdsForPermission(string $operation, \WP_REST_Request $request): array
    {
        if (!in_array($operation, ['move_item', 'replace_tree'], true)) {
            return [];
        }

        $requestedItemIds = $operation === 'replace_tree'
            ? $this->treeItemIdsForPermission($request->get_param('items'))
            : [];
        $menuId = $this->positiveId($request->get_param('menu_id'), 'menu_id');
        if ($menuId <= 0) {
            throw new InvalidNavigationRequestException('menu_id is required.');
        }
        $menu = $this->menus->readMenu($menuId);
        if (!is_array($menu)) {
            // Report a missing menu only to a caller who may edit navigation,
            // so the capability error still hides which menus exist.
            if ($this->permissions->canManageNavigation('replace_tree', 0, [])) {
                throw new NavigationMenuNotFoundException($menuId);
            }

            return [];
        }

        $ids = [];
        foreach ((array) ($menu['items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $ids[] = $this->positiveId($item['id'] ?? null, 'stored item ID');
        }

        if ($operation === 'replace_tree' && in_array(0, $requestedItemIds, true)) {
            $ids[] = 0;
        }

        return $ids;
    }

    /**
     * @param int[] $itemIds
     * @return int[]
     */
    private function positiveItemIds(array $itemIds): array
    {
        return array_values(array_filter(
            $itemIds,
            static fn (int $itemId): bool => $itemId > 0,
        ));
    }

    private function positiveId(mixed $value, string $field): int
    {
        if ($value === null) {
            return 0;
        }

        $id = RequestId::positive($value);
        if ($id === null) {
            throw new InvalidNavigationRequestException($field . ' must be a positive integer.');
        }

        return $id;
    }

    private function nonNegativeId(mixed $value, string $field): int
    {
        if ($value === null) {
            return 0;
        }

        $id = RequestId::nonNegative($value);
        if ($id === null) {
            throw new InvalidNavigationRequestException($field . ' must be a non-negative integer.');
        }

        return $id;
    }

    /**
     * @param list<string> $lines
     */
    private function textError(int $status, string $code, array $lines): \WP_REST_Response
    {
        return AgentTextResponse::withStatus(implode("\n", [
            'TOOL: manage_navigation',
            'RESULT: error',
            'ERROR_CODE: ' . $code,
            ...$lines,
        ]), $status);
    }
}
