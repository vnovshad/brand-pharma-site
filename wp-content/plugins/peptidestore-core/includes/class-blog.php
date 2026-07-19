<?php
/**
 * Blog architecture module.
 *
 * Enriches the Article JSON-LD schema (emitted by class-schema.php) with:
 *   - @type → BlogPosting  (more specific than Article for blog content)
 *   - publisher (Organization)
 *   - description (post excerpt)
 *   - keywords (comma-separated post tags)
 *   - url (canonical permalink)
 *   - image (featured image, already added by class-schema.php for posts
 *     with thumbnails — this adds a structured ImageObject form)
 *
 * The filter peptidestore_schema_article fires for every singular post.
 * Any content module that needs richer blog-post schema can add its own
 * filter at a higher priority without touching this class.
 *
 * @package Peptide_Store
 */
namespace Peptide_Store;

defined( 'ABSPATH' ) || exit;

class Blog {

	public function __construct() {
		add_filter( 'peptidestore_schema_article', array( $this, 'enrich_article_schema' ), 10, 2 );
		add_filter( 'the_content', array( $this, 'append_about_box' ), 20 );
	}

	/**
	 * Append a house author/about box after the post content on singular posts.
	 * Keyed on is_singular('post') so it never fires on pages, products, or archives.
	 */
	public function append_about_box( string $content ): string {
		if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$box = '
<aside class="psc-about-box" aria-label="About the author">
	<p class="psc-about-box__name">Brand Pharma Team</p>
	<p class="psc-about-box__bio">Brand Pharma is a Canadian supplier of research-use-only peptides, held to a pharmaceutical standard. Every batch is independently verified by HPLC-MS at a third-party laboratory, with a Certificate of Analysis available for each product. The articles in our Research Library draw on published, peer-reviewed studies and are intended for qualified researchers.</p>
</aside>';
		return $content . $box;
	}

	public function enrich_article_schema( array $data, int $post_id ): array {

		// Use the more-specific BlogPosting type for site posts.
		$data['@type'] = 'BlogPosting';

		// Canonical URL.
		$data['url'] = get_permalink( $post_id );

		// Description from post excerpt (strip tags, trim).
		$excerpt = get_the_excerpt( $post_id );
		if ( $excerpt ) {
			$data['description'] = wp_strip_all_tags( $excerpt );
		}

		// Keywords from assigned tags.
		$tags = get_the_tags( $post_id );
		if ( $tags && ! is_wp_error( $tags ) ) {
			$data['keywords'] = implode( ', ', wp_list_pluck( $tags, 'name' ) );
		}

		// Publisher references the single enriched Organization node (class-schema.php),
		// rather than repeating a stub. Keeps one Organization node per @graph.
		$data['publisher'] = array( '@id' => home_url( '/#organization' ) );

		// Upgrade image to structured ImageObject if a featured image exists
		// and class-schema.php has already placed the raw URL in $data['image'].
		if ( ! empty( $data['image'] ) && is_string( $data['image'] ) ) {
			$img_id = get_post_thumbnail_id( $post_id );
			if ( $img_id ) {
				$meta = wp_get_attachment_metadata( $img_id );
				$data['image'] = array(
					'@type'  => 'ImageObject',
					'url'    => $data['image'],
					'width'  => $meta['width']  ?? null,
					'height' => $meta['height'] ?? null,
				);
			}
		}

		// Article section from the primary category.
		$cats = get_the_category( $post_id );
		if ( $cats ) {
			$data['articleSection'] = $cats[0]->name;
		}

		// inLanguage
		$data['inLanguage'] = 'en-CA';

		return $data;
	}
}
