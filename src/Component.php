<?php
/**
 * Implementation of the imagefilter component.
 *
 * This component is responsible for generating URLs for filtered images and
 * validating tokens in incoming requests. It manages filter pipelines and
 * provides helper methods for creating image URLs and img tags.
 *
 * @since 1.0
 */

namespace fv\yii\imagefilter;

use yii\helpers\Url;

class Component extends \yii\base\Component
{
    /**
     * @var bool Whether to use XSendFile for serving processed images.
     * XSendFile allows the web server to serve files directly after PHP
     * processes them, improving performance.
     */
    public $useXSendFile = FALSE;

    /**
     * @var string Base path for filtered images, relative to webroot.
     * Filtered images will be stored under this path.
     */
    public $path = 'assets/img';

    /**
     * @var string|null Secret key for HMAC token validation.
     * When set, enables HMAC-SHA1 token validation for apps without
     * filesystem access. The token format is "t:{8chars}".
     */
    public $tokenSecret = null;

    /**
     * @var array Filter pipeline configurations.
     * Each key is a pipeline name, and the value is an array with:
     * - filters: array of filter configurations
     * - version: optional version string for cache busting
     */
    public $pipelines = [];

    /**
     * Get the version for a given pipeline.
     *
     * @param string $pipeline Pipeline name
     * @return string Version string (defaults to "0")
     */
    protected function getPipelineVersion($pipeline)
    {
        if (empty($this->pipelines[$pipeline]['version'])) {
            return '0';
        }

        return $this->pipelines[$pipeline]['version'];
    }


    /**
     * Get a file token based on source path, pipeline, version and modification time.
     *
     * Uses CRC32B hash of the path, pipeline, version and file modification time,
     * separated by null bytes to prevent injection attacks. This provides basic
     * protection against URL guessing.
     *
     * @param string $src Source image path relative to webroot, e.g. "img/foobar.png"
     * @param string $pipeline Pipeline name
     * @param string $version Pipeline version
     * @return string|null CRC32B hash string, or null on error
     */
    protected function getToken($src, $pipeline, $version)
    {
        $full_path = \Yii::getAlias('@webroot') . "/$src";

        try {
            $token = hash('crc32b', $src . "\0" . $pipeline . "\0" . $version . "\0" . filemtime($full_path));
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
     * Get an HMAC token for the given source path.
     *
     * Generates a token using HMAC-SHA1 with the configured secret.
     * The token format is "t:{8chars}" where 8 chars are the first hex
     * digits of the HMAC hash.
     *
     * The HMAC is computed over the path components separated by null bytes
     * to prevent injection attacks: "$path\0$pipeline\0$version\0$src".
     *
     * @param string $src Source image path relative to webroot, e.g. "img/foobar.png"
     * @param string $pipeline Pipeline name
     * @param string $version Pipeline version
     * @return string|null Token string prefixed with "t:", or null if no secret configured
     */
    protected function getHMACToken($src, $pipeline, $version)
    {
        if ($this->tokenSecret === null) {
            return null;
        }

        $path = $this->path . '/' . $pipeline . '/' . $version . '/' . $src;
        $hash = hash_hmac('sha1', $path, $this->tokenSecret);
        return 't:' . substr($hash, 0, 8);
    }


    /**
     * Check if a token is valid for the given source path.
     *
     * Supports two token formats:
     * - Plain CRC32 hash: validated against file modification time
     * - HMAC token (t: prefix): validated using the configured secret
     *
     * For both token types, path components are separated by null bytes
     * to prevent injection attacks.
     *
     * @param string $src Source image path relative to webroot, e.g. "img/foobar.png"
     * @param string $token Token to validate (CRC hash or "t:{8chars}")
     * @param string $pipeline Pipeline name
     * @param string $version Pipeline version
     * @return bool True if token is valid
     */
    public function isValidToken($src, $token, $pipeline, $version)
    {
        if (strpos($token, 't:') === 0) {
            if ($this->tokenSecret === null) {
                \Yii::warning("Token secret not configured", __METHOD__);
                return false;
            }

            $hash = substr($token, 2);
            $path = $this->path . "\0" . $pipeline . "\0" . $version . "\0" . $src;
            $expected = substr(hash_hmac('sha1', $path, $this->tokenSecret), 0, 8);

            if (!hash_equals($expected, $hash)) {
                \Yii::warning("Invalid token", __METHOD__);
                return false;
            }

            return true;
        }

        $img_token = $this->getToken($src, $pipeline, $version);
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
     * Generate a URL to render a filtered version of an image.
     *
     * @param string $pipeline Pipeline name
     * @param string $src Source image path relative to webroot
     * @param bool|string $scheme URL scheme (false for relative)
     * @return string Generated URL with token query parameter
     */
    public function url($pipeline, $src, $scheme = false)
    {
        $src = ltrim(is_array($src) ? $src[0] : $src, '/');
        $version = $this->getPipelineVersion($pipeline);
        return Url::to("@web/$this->path/$pipeline/$version/$src", $scheme)
            . '?'
            . $this->getToken($src, $pipeline, $version);
    }


    /**
     * Generate an HTML IMG tag for a filtered image.
     *
     * @param string $pipeline Pipeline name
     * @param string $src Source image path relative to webroot
     * @param array $options HTML options for the img tag
     * @return string Generated HTML img tag
     */
    public function img($pipeline, $src, array $options = [])
    {
        return \yii\helpers\Html::img($this->url($pipeline, $src), $options);
    }
}
