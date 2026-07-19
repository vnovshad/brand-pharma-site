<?php
/**
 * Blog importer.
 *
 * One-time routine that clears existing posts and imports the blog package in
 * data/peptide-blog-posts.md (title, body, category, tags, meta description).
 * Owner-supplied content — published verbatim per owner direction.
 *
 * Re-run by bumping IMPORT_VERSION.
 *
 * @package Peptide_Store
 */
namespace Peptide_Store;

defined( 'ABSPATH' ) || exit;

class Blog_Importer {

	const IMPORT_VERSION = '1';
	const OPTION_KEY     = 'peptidestore_blog_import_version';

	public function __construct() {
		add_action( 'init', array( $this, 'maybe_import' ), 25 );
	}

	public function maybe_import(): void {
		if ( get_option( self::OPTION_KEY ) === self::IMPORT_VERSION ) {
			return;
		}

		$file = PEPTIDE_STORE_PATH . 'data/peptide-blog-posts.md';
		if ( ! file_exists( $file ) ) {
			return;
		}

		try {
			$posts = $this->parse( (string) file_get_contents( $file ) );
			if ( ! empty( $posts ) ) {
				$this->trash_existing_posts();
				// Insert in reverse so POST 1 ends up newest (top of the blog).
				foreach ( array_reverse( $posts ) as $post ) {
					$this->insert_post( $post );
				}
			}
		} catch ( \Throwable $e ) {
			if ( function_exists( 'error_log' ) ) {
				error_log( '[peptidestore] blog import error: ' . $e->getMessage() );
			}
		}

		update_option( self::OPTION_KEY, self::IMPORT_VERSION );
	}

	// ── Parsing ───────────────────────────────────────────────────────────────

	/** @return array<int,array{title:string,category:string,tags:array,excerpt:string,content:string}> */
	private function parse( string $raw ): array {
		$raw = str_replace( "\r\n", "\n", $raw );
		// Drop the trailing "# END OF BLOG POST PACKAGE" footer.
		$raw = preg_replace( '/\n#\s*END OF BLOG POST.*$/s', '', $raw );

		$chunks = preg_split( '/^#\s*POST\s+\d+:\s*/m', $raw );
		array_shift( $chunks ); // file header before POST 1

		$posts = array();
		foreach ( $chunks as $chunk ) {
			$nl    = strpos( $chunk, "\n" );
			$title = trim( false === $nl ? $chunk : substr( $chunk, 0, $nl ) );
			$rest  = false === $nl ? '' : substr( $chunk, $nl + 1 );
			if ( '' === $title ) {
				continue;
			}

			$category = '';
			$tags     = array();
			$excerpt  = '';
			if ( preg_match( '/\*\*Category:\*\*\s*([^|\n]+?)\s*(?:\||\n|$)/', $rest, $m ) ) {
				$category = trim( $m[1] );
			}
			if ( preg_match( '/\*\*Tags:\*\*\s*([^\n]+)/', $rest, $m ) ) {
				$tags = array_filter( array_map( 'trim', explode( ',', $m[1] ) ) );
			}
			if ( preg_match( '/\*\*Meta Description:\*\*\s*([^\n]+)/', $rest, $m ) ) {
				$excerpt = trim( $m[1] );
			}

			// Body = everything after the first "---" line (which follows the meta block).
			$sep = strpos( $rest, "\n---\n" );
			$body = false === $sep ? $rest : substr( $rest, $sep + 5 );
			$body = trim( (string) preg_replace( '/(\n-{3,}\s*)+$/', '', trim( $body ) ) );

			$posts[] = array(
				'title'    => $title,
				'category' => $category,
				'tags'     => $tags,
				'excerpt'  => $excerpt,
				'content'  => $this->body_to_html( $body ),
			);
		}
		return $posts;
	}

	private function body_to_html( string $body ): string {
		$blocks = preg_split( '/\n[ \t]*\n/', $body );
		$html   = '';
		foreach ( $blocks as $block ) {
			$b = trim( $block );
			if ( '' === $b || preg_match( '/^-{3,}$/', $b ) ) {
				continue;
			}
			// Section heading: whole block wrapped in ** ** with no inner markup / newline.
			if ( preg_match( '/^\*\*(.+?)\*\*$/s', $b, $m ) && false === strpos( $m[1], '**' ) && false === strpos( $m[1], "\n" ) ) {
				$html .= '<h2>' . esc_html( $m[1] ) . "</h2>\n\n";
				continue;
			}
			// Italic disclaimer line: *...* (single asterisks).
			if ( preg_match( '/^\*([^*].*?)\*$/s', $b, $m ) ) {
				$html .= '<p class="psc-blog-disclaimer"><em>' . $this->inline( $m[1] ) . "</em></p>\n\n";
				continue;
			}
			$html .= '<p>' . $this->inline( $b ) . "</p>\n\n";
		}
		return trim( $html );
	}

	/** Escape, then apply inline **bold**. */
	private function inline( string $text ): string {
		$text = esc_html( $text );
		$text = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text );
		return $text;
	}

	// ── Writing ───────────────────────────────────────────────────────────────

	private function trash_existing_posts(): void {
		$ids = get_posts( array(
			'post_type'   => 'post',
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
		) );
		foreach ( $ids as $id ) {
			wp_trash_post( (int) $id );
		}
	}

	/** @param array{title:string,category:string,tags:array,excerpt:string,content:string} $post */
	private function insert_post( array $post ): void {
		$cat_ids = array();
		if ( '' !== $post['category'] ) {
			$term = term_exists( $post['category'], 'category' );
			if ( ! $term ) {
				$term = wp_insert_term( $post['category'], 'category' );
			}
			if ( ! is_wp_error( $term ) ) {
				$cat_ids[] = (int) ( is_array( $term ) ? $term['term_id'] : $term );
			}
		}

		wp_insert_post( array(
			'post_title'    => $post['title'],
			'post_content'  => $post['content'],
			'post_excerpt'  => $post['excerpt'],
			'post_status'   => 'publish',
			'post_type'     => 'post',
			'post_author'   => 1,
			'post_category' => $cat_ids,
			'tags_input'    => $post['tags'],
		) );
	}
}
