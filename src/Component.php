<?php
/**
 * Implementation of the imagefilter component.
 */

namespace fv\yii\imagefilter;

class Component extends \yii\base\Component {

    public $useXSendFile = FALSE;
    public $path = 'assets/img';

    public $pipelines = [];

    protected function getPipelineVersion($pipeline)
    {
        if (empty($this->pipelines[$pipeline]['version'])) {
            return '0';
        }

        return $this->pipelines[$pipeline]['version'];
    }


    /**
     * Get a file token, based on pipe configuration and source path.
     */
    public function getToken($src)
    {
        $full_path = \Yii::getAlias('@webroot') . "/$src";
        return hash('crc32b', "$full_path " . fileinode($full_path));
    }


    /**
     * Check if a token is valid for a given (pipe,url) tuple.
     */
    public function isValidToken($src, $token)
    {
        return hash_equals($this->getToken($src), $token);
    }


    /**
     * Return a URL to render a filtered version of an image.
     */
    public function url($pipeline, $src)
    {
        $src = ltrim($src, '/');
        $version = $this->getPipelineVersion($pipeline);
        return \Yii::getAlias('@web')
            . "/$this->path/$pipeline/$version/$src?"
            . $this->getToken($src);
    }


    /**
     * Return a HTML IMG tag.
     */
    public function img($pipeline, $src, array $options = [])
    {
        return \yii\helpers\Html::img($this->url($pipeline, $src), $options);
    }
}
