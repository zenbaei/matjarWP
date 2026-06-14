<?php

if (! defined('ABSPATH')) {
    exit;
}

class Login
{

    public function __construct()
    {

        add_action(
            'wp_login',
            array($this, 'redirect_after_login'),
            9999,
            2
        );
    }

    public function redirect_after_login($user_login, $user)
    {

        if (wp_doing_ajax()) {
            return;
        }

        wp_safe_redirect(home_url());
        exit;
    }
}

new Login();
