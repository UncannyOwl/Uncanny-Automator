# admin_email — Admin Email

Render the site administrator email address for privileged users only.

## Rendered HTML

This binding renders **plain text only** — no elements are added. The text
replaces the region element's children, so CSS targets the element you
authored:

```html
<!-- You author (or the mask form <!-- upb:bindings:dynamic_data:admin_email -->): -->
<span data-ai-dynamic="admin_email" class="your-class"></span>

<!-- Renders as: -->
<span data-ai-dynamic="admin_email" class="your-class">The resolved text value</span>
```

Style it by styling your own element (`.your-class`). There are no
binding-generated classes to target.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `admin_email` | Marks this as a admin_email region |

## Bind Keys

**None.** This is a self-rendering binding. The content is replaced automatically.

## Example

```html
<span data-ai-dynamic="admin_email">Placeholder</span>
```

## Notes

- Uses `get_bloginfo('admin_email')`.
- Runtime only renders for users who can `manage_options`.
- For non-admin visitors, this binding renders empty.
- Treat this as an admin-only/request-sensitive binding, not a public contact binding.
