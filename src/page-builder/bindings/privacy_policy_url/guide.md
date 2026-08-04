# privacy_policy_url — Privacy Policy URL

Render the WordPress privacy policy page URL.

## Rendered HTML

This binding renders **no markup at all** — it writes a URL onto the host
element's attribute (`href` on `<a>`, `src` on `<img>`). Children of the
host element are your authored content and are untouched:

```html
<!-- You author: -->
<a data-ai-dynamic="privacy_policy_url" href="#" class="your-class">Your link text</a>

<!-- Renders as: -->
<a data-ai-dynamic="privacy_policy_url" href="https://example.com/resolved" class="your-class">Your link text</a>
```

Style the host element and its children — there are no binding-generated
elements or classes.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `privacy_policy_url` | Marks this as a privacy_policy_url region |

## Bind Keys

**None.** This is a self-rendering binding. The content is replaced automatically.

## Example

```html
<span data-ai-dynamic="privacy_policy_url">Placeholder</span>
```

## Notes

- Uses get_privacy_policy_url(). Returns empty if no privacy policy page is set in Settings > Privacy.
