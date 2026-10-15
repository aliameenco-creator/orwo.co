<?php
/** Explicit, repeatable setup. No page content is changed merely by theme activation. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
function orwoco_pages_data() {
    return (array) json_decode( file_get_contents( get_theme_file_path( 'parts/pages.json' ) ), true );
}
function orwoco_pages_seed() {
    if ( ! current_user_can( 'manage_options' ) ) { return array(); }
    $report = array();
    // Keep the untouched WordPress starter article as a draft; never delete or alter a user's article.
    $sample = get_page_by_path( 'hello-world', OBJECT, 'post' );
    if ( $sample && 'Hello world!' === $sample->post_title && str_contains( $sample->post_content, 'Welcome to WordPress. This is your first post. Edit or delete it, then start writing!' ) ) {
        wp_update_post( array( 'ID' => $sample->ID, 'post_status' => 'draft' ) );
    }
    foreach ( orwoco_pages_data() as $row ) {
        $key = $row['slug'];
        if ( get_option( 'orwoco_page_' . $key ) ) { $report[] = $row['title'] . ': already imported; edits preserved.'; continue; }
        $type = $row['type'] ?? 'page';
        $existing = get_page_by_path( $row['path'] ?: 'home', OBJECT, array( $type ) );
        // Draft/private/trashed records are conflicts too; never overwrite or silently duplicate them.
        if ( ! $existing ) {
            $matches = get_posts( array( 'post_type' => $type, 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ), 'name' => $key, 'numberposts' => 1 ) );
            $existing = $matches[0] ?? null;
        }
        if ( $existing ) { $report[] = $row['title'] . ': SKIPPED — an existing record uses this slug.'; continue; }
        $parent = 0;
        if ( 'pages/cookie-policy' === $row['path'] ) {
            $container = get_page_by_path( 'pages' );
            $parent = $container ? $container->ID : wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Legal', 'post_name' => 'pages', 'post_status' => 'publish' ), true );
            if ( is_wp_error( $parent ) ) { $report[] = 'Cookie policy: FAILED to create URL parent.'; continue; }
        }
        $markup = orwoco_resolve( file_get_contents( get_theme_file_path( 'parts/pages/' . $key . '.html' ) ) );
        $args = array( 'post_type' => $type, 'post_status' => 'publish', 'post_name' => $key, 'post_title' => $row['title'], 'post_content' => $markup, 'post_parent' => $parent, 'comment_status' => 'closed', 'ping_status' => 'closed' );
        if ( ! empty( $row['date'] ) ) { $args['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', strtotime( $row['date'] ) ); $args['post_date'] = get_date_from_gmt( $args['post_date_gmt'] ); }
        if ( ! empty( $row['excerpt'] ) ) { $args['post_excerpt'] = wp_trim_words( $row['excerpt'], 36 ); }
        $id = wp_insert_post( wp_slash( $args ), true );
        if ( is_wp_error( $id ) ) { $report[] = $row['title'] . ': FAILED — ' . $id->get_error_message(); continue; }
        update_option( 'orwoco_page_' . $key, $id, false );
        if ( 'contact' === $key ) { update_option( 'orwoco_update_contact_map', 1, false ); }
        if ( 'home' === $key ) { update_option( 'orwoco_update_clothing', 1, false ); }
        update_post_meta( $id, '_orwoco_original_path', $row['path'] );
        $title = $row['seo_title'] ?? $row['title'] . ' - ORWO Film Community';
        update_post_meta( $id, '_orwoco_seo_title', $title );
        update_post_meta( $id, '_yoast_wpseo_title', $title );
        if ( ! empty( $row['description'] ) ) { update_post_meta( $id, '_orwoco_description', $row['description'] ); update_post_meta( $id, '_yoast_wpseo_metadesc', $row['description'] ); }
        if ( ! empty( $row['featured'] ) && preg_match( '~/assets/img/([a-f0-9]+)-~', $row['featured'], $m ) ) {
            $ids = orwoco_media_ids(); if ( ! empty( $ids[ $m[1] ] ) ) { set_post_thumbnail( $id, $ids[ $m[1] ] ); }
        }
        if ( ! empty( $row['front'] ) ) { update_option( 'show_on_front', 'page' ); update_option( 'page_on_front', $id ); }
        $report[] = $row['title'] . ': created.';
    }
    // Native Site Editor parts, saved once with actual Media Library URLs. Theme updates cannot overwrite edits.
    foreach ( array( 'header', 'footer' ) as $slug ) {
        if ( get_option( 'orwoco_part_' . $slug ) ) { continue; }
        $existing = get_block_template( get_stylesheet() . '//' . $slug, 'wp_template_part' );
        if ( $existing && 'custom' === $existing->source ) { continue; }
        $id = wp_insert_post( wp_slash( array( 'post_type' => 'wp_template_part', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => ucfirst( $slug ), 'post_content' => orwoco_resolve( file_get_contents( get_theme_file_path( 'parts/' . $slug . '.html' ) ) ), 'tax_input' => array( 'wp_theme' => get_stylesheet(), 'wp_template_part_area' => $slug ) ) ), true );
        if ( ! is_wp_error( $id ) ) { update_option( 'orwoco_part_' . $slug, $id, false ); }
    }
    update_option( 'orwoco_setup_report', $report, false );
    update_option( 'orwoco_setup_done', 1, false );
    flush_rewrite_rules();
    return $report;
}
/**
 * v1.1.0 content update for sites set up with 1.0.0: append the embedded map to the existing Contact page.
 * Insert-only; the rest of the page (and any edits) stays exactly as it is. Safe to run repeatedly.
 */
