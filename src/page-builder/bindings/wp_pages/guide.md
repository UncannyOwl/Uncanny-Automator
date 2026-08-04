# wp_pages — WordPress Pages

Render top-level pages as repeating cards.

This binding uses the same renderer as `wp_children`, but it always queries
top-level pages and ignores current page context.

## Rendered HTML

Your first child element is the **card template**; it is cloned once per
result with `data-ai-bind` values filled in. The region element and your
template markup ARE the rendered structure — CSS targets your own classes:

```html
<!-- You author: -->
<div data-ai-dynamic="wp_pages" class="page-grid">
  <article class="page-card">
    <h3 data-ai-bind="title">Title</h3>
    <p data-ai-bind="excerpt">Excerpt</p>
  </article>
</div>

<!-- Renders as (one .page-card per result): -->
<div data-ai-dynamic="wp_pages" class="page-grid">
  <article class="page-card">…result 1…</article>
  <article class="page-card">…result 2…</article>
</div>
```

No binding-generated classes exist; the loop reuses your template verbatim.
Style `.page-grid` for layout (grid/flex) and `.page-card` for items.

## Binding Type

- Type: `card`
- Runtime: server-side
- Output shape: one rendered card per matched top-level page

## Parameters

### Authoring-Required

| Attribute | Type | Authoring Rule | Runtime Default | Description |
|-----------|------|----------------|-----------------|-------------|
| `data-ai-dynamic` | string | required | — | Set to `wp_pages`. |
| `data-count` | integer | recommended | `10` | Number of pages to fetch. |
| `data-orderby` | string | recommended | `menu_order` | Sort field. |

Allowed `data-orderby` values:

- `menu_order`
- `title`
- `date`
- `modified`
- `ID`
- `name`
- `rand`

Invalid `data-orderby` values fail closed to `menu_order`.

## Bind Keys

| Key | Use On | Result |
|-----|--------|--------|
| `title` | text element | Page title |
| `excerpt` | text element | Page excerpt |
| `permalink` | `<a>` | Page URL applied to `href` |
| `thumbnail` | `<img>` | Featured image URL applied to `src` |
| `date` | text element | Publish date |
| `order` | text element | Menu order value |

## Structure Rules

Safe contract:

1. Put exactly one direct child element inside the `wp_pages` region.
2. That child is the page-card template.
3. Put all `data-ai-bind` nodes inside that template.

Current runtime behavior:

- If multiple direct children exist, the first direct child element becomes the template.

## Example

```html
<div data-ai-dynamic="wp_pages"
     data-count="10"
     data-orderby="menu_order">
  <article>
    <img data-ai-bind="thumbnail" alt="">
    <h3 data-ai-bind="title"></h3>
    <p data-ai-bind="excerpt"></p>
    <a data-ai-bind="permalink">Visit</a>
  </article>
</div>
```

## Failure Points

- Empty result sets return `<!-- No pages found -->`.
- Unsupported `data-orderby` silently degrades to `menu_order`.
- Multiple direct children can cause the wrong node to become the template.
