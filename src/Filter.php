<?php
/**
 * Define the imagefilter filter interface.
 */
namespace fv\yii\imagefilter;

interface Filter {
    public function filterImage($src, $dest);
}
