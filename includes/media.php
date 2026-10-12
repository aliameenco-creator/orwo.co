<?php
/**
 * Media Library: one-click import of every image the theme shows (descriptive file names and alt text
 * from parts/media.json), then the theme serves those images from the Media Library: editable alt text,
 * WordPress image sizes (srcset) and normal uploads URLs. Until the import runs, the bundled copies are used.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function orwoco_media_manifest() {
    static $manifest = null;
    if ( null === $manifest ) {
        $file = get_theme_file_path( 'parts/media.json' );
        $manifest = is_readable( $file ) ? (array) json_decode( (string) file_get_contents( $file ), true ) : array();
    }
    return $manifest;
}

/** Imported images: manifest key => attachment ID (only attachments that still exist). */
function orwoco_media_ids() {
    static $ids = null;
    if ( null !== $ids ) { return $ids; }
    $ids = array_filter( array_map( 'intval', (array) get_option( 'orwoco_media_ids', array() ) ) );
    if ( $ids ) {
        _prime_post_caches( array_values( $ids ), false, true );
        $ids = array_filter( $ids, function ( $id ) { return 'attachment' === get_post_type( $id ); } );
    }
    return $ids;
}

function orwoco_media_pending() {
    $ids = orwoco_media_ids();
    $failed = (array) get_option( 'orwoco_media_failed', array() );
    return array_values( array_filter( orwoco_media_manifest(), function ( $m ) use ( $ids, $failed ) {
        return empty( $ids[ $m['key'] ] ) && ! isset( $failed[ $m['key'] ] );
    } ) );
}

/** Raw (not HTML-escaped) URL: used for redirects; escape with esc_url() when printing. */
function orwoco_media_import_url() {
    return add_query_arg( array( 'action' => 'orwoco_media_import', '_wpnonce' => wp_create_nonce( 'orwoco_media_import' ) ), admin_url( 'admin-post.php' ) );
}

/** Imports a few images per request (shared hosting time limits) and continues until all are in the library. */
add_action( 'admin_post_orwoco_media_import', function () {
    if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'upload_files' ) ) { wp_die( 'Not allowed.' ); }
    check_admin_referer( 'orwoco_media_import' );
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $ids = (array) get_option( 'orwoco_media_ids', array() );
    $failed = (array) get_option( 'orwoco_media_failed', array() );
    $start = microtime( true );
    $imported = 0;
    foreach ( orwoco_media_pending() as $m ) {
        $key = $m['key'];
        // Re-use an attachment from an earlier run (safe to re-run; never duplicates).
        $existing = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_orwoco_media_key', 'meta_value' => $key, 'fields' => 'ids', 'numberposts' => 1 ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
        if ( $existing ) { $ids[ $key ] = (int) $existing[0]; continue; }
        if ( $imported >= 4 || ( $imported && 'video' === ( $m['type'] ?? '' ) ) || microtime( true ) - $start > 18 ) { break; }
        $source = get_theme_file_path( $m['file'] );
        $ext = strtolower( pathinfo( $m['file'], PATHINFO_EXTENSION ) );
        $tmp = wp_tempnam( $m['name'] . '.' . $ext );
        if ( ! is_readable( $source ) || ! $tmp || ! copy( $source, $tmp ) ) { $failed[ $key ] = 'Bundled file missing: ' . $m['file']; continue; }
        $id = media_handle_sideload( array( 'name' => $m['name'] . '.' . $ext, 'tmp_name' => $tmp ), 0, $m['title'] );
        if ( is_wp_error( $id ) ) {
            wp_delete_file( $tmp );
            $failed[ $key ] = $id->get_error_message();
            continue;
        }
        if ( 'video' !== ( $m['type'] ?? '' ) ) { update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $m['alt'] ) ); }
        update_post_meta( $id, '_orwoco_media_key', $key );
        // Attachment slugs share WordPress's URL namespace with pages. Keep filenames descriptive,
        // but namespace attachment permalinks so a photo titled "Shot on ORWO" cannot steal that page.
        wp_update_post( array( 'ID' => $id, 'post_name' => 'orwoco-media-' . sanitize_key( $key ) ) );
        $ids[ $key ] = (int) $id;
        ++$imported;
    }
    update_option( 'orwoco_media_ids', $ids, false );
    update_option( 'orwoco_media_failed', $failed, false );
    $left = count( array_filter( orwoco_media_manifest(), function ( $m ) use ( $ids, $failed ) { return empty( $ids[ $m['key'] ] ) && ! isset( $failed[ $m['key'] ] ); } ) );
    if ( $left ) {
        // Small progress page that continues automatically (and works as a plain link without JavaScript).
        $next = orwoco_media_import_url();
        $total = count( orwoco_media_manifest() );
        echo '<!doctype html><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=' . esc_url( $next ) . '"><title>Adding images…</title><body style="font:16px system-ui;padding:40px;background:#0b0b0b;color:#eee"><p>Adding ORWO Film Community images to the Media Library: ' . (int) ( $total - $left ) . ' of ' . (int) $total . '…</p><p><a style="color:#fff" href="' . esc_url( $next ) . '">Continue</a></p>';
        exit;
    }
    // Images and videos are in the library: continue the setup (creates the editable pages).
    wp_safe_redirect( add_query_arg( array( 'action' => 'orwoco_setup', '_wpnonce' => wp_create_nonce( 'orwoco_setup' ), 'orwoco_media' => 'done' ), admin_url( 'admin-post.php' ) ) );
    exit;
} );

