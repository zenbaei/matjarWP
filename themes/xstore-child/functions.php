<?php
add_action( 'wp_enqueue_scripts', 'theme_enqueue_styles', 1001 );
function theme_enqueue_styles() {
	if (function_exists('etheme_child_styles')){
		etheme_child_styles();
	}
}

// Switching locale on ajax calls
add_action( 'init', function () {
    if ( wp_doing_ajax() ) {
        switch_to_locale( get_option( 'WPLANG' ) ?: 'ar' );
    }
}, 0 );

