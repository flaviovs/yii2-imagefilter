<?php
/**
 * Controller action for processing filtered images.
 *
 * This action handles incoming requests for filtered images. It validates
 * the request token, applies configured filters from the pipeline, and
 * serves the resulting image.
 *
 * URL format: /assets/img/{pipeline}/{version}/{src}?{token}
 *
 * @since 1.0
 */

namespace fv\yii\imagefilter;

use yii\helpers\FileHelper;

class Action extends \yii\base\Action
{
    /**
     * @var string Name of the imagefilter component to use.
     */
    public $imagefilterComponent = 'imagefilter';


    /**
     * Handle filtering of a given (pipeline,version) tuple for an image.
     *
     * Request flow:
     * 1. Validate token from query string
     * 2. Verify pipeline is configured
     * 3. Apply each filter in the pipeline sequentially
     * 4. Serve the final filtered image
     *
     * @param string $pipeline Pipeline name (e.g., "thumbnail-100")
     * @param string $version Version string for cache busting
     * @param string $src Source image path relative to webroot
     * @return mixed
     * @throws \yii\web\NotFoundHttpException If token is invalid
     * @throws \yii\base\InvalidConfigException If pipeline is not configured
     */
    public function run($pipeline, $version, $src)
    {
        $app = \Yii::$app;

        $imagefilter = $app->get($this->imagefilterComponent);

        $src_file = \Yii::getAlias('@webroot') . "/$src";

        // Validate token from query string. This will fail if the source
        // file doesn't exist because the CRC token depends on filemtime().
        if (!$imagefilter->isValidToken($src, $app->request->queryString, $pipeline, $version)) {
            throw new \yii\web\NotFoundHttpException();
        }

        // Verify pipeline is configured.
        if (empty($imagefilter->pipelines[$pipeline]['filters'])) {
            throw new \yii\base\InvalidConfigException(
                "Pipeline \"$pipeline\" not configured"
            );
        }

        // Warn if attempting to filter an already-filtered image.
        if (strpos("$src/", "$imagefilter->path/") === 0) {
            \Yii::warning("Filtering already filtered image: $src", __METHOD__);
        }

        $dest_path = \Yii::getAlias('@webroot')
                   . "/$imagefilter->path/$pipeline/$version/" . dirname($src);
        FileHelper::createDirectory($dest_path, 0775, TRUE);

        $base = basename($src);

        $i = 0;

        // Apply each filter in sequence. Intermediate files are deleted
        // after each filter to save space.
        foreach ($imagefilter->pipelines[$pipeline]['filters'] as $cfg) {
            $obj = \Yii::createObject($cfg);

            $dest_file = "$dest_path/~$i-$base";

            if (is_file($dest_file)) {
                FileHelper::unlink($dest_file);
            }

            $this->applyFilter($obj, $src_file, $dest_file);

            // Delete intermediate files (all but the last one).
            if ($i && is_file($src_file)) {
                FileHelper::unlink($src_file);
            }

            $src_file = $dest_file;
            $i++;
        }

        // Rename final temporary file to the destination filename.
        $dest_file = "$dest_path/$base";
        rename($src_file, $dest_file);

        $send_options = [
            'mimeType' => \yii\helpers\FileHelper::getMimeType($dest_file),
            'inline' => TRUE,
            'fileSize' => filesize($dest_file),
        ];

        // Close session before sending file to release the session lock.
        // This allows other requests to proceed while the file is being sent.
        if ($app->has('session')) {
            $app->session->close();
        }

        if ($imagefilter->useXSendFile) {
            return $app->response->xSendFile($dest_file, $base, $send_options);
        } else {
            return $app->response->sendStreamAsFile(
                fopen($dest_file, 'rb'),
                $base,
                $send_options
            );
        }
    }


    /**
     * Apply a filter to an image.
     *
     * @param Filter $obj Filter instance to apply
     * @param string $src_file Source image file path
     * @param string $dest_file Destination image file path
     * @return void
     */
    protected function applyFilter($obj, $src_file, $dest_file)
    {
        if (YII_DEBUG) {
            \Yii::trace("Applying " . get_class($obj) . " to $src_file",
                        __METHOD__);
        }

        $obj->filterImage($src_file, $dest_file);
    }

}
