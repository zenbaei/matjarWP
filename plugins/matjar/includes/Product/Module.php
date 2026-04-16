<?php

namespace Matjar\Product;

use Matjar\Product\Fields\Fields;
use Matjar\Product\Taxonomy\Person;
use Matjar\Product\Taxonomy\Publisher;
use Matjar\Product\Validation;

/**
 * Product Module
 *
 * Orchestrates:
 * - Taxonomies
 * - Product features (Fields)
 */
class Module
{

    public function __construct()
    {

        // Taxonomies
        new Person();
        new Publisher();

        // Features
        new Fields();

        new Validation();
    }
}
