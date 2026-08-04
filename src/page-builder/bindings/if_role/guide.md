# if_role — Conditional: User Role

Shows its children only when the current user has the specified WordPress role. If the user is logged out or does not have the role, the wrapper produces no output.

## Rendered HTML

This binding renders **no markup of its own**. When the condition is true,
the wrapper element is removed and your children render in its place; when
false, the wrapper and everything inside it are removed entirely:

```html
<!-- You author: -->
<div data-ai-dynamic="if_role"><p class="greeting">Hello!</p></div>

<!-- Condition true — renders as: -->
<p class="greeting">Hello!</p>

<!-- Condition false — renders as: nothing -->
```

IMPORTANT for CSS: the wrapper `<div>` does NOT exist in rendered output —
never target it. Style the children directly (`.greeting`).

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `if_role` | Marks this as a role-conditional wrapper |
| `data-role` | e.g. `administrator`, `editor`, `subscriber` | WordPress role slug to check |

## How It Works

- The wrapper renders its children only if the logged-in user has the exact role specified.
- Logged-out visitors never see the content.
- Only one role can be checked per wrapper. For multiple roles, use multiple wrappers.

## Example

```html
<div data-ai-dynamic="if_role" data-role="administrator">
  <p>Admin-only content: <a href="/wp-admin/">Go to Dashboard</a></p>
</div>
```

## Usage Notes

- Use standard WordPress role slugs: `administrator`, `editor`, `author`, `contributor`, `subscriber`.
- Custom roles from plugins (e.g. `customer`, `teacher`) also work.
- This checks the user's primary role. Users with multiple roles match if any role matches.
