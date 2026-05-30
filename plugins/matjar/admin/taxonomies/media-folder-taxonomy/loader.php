<?php


if (!defined('ABSPATH')) exit;

/**
 * Bootstrap Fields module
 */

require_once __DIR__ . '/media-folder-taxonomy.php';
require_once __DIR__ . '/media-folder-crud.php';

$module = new Media_Folder_Taxonomy();
$crud = new Media_Folder_CRUD();
