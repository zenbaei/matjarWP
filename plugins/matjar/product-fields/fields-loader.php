<?php

namespace Matjar\Product_Fields;

if (!defined('ABSPATH')) exit;

/**
 * Bootstrap Fields module
 */

require_once __DIR__ . '/UI.php';
require_once __DIR__ . '/Service.php';
require_once __DIR__ . '/Fields.php';

$module = new Fields();
