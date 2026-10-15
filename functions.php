<?php
/** ORWO.co native block theme. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once __DIR__ . '/includes/media.php';
require_once __DIR__ . '/includes/setup.php';
require_once __DIR__ . '/includes/contact-form.php';
require_once __DIR__ . '/includes/form-settings.php';
require_once __DIR__ . '/includes/github-updater.php';

add_action( 'after_setup_theme', function () {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'responsive-embeds' );
    add_theme_support( 'editor-styles' );
    add_editor_style( 'assets/editor.css' );
} );
add_action( 'wp_enqueue_scripts', function () {
    $v = wp_get_theme()->get( 'Version' );
    wp_enqueue_style( 'orwoco-theme', get_theme_file_uri( 'assets/site.css' ), array(), $v );
    wp_enqueue_script( 'orwoco-theme', get_theme_file_uri( 'assets/site.js' ), array(), $v, array( 'strategy' => 'defer', 'in_footer' => true ) );
} );

/** Resolve portable bundle tokens at import time, with real attachment IDs in native image blocks. */
function orwoco_resolve( $markup ) {
    $ids = orwoco_media_ids();
    $markup = preg_replace_callback( '~<!-- wp:image (\{[^}]*\}) -->(.*?)<!-- /wp:image -->~s', function ( $m ) use ( $ids ) {
        if ( ! preg_match( '~/assets/img/([a-z0-9]+)-\d+\.webp~', $m[2], $k ) || empty( $ids[ $k[1] ] ) ) { return $m[0]; }
        $id = (int) $ids[ $k[1] ];
        return '<!-- wp:image ' . str_replace( '"id":0', '"id":' . $id, $m[1] ) . ' -->' . str_replace( 'wp-image-0', 'wp-image-' . $id, $m[2] ) . '<!-- /wp:image -->';
    }, $markup );
    $markup = preg_replace_callback( '~(?<![a-zA-Z0-9_./:-])/assets/img/([a-z0-9]+)-(\d+)\.webp~', function ( $m ) use ( $ids ) {
        $url = isset( $ids[ $m[1] ] ) ? wp_get_attachment_url( $ids[ $m[1] ] ) : '';
        return $url ?: get_theme_file_uri( 'assets/img/' . $m[1] . '-' . $m[2] . '.webp' );
    }, $markup );
    return preg_replace_callback( '~(href="|"url":")(/(?!/)[^"<>]*)"~', function ( $m ) {
        return $m[1] . esc_url( home_url( $m[2] ) ) . '"';
    }, $markup );
}
add_filter( 'render_block', function ( $html ) { return orwoco_resolve( $html ); }, 20 );

add_shortcode( 'orwoco_form', function ( $attrs ) {
    $atts = shortcode_atts( array( 'id' => 'contact' ), $attrs );
    $id = in_array( $atts['id'], array( 'contact', 'subscribe' ), true ) ? $atts['id'] : 'contact';
    ob_start(); orwoco_form( $id ); return ob_get_clean();
} );
add_shortcode( 'orwoco_copyright', function () { return '<p>ORWO &copy; ' . esc_html( wp_date( 'Y' ) ) . '</p>'; } );

/** Embedded Google map; the address is editable in the shortcode block: [orwoco_map address="…"]. */
add_shortcode( 'orwoco_map', function ( $attrs ) {
    $atts = shortcode_atts( array( 'address' => 'Röntgenstraße 3, 06766 Bitterfeld-Wolfen, Germany' ), $attrs );
    $q = rawurlencode( $atts['address'] );
    return '<div class="orwoco-map-frame"><iframe title="' . esc_attr( 'Map: ' . $atts['address'] ) . '" src="' . esc_url( 'https://maps.google.com/maps?q=' . $q . '&z=15&output=embed' ) . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe></div>'
        . '<p class="orwoco-map-link"><a href="' . esc_url( 'https://www.google.com/maps/search/?api=1&query=' . $q ) . '">Open in Google Maps ↗</a></p>';
} );

/** Article reading time for the single-article template. */
add_shortcode( 'orwoco_reading_time', function () {
    $words = str_word_count( wp_strip_all_tags( (string) get_post_field( 'post_content', get_the_ID() ) ) );
    return '<p class="orwoco-reading-time">' . esc_html( max( 1, (int) ceil( $words / 220 ) ) . ' min read' ) . '</p>';
} );

/** "More from the blog" on an article never repeats the article being read. */
add_filter( 'query_loop_block_query_vars', function ( $query, $block ) {
    if ( is_singular( 'post' ) && 77 === (int) ( $block->context['queryId'] ?? 0 ) ) {
        $query['post__not_in'] = array( get_queried_object_id() );
    }
    return $query;
}, 10, 2 );