add_action( 'admin_notices', function () {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $failed = (array) get_option( 'orwoco_media_failed', array() );
    if ( isset( $_GET['orwoco_media'] ) && ! $failed ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        echo '<div class="notice notice-success is-dismissible"><p><strong>ORWO Film Community:</strong> all ' . count( orwoco_media_manifest() ) . ' images are in the Media Library with descriptive file names and alt text. Edit any alt text here and the website uses it.</p></div>';
    }
    if ( $failed ) {
        $retry = wp_nonce_url( admin_url( 'admin-post.php?action=orwoco_media_retry' ), 'orwoco_media_retry' );
        echo '<div class="notice notice-error"><p><strong>ORWO Film Community:</strong> ' . count( $failed ) . ' image(s) could not be added to the Media Library (the website keeps using the theme copies). First problem: ' . esc_html( (string) reset( $failed ) ) . '</p><p><a class="button" href="' . esc_url( $retry ) . '">Try again</a></p></div>';
    }
} );

add_action( 'admin_post_orwoco_media_retry', function () {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Not allowed.' ); }
    check_admin_referer( 'orwoco_media_retry' );
    delete_option( 'orwoco_media_failed' );
    wp_safe_redirect( orwoco_media_import_url() );
    exit;
} );

/** Serve imported images from the Media Library: uploads URL, srcset, the alt text edited in WordPress. */
function orwoco_media_swap( $html ) {
    $ids = orwoco_media_ids();
    if ( ! $ids ) { return $html; }
    $html = preg_replace_callback( '~<img\b[^>]*\bsrc="/assets/img/([a-z0-9]+)-\d+\.webp"[^>]*>~', function ( $m ) use ( $ids ) {
        $id = $ids[ $m[1] ] ?? 0;
        $url = $id ? wp_get_attachment_image_url( $id, 'full' ) : '';
        if ( ! $url ) { return $m[0]; }
        $tag = preg_replace( '~\bsrc="[^"]*"~', 'src="' . esc_url( $url ) . '"', $m[0], 1 );
        $srcset = wp_get_attachment_image_srcset( $id, 'full' );
        if ( $srcset ) {
            $sizes = str_contains( $tag, ' sizes="' ) ? '' : ' sizes="(max-width: 900px) 100vw, 50vw"';
            $tag = preg_replace( '~^<img\b~', '<img srcset="' . esc_attr( $srcset ) . '"' . $sizes, $tag, 1 );
        }
        $alt = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
        if ( '' !== $alt && ! str_contains( $tag, 'alt=""' ) ) {
            $tag = preg_replace( '~\balt="[^"]*"~', 'alt="' . esc_attr( $alt ) . '"', $tag, 1 );
        }
        $tag = str_contains( $tag, ' class="' ) ? preg_replace( '~ class="~', ' class="wp-image-' . (int) $id . ' ', $tag, 1 ) : preg_replace( '~^<img\b~', '<img class="wp-image-' . (int) $id . '"', $tag, 1 );
        return $tag;
    }, $html );
    // Background photos and lightbox links.
    return preg_replace_callback( '~/assets/img/([a-z0-9]+)-\d+\.webp~', function ( $m ) use ( $ids ) {
        $url = isset( $ids[ $m[1] ] ) ? wp_get_attachment_image_url( $ids[ $m[1] ], 'full' ) : '';
        return $url ? esc_url( $url ) : $m[0];
    }, $html );
}
