<?php
add_action( 'wp_enqueue_scripts', 'theme_enqueue_styles', 1001 );
function theme_enqueue_styles() {
	if (function_exists('etheme_child_styles')){
		etheme_child_styles();
	}
}

## No need for it after adding wp_doing_ajax func
function n2t_text_strings( $translated_text, $text, $domain ) {
    switch ( $translated_text ) {
		case 'SUBTOTAL:': // Original text
            $translated_text = 'الإجمالي'; // Your translation
            break;
		case 'LOGIN:': // Original text
            $translated_text = 'دخول'; // Your translation
            break;
    }
    return $translated_text;
}
add_filter( 'gettext', 'n2t_text_strings', 20, 3 );

// Switching locale on ajax calls
add_action( 'init', function () {
    if ( wp_doing_ajax() ) {
        switch_to_locale( get_option( 'WPLANG' ) ?: 'ar' );
    }
}, 0 );



