# wp_children — WordPress Child Pages

Render child pages or child posts of the current or specified parent.

Use this binding for sub-page grids, section landing pages, or any layout where
the children come from WordPress but the card markup is agent-authored.

## Rendered HTML

Your first child element is the **card template**; it is cloned once per
result with `data-ai-bind` values filled in. The region element and your
template markup ARE the rendered structure — CSS targets your own classes:

```html
<!-- You author: -->
<div data-ai-dynamic="wp_children" class="page-grid">
  <article class="page-card">
    <h3 data-ai-bind="title">Title</h3>
    <p data-ai-bind="excerpt">Excerpt</p>
  </article>
</div>

<!-- Renders as (one .page-card per result): -->
<div data-ai-dynamic="wp_children" class="page-grid">
  <article class="page-card">…result 1…</article>
  <article class="page-card">…result 2…</article>
</div>
```

No binding-generated classes exist; the loop reuses your template verbatim.
Style `.page-grid` for layout (grid/flex) and `.page-card` for items.

## Binding Type

- Type: `card`
- Runtime: server-side
- Output shape: one rendered card per matched child post

## Parameters

### Authoring-Required

| Attribute | Type | Authoring Rule | Runtime Default | Description |
|-----------|------|----------------|-----------------|-------------|
| `data-ai-dynamic` | string | required | — | Set to `wp_children`. |
| `data-count` | integer | recommended | `10` | Number of child posts to fetch. |
| `data-orderby` | string | recommended | `menu_order` | Sort field. |

### Optional

| Attribute | Type | Default | Allowed / Example | Description |
|-----------|------|---------|-------------------|-------------|
| `data-parent` | integer | `0` | `0`, `42` | `0` means current page/post context. |
| `data-post-type` | string | `page` | `page`, `post` | Post type to query beneath the parent. |

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
| `title` | text element | Child post title |
| `excerpt` | text element | Child post excerpt |
| `permalink` | `<a>` | Child post URL applied to `href` |
| `thumbnail` | `<img>` | Featured image URL applied to `src` |
| `date` | text element | Publish date |
| `order` | text element | Menu order value |

## Structure Rules

Safe contract:

1. Put exactly one direct child element inside the `wp_children` region.
2. That child is the child-card template.
3. Put all `data-ai-bind` nodes inside that template.

Current runtime behavior:

- If multiple direct children exist, the first direct child element becomes the template.

## Example

```html
<div data-ai-dynamic="wp_children"
     data-parent="0"
     data-count="10"
     data-orderby="menu_order"
     data-post-type="page">
  <article>
    <img data-ai-bind="thumbnail" alt="">
    <h3 data-ai-bind="title"></h3>
    <p data-ai-bind="excerpt"></p>
    <a data-ai-bind="permalink">View page</a>
  </article>
</div>
```

## Failure Points

- If `data-parent="0"` and there is no current page/post context, the binding fails closed with `<!-- No child pages found -->`.
- Empty result sets return `<!-- No child pages found -->`.
- Unsupported `data-orderby` silently degrades to `menu_order`.
- Multiple direct children can cause the wrong node to become the template.
