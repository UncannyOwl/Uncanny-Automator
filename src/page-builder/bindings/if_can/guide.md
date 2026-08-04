# if_can — Conditional: If Capability

Shows its children only when the current user has the specified WordPress capability. If the user is logged out or does not have the capability, the wrapper produces no output.

## Rendered HTML

This binding renders **no markup of its own**. When the condition is true,
the wrapper element is removed and your children render in its place; when
false, the wrapper and everything inside it are removed entirely:

```html
<!-- You author: -->
<div data-ai-dynamic="if_can"><p class="greeting">Hello!</p></div>

<!-- Condition true — renders as: -->
<p class="greeting">Hello!</p>

<!-- Condition false — renders as: nothing -->
```

IMPORTANT for CSS: the wrapper `<div>` does NOT exist in rendered output —
never target it. Style the children directly (`.greeting`).

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `if_can` | Marks this as a capability conditional wrapper |
| `data-capability` | e.g. `edit_posts`, `manage_options` | WordPress capability to check |

## How It Works

- The wrapper checks `current_user_can()` with the specified capability.
- If the current user has the capability, the wrapper and its children render normally.
- If the user is logged out or lacks the capability, the wrapper produces no output.
- Only one capability can be checked per wrapper. For multiple capabilities, use multiple wrappers.

## Example

```html
<div data-ai-dynamic="if_can" data-capability="edit_posts">
  <p>You have permission to edit posts.</p>
  <a href="/wp-admin/edit.php">Manage Posts</a>
</div>
```

## Usage Notes

- Common capabilities: `edit_posts`, `manage_options`, `upload_files`, `edit_pages`, `publish_posts`, `moderate_comments`.
- Capabilities are more granular than roles. A single role may have many capabilities.
- Custom capabilities registered by plugins also work.
- This is server-side only — content never reaches the browser for users without the capability.