function orwoco_contact_map_block() {
    return "

" . '<!-- wp:group {"className":"orwoco-map","tagName":"section","anchor":"map"} -->' . "
" . '<section id="map" class="wp-block-group orwoco-map"><!-- wp:shortcode -->' . "
[orwoco_map]
" . '<!-- /wp:shortcode --></section>' . "
" . '<!-- /wp:group -->';
}
function orwoco_contact_map_target() {
    $id = (int) get_option( 'orwoco_page_contact' );
    $page = $id ? get_post( $id ) : get_page_by_path( 'contact' );
    return ( $page && 'page' === $page->post_type && 'trash' !== $page->post_status ) ? $page : null;
}
function orwoco_contact_map_pending() {
    $page = orwoco_contact_map_target();
    return $page && ! get_option( 'orwoco_update_contact_map' ) && ! str_contains( $page->post_content, '[orwoco_map' );
}
function orwoco_apply_contact_map() {
    $page = orwoco_contact_map_target();
    if ( ! $page ) { return 'Contact map: SKIPPED — no Contact page found.'; }
    if ( str_contains( $page->post_content, '[orwoco_map' ) ) { update_option( 'orwoco_update_contact_map', 1, false ); return 'Contact map: already on the Contact page.'; }
    wp_save_post_revision( $page->ID );
    $done = wp_update_post( wp_slash( array( 'ID' => $page->ID, 'post_content' => rtrim( $page->post_content ) . orwoco_contact_map_block() ) ), true );
    if ( is_wp_error( $done ) ) { return 'Contact map: FAILED — ' . $done->get_error_message(); }
    update_option( 'orwoco_update_contact_map', 1, false );
    return 'Contact map: added below the contact form (previous version kept as a revision).';
}

