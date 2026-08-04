# wp_taxonomy — WordPress Taxonomy Terms

Render taxonomy terms as repeating cards.

Use this binding for category grids, tag lists, or custom taxonomy cards where
the markup is agent-authored but the term data comes from WordPress.

## Rendered HTML

Your first child element is the **card template**; it is cloned once per
result with `data-ai-bind` values filled in. The region element and your
template markup ARE the rendered structure — CSS targets your own classes:

```html
<!-- You author: -->
<div data-ai-dynamic="wp_taxonomy" class="term-grid">
  <article class="term-card">
    <h3 data-ai-bind="name">Name</h3>
    <p data-ai-bind="description">Description</p>
  </article>
</div>

<!-- Renders as (one .term-card per result): -->
<div data-ai-dynamic="wp_taxonomy" class="term-grid">
  <article class="term-card">…result 1…</article>
  <article class="term-card">…result 2…</article>
</div>
```

No binding-generated classes exist; the loop reuses your template verbatim.
Style `.term-grid` for layout (grid/flex) and `.term-card` for items.

## Binding Type

- Type: `card`
- Runtime: server-side
- Output shape: one rendered card per matched term

## Parameters

### Authoring-Required

| Attribute | Type | Authoring Rule | Runtime Default | Description |
|-----------|------|----------------|-----------------|-------------|
| `data-ai-dynamic` | string | required | — | Set to `wp_taxonomy`. |
| `data-taxonomy` | string | recommended | `category` | Taxonomy slug to query. |
| `data-count` | integer | recommended | `6` | Number of terms to fetch. |
| `data-orderby` | string | recommended | `name` | Sort field. |
| `data-hide-empty` | boolean | recommended | `true` | Hide empty terms. |

### Optional

| Attribute | Type | Default | Allowed / Example | Description |
|-----------|------|---------|-------------------|-------------|
| `data-parent` | integer | `-1` | `-1`, `0`, `42` | `-1` = all terms, `0` = top-level only, other IDs = children of that parent term. |

Allowed `data-orderby` values:

- `name`
- `slug`
- `term_group`
- `term_id`
- `id`
- `description`
- `count`
- `include`

Invalid `data-orderby` values fail closed to `name`.

## Bind Keys

| Key | Use On | Result |
|-----|--------|--------|
| `name` | text element | Term name |
| `description` | text element | Term description |
| `count` | text element | Number of posts in the term |
| `permalink` | `<a>` | Term archive URL applied to `href` |
| `thumbnail` | `<img>` | Term image URL applied to `src` |
| `slug` | text element | Term slug |

## Structure Rules

Safe contract:

1. Put exactly one direct child element inside the `wp_taxonomy` region.
2. That child is the term card template.
3. Put all `data-ai-bind` nodes inside that template.

Current runtime behavior:

- If multiple direct children exist, the first direct child element becomes the template.

## Example

```html
<div data-ai-dynamic="wp_taxonomy"
     data-taxonomy="category"
     data-count="6"
     data-orderby="name"
     data-hide-empty="true">
  <article>
    <img data-ai-bind="thumbnail" alt="">
    <h3 data-ai-bind="name"></h3>
    <p data-ai-bind="description"></p>
    <a data-ai-bind="permalink">Browse</a>
  </article>
</div>
```

## Failure Points

- Empty results or `WP_Error` return `<!-- No terms found -->`.
- Unsupported `data-orderby` silently degrades to `name`.
- `thumbnail` renders empty when no term thumbnail meta exists.
- Multiple direct children can cause the wrong node to become the template.
