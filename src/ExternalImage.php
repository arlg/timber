<?php

namespace Timber;

/**
 * Class ExternalImage
 *
 * The `Timber\ExternalImage` class represents an image that is not part of the WordPress content (Attachment).
 * Instead, it’s an image that can be either a path (relative/absolute) on the same server, or any arbitrary HTTP
 * resource (either from the same or from a different website).
 *
 * @api
 * @example
 * ```php
 * $context = Timber::context();
 *
 * // Lets say you have an external image that you want to use in your theme
 *
 * $context['cover_image'] = Timber::get_external_image($url);
 *
 * Timber::render('single.twig', $context);
 * ```
 *
 * ```twig
 * <article>
 *   <img src="{{ cover_image.src }}" class="cover-image" />
 *   <h1 class="headline">{{ post.title }}</h1>
 *   <div class="body">
 *     {{ post.content }}
 *   </div>
 * </article>
 * ```
 *
 * ```html
 * <article>
 *   <img src="http://example.org/wp-content/uploads/2015/06/nevermind.jpg" class="cover-image" />
 *   <h1 class="headline">Now you've done it!</h1>
 *   <div class="body">
 *     Whatever whatever
 *   </div>
 * </article>
 * ```
 */
class ExternalImage implements ImageInterface
{
    /**
     * Alt text.
     *
     * @api
     * @var string
     */
    private $alt_text;

    /**
     * Caption.
     *
     * @api
     * @var string
     */
    private $caption = '';

    /**
     * File.
     *
     * @api
     * @var mixed
     */
    public $file;

    /**
     * File location.
     *
     * @api
     * @var string The absolute path to the attachmend file in the filesystem
     *             (Example: `/var/www/htdocs/wp-content/uploads/2015/08/my-pic.jpg`)
     */
    public $file_loc;

    /**
     * Absolute URL.
     *
     * @var string The absolute URL to the attachment.
     */
    public $abs_url;

    /**
     * File extension.
     *
     * @api
     * @since 2.0.0
     * @var null|string A file extension.
     */
    public $file_extension = null;

    /**
     * Formatted file size.
     *
     * @api
     * @since 2.0.0
     * @var FileSize File size string.
     */
    private $file_size = null;

    /**
     * File types.
     *
     * @var array An array of supported relative file types.
     */
    private $image_file_types = [
        'jpg',
        'jpeg',
        'png',
        'svg',
        'bmp',
        'ico',
        'gif',
        'tiff',
        'pdf',
    ];

    /**
     * Image dimensions.
     *
     * @internal
     * @var ImageDimensions stores Image Dimensions in a structured way.
     */
    protected ImageDimensions $image_dimensions;

