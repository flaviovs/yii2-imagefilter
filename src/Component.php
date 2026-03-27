<?php
/**
 * Implementation of the imagefilter component.
 */

namespace fv\yii\imagefilter;

use yii\helpers\Url;

class Component extends \yii\base\Component
{

    public $useXSendFile = FALSE;
    public $path = 'assets/img';
    public $tokenSecret = null;

    public $pipelines = [];

    protected function getPipelineVersion($pipeline)
    {
        if (empty($this->pipelines[$pipeline]['version'])) {
            return '0';
        }

        return $this->pipelines[$pipeline]['version'];
    }


    /**
     * Get a file token, based on source path.
     *
     * @param string $src Original path in web root, e.g. "/img/foobar/file.png"
     */
    protected function getToken($src)
    {
        $full_path = \Yii::getAlias('@webroot') . "/$src";

        try {
            $token =  hash('crc32b', $src . " " . filemtime($full_path));
        } catch (\Exception $ex) {
            \Yii::error($ex, __METHOD__);
            return null;
        } catch (\Throwable $ex) {
            \Yii::error($ex, __METHOD__);
            return null;
        }

        return $token;
    }


    protected function getHMACToken($src)
    {
        if ($this->tokenSecret === null) {
            return null;
        }

        $path = $this->path . '/' . $src;
        $hash = hash_hmac('sha1', $path, $this->tokenSecret);
        return 't:' . substr($hash, 0, 8);
    }


    public function isValidToken($src, $token)
    {
        if (strpos($token, 't:') === 0) {
            if ($this->tokenSecret === null) {
                \Yii::warning("Token secret not configured", __METHOD__);
                return false;
            }

            $hash = substr($token, 2);
            $path = $this->path . '/' . $src;
            $expected = substr(hash_hmac('sha1', $path, $this->tokenSecret), 0, 8);

            if (!hash_equals($expected, $hash)) {
                \Yii::warning("Invalid token", __METHOD__);
                return false;
            }

            return true;
        }

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
