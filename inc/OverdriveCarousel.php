<?php

namespace BCLibCoop\OverdriveCarousel;

class OverdriveCarousel
{
    public static $instance;
    protected $odauth;
    protected $config;
    public $caturl;

    private $transient_key = 'coop_overdrive';

    private const FORMATS = [
        'ebook' => [
            'ebook-kindle',
            'ebook-overdrive',
            'ebook-epub-adobe',
            'ebook-epub-open',
            'ebook-pdf-adobe',
            'ebook-pdf-open',
            'ebook-mediado',
        ],
        'magazine' => [
            'magazine-overdrive',
        ],
        'audiobook' => [
            'audiobook-overdrive',
            'audiobook-mp3',
        ],
        'video' => [
            'video-streaming',
        ],
    ];

    public function __construct()
    {
        if (isset(self::$instance)) {
            return;
        }

        self::$instance = $this;

        $this->config = defined('OVERDRIVE_CONFIG') ? OVERDRIVE_CONFIG : [];

        add_action('init', [&$this, 'init']);
        add_action('widgets_init', [&$this, 'widgetsInit']);

        add_filter('option_sidebars_widgets', [&$this, 'legacySidebarConfig']);
        add_filter('option_widget_carousel-overdrive', [&$this, 'legacyWidgetInstance']);
    }

    public function init()
    {
        try {
            $this->odauth = new ODauth($this->config);
            $this->caturl = $this->odauth->caturl;
        } catch (\Exception $e) {
            $this->odauth = null;
        }

        add_shortcode('overdrive_carousel', [&$this, 'odShortcode']);

        add_action('wp_enqueue_scripts', [&$this, 'frontsideEnqueueStylesScripts']);
    }

    public function widgetsInit()
    {
        register_widget(OverdriveCarouselWidget::class);
    }

    /**
     * Widget previously registered as a single widget, add an instance ID
     * so they continue to function correctly
     */
    public function legacySidebarConfig($sidebars)
    {
        foreach ($sidebars as &$sidebar_widgets) {
            if (is_array($sidebar_widgets)) {
                foreach ($sidebar_widgets as &$widget) {
                    if (
                        in_array($widget, ['carousel-overdrive'])
                        && !preg_match('/-\d$/', $widget)
                    ) {
                        $widget = $widget . '-1';
                        break;
                    }
                }
            }
        }

        return $sidebars;
    }

    /**
     * Widget previously registered as a single widget, add a setting for
     * the first instance if one doesn't exist
     */
    public function legacyWidgetInstance($widget_settings)
    {
        if (!isset($widget_settings[1])) {
            $widget_settings[1] = [];
        }

        return $widget_settings;
    }

    public function frontsideEnqueueStylesScripts()
    {
        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

        /**
         * All Coop plugins will include their own copy of flickity, but
         * only the first one actually enqued should be needed/registered.
         * Assuming we keep versions in sync, this shouldn't be an issue.
         */

        /* flickity */
        wp_enqueue_script(
            'flickity',
            plugins_url('/assets/js/flickity.pkgd' . $suffix . '.js', dirname(COOP_OVERDRIVE_PLUGIN_FILE)),
            [
                'jquery',
            ],
            '2.3.0',
            true
        );

        wp_enqueue_script(
            'flickity-fade',
            plugins_url('/assets/js/flickity-fade.js', dirname(COOP_OVERDRIVE_PLUGIN_FILE)),
            [
                'flickity',
            ],
            '1.0.0',
            true
        );

        wp_register_style(
            'flickity',
            plugins_url('/assets/css/flickity' . $suffix . '.css', dirname(COOP_OVERDRIVE_PLUGIN_FILE)),
            [],
            '2.3.0'
        );

        wp_register_style(
            'flickity-fade',
            plugins_url('/assets/css/flickity-fade.css', dirname(COOP_OVERDRIVE_PLUGIN_FILE)),
            ['flickity'],
            '1.0.0'
        );

        wp_enqueue_style(
            'coop-overdrive',
            plugins_url('/assets/css/overdrive.css', COOP_OVERDRIVE_PLUGIN_FILE),
            [
                'flickity',
                'flickity-fade'
            ],
            get_plugin_data(COOP_OVERDRIVE_PLUGIN_FILE, false, false)['Version']
        );
    }

    private function handleFormats($formats = '')
    {
        // Make sure we have a nice clean array of possible formats
        $formats = explode(',', $formats);
        $formats = array_filter(array_filter($formats, 'trim'));

        // Expand our meta-formats
        foreach ($formats as $id => $format) {
            if (in_array($format, array_keys(self::FORMATS), true)) {
                unset($formats[$id]);
                $formats = array_merge($formats, self::FORMATS[$format]);
            }
        }

        // Only allow the defined formats
        $formats = array_filter($formats, function ($format) {
            return in_array($format, array_merge([], ...array_values(self::FORMATS)));
        });

        // Back to a string
        return implode(',', $formats);
    }

    public function getProducts($cover_count = 20, $formats = '')
    {
        $products = [];

        if (empty($this->odauth)) {
            return $products;
        }

        // Get only allowed formats
        $formats = $this->handleFormats($formats);

        // MD5 to keep our transients a fixed length
        $formats_key = md5($formats);

        $products = get_site_transient("{$this->transient_key}_{$this->odauth->province}_$formats_key");

        if (empty($products)) {
            // If the transient does not exist or is expired, refresh the data
            $products = $this->odauth->getNewestN($cover_count, $formats);

            if (!empty($products)) {
                // Just store the data we're going to use
                $products = array_map(function ($product) {
                    return [
                        'title' => $product['title'] ?? '',
                        'author' => $product['primaryCreator']['name'] ?? '',
                        'link' => $product['contentDetails'][0]['href'],
                        'image' => $product['images']['cover150Wide']['href'],
                    ];
                }, $products);

                set_site_transient(
                    "{$this->transient_key}_{$this->odauth->province}_$formats_key",
                    $products,
                    DAY_IN_SECONDS
                );
            }
        }

        return (array) $products;
    }

    public function odShortcode($atts)
    {
        // If there's an old saved dwell time that's too short, pad it out
        $dwell = (int) get_option('coop-od-dwell', '4000');
        $dwell += ($dwell < 1000) ? 2000 : 0;

        extract(shortcode_atts([
            'cover_count' => (int) get_option('coop-od-covers', '20'),
            'dwell' => $dwell,
            'formats' => '',
        ], $atts));

        $products = $this->getProducts($cover_count, $formats);

        $flickity_options = [
            'autoPlay' => $dwell,
            'wrapAround' => true,
            'pageDots' => false,
            'fade' => true,
            'imagesLoaded' => true,
            'lazyLoad' => 2,
        ];
        $flickity_options = json_encode($flickity_options);

        ob_start();

        require dirname(COOP_OVERDRIVE_PLUGIN_FILE) . '/inc/views/shortcode.php';

        return ob_get_clean();
    }
}
