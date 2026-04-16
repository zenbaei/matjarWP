<?php

namespace Matjar\WpMedia;

use Matjar\WpMedia\Taxonomy;

class Module
{

    public function __construct()
    {
        new Taxonomy();
    }
}
