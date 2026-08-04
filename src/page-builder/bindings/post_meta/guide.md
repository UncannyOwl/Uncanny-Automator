# post_meta — Post Meta Field

Renders the value of a governed post meta field for the current post or a specific post.

## Rendered HTML

This binding renders **plain text only** — no elements are added. The text
replaces the region element's children, so CSS targets the element you
authored:

```html
<!-- You author (or the mask form <!-- upb:bindings:dynamic_data:post_meta -->): -->
<span data-ai-dynamic="post_meta" class="your-class"></span>

<!-- Renders as: -->
<span data-ai-dynamic="post_meta" class="your-class">The resolved text value</span>
```

Style it by styling your own element (`.your-class`). There are no
binding-generated classes to target.

## Required Attributes

| Attribute | Required | Default | Description |
|-----------|----------|---------|-------------|
| `data-ai-dynamic` | yes | — | Set to `post_meta` |
| `data-key` | yes | — | The meta key to retrieve (e.g. `price`, `event_date`) |
| `data-post-id` | no | `0` (current post) | Specific post ID; `0` uses the current post |

## Example

```html
<span data-ai-dynamic="post_meta" data-key="price"></span>
<span data-ai-dynamic="post_meta" data-key="event_date" data-post-id="42"></span>
```

## Usage Notes

- Returns the first value for the given meta key (single mode).
- If the key does not exist, the element renders empty.
- Works with ACF, Pods, Toolset, or any plugin that stores data in `wp_postmeta`.
- Typed meta values are rendered according to the registered key policy:
  - text keys render as text
  - URL keys render as URLs
  - image keys render as attachment or image URLs
  - number keys render as normalized numeric text
- Protected underscore-prefixed keys are rejected unless the system explicitly registers the key for safe typed output.
- Sensitive or blocked meta keys fail closed and render empty.
