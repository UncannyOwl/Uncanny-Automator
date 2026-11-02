<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Automator;

/** Explicit input limits for each reviewed authoring facade and HTTP method. */
final class SharedRestInputSchema
{
    /** @param array<string, mixed> $tool */
    public function forTool(array $tool, string $method): array
    {
        $p = [];
        foreach ($tool['parameters'] ?? [] as $parameter) {
            $p[$parameter['name']] = $this->schema($parameter);
        }
        $query = $this->object([]);
        $body = $this->object([]);
        $id = $this->id();
        $text = ['type' => 'string'];
        switch ($tool['name']) {
            case 'manage_canvas':
                $body = $this->alternatives([
                    $this->action('create', ['kind' => $this->choice(['page']), 'title' => $text], ['kind']),
                    $this->action('create', ['kind' => $this->choice(['global_part']), 'title' => $text,
                        'global_part_type' => $this->choice(['header', 'footer', 'section'])], ['kind', 'global_part_type']),
                    $this->action('list', ['kind' => $this->choice(['page', 'global_part'])], ['kind']),
                    $this->action('update', ['canvas_id' => $id, 'title' => $text], ['canvas_id', 'title']),
                    $this->action('update', ['canvas_id' => $id,
                        'shell_mode' => $this->choice(['uncanny_native', 'theme_composition', 'none'])], ['canvas_id', 'shell_mode']),
                    $this->action('attach_reusable', ['canvas_id' => $id, 'reusable_id' => $id], ['canvas_id', 'reusable_id']),
                ]);
                break;
            case 'create_preview_link':
                $body = $this->object(['page_id' => $id], ['page_id']);
                break;
            case 'create_section':
                $body = $this->object(['name' => $p['name'], 'html' => $p['html'], 'css' => $p['css']], ['name', 'html', 'css']);
                break;
            case 'read_page_context':
                $query = $this->object(['include' => $this->choice(['global_parts'])]);
                break;
            case 'read_part':
                $include = ['type' => 'string', 'pattern' => '^(source|manifest|content_targets|design_targets|bindings)(,(source|manifest|content_targets|design_targets|bindings))*$'];
                $query = $this->alternatives([
                    $this->object(['kind' => $this->choice(['section']), 'page_id' => $id,
                        'section_id' => $id, 'include' => $include], ['kind', 'page_id', 'section_id', 'include']),
                    $this->object(['kind' => $this->choice(['global_part']), 'global_part_id' => $id,
                        'include' => $include], ['kind', 'global_part_id', 'include']),
                ]);
                break;
            case 'edit_part':
                $body = $this->alternatives([
                    $this->object(['page_id' => $id,
                        'part' => $this->object(['kind' => $this->choice(['section']), 'section_id' => $id], ['kind', 'section_id']),
                        'operation' => $this->editOperation($p['operation'], false)], ['page_id', 'part', 'operation']),
                    $this->object([
                        'part' => $this->object(['kind' => $this->choice(['global_part']), 'global_part_id' => $id], ['kind', 'global_part_id']),
                        'operation' => $this->editOperation($p['operation'], true)], ['part', 'operation']),
                ]);
                break;
            case 'manage_sections':
                $body = $this->alternatives([
                    $this->action('reorder', ['section_ids' => $this->listOf($id, 200)], ['section_ids']),
                    $this->action('delete', ['section_id' => $id], ['section_id']),
                ]);
                break;
            case 'manage_reusable':
                $type = $this->choice(['header', 'footer', 'section']);
                $body = $this->alternatives([
                    $this->action('list', ['reusable_type' => $type]),
                    $this->action('create', ['title' => $text, 'reusable_type' => $type], ['reusable_type']),
                    $this->action('convert', ['page_id' => $id, 'section_id' => $id, 'title' => $text,
                        'reusable_type' => $type], ['page_id', 'section_id', 'reusable_type']),
                    $this->action('update', ['reusable_id' => $id, 'title' => $text, 'reusable_type' => $type], ['reusable_id']),
                    $this->action('delete', ['reusable_id' => $id, 'delete_mode' => $this->choice(['trash', 'delete'])], ['reusable_id', 'delete_mode']),
                ]);
                break;
            case 'manage_binding':
                if ($method === 'GET') {
                    $query = $this->alternatives([
                        $this->action('search', ['search' => $text]),
                        $this->action('guide', ['binding_id' => ['type' => 'string', 'minLength' => 1]], ['binding_id']),
                    ]);
                } else {
                    $binding = ['page_id' => $id, 'section_id' => $id, 'binding_id' => ['type' => 'string', 'minLength' => 1]];
                    $body = $this->alternatives([
                        $this->action('inspect', $binding, ['page_id', 'section_id']),
                        $this->action(
                            'update_query',
                            $binding + ['query_args' => ['type' => 'object', 'minProperties' => 1,
                            'maxProperties' => 100, 'additionalProperties' => ['type' => ['string', 'integer', 'number', 'boolean', 'null']]]],
                            ['page_id', 'section_id', 'binding_id', 'query_args']
                        ),
                        $this->action(
                            'update_template',
                            $binding + ['template_html' => ['type' => 'string', 'minLength' => 1]],
                            ['page_id', 'section_id', 'binding_id', 'template_html']
                        ),
                    ]);
                }
                break;
            case 'read_runtime':
                $query = $this->runtimeTarget();
                break;
            case 'edit_runtime':
                $body = $this->runtimeTarget([
                    $this->action('replace', ['javascript' => $text], ['javascript']),
                    $this->action('source_patch', ['javascript_patches' => $this->patches()], ['javascript_patches']),
                    $this->action('clear', []),
                ]);
                break;
            case 'preview_runtime_change':
                $body = $this->runtimeTarget([$this->object(['javascript_patches' => $this->patches()], ['javascript_patches'])]);
                break;
            case 'preview_change':
                $body = $this->object(
                    ['page_id' => $id, 'section_id' => $id, 'mode' => $this->choice(['source_patch']),
                    'html_patches' => $this->patches(), 'css_patches' => $this->patches(), 'css_rules' => $this->cssRules()],
                    ['page_id', 'section_id', 'mode']
                );
                break;
            case 'manage_navigation':
                $schema = $this->navigation($method);
                if ($method === 'GET') {
                    $query = $schema;
                } else {
                    $body = $schema;
                }
                break;
            case 'manage_media':
                if ($method === 'GET') {
                    $query = $this->alternatives([
                        $this->action('search', ['search' => $text, 'mime_type' => $text, 'size' => $text]),
                        $this->action('read', ['attachment_id' => $id, 'size' => $text], ['attachment_id']),
                    ]);
                } else {
                    $body = $this->action('upload', ['image_data' => ['type' => 'string', 'minLength' => 1],
                        'filename' => ['type' => 'string', 'minLength' => 1], 'alt' => $text], ['image_data', 'filename']);
                }
                break;
            case 'find_lucide_icons':
                $query = $this->object(['query' => ['type' => 'string', 'minLength' => 1],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 12]], ['query']);
                break;
            case 'manage_page_parts':
                if ($method === 'GET') {
                    $query = $this->alternatives([
                        $this->object(['scope' => $this->choice(['page']), 'page_id' => $id], ['scope', 'page_id']),
                        $this->object(['scope' => $this->choice(['site_defaults'])], ['scope']),
                    ]);
                } else {
                    $body = $this->pageParts();
                }
                break;
            case 'get_site_design':
                break;
            default:
                throw new \InvalidArgumentException('The Page Builder operation is outside the reviewed authoring contract.');
        }
        return $this->object(['query' => $query, 'body' => $body], ['query', 'body']);
    }

    private function editOperation(array $native, bool $global): array
    {
        $p = $native['properties'];
        $modes = [
            $this->mode('css_rule', ['css_rules' => $this->cssRules()], ['css_rules']),
            $this->mode('source_patch', ['html_patches' => $this->patches(), 'css_patches' => $this->patches(), 'css_rules' => $this->cssRules()]),
            $this->mode('source_replace', ['html' => $p['html'], 'css' => $p['css'], 'name' => $p['name'], 'reason' => $p['reason']], ['html', 'css']),
        ];
        if (!$global) {
            $target = $this->target();
            $modes = [...$modes,
                $this->mode('text', ['target' => $this->target(['expected_text' => ['type' => 'string', 'pattern' => '\\S']], ['expected_text']),
                    'text' => $p['text'], 'format' => $p['format']], ['target', 'text']),
                $this->mode('link', ['target' => $this->target(['expected_href' => ['type' => 'string', 'minLength' => 1]], ['expected_href']),
                    'href' => $p['href'], 'text' => $p['text']], ['target', 'href']),
                $this->mode('image', ['target' => $this->target(['expected_src' => ['type' => 'string', 'minLength' => 1]], ['expected_src']),
                    'src' => $p['src'], 'alt' => $p['alt'], 'loading' => $p['loading'], 'decoding' => $p['decoding'],
                    'width' => $this->id(), 'height' => $this->id()], ['target', 'src']),
                $this->mode('durable_style', ['target' => $target, 'styles' => ['type' => 'object', 'minProperties' => 1,
                    'maxProperties' => 100, 'additionalProperties' => ['type' => 'string']],
                    'viewport' => $p['viewport'], 'state' => $p['state']], ['target', 'styles']),
                $this->mode('insert_element', ['target' => $target, 'placement' => $p['placement'], 'html' => $p['html']], ['target', 'placement', 'html']),
                $this->mode('move_element', ['source' => $target, 'destination' => $target, 'placement' => $p['placement']], ['source', 'destination', 'placement']),
                $this->mode('delete_element', ['target' => $target], ['target']),
            ];
        }
        return $this->alternatives($modes);
    }

    private function target(array $extra = [], array $required = []): array
    {
        return $this->object($extra + [
            'source_path' => ['type' => 'string', 'pattern' => '^[0-9]+(?:\.[0-9]+)*$'],
            'tag' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9]*$'],
            'element_id' => ['type' => 'string'], 'selector' => ['type' => 'string'], 'identity' => ['type' => 'string'],
        ], ['source_path', 'tag', ...$required]);
    }

    /** @param list<array<string, mixed>> $operations */
    private function runtimeTarget(array $operations = []): array
    {
        $variants = [];
        foreach (['page' => 'page_id', 'global_part' => 'global_part_id'] as $scope => $field) {
            $target = ['scope' => $this->choice([$scope]), $field => $this->id()];
            if ($operations === []) {
                $variants[] = $this->object($target, ['scope', $field]);
                continue;
            }
            foreach ($operations as $operation) {
                $variants[] = $this->object($target + $operation['properties'], ['scope', $field, ...$operation['required']]);
            }
        }
        return $this->alternatives($variants);
    }

    private function navigation(string $method): array
    {
        $id = $this->id();
        if ($method === 'GET') {
            return $this->alternatives([
                $this->action('list_locations', []), $this->action('list_menus', []),
                $this->action('read_menu', ['menu_id' => $id], ['menu_id']),
            ]);
        }
        $zeroId = ['type' => 'integer', 'minimum' => 0];
        $text = ['type' => 'string'];
        $item = ['type' => $this->choice(['custom', 'post_type', 'taxonomy']), 'object_type' => $text,
            'object_id' => $zeroId, 'label' => $text, 'url' => $text, 'parent_id' => $zeroId,
            'target' => $text, 'classes' => ['type' => 'array', 'maxItems' => 50, 'items' => $text]];
        $treeItem = $this->object(['item_id' => $zeroId, 'position' => $zeroId] + $item);
        return $this->alternatives([
            $this->action('create_menu', ['name' => ['type' => 'string', 'minLength' => 1]], ['name']),
            $this->action('add_item', ['menu_id' => $id] + $item, ['menu_id', 'type', 'label']),
            $this->action('update_item', ['menu_id' => $id, 'item_id' => $id] + $item, ['menu_id', 'item_id']),
            $this->action('delete_item', ['menu_id' => $id, 'item_id' => $id], ['menu_id', 'item_id']),
            $this->action('move_item', ['menu_id' => $id, 'item_id' => $id, 'parent_id' => $zeroId,
                'position' => $zeroId], ['menu_id', 'item_id', 'parent_id', 'position']),
            $this->action('replace_tree', ['menu_id' => $id, 'replace_tree' => ['type' => 'boolean', 'enum' => [true]],
                'items' => ['type' => 'array', 'maxItems' => 200, 'items' => $treeItem]], ['menu_id', 'replace_tree', 'items']),
            $this->action('assign_location', ['menu_id' => $id, 'location_slug' => ['type' => 'string', 'minLength' => 1]], ['menu_id', 'location_slug']),
        ]);
    }

    private function pageParts(): array
    {
        $variants = [];
        foreach (['page', 'site_defaults'] as $scope) {
            foreach ($scope === 'page' ? ['part', 'inherit', 'none'] : ['part', 'inherit'] as $selection) {
                $fields = ['scope' => $this->choice([$scope]), 'slot' => $this->choice(['header', 'footer']),
                    'selection' => $this->choice([$selection]), 'expected_generation' => ['type' => 'integer', 'minimum' => 0]];
                $required = array_keys($fields);
                if ($scope === 'page') {
                    $fields['page_id'] = $this->id();
                    $required[] = 'page_id';
                }
                if ($selection === 'part') {
                    $fields['global_part_id'] = $this->id();
                    $required[] = 'global_part_id';
                }
                $variants[] = $this->action('assign', $fields, $required);
            }
        }
        return $this->alternatives($variants);
    }

    private function cssRules(): array
    {
        return $this->listOf($this->object([
            'selector' => ['type' => 'string', 'minLength' => 1],
            'set' => ['type' => 'object', 'minProperties' => 1, 'maxProperties' => 100, 'additionalProperties' => ['type' => 'string']],
            'media' => ['type' => 'string'],
        ], ['selector', 'set']), 50);
    }

    private function patches(): array
    {
        return $this->listOf($this->object([
            'action' => $this->choice(['replace', 'insert_after', 'insert_before', 'delete']),
            'search' => ['type' => 'string', 'minLength' => 1],
            'replace' => ['type' => 'string'], 'content' => ['type' => 'string'],
        ], ['search']), 50);
    }

    private function action(string $operation, array $properties, array $required = []): array
    {
        return $this->object(['operation' => $this->choice([$operation])] + $properties, ['operation', ...$required]);
    }

    private function mode(string $mode, array $properties, array $required = []): array
    {
        return $this->object(['mode' => $this->choice([$mode])] + $properties, ['mode', ...$required]);
    }

    private function id(): array
    {
        return ['type' => 'integer', 'minimum' => 1];
    }

    private function choice(array $values): array
    {
        return ['type' => 'string', 'enum' => $values];
    }

    private function listOf(array $items, int $maximum): array
    {
        return ['type' => 'array', 'minItems' => 1, 'maxItems' => $maximum, 'items' => $items];
    }

    private function alternatives(array $variants): array
    {
        return ['type' => 'object', 'oneOf' => $variants];
    }

    private function object(array $properties, array $required = []): array
    {
        return ['type' => 'object', 'properties' => $properties, 'required' => $required, 'additionalProperties' => false];
    }

    /** Retain the live parameter schema without transport or injection metadata. */
    private function schema(array $parameter): array
    {
        unset($parameter['name'], $parameter['required'], $parameter['in'], $parameter['inject_from'], $parameter['maps_to']);
        return $parameter;
    }
}
