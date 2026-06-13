<?php

namespace Matjar\Product_Custom_Fields;

if (!defined('ABSPATH')) exit;

/**
 * Class Fields
 *
 * Module entry point for Book Fields.
 *
 * Responsibilities:
 * - Register hooks
 * - Connect UI with Service layer
 *
 * Architecture Role:
 * - Orchestrator (no logic, no HTML)
 */
class Fields
{

    private UI $ui;
    private Service $service;

    public function __construct()
    {
        $this->service = new Service();

        // UI
        add_filter('woocommerce_product_data_tabs', [$this, 'registerTab']);
        add_action('woocommerce_product_data_panels', [$this, 'renderPanel']);
        // Scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);

        // Save
        add_action('woocommerce_process_product_meta', [$this->service, 'save']);

        // AJAX
        add_action('wp_ajax_search_writers', [$this->service, 'searchWriters']);
    }

    /**
     * Dynamic field configuration
     *
     * @return array
     */
    private function getFields(): array
    {

        return [

            '_book_series' => [
                'label' => 'عدد الأجزاء',
                'type'  => 'number',
                'default' => 1,
            ],

            '_book_edition' => [
                'label' => 'الطبعة',
                'type'  => 'number',
                'default' => 0,
            ],

            '_book_year' => [
                'label'   => 'سنة النشر',
                'type'    => 'select',
                'options' => ['' => 'غير محددة'] + $this->getYears(),
            ],

            '_book_notes' => [
                'label' => 'ملاحظات',
                'type'  => 'textarea',
            ],
        ];
    }

    /**
     * Generate years list
     *
     * @return array
     */
    private function getYears(): array
    {

        $years = range(date('Y'), 1850);

        return array_combine($years, $years);
    }

    /**
     * Register WooCommerce tab
     */
    public function registerTab($tabs)
    {

        $tabs['book_info'] = [
            'label'    => __('Book Info', 'matjar-plugin'),
            'target'   => 'book_info_panel',
            'priority' => 25,
        ];

        return $tabs;
    }

    /**
     * Render full panel
     */
    public function renderPanel()
    {

        global $post;

        echo '<div id="book_info_panel" class="panel woocommerce_options_panel">';

        // Complex fields
        $this->renderSelect2Field($post->ID, '_book_editors', 'المحقق');

        $this->renderDynamicFields($post->ID);

        echo '</div>';
    }

    /**
     * Render dynamic (simple) fields
     */
    private function renderDynamicFields(int $postId): void
    {

        foreach ($this->getFields() as $key => $field) {

            $value = !empty($field['option_key'])
                ? get_option($field['option_key'], $field['default'] ?? '')
                : get_post_meta($postId, $key, true);

            if ($value === '' && isset($field['default'])) {
                $value = $field['default'];
            }

            echo '<p class="form-field">';
            echo '<label>' . esc_html($field['label']) . '</label>';

            switch ($field['type']) {

                case 'textarea':
                    printf(
                        '<textarea name="%s">%s</textarea>',
                        esc_attr($key),
                        esc_textarea($value)
                    );
                    break;

                case 'select':
                    echo '<select name="' . esc_attr($key) . '">';
                    foreach ($field['options'] as $k => $v) {
                        printf(
                            '<option value="%s" %s>%s</option>',
                            esc_attr($k),
                            selected($value, $k, false),
                            esc_html($v)
                        );
                    }
                    echo '</select>';
                    break;

                case 'checkbox':
                    printf(
                        '<label><input type="checkbox" name="%s" value="1" %s /> %s</label>',
                        esc_attr($key),
                        checked($value, '1', false),
                        esc_html($field['label'])
                    );
                    break;

                default:

                    $readonly = !empty($field['readonly'])
                        ? 'readonly="readonly"'
                        : '';

                    printf(
                        '<input type="%s" name="%s" value="%s" %s />',
                        esc_attr($field['type']),
                        esc_attr($key),
                        esc_attr($value),
                        $readonly
                    );
            }

            echo '</p>';
        }
    }

    /**
     * Render Select2 AJAX field (reusable)
     *
     * @param int $postId
     * @param string $metaKey
     * @param string $label
     */
    private function renderSelect2Field(
        int $postId,
        string $metaKey,
        string $label,
        bool $multiple = true,
        string $action = 'search_writers'
    ): void {

        $values = (array) get_post_meta($postId, $metaKey, true);

        echo '<p class="form-field">';
        echo '<label>' . esc_html($label) . '</label>';

        printf(
            '<select 
            class="select2-ajax"
            id="editors-select"
            name="%s%s"
            data-action="%s"
            data-placeholder="... ابحث عن %s"
            %s
        >',
            esc_attr($metaKey),
            $multiple ? '[]' : '',
            esc_attr($action),
            esc_attr(strtolower($label)),
            $multiple ? 'multiple' : ''
        );

        foreach ($values as $id) {

            $taxonomy = 'writer';

            $term = get_term($id, $taxonomy);

            if ($term && !is_wp_error($term)) {
                printf(
                    '<option value="%d" selected>%s</option>',
                    esc_attr($id),
                    esc_html($term->name)
                );
            }
        }

        echo '</select>';
        echo '</p>';
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue(): void
    {

        wp_enqueue_script(
            'select2-ajax',
            plugin_dir_url(__FILE__) . 'js/select2-ajax.js',
            ['jquery', 'selectWoo'],
            filemtime(__DIR__ . '/js/select2-ajax.js'),
            true
        );

        wp_localize_script(
            'select2-ajax',
            'matjarAjax',
            [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('book_nonce'),
            ]
        );
    }
}
