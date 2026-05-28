<?php

class Expenses_Field
{
    /**
     * Option key.
     */
    const OPTION_KEY = 'expenses';

    public function __construct()
    {
        add_filter(
            'woocommerce_get_settings_general',
            array($this, 'add_setting'),
            10,
            2
        );
    }

    /**
     * Add custom setting to WooCommerce > Settings > General.
     *
     * @param array  $settings Current settings.
     * @param string $current_section Current section.
     *
     * @return array
     */
    public function add_setting($settings, $current_section)
    {
        if ($current_section !== '') {
            return $settings;
        }

        $custom_settings = array(

            array(
                'title' => 'Expenses Settings',
                'type'  => 'title',
                'desc'  => 'Global expenses configuration.',
                'id'    => 'woo_expenses_settings_section',
            ),

            array(
                'title'             => 'Global Expenses',
                'desc'              => 'Set the global expenses amount.',
                'id'                => self::OPTION_KEY,
                'type'              => 'number',
                'default'           => '0',
                'custom_attributes' => array(
                    'step' => '0.01',
                    'min'  => '0',
                ),
            ),

            array(
                'type' => 'sectionend',
                'id'   => 'woo_expenses_settings_section',
            ),
        );

        return array_merge(
            $settings,
            $custom_settings
        );
    }
}

new Expenses_Field();
