# user_meta — User Meta Field

Renders the value of a governed user meta field for the current user or a specific user.

## Rendered HTML

This binding renders **plain text only** — no elements are added. The text
replaces the region element's children, so CSS targets the element you
authored.

```html
<!-- You author (or the mask form <!-- upb:bindings:dynamic_data:user_meta -->): -->
<span data-ai-dynamic="user_meta" class="your-class"></span>

<!-- Renders as: -->
<span data-ai-dynamic="user_meta" class="your-class">The resolved text value</span>
```

Style it by styling your own element (`.your-class`). There are no
binding-generated classes to target.

## Required Attributes

| Attribute | Required | Default | Description |
|-----------|----------|---------|-------------|
| `data-ai-dynamic` | yes | — | Set to `user_meta` |
| `data-key` | yes | — | The user meta key to retrieve |
| `data-user-id` | no | `0` (current user) | Specific user ID; `0` uses the current logged-in user |

## Example

```html
<span data-ai-dynamic="user_meta" data-key="company_name"></span>
<span data-ai-dynamic="user_meta" data-key="phone" data-user-id="5"></span>
```

## Usage Notes

- Returns the first value for the given meta key (single mode).
- If the user is logged out and no `data-user-id` is specified, the element renders empty.
- Works with any plugin that stores data in `wp_usermeta` (BuddyPress, Ultimate Member, etc.).
- Typed meta values are rendered according to the registered key policy:
  - text keys render as text
  - URL keys render as URLs
  - image keys render as attachment or image URLs
  - number keys render as normalized numeric text
- Sensitive, blocked, or ungoverned protected keys fail closed and render empty.
- This binding is request-sensitive because it can depend on the logged-in user.
