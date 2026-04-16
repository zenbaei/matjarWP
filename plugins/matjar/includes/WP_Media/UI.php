<?php
namespace Matjar\WP_Media;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UI
 *
 * Handles admin UI for media folders.
 *
 * Responsibilities:
 * - Render dropdown filter in Media Library
 * - Initialize Select2
 *
 * Layer:
 * - UI Layer
 *
 * @package Matjar\Media
 */
class UI {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('restrict_manage_posts', [$this, 'renderDropdown']);
        add_action('admin_footer-upload.php', [$this, 'initSelect2']);
    }

    /**
     * Render media folder dropdown filter
     *
     * @return void
     */
    public function renderDropdown(): void {

        global $pagenow;

        if ($pagenow !== 'upload.php') {
            return;
        }

        $terms = get_terms([
            'taxonomy'   => 'media_folder',
            'hide_empty' => false,
        ]);

        echo '<select id="media-folder-filter" name="term">';
        echo '<option value="">All Folders</option>';
        echo '<option value="no_folder">No Folder</option>';

        foreach ($terms as $term) {
            echo "<option value='{$term->slug}'>{$term->name}</option>";
        }

        echo '</select>';
        echo '<input type="hidden" name="taxonomy" value="media_folder">';
    }

    /**
     * Initialize Select2 dropdown
     *
     * @return void
     */
    public function initSelect2(): void {
?>
<script>
jQuery(function($){
    $('#media-folder-filter').select2({
        width: '220px',
        placeholder: 'Search folder'
    });
});
</script>
<?php
    }
}