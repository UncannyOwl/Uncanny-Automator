# if_logged_out — Conditional: Logged Out

Shows its children only when the current visitor is NOT logged in. If the visitor is logged in, the entire wrapper and its children are removed from output.

## Rendered HTML

This binding renders **no markup of its own**. When the condition is true,
the wrapper element is removed and your children render in its place; when
false, the wrapper and everything inside it are removed entirely:

```html
<!-- You author: -->
<div data-ai-dynamic="if_logged_out"><p class="greeting">Hello!</p></div>

<!-- Condition true — renders as: -->
<p class="greeting">Hello!</p>

<!-- Condition false — renders as: nothing -->
```

IMPORTANT for CSS: the wrapper `<div>` does NOT exist in rendered output —
never target it. Style the children directly (`.greeting`).

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `if_logged_out` | Marks this as a logged-out conditional wrapper |

## How It Works

- The wrapper element and all its children render normally when the user is logged out.
- When the user is logged in, the wrapper produces no output at all.
- This is a visibility wrapper — it does not alter or bind any data inside.

## Example

```html
<div data-ai-dynamic="if_logged_out">
  <p>Please log in to access your account.</p>
  <a href="/login">Log In</a>
</div>
```

## Usage Notes

- Pair with `if_logged_in` for a complete logged-in / logged-out experience.
- Content inside never reaches the browser for logged-in visitors.
