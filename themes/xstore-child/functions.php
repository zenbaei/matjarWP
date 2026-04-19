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

// Javascript code to hide "Previously Viewed" section when products are empty (case for non registered users)
function add_inline_javascript_footer() {
    ?>
    <script>
        /**
        const section = document.querySelector('[data-id="b4f1946"]');
		// search within the section as same class name exists on every product list on the page
		if (section) {		
		 	if (!section.querySelector('.etheme-product-list'))  {
  				section.style.display = 'none';
			} else {
				section.style.display = 'block';

			}
		}
		*/
		
	// First add .products-widget class to products widget from elementor in order for this code to fire
		if (jQuery('.recently-viewed').find('img').length === 0) {
			jQuery('.recently-viewed').closest('section').hide();
		}
		
    </script>
    <?php
}
add_action( 'wp_footer', 'add_inline_javascript_footer' );

/*
function hide_you_may_also_like_sec_on_account_page_js() {
	if ( function_exists( 'is_account_page' ) && is_account_page() ) {
		?>
		<script>
        // Your inline JavaScript code goes here
        const section = document.querySelector('.swiper-entry');
		if (section) {
  			section.style.display = 'none';
		}
    </script>
    <?php
	}
}

add_action( 'wp_footer', 'hide_you_may_also_like_sec_on_account_page_js' );
*/

