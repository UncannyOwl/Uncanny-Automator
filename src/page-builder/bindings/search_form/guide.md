# search_form — Search Form

Render the WordPress search form.

## Rendered HTML

The region's children are replaced with WordPress core's search form. The
default markup and its stable CSS hooks:

```html
<form role="search" method="get" class="search-form" action="https://example.com/">
	<label>
		<span class="screen-reader-text">Search for:</span>
		<input type="search" class="search-field" placeholder="Search …" value="" name="s" />
	</label>
	<input type="submit" class="search-submit" value="Search" />
</form>
```

IMPORTANT: the output comes from `get_search_form()`, which themes and
plugins filter — e.g. WooCommerce replaces it with a product search form
(`form.woocommerce-product-search`, `input.wc-search-field`). Write CSS
against your authored wrapper element first, and against `.search-form` /
`.search-field` / `.search-submit` only as the default-case hooks.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `search_form` | Marks this as a search_form region |

## Bind Keys

**None.** Self-rendering binding.

## Example

```html
<div data-ai-dynamic="search_form">Placeholder</div>
```

## Notes

- Uses get_search_form(). Output markup depends on the active theme.
