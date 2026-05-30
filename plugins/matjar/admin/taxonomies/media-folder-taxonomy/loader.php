<?php

namespace Matjar\Product_Custom_Fields;

if (!defined('ABSPATH')) exit;

/**
 * Bootstrap Fields module
 */

require_once __DIR__ . '/media-folder-taxonomy.php';
require_once __DIR__ . '/media-folder-crud.php';

$module = new Fields();
