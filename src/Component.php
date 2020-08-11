<?php
/**
 * Implementation of the imagefilter component.
 */

namespace fv\yii\imagefilter;

use yii\helpers\Url;

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
    protected function getToken($src)
    {
        $full_path = \Yii::getAlias('@webroot') . "/$src";

        try {
            $token =  hash('crc32b', '$full_path ' . filemtime($full_path));
        } catch (\Exception $ex) {
            \Yii::error($ex, __METHOD__);
            return null;
        } catch (\Throwable $ex) {
            \Yii::error($ex, __METHOD__);
            return null;
        }

        return $token;
    }


    /**
     * Check if a token is valid for a given (pipe,url) tuple.
     */
    public function isValidToken($src, $token)
    {
        $img_token = $this->getToken($src);
        if ($img_token === null) {
            return false;
        }

        if (!hash_equals($img_token, $token)) {
            \Yii::warning("Invalid token", __METHOD__);
            return false;
        }

        return true;
    }


    /**
     * Return a URL to render a filtered version of an image.
     */
    public function url($pipeline, $src, $scheme = false)
    {
        $src = ltrim(is_array($src) ? $src[0] : $src, '/');
        $version = $this->getPipelineVersion($pipeline);
        return Url::to("@web/$this->path/$pipeline/$version/$src", $scheme)
            . '?'
            . $this->getToken($src);
    }


    /**
     * Return an HTML IMG tag.
     */
    public function img($pipeline, $src, array $options = [])
    {
        return \yii\helpers\Html::img($this->url($pipeline, $src), $options);
    }
}
