Yii2 Imagefilter extension
==========================

The Yii2 Imagefilter extension provides a mechanism to automatically
transform and generate ("filter") image files. For example, you can
define a pipeline called "thumbnail-100", and add a filter to it that
transforms an image into a 100px thumbnail. When the pipeline image
URL is accessed for the first time, Yii2 Imagefilter will apply all
filters configured pipeline, and then save and output the image. On a
properly configured web server software (i.e. nginx, Apache, etc.),
the next time the image URL is accessed the file is served directly,
withou any PHP/Yii2 overhead.

**Important**: processed images are saved to your `@webroot`, so your
web server should be configured to serve existing files
directly. Also, this extension requires `enablePrettyUrl` to be
enabled in your Yii2 URL manager configuration. See
https://www.yiiframework.com/doc/guide/2.0/en/runtime-routing#using-pretty-urls
for more info.


Usage
-----

1. Configure the `imagefilter` component in your Yii2 application. This
   usually means editing `@app\config\web.php` and including the following
   lines:

   ```php
   'components' => [
       // (...)
       'imagefilter' => [
           'class' => \fv\yii\imagefilter\Component::class,
           'pipelines' => [
               'thumbnail-100' => [
                   'filters' => [
                       [
                           'class' => 'app\filters\Scale',
                           'width' => 100,
                           'height' => 100,
                       ],
                   ],
               ],
           ],
       ],
       // (...)
   ]
   ```

   Notice that you can have several filters in a single pipeline. Multiple
   filters are applied in the sequence they are configured.

   See *Creating filters* below to know about creating image filters.


2. Add the image filter Action to one of your controllers. For
   example, you could create a controller that looks like this:

   ```php
   namespace app\controllers;

   class ImageController extends \yii\web\Controller
   {
	   public function actions()
	   {
		   return [
			   'filter' => \fv\yii\imagefilter\Action::class,
		   ];
	   }
   }
   ```

3. Configure an URL rule pointing to the Imagefilter action you just
created in the previous step:

   ```php
   'urlManager' => [
       // (...)
       'rules' => [
           // (...)
           'assets/img/<pipeline>/<version>/<src:.+>' => 'image/filter',
           // (...)
        ],
   ],
   ```

4. Generate URLs to images using the `imagefilter` component.

   To generate na URL, call `$app->imagefilter->url()`:

   ```php
   $app->imagefilter->url('thumbnail-100', '/img/foobar.png')
   ```

   Call `$app->imagefilter->img()` to generate a complete `<img>` tag:

   ```php
   $app->imagefilter->img('thunbnail-100', '/img/foobar.png', ['alt' => 'Foo bar']);
   ```

   Note: URL path and options array are used the same way as in
   [\yii\helpers\Html::img()](https://www.yiiframework.com/doc/api/2.0/yii-helpers-basehtml#img()-detail).



Creating filters
----------------

Filters are defined by PHP classes that implements the
`fv\yii\imagefilter\Filter` interface:

```php
class MyFilter extends \yii\base\BaseObject implements \fv\yii\imagefilter\Filter
{
    public $width;
    public $height;

    public function filterImage($src, $dest)
	{
         \yii\imagine\Image::thumbnail($src, $this->width, $this->height)
            ->save($dest);
    }
}
```

The separate [Yii2 Imagefilters] extension (note: plural) contains
some ready-to-use filters for Yii2 Imagefilter.


Pipeline versioning
-------------------

Each pipeline can have a version (default "0"). The version is used to
generate the final, filtered image URL. This allow you to force browsers to
load new images on the next requests by just changing a pipeline
version. This also allows you to implement very aggressive caching for image
files (i.e. you can instructing your web server to generate HTTP caching
headers that expire image files far in the future).

Example:

```php
'standard-watermark' => [
    'filters' => [
        [
            'class' => 'app\filters\Scale',
            'width' => 400,
            'height' => 400,
        ],
        [
            'class' => 'app\filters\AddWatermark',
            'text' => 'My Image',
            'fontSize' => 10,
            'x' => -1,
            'y' => -1,
        ],
    ],
]
```

Since no version was specified, this pipeline is assigned version "0" (you
can check this by looking at URLs of filtered images, which should be like
`/assets/img/standard-watermark/0/img/my-image.png` -- note the "0" as the
fourth element in the path).

Now suppose that you changed your watermark font size from 10 to 12
pixels. To force browsers to load new images, just change the pipeline
version:

```php
'standard-watermark' => [
    'version' => '1', // Force a new version
    'filters' => [
        // (...)
        [
            // (...)
            'fontSize' => 12,
            // (...)
        ],
    ],
]
```

Notes:

1. The extension will never delete older versions from your `@webroot`. You
   should do it manually.

2. Another approach to force browsers get new filtered images is to simply
   remove the directory corresponding to the current version. This will
   force Yii2 Imagefilter to regenerate new images.


Token authentication
--------------------

By default, URLs are validated using a CRC32 hash of the file path and
modification time. This requires filesystem access to compute the token.

For applications without filesystem access (e.g., frontend apps, API
services), configure a `tokenSecret` to enable HMAC-based token validation:

```php
'imagefilter' => [
    'class' => \fv\yii\imagefilter\Component::class,
    'tokenSecret' => 'your-secret-key',
    // ...
],
```

When `tokenSecret` is configured, the extension accepts an HMAC-SHA1 token
in the URL query string. The token must use the format `t:{8chars}` where
the 8 characters are the first 8 hex digits of the HMAC-SHA1 hash.

The HMAC is calculated over the full path using SHA1 as the algorithm
and `tokenSecret` as the key:

```
HMAC-SHA1(key=tokenSecret, data="{path}/{pipeline}/{version}/{src}")
```

Where:
- `tokenSecret` - the configured secret key
- `path` - the configured cache path (default: "assets/img")
- `pipeline` - the pipeline name
- `version` - the pipeline version
- `src` - the source image path relative to webroot

Examples (using secret `your-secret-key`):

| Path                                                  | Token        |
|-------------------------------------------------------|--------------|
| assets/img/thumbnail-100/0/img/foobar.png             | `t:097225f9` |
| assets/img/thumbnail-100/0/img/subdir/image.jpg       | `t:c6e00b6f` |
| assets/img/standard-watermark/1/photos/landscape.jpg  | `t:dcb5628c` |

Note: URLs generated by the `url()` method always use the default CRC32. If you
need HMAC tokens in your URLs, you must manually construct them using the same
algorithm.


Support
-------
Visit http://github.org/flaviovs/yii2-imagefilter


[Yii2 Imagefilters]: https://github.com/flaviovs/yii2-imagefilters
