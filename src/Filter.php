<?php
/**
 * Define the imagefilter filter interface.
 *
 * @since 1.0
 */
namespace fv\yii\imagefilter;

interface Filter
{
    /**
     * Apply a filter to an image.
     *
     * @param string $src Source image file path, absolute path
     * @param string $dest Destination image file path
     * @return void
     */
    public function filterImage($src, $dest);
}
