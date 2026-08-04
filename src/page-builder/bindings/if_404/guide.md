# if_404 — Conditional: If 404 Not Found

Shows its children only when the current page is the 404 error page. If the visitor is on any valid page, the wrapper produces no output.

## Rendered HTML

This binding renders **no markup of its own**. When the condition is true,
the wrapper element is removed and your children render in its place; when
false, the wrapper and everything inside it are removed entirely:

```html
<!-- You author: -->
<div data-ai-dynamic="if_404"><p class="greeting">Hello!</p></div>

<!-- Condition true — renders as: -->
<p class="greeting">Hello!</p>

<!-- Condition false — renders as: nothing -->
```

IMPORTANT for CSS: the wrapper `<div>` does NOT exist in rendered output —
never target it. Style the children directly (`.greeting`).

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `if_404` | Marks this as a 404 error conditional wrapper |

## How It Works

- The wrapper checks `is_404()` to determine if the current request resulted in a 404 Not Found error.
- If the current page is a 404 error, the wrapper and its children render normally.
- On all valid pages, the wrapper produces no output.

## Example

```html
<div data-ai-dynamic="if_404">
  <div class="error-message">
    <h2>Page Not Found</h2>
    <p>The page you are looking for does not exist. Try searching or return to the homepage.</p>
  </div>
</div>
```

## Usage Notes

- Use this for custom 404 content within a global layout, such as helpful links, search forms, or friendly error messages.
- This allows building a single global template that handles 404 pages gracefully without a separate 404 template file.