    /**
     * Inits the ExternalImage object.
     *
     * @param $url string URL to load the image from.
     * @param $alt string ALT text for the image.
     * @internal
     */
    public static function build($url, array $args = [])
    {
        $args = wp_parse_args($args, [
            'alt' => '',
        ]);

        if (!is_numeric($url) && is_string($url)) {
            $external_image = new static();

            if ($args['alt'] != '') {
                $external_image->set_alt_text($args['alt']);
            }

            if (strstr($url, '://')) {
                // Assume URL.
                $external_image->init_with_url($url);

                return $external_image;
            } elseif (strstr($url, ABSPATH)) {
                // Assume absolute path.
                $external_image->init_with_file_path($url);

                return $external_image;
            } else {
                // Check for image file types.
                foreach ($external_image->image_file_types as $type) {
                    // Assume a relative path.
                    if (strstr(strtolower($url), $type)) {
                        $external_image->init_with_relative_path($url);

                        return $external_image;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Gets the source URL for the image.
     *
     * @param string $size Ignored. For compatibility with Timber\Image.
     *
     * @return string The src URL for the image.
     * @api
     * @example
     * ```twig
     * <img src="{{ post.thumbnail.src }}">
     * <img src="{{ post.thumbnail.src('medium') }}">
     * ```
     * ```html
     * <img src="http://example.org/wp-content/uploads/2015/08/pic.jpg" />
     * <img src="http://example.org/wp-content/uploads/2015/08/pic-800-600.jpg">
     * ```
     *
     */
    public function src($size = 'full')
    {
        return URLHelper::maybe_secure_url($this->abs_url);
    }

    /**
     * Gets the relative path to an attachment.
     *
     * @return string The relative path to an attachment.
     * @example
     * ```twig
     * <img src="{{ image.path }}" />
     * ```
     * ```html
     * <img src="/wp-content/uploads/2015/08/pic.jpg" />
     * ```
     *
     * @api
     */
    public function path()
    {
        return URLHelper::get_rel_path($this->src());
    }

    /**
     * Gets filesize in a human readable format.
     *
     * This can be useful if you want to display the human readable filesize for a file. It’s
     * easier to read «16 KB» than «16555 bytes» or «1 MB» than «1048576 bytes».
     *
     * @return mixed|null The filesize string in a human readable format.
     * @since 2.0.0
     * @example
     *
     * Use filesize information in a link that downloads a file:
     *
     * ```twig
     * <a class="download" href="{{ attachment.src }}" download="{{ attachment.title }}">
     *     <span class="download-title">{{ attachment.title }}</span>
     *     <span class="download-info">(Download, {{ attachment.size }})</span>
     * </a>
     * ```
     *
     * @api
     */
    public function size()
    {
        if ($this->file_size) {
            return $this->file_size->size();
        }

        return false;
    }

    /**
     * Gets filesize in bytes.
     *
     * @return mixed|null The filesize string in bytes, or false if the filesize can’t be read.
     * @since 2.0.0
     * @example
     *
     * ```twig
     * <table>
     *     {% for attachment in Attachment(attachment_ids) %}
     *         <tr>
     *             <td>{{ attachment.title }}</td>
     *             <td>{{ attachment.extension }}</td>
     *             <td>{{ attachment.size_raw }} bytes</td>
     *         </tr>
     *     {% endfor %}
     * </table>
     * ```
     *
     * @api
     */
    public function size_raw()
    {
        if ($this->file_size) {
            return $this->file_size->size_raw();
        }

        return false;
    }

    /**
     * Gets the src for an attachment.
     *
     * @return string The src of the attachment.
     * @api
     *
     */
    public function __toString()
    {
        return $this->src();
    }

    /**
     * Gets the extension of the attached file.
     *
     * @return null|string An uppercase extension string.
     * @since 2.0.0
     * @example
     *
     * Use extension information in a link that downloads a file:
     *
     * ```twig
     * <a class="download" href="{{ attachment.src }}" download="{{ attachment.title }}">
     *     <span class="download-title">{{ attachment.title }}</span>
     *     <span class="download-info">
     *         (Download {{ attachment.extension|upper }}, {{ attachment.size }})
     *     </span>
     * </a>
     * ```
     *
     * @api
     */
    public function extension()
    {
        if (!$this->file_extension) {
            $file_info = wp_check_filetype($this->file);

            if (!empty($file_info['ext'])) {
                $this->file_extension = strtoupper($file_info['ext']);
            }
        }

        return $this->file_extension;
    }

    /**
     * Gets the width of the image in pixels.
     *
     * @return int The width of the image in pixels.
     * @example
     * ```twig
     * <img src="{{ image.src }}" width="{{ image.width }}" />
     * ```
     * ```html
     * <img src="http://example.org/wp-content/uploads/2015/08/pic.jpg" width="1600" />
     * ```
     *
     * @api
     */
    public function width()
    {
        return $this->image_dimensions->width();
    }

    /**
     * Gets the height of the image in pixels.
     *
     * @return int The height of the image in pixels.
     * @example
     * ```twig
     * <img src="{{ image.src }}" height="{{ image.height }}" />
     * ```
     * ```html
     * <img src="http://example.org/wp-content/uploads/2015/08/pic.jpg" height="900" />
     * ```
     *
     * @api
     */
    public function height()
    {
        return $this->image_dimensions->height();
    }

    /**
     * Gets the aspect ratio of the image.
     *
     * @return float The aspect ratio of the image.
     * @example
     * ```twig
     * {% if post.thumbnail.aspect < 1 %}
     *   {# handle vertical image #}
     *   <img src="{{ post.thumbnail.src|resize(300, 500) }}" alt="A basketball player" />
     * {% else %}
     *   <img src="{{ post.thumbnail.src|resize(500) }}" alt="A sumo wrestler" />
     * {% endif %}
     * ```
     *
     * @api
     */
    public function aspect()
    {
        return $this->image_dimensions->aspect();
    }

    /**
     * Sets the relative alt text of the image.
     *
     * @param string $alt Alt text for the image.
     */
    public function set_alt_text(string $alt)
    {
        $this->alt_text = $alt;
    }

    /**
     * Sets the relative alt text of the image.
     *
     * @param string $caption Caption text for the image
     */
    public function set_caption(string $caption)
    {
        $this->caption = $caption;
    }

    /**
     * Inits the object with an absolute path.
     *
     * @param string $file_path An absolute path to a file.
     * @internal
     *
     */
    protected function init_with_file_path($file_path)
    {
        $url = URLHelper::file_system_to_url($file_path);

        $this->abs_url = $url;
        $this->file_loc = $file_path;
        $this->file = $file_path;
        $this->image_dimensions = new ImageDimensions($file_path);
        $this->file_size = new FileSize($file_path);
    }

    /**
     * Inits the object with a relative path.
     *
     * @param string $relative_path A relative path to a file.
     * @internal
     *
     */
    protected function init_with_relative_path($relative_path)
    {
        $file_path = URLHelper::get_full_path($relative_path);

        $this->abs_url = home_url($relative_path);
        $this->file_loc = $file_path;
        $this->file = $file_path;
        $this->image_dimensions = new ImageDimensions($file_path);
        $this->file_size = new FileSize($file_path);
    }

    /**
     * Inits the object with an URL.
     *
     * @param string $url An URL on the same host.
     * @internal
     *
     */
    protected function init_with_url($url)
    {
        $this->abs_url = $url;

        if (!URLHelper::is_local($url)) {
            $url = ImageHelper::sideload_image($url);
        }

        if (URLHelper::is_local($url)) {
            $this->file = URLHelper::remove_double_slashes(
                ABSPATH . URLHelper::get_rel_url($url)
            );
            $this->file_loc = URLHelper::remove_double_slashes(
                ABSPATH . URLHelper::get_rel_url($url)
            );
            $this->image_dimensions = new ImageDimensions($this->file_loc);
            $this->file_size = new FileSize($this->file_loc);
        } else {
            $this->image_dimensions = new ImageDimensions();
        }
    }

    /**
     * Gets the alt text for an image.
     *
     * For better accessibility, you should always add an alt attribute to your images, even if it’s
     * empty.
     *
     * @return string Alt text stored in WordPress.
     * @example
     * ```twig
     * <img src="{{ image.src }}" alt="{{ image.alt }}" />
     * ```
     * ```html
     * <img src="http://example.org/wp-content/uploads/2015/08/pic.jpg"
     *     alt="You should always add alt texts to your images for better accessibility" />
     * ```
     *
     * @api
     */
    public function alt(): string
    {
        return $this->alt_text;
    }

    public function caption(): string
    {
        return $this->caption;
    }

    public function img_sizes($size = "full")
    {
        return [$size];
    }

    public function srcset($size = "full")
    {
        $source = [
            'url' => $this->src(),
            'descriptor' => 'w',
            'value' => $size,
        ];

        return str_replace(' ', '%20', $source['url']) . ' ' . $source['value'] . $source['descriptor'];
    }
}
