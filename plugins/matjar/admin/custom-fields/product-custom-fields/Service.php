<?php

namespace Matjar\Product_Custom_Fields;

if (!defined('ABSPATH')) exit;

/**
 * Class Fields_Service
 *
 * Handles:
 * - Saving dynamic fields
 * - Saving complex fields
 * - AJAX logic
 *
 * Layer:
 * - Business Logic Layer
 */
class Service
{

    /**
     * Dynamic field keys
     */
    private function getFields(): array
    {
        return [
            '_book_series',
            '_book_edition',
            '_book_notes',
            '_book_year',
        ];
    }

    /**
     * Save all fields
     */
    public function save($postId): void
    {

        // 🔹 Dynamic
        foreach ($this->getFields() as $key) {

            if (isset($_POST[$key])) {
                update_post_meta(
                    $postId,
                    $key,
                    sanitize_text_field($_POST[$key])
                );
            }
        }

        // 🔥 Complex
        $this->saveMulti('_book_editors', $postId);
    }

    /**
     * Save multi-select fields
     */
    private function saveMulti(string $key, int $postId): void
    {

        if (!isset($_POST[$key])) return;

        $values = array_map('intval', $_POST[$key]);

        update_post_meta($postId, $key, $values);
    }


    /**
     * Save single-select fields
     */
    private function saveSingle(string $key, int $postId): void
    {

        if (!isset($_POST[$key])) return;

        update_post_meta(
            $postId,
            $key,
            intval($_POST[$key])
        );
    }

    /**
     * AJAX search authors/editors
     */
    public function searchWriters(): void
    {
        if (
            empty($_REQUEST['nonce']) ||
            !\wp_verify_nonce($_REQUEST['nonce'], 'book_nonce')
        ) {
            \wp_send_json_error('Nonce failed');
        }

        $search = sanitize_text_field($_GET['term'] ?? '');

        $terms = get_terms([
            'taxonomy'   => 'writer',
            'search'     => $search,
            'hide_empty' => false,
            'number'     => 20,
        ]);

        $results = [];

        foreach ($terms as $term) {
            $results[] = [
                'id'   => $term->term_id,
                'text' => $term->name,
            ];
        }

        wp_send_json($results);
    }
}