/** Article section titles that 1.0.0 imported as plain paragraphs; converted only while a paragraph is still unedited. */
function orwoco_heading_fixes( $post ) {
    $file = get_theme_file_path( 'parts/pages/' . $post->post_name . '.html' );
    if ( ! is_readable( $file ) || ! preg_match_all( '~<h2 class="wp-block-heading">(.*?)</h2>~', (string) file_get_contents( $file ), $m ) ) { return array(); }
    $fixes = array();
    foreach ( $m[1] as $text ) {
        $old = "<!-- wp:paragraph -->
<p>" . $text . "</p>
<!-- /wp:paragraph -->";
        if ( str_contains( $post->post_content, $old ) ) { $fixes[ $old ] = "<!-- wp:heading -->
<h2 class=\"wp-block-heading\">" . $text . "</h2>
<!-- /wp:heading -->"; }
    }
    return $fixes;
}
function orwoco_article_posts() {
    $posts = array();
    foreach ( orwoco_pages_data() as $row ) {
        $id = 'post' === ( $row['type'] ?? '' ) ? (int) get_option( 'orwoco_page_' . $row['slug'] ) : 0;
        $post = $id ? get_post( $id ) : null;
        if ( $post && 'post' === $post->post_type ) { $posts[] = $post; }
    }
    return $posts;
}
function orwoco_headings_pending() {
    if ( get_option( 'orwoco_update_headings' ) ) { return false; }
    foreach ( orwoco_article_posts() as $post ) { if ( orwoco_heading_fixes( $post ) ) { return true; } }
    return false;
}
function orwoco_apply_headings() {
    $lines = array();
    foreach ( orwoco_article_posts() as $post ) {
        $fixes = orwoco_heading_fixes( $post );
        if ( ! $fixes ) { continue; }
        wp_save_post_revision( $post->ID );
        $done = wp_update_post( wp_slash( array( 'ID' => $post->ID, 'post_content' => strtr( $post->post_content, $fixes ) ) ), true );
        $lines[] = is_wp_error( $done ) ? $post->post_title . ': FAILED — ' . $done->get_error_message() : $post->post_title . ': ' . count( $fixes ) . ' section titles shown as headings (previous version kept as a revision).';
    }
    update_option( 'orwoco_update_headings', 1, false );
    return $lines ?: array( 'Article headings: nothing to change.' );
}
/** 1.2.0: the homepage Clothing tile is removed (owner request); matched by its heading and image alt, never by position. */
function orwoco_clothing_pattern() {
    return '~\s*<!-- wp:group \{"className":"image-tile"\} -->\s*<div class="wp-block-group image-tile"><!-- wp:image \{[^}]*\} -->\s*<figure[^>]*><img[^>]*alt="ORWO clothing"[^>]*/?></figure>\s*<!-- /wp:image -->\s*<!-- wp:heading \{"level":3\} -->\s*<h3 class="wp-block-heading">Clothing</h3>\s*<!-- /wp:heading --></div>\s*<!-- /wp:group -->~';
}
function orwoco_home_page() {
    $id = (int) get_option( 'orwoco_page_home' );
    $page = $id ? get_post( $id ) : null;
    return ( $page && 'page' === $page->post_type ) ? $page : null;
}
function orwoco_clothing_pending() {
    $page = orwoco_home_page();
    return $page && ! get_option( 'orwoco_update_clothing' ) && preg_match( orwoco_clothing_pattern(), $page->post_content );
}
function orwoco_apply_clothing() {
    $page = orwoco_home_page();
    update_option( 'orwoco_update_clothing', 1, false );
    if ( ! $page || ! preg_match( orwoco_clothing_pattern(), $page->post_content ) ) { return 'Clothing tile: not found unchanged on the homepage; nothing removed.'; }
    wp_save_post_revision( $page->ID );
    $done = wp_update_post( wp_slash( array( 'ID' => $page->ID, 'post_content' => preg_replace( orwoco_clothing_pattern(), '', $page->post_content, 1 ) ) ), true );
    return is_wp_error( $done ) ? 'Clothing tile: FAILED — ' . $done->get_error_message() : 'Clothing tile: removed from the homepage (previous version kept as a revision).';
}
function orwoco_updates_pending() { return orwoco_contact_map_pending() || orwoco_headings_pending() || orwoco_clothing_pending(); }

add_action( 'admin_menu', function () {
    add_theme_page( 'ORWO Setup', 'ORWO Setup', 'manage_options', 'orwoco-setup', function () {
        echo '<div class="wrap"><h1>ORWO Film Community</h1><p>Create the original editable pages, two posts, Media Library images, navigation, header and footer. Existing records and editor changes are preserved. Setup publishes new original pages and selects Home as the front page. Back up an existing site first.</p><p><a class="button button-primary" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=orwoco_setup' ), 'orwoco_setup' ) ) . '">Complete ORWO setup</a></p><p>Edit pages under Pages, articles under Posts, and the header/footer/navigation under Appearance → Editor. Notification destination is under Settings → General. Submissions are under Enquiries; newsletter sign-ups are stored for your own mailing system.</p>';
        if ( orwoco_updates_pending() ) { echo '<h2>Theme content updates</h2><p>Adds the embedded Google map to your Contact page below the form, shows the original article section titles as headings, and removes the Clothing tile from the homepage. Only those parts change; edited paragraphs are left alone and each page keeps its previous version as a revision.</p><p><a class="button button-primary" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=orwoco_update_map' ), 'orwoco_update_map' ) ) . '">Apply theme updates</a></p>'; }
        echo '<h2>Latest setup report</h2><ul>';
        foreach ( (array) get_option( 'orwoco_setup_report', array() ) as $line ) { echo '<li>' . esc_html( $line ) . '</li>'; }
        echo '</ul></div>';
    } );
} );
add_action( 'admin_notices', function () {
    if ( current_user_can( 'manage_options' ) && ! get_option( 'orwoco_setup_done' ) ) { echo '<div class="notice notice-info"><p><strong>ORWO:</strong> <a href="' . esc_url( admin_url( 'themes.php?page=orwoco-setup' ) ) . '">Complete ORWO setup</a> to create your editable website.</p></div>'; }
} );
add_action( 'admin_notices', function () {
    if ( current_user_can( 'manage_options' ) && get_option( 'orwoco_setup_done' ) && orwoco_updates_pending() ) { echo '<div class="notice notice-info"><p><strong>ORWO:</strong> <a href="' . esc_url( admin_url( 'themes.php?page=orwoco-setup' ) ) . '">Apply theme updates</a> to your pages (Contact map, article headings, homepage Clothing tile).</p></div>'; }
} );
add_action( 'admin_post_orwoco_update_map', function () {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Not allowed.' ); }
    check_admin_referer( 'orwoco_update_map' );
    update_option( 'orwoco_setup_report', array_merge( array( orwoco_apply_contact_map(), orwoco_apply_clothing() ), orwoco_apply_headings() ), false );
    wp_safe_redirect( admin_url( 'themes.php?page=orwoco-setup' ) ); exit;
} );
add_action( 'admin_post_orwoco_setup', function () {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Not allowed.' ); }
    check_admin_referer( 'orwoco_setup' );
    if ( '' === (string) get_option( 'permalink_structure' ) ) { update_option( 'permalink_structure', '/%postname%/' ); }
    if ( orwoco_media_pending() ) { wp_safe_redirect( orwoco_media_import_url() ); exit; }
    if ( ! get_option( 'orwoco_setup_done' ) ) { update_option( 'blogname', 'ORWO Film Community' ); update_option( 'blogdescription', '' ); }
    orwoco_pages_seed();
    wp_safe_redirect( admin_url( 'themes.php?page=orwoco-setup' ) ); exit;
} );
