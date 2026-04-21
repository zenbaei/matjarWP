<?php

namespace Matjar\Fields;


if (!defined('ABSPATH')) exit;

/**
 * Class UI
 *
 * Handles:
 * - Rendering WooCommerce custom tab
 * - Rendering dynamic fields (simple)
 * - Rendering complex Select2 fields (AJAX)
 * - Enqueuing admin assets
 *
 * Architecture:
 * - UI Layer only (no business logic)
 * - Hybrid system (dynamic + custom fields)
 */
class UI
{

    /**
     * Dynamic field configuration
     *
     * @return array
     */
    private function getFields(): array
    {

        return [

            '_book_series' => [
                'label' => 'Series',
                'type'  => 'number',
                'default' => 1,
            ],

            '_book_edition' => [
                'label' => 'Edition',
                'type'  => 'number',
                'default' => 1,
            ],

            '_book_notes' => [
                'label' => 'Notes',
                'type'  => 'textarea',
            ],

            '_book_year' => [
                'label'   => 'Publishing Year',
                'type'    => 'select',
                'options' => $this->getYears(),
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

        $years = range(date('Y'), 1900);

        return array_combine($years, $years);
    }

    /**
     * Register WooCommerce tab
     */
    public function registerTab($tabs)
    {

        $tabs['book_info'] = [
            'label'    => __('Book Info', 'matjar-book-plugin'),
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

        $this->renderDynamicFields($post->ID);

        // Complex fields
        $this->renderSelect2Field($post->ID, '_book_authors', 'Authors');
        $this->renderSelect2Field($post->ID, '_book_editors', 'Editors');
        $this->renderSelect2Field($post->ID, '_book_publisher', 'Publisher', false, 'search_publishers');

        echo '</div>';
    }

    /**
     * Render dynamic (simple) fields
     */
    private function renderDynamicFields(int $postId): void
    {

        foreach ($this->getFields() as $key => $field) {

            $value = get_post_meta($postId, $key, true);

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
                    printf(
                        '<input type="%s" name="%s" value="%s" />',
                        esc_attr($field['type']),
                        esc_attr($key),
                        esc_attr($value)
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
            name="%s%s"
            data-action="%s"
            data-nonce="%s"
            data-placeholder="Search %s..."
            %s
        >',
            esc_attr($metaKey),
            $multiple ? '[]' : '',
            esc_attr($action),
            esc_attr(wp_create_nonce('book_nonce')),
            esc_attr(strtolower($label)),
            $multiple ? 'multiple' : ''
        );

        foreach ($values as $id) {

            $taxonomy = ($action === 'search_publishers') ? 'publisher' : 'writer';

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
            MATJAR_URL . 'assets/js/select2-ajax.js',
            ['jquery', 'selectWoo'],
            MATJAR_VERSION,
            true
        );
    }
}
