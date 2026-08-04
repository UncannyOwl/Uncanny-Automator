# wp_embed — oEmbed Content

Renders embedded media content using WordPress oEmbed, but only for WordPress sanctioned providers.

## Rendered HTML

The region's children are replaced with the markup below (captured from a real render). Classes shown here are the stable CSS hooks:

```html
<div class="upb-embed"><iframe title="Rick Astley - Never Gonna Give You Up (Official Video) (4K Remaster)" width="1140" height="641" src="https://www.youtube.com/embed/dQw4w9WgXcQ?feature=oembed" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe></div>
```

## Required Attributes

| Attribute | Required | Description |
|-----------|----------|-------------|
| `data-ai-dynamic` | yes | Set to `wp_embed` |
| `data-url` | yes | Full URL to embed (e.g. a YouTube video URL) |

## Example

```html
<div data-ai-dynamic="wp_embed" data-url="https://www.youtube.com/watch?v=dQw4w9WgXcQ"></div>
<div data-ai-dynamic="wp_embed" data-url="https://vimeo.com/123456789"></div>
```

## Usage Notes

- Uses WordPress built-in oEmbed with provider discovery disabled.
- Supported providers are the sanctioned providers WordPress already ships with or explicitly registers.
- Arbitrary `https://` URLs are not treated as embeddable just because they return discovery metadata.
- If the URL is not recognized or the provider is unavailable, the element renders no visible output.
- WordPress caches oEmbed responses, so repeated renders are fast.
- The embed output is responsive by default via WordPress embed handling.
