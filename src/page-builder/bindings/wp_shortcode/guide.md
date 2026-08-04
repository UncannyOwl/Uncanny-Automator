# wp_shortcode — WordPress Shortcode

Renders the output of a WordPress shortcode. This is a self-rendering binding — you do NOT use `data-ai-bind` keys. The renderer executes the shortcode server-side and replaces the element content with the result.

## Rendered HTML

The region's children are replaced with the markup below (captured from a real render). Classes shown here are the stable CSS hooks:

```html
<div id='gallery-1' class='gallery galleryid-0 gallery-columns-3 gallery-size-thumbnail'><figure class='gallery-item'>
			<div class='gallery-icon landscape'>
				<a href='https://example.com/sample-page/'><img width="150" height="150" src="https://example.com/wp-content/uploads/sample-image-150x150.jpg" class="attachment-thumbnail size-thumbnail" alt="" decoding="async" /></a>
			</div></figure><figure class='gallery-item'>
			<div class='gallery-icon landscape'>
				<a href='https://example.com/favicon-32px/'><img width="32" height="32" src="https://example.com/wp-content/uploads/2025/09/favicon-32px.webp" class="attachment-thumbnail size-thumbnail" alt="" decoding="async" /></a>
			</div></figure>
		</div>
```

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `wp_shortcode` | Marks this as a shortcode region |
| `data-shortcode` | e.g. `[gallery ids="1,2,3"]` | The full shortcode string to execute |

## Bind Keys

**None.** `wp_shortcode` is self-rendering. Do not use `data-ai-bind` inside this region.

## Example

```html
<section class="shortcode-section py-5">
  <div class="container">
    <div data-ai-dynamic="wp_shortcode"
         data-shortcode='[gallery ids="1,2,3" columns="3"]'>
      <!-- Renderer replaces this with the shortcode output -->
    </div>
  </div>
</section>
```

## Notes

- The shortcode is executed server-side with `do_shortcode()` after every shortcode tag in the string passes the runtime blocklist.
- Plugin shortcodes are allowed by default so installed WordPress shortcode integrations render normally.
- `[embed]` is blocked by default; use the `wp_embed` binding for embeds because it disables remote provider discovery.
- The shortcode string must be complete and valid, including brackets
- Developers can block additional tags with `uncanny_page_builder_blocked_shortcodes`. Returning `true` blocks all shortcode execution for this binding.

## Security Warning

Shortcodes can execute plugin code and may have side effects. Use this binding only when the shortcode itself is the intended source of truth, and block tags that are unsafe for the site.
