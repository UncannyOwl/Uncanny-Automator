# if_single — Conditional: If Single Post

Shows its children only when WordPress reports the current request as `is_single()`. If the visitor is on any other page type, the wrapper produces no output.

## Rendered HTML

This binding renders **no markup of its own**. When the condition is true,
the wrapper element is removed and your children render in its place; when
false, the wrapper and everything inside it are removed entirely:

```html
<!-- You author: -->
<div data-ai-dynamic="if_single"><p class="greeting">Hello!</p></div>

<!-- Condition true — renders as: -->
<p class="greeting">Hello!</p>

<!-- Condition false — renders as: nothing -->
```

IMPORTANT for CSS: the wrapper `<div>` does NOT exist in rendered output —
never target it. Style the children directly (`.greeting`).

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `if_single` | Marks this as a single post conditional wrapper |

## How It Works

- The wrapper checks `is_single()` directly.
- If the current page matches `is_single()`, the wrapper and its children render normally.
- On all other page types, the wrapper produces no output.
- It does not match static pages.
- Custom post type behavior follows WordPress `is_single()` semantics, not `is_singular()`.

## Example

```html
<div data-ai-dynamic="if_single">
  <div class="post-meta-bar">
    <p>You are reading a blog post. Share it with your friends!</p>
  </div>
</div>
```

## Usage Notes

- `if_single` follows raw WordPress `is_single()` behavior.
- For static pages, use `if_page` instead.
- Use this for post-specific elements like share buttons, author bios, or related posts sections within a global layout.
