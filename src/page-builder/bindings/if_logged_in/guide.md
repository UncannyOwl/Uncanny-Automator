# if_logged_in — Conditional: Logged In

Shows its children only when the current visitor is a logged-in WordPress user. If the visitor is logged out, the entire wrapper and its children are removed from output.

## Rendered HTML

This binding renders **no markup of its own**. When the condition is true,
the wrapper element is removed and your children render in its place; when
false, the wrapper and everything inside it are removed entirely:

```html
<!-- You author: -->
<div data-ai-dynamic="if_logged_in"><p class="greeting">Hello!</p></div>

<!-- Condition true — renders as: -->
<p class="greeting">Hello!</p>

<!-- Condition false — renders as: nothing -->
```

IMPORTANT for CSS: the wrapper `<div>` does NOT exist in rendered output —
never target it. Style the children directly (`.greeting`).

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `if_logged_in` | Marks this as a logged-in conditional wrapper |

## How It Works

- The wrapper element and all its children render normally when the user is logged in.
- When the user is logged out, the wrapper produces no output at all.
- This is a visibility wrapper — it does not alter or bind any data inside.

## Example

```html
<div data-ai-dynamic="if_logged_in">
  <p>Welcome back! You are logged in.</p>
  <a href="/dashboard">Go to Dashboard</a>
</div>
```

## Usage Notes

- Pair with `if_logged_out` for a complete logged-in / logged-out experience.
- Do not nest `if_logged_in` inside another `if_logged_in` — it is redundant.
- Content inside is only hidden server-side; it never reaches the browser for logged-out visitors.
