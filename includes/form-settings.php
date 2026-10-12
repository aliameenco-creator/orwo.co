<?php
/** Editable form labels and confirmation text, separate from stored enquiries. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
add_action( 'admin_menu', function () {
    add_options_page( 'ORWO Forms', 'ORWO Forms', 'manage_options', 'orwoco-forms', function () {
        echo '<div class="wrap"><h1>ORWO Forms</h1><p>Edit visible labels and confirmation text here. Add or move a form in any Page with a Shortcode block: <code>[orwoco_form id="contact"]</code> or <code>[orwoco_form id="subscribe"]</code>. Set the notification destination in Settings → General. Newsletter submissions are saved and emailed; this does not send newsletters.</p><form action="options.php" method="post">';
        settings_fields( 'orwoco_forms' );
        foreach ( orwoco_form_definitions() as $id => $form ) {
            echo '<h2>' . esc_html( $form['title'] ) . '</h2><table class="form-table">';
            $fields = array( 'title' => 'Form name', 'submit' => 'Submit button', 'thanks' => 'Confirmation message' );
            foreach ( $form['fields'] as $field ) { $fields[ $field['key'] ] = $field['label'] . ' label'; $form[ $field['key'] ] = $field['label']; }
            foreach ( $fields as $key => $label ) {
                $name = 'orwoco_form_labels[' . $id . '][' . $key . ']';
                echo '<tr><th><label for="' . esc_attr( $id . '_' . $key ) . '">' . esc_html( $label ) . '</label></th><td><input class="regular-text" id="' . esc_attr( $id . '_' . $key ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $form[ $key ] ) . '" maxlength="500"></td></tr>';
            }
            echo '</table>';
        }
        submit_button(); echo '</form></div>';
    } );
} );
add_action( 'admin_init', function () {
    register_setting( 'orwoco_forms', 'orwoco_form_labels', array( 'type' => 'array', 'sanitize_callback' => function ( $input ) {
        $out = array();
        foreach ( array( 'contact', 'subscribe' ) as $id ) {
            foreach ( array( 'title', 'submit', 'thanks', 'name', 'email', 'message' ) as $key ) {
                if ( is_string( $input[ $id ][ $key ] ?? null ) ) { $out[ $id ][ $key ] = mb_substr( sanitize_text_field( $input[ $id ][ $key ] ), 0, 500 ); }
            }
        }
        return $out;
    } ) );
} );
