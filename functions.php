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

/** No title duplication: seeded layouts contain their own H1; editor-created pages get a native title. */
add_filter( 'render_block_core/post-title', function ( $html, $block ) {
    if ( ! empty( $block['attrs']['className'] ) && str_contains( $block['attrs']['className'], 'orwoco-auto-title' ) && str_contains( (string) get_post_field( 'post_content', get_the_ID() ), 'orwoco-hero' ) ) { return ''; }
    return $html;
}, 10, 2 );

/** Original post paths stay /blog/<slug>/ without replacing other sites' permalink settings. */
add_action( 'init', function () {
    foreach ( orwoco_pages_data() as $row ) {
        if ( 'post' === ( $row['type'] ?? '' ) ) { add_rewrite_rule( '^' . preg_quote( $row['path'], '~' ) . '/?$', 'index.php?name=' . $row['slug'], 'top' ); }
    }
} );
add_filter( 'post_link', function ( $link, $post ) {
    $path = get_post_meta( $post->ID, '_orwoco_original_path', true );
    return $path ? home_url( user_trailingslashit( '/' . $path ) ) : $link;
}, 10, 2 );
add_action( 'template_redirect', function () {
    if ( is_preview() || ! in_array( $_SERVER['REQUEST_METHOD'] ?? '', array( 'GET', 'HEAD' ), true ) ) { return; }
    $path = trim( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' );
    $base = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
    if ( $base && str_starts_with( $path, $base . '/' ) ) { $path = substr( $path, strlen( $base ) + 1 ); }
    $aliases = array( 'home' => '/', '_blog' => '/blog/', 'blog/categories' => '/blog/', 'pages' => '/pages/cookie-policy/' );
    if ( isset( $aliases[ $path ] ) ) { wp_safe_redirect( home_url( $aliases[ $path ] ), 301 ); exit; }
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