/** No title duplication: seeded layouts contain their own H1; editor-created pages get a native title. */
add_filter( 'render_block_core/post-title', function ( $html, $block ) {
    if ( ! empty( $block['attrs']['className'] ) && str_contains( $block['attrs']['className'], 'orwoco-auto-title' ) && str_contains( (string) get_post_field( 'post_content', get_the_ID() ), 'orwoco-hero' ) ) { return ''; }
    return $html;
}, 10, 2 );

/**
 * Every article lives at /blog/<slug>/ (the original ORWO structure), including articles written later.
 * Resolved directly from the request path, so it never depends on rewrite rules having been flushed on the host.
 */
function orwoco_request_path() {
    $path = trim( rawurldecode( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ) ), '/' );
    $base = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
    return ( $base && str_starts_with( $path, $base . '/' ) ) ? substr( $path, strlen( $base ) + 1 ) : $path;
}
add_filter( 'request', function ( $vars ) {
    if ( is_admin() || ! preg_match( '~^blog/([^/]+)$~', orwoco_request_path(), $m ) || get_page_by_path( 'blog/' . $m[1] ) ) { return $vars; }
    $slug = sanitize_title( $m[1] );
    $found = get_posts( array( 'post_type' => 'post', 'name' => $slug, 'post_status' => array( 'publish', 'private', 'future', 'draft', 'pending' ), 'numberposts' => 1, 'fields' => 'ids', 'suppress_filters' => false ) );
    if ( ! $found ) { return $vars; }
    $keep = array_intersect_key( $vars, array_flip( array( 'preview', 'p', 'preview_id', 'preview_nonce', 'cpage' ) ) );
    return array_merge( $keep, array( 'name' => $slug, 'post_type' => 'post' ) );
} );
add_filter( 'post_link', function ( $link, $post ) {
    if ( '' === (string) get_option( 'permalink_structure' ) || '' === $post->post_name || ! in_array( $post->post_status, array( 'publish', 'private', 'future' ), true ) ) { return $link; }
    return home_url( user_trailingslashit( '/blog/' . $post->post_name ) );
}, 10, 2 );
// A mistyped article address shows the 404 page instead of a fuzzy redirect to a similarly named image file.
add_filter( 'strict_redirect_guess_404_permalink', '__return_true' );
add_action( 'template_redirect', function () {
    if ( is_preview() || ! in_array( $_SERVER['REQUEST_METHOD'] ?? '', array( 'GET', 'HEAD' ), true ) ) { return; }
    $path = orwoco_request_path();
    $aliases = array( 'home' => '/', '_blog' => '/blog/', 'blog/categories' => '/blog/', 'pages' => '/pages/cookie-policy/' );
    if ( isset( $aliases[ $path ] ) ) { wp_safe_redirect( home_url( $aliases[ $path ] ), 301 ); exit; }
    // One address per article: date/postname/?p= variants redirect to /blog/<slug>/.
    if ( is_singular( 'post' ) && 'publish' === get_post_status() && '' !== (string) get_option( 'permalink_structure' ) && 'blog/' . rawurldecode( (string) get_post_field( 'post_name' ) ) !== $path ) {
        wp_safe_redirect( get_permalink(), 301 ); exit;
    }
    if ( 'search' === $path ) { wp_safe_redirect( add_query_arg( 's', sanitize_text_field( is_string( $_GET['q'] ?? null ) ? wp_unslash( $_GET['q'] ) : '' ), home_url( '/' ) ), 301 ); exit; }
} );

/** Preserve archived SEO copy until edited; defer metadata output to a SEO plugin when installed. */
add_filter( 'pre_get_document_title', function ( $title ) {
    if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || ! is_singular() ) { return $title; }
    return get_post_meta( get_queried_object_id(), '_orwoco_seo_title', true ) ?: $title;
} );
add_action( 'wp_head', function () {
    if ( ! has_site_icon() ) { echo '<link rel="icon" href="' . esc_url( get_theme_file_uri( 'assets/favicon.png' ) ) . '">'; }
    if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || ! is_singular() ) { return; }
    $id = get_queried_object_id();
    $description = get_post_meta( $id, '_orwoco_description', true );
    if ( $description ) { echo '<meta name="description" content="' . esc_attr( $description ) . '">'; }
    echo '<meta property="og:title" content="' . esc_attr( wp_get_document_title() ) . '"><meta property="og:url" content="' . esc_url( get_permalink( $id ) ) . '"><meta property="og:type" content="' . ( is_single() ? 'article' : 'website' ) . '">';
    if ( has_post_thumbnail( $id ) ) { echo '<meta property="og:image" content="' . esc_url( get_the_post_thumbnail_url( $id, 'full' ) ) . '">'; }
} );
