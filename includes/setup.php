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
add_action( 'admin_menu', function () {
    add_theme_page( 'ORWO Setup', 'ORWO Setup', 'manage_options', 'orwoco-setup', function () {
        echo '<div class="wrap"><h1>ORWO Film Community</h1><p>Create the original editable pages, two posts, Media Library images, navigation, header and footer. Existing records and editor changes are preserved. Setup publishes new original pages and selects Home as the front page. Back up an existing site first.</p><p><a class="button button-primary" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=orwoco_setup' ), 'orwoco_setup' ) ) . '">Complete ORWO setup</a></p><p>Edit pages under Pages, articles under Posts, and the header/footer/navigation under Appearance → Editor. Notification destination is under Settings → General. Submissions are under Enquiries; newsletter sign-ups are stored for your own mailing system.</p><h2>Latest setup report</h2><ul>';
        foreach ( (array) get_option( 'orwoco_setup_report', array() ) as $line ) { echo '<li>' . esc_html( $line ) . '</li>'; }
        echo '</ul></div>';
    } );
} );
add_action( 'admin_notices', function () {
    if ( current_user_can( 'manage_options' ) && ! get_option( 'orwoco_setup_done' ) ) { echo '<div class="notice notice-info"><p><strong>ORWO:</strong> <a href="' . esc_url( admin_url( 'themes.php?page=orwoco-setup' ) ) . '">Complete ORWO setup</a> to create your editable website.</p></div>'; }
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
