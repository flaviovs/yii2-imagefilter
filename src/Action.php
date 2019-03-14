<?php

namespace fv\yii\imagefilter;

use yii\helpers\FileHelper;

class Action extends \yii\base\Action
{
    public $imagefilterComponent = 'imagefilter';


    /**
     * Handle filtering of a given (pipeline,version) tuple for an image.
     */
    public function run($pipeline, $version, $src)
    {
        $app = \Yii::$app;

        $imagefilter = $app->get($this->imagefilterComponent);

        $src_file = \Yii::getAlias('@webroot') . "/$src";

        // Do not continue if the supplied token is invalid. Notice that this
        // will fail if the file does not exist.
        if (!$imagefilter->isValidToken($src, $app->request->queryString)) {
            throw new \yii\web\NotFoundHttpException();
        }

        // Lastly, bail out if pipeline is not configured.
        if (empty($imagefilter->pipelines[$pipeline]['filters'])) {
            throw new \yii\base\InvalidConfigException(
                "Pipeline \"$pipeline\" not configured"
            );
        }

        if (strpos("$src/", "$imagefilter->path/") === 0) {
            \Yii::warning("Filtering already filtered image: $src", __METHOD__);
        }

        $dest_path = \Yii::getAlias('@webroot')
                   . "/$imagefilter->path/$pipeline/$version/" . dirname($src);
        FileHelper::createDirectory($dest_path, 0775, TRUE);

        $base = basename($src);

        $i = 0;

        // Loop over each filter in the pipeline.
        foreach ($imagefilter->pipelines[$pipeline]['filters'] as $cfg) {
            $obj = \Yii::createObject($cfg);

            $dest_file = "$dest_path/~$i-$base";

            if (is_file($dest_file)) {
                FileHelper::unlink($dest_file);
            }

            $this->applyFilter($obj, $src_file, $dest_file);

            if ($i && is_file($src_file)) {
                FileHelper::unlink($src_file);
            }

            $src_file = $dest_file;
            $i++;
        }

        $dest_file = "$dest_path/$base";
        rename($src_file, $dest_file);

        $send_options = [
            'mimeType' => \yii\helpers\FileHelper::getMimeType($dest_file),
            'inline' => TRUE,
            'fileSize' => filesize($dest_file),
        ];

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


    protected function applyFilter($obj, $src_file, $dest_file)
    {
        if (YII_DEBUG) {
            \Yii::trace("Applying " . get_class($obj) . " to $src_file",
                        __METHOD__);
        }

        $obj->filterImage($src_file, $dest_file);
    }

}
