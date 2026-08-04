# if_page — Conditional: If Page

Shows its children only when the current page is a WordPress static page. If the visitor is on any other page type, the wrapper produces no output.

## Rendered HTML

This binding renders **no markup of its own**. When the condition is true,
the wrapper element is removed and your children render in its place; when
false, the wrapper and everything inside it are removed entirely:

```html
<!-- You author: -->
<div data-ai-dynamic="if_page"><p class="greeting">Hello!</p></div>

<!-- Condition true — renders as: -->
<p class="greeting">Hello!</p>

<!-- Condition false — renders as: nothing -->
```

IMPORTANT for CSS: the wrapper `<div>` does NOT exist in rendered output —
never target it. Style the children directly (`.greeting`).

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `if_page` | Marks this as a page conditional wrapper |

## How It Works

- The wrapper checks `is_page()` to determine if the current page is a WordPress static page.
- If the current page is a static page, the wrapper and its children render normally.
- On all other page types (posts, archives, search), the wrapper produces no output.

## Example

```html
<div data-ai-dynamic="if_page">
  <nav class="page-sidebar">
    <p>This sidebar only appears on static pages.</p>
  </nav>
</div>
```

## Usage Notes

- `if_page` matches WordPress pages (the "Pages" post type), not blog posts.
- For single blog posts, use `if_single` instead.
- Use this for page-specific layouts, sidebars, or calls to action that should not appear on posts or archives.
