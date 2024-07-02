<?php
use Uncanny_Automator\Services\Dashboard\Recent_Articles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<ul id="uap-blog-posts-list">
	<li style="list-style: none;">
		<uo-button color="secondary" loading="true">
			<?php esc_html_e( 'Fetching blog posts...', 'uncanny-automator' ); ?>   
		</uo-button>
	</li>
</ul>

<script>

	<?php $nonce = wp_create_nonce( 'automator_get_recent_articles' ); ?>
	// The endpoint URL.
	const url = `<?php echo esc_url_raw( admin_url( "admin-ajax.php?action=automator_get_recent_articles&nonce={$nonce}" ) ); ?>`;
	// The unordered list element where we insert the list later on.
	const listElement = document.getElementById('uap-blog-posts-list');

	// Function to count words in a string.
	const countWords = str => str.trim().split(/\s+/).length;

	// Function to calculate reading time from content.
	const getReadingTime = content => {
		const wordsPerMinute = 250;
		const numberOfWords = countWords(content.replace(/<[^>]+>/g, ''));
		return `&mdash; ${Math.ceil((numberOfWords / wordsPerMinute))} minutes`;
	}

	// Start an AJAX request to automatorplugin.com endpoint.
	fetch(url)
		.then(response => {
			if (!response.ok) {
				throw new Error('Network response was not ok');
			}
			return response.json();
		})
		.then(data => {
			const listItems = data.posts.map(item => {
				const categoryName = (item._embedded['wp:term'][0][0]?.name) || '';
				const readingTime = getReadingTime(item.content.rendered);
				return `
					<li class="uap-blog-posts-list__item" style="list-style: none;">
						<div>
							<div class="uap-blog-posts-list__item__category">
								<span class="uap-blog-posts-list__item__category__name">
									${categoryName}
								</span>
								<span class="uap-blog-posts-list__item__category__reading-time">
									${readingTime}
								</span>
							</div>
							<div class="uap-blog-posts-list__item__link-title">
								<a target="_blank" href="${item.link}" title="${item.title.rendered}">
									${item.title.rendered}
								</a>
							</div>
						</div>
					</li>`;
			}).join('');
			listElement.innerHTML = listItems;
		})
		.catch(error => {
			// Show error message?
			listElement.innerHTML = '';
		});

</script>
