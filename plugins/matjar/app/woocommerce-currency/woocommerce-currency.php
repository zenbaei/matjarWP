<?php

class Change_EGP_Currency_Symbol
{

    public function __construct()
    {
        add_filter(
            'woocommerce_currency_symbol',
            array($this, 'change_symbol'),
            10,
            2
        );

        add_action(
            'wp_enqueue_scripts',
            array($this, 'enqueue_inline_styles')
        );
    }

    /**
     * Change EGP currency symbol.
     *
     * @param string $currency_symbol Current currency symbol.
     * @param string $currency         Currency code.
     *
     * 
     * 
     * @return string
     */
    public function change_symbol($currency_symbol, $currency)
    {

        if ('EGP' === $currency) {
            $currency_symbol = 'ج.م';
        }

        return $currency_symbol;
    }

    public function enqueue_inline_styles()
    {
        $base_url  = plugin_dir_url(__FILE__);
        $base_path = plugin_dir_path(__FILE__);

        wp_enqueue_style(
            'woocommerce-currency-css',
            $base_url . 'woocommerce-currency.css',
            [],
            filemtime($base_path . 'woocommerce-currency.css')
        );
    }
}

new Change_EGP_Currency_Symbol();
