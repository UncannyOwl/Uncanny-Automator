# if_home — Conditional: If Home (Blog)

Shows its children only when the current page is the blog posts listing page. If the visitor is on any other page, the wrapper produces no output.

## Rendered HTML

This binding renders **no markup of its own**. When the condition is true,
the wrapper element is removed and your children render in its place; when
false, the wrapper and everything inside it are removed entirely:

```html
<!-- You author: -->
<div data-ai-dynamic="if_home"><p class="greeting">Hello!</p></div>

<!-- Condition true — renders as: -->
<p class="greeting">Hello!</p>

<!-- Condition false — renders as: nothing -->
```

IMPORTANT for CSS: the wrapper `<div>` does NOT exist in rendered output —
never target it. Style the children directly (`.greeting`).

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `if_home` | Marks this as a blog home conditional wrapper |

## How It Works

- The wrapper checks `is_home()` to determine if the current page is the blog posts listing page.
- If the current page is the blog posts page, the wrapper and its children render normally.
- On all other pages, the wrapper produces no output.
- This checks the blog posts page, not necessarily the front page of the site.

## Example

```html
<div data-ai-dynamic="if_home">
  <h2>Latest Blog Posts</h2>
  <p>Browse our most recent articles below.</p>
</div>
```

## Usage Notes

- `if_home` matches the blog posts listing page. This is different from `if_front_page`, which matches the static front page set in Settings > Reading.
- If a static front page is set, `if_home` matches the separate posts page (the page assigned as "Posts page" in Settings > Reading).
- When no static front page is set, the default blog listing page matches both `if_home` and `if_front_page`.
- Use `if_home` for blog-specific headers, introductions, or layout sections within a global template.
