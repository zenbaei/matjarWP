<?php

if (! defined('ABSPATH')) {
    exit;
}

class Login
{
    public function __construct()
    {

        add_filter('etheme_woocommerce_origin_login_redirect', '__return_true');

        add_filter('woocommerce_login_redirect', function ($redirect, $user) {
            return home_url('/');
        }, 9999, 2);
    }
}

new Login();
