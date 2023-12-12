<?php

namespace BCLibCoop\OverdriveCarousel;

use BCLibCoop\CoopHighlights\CoopHighlights;

use function TenUp\AsyncTransients\get_async_transient;
use function TenUp\AsyncTransients\set_async_transient;

class OverdriveCarousel
{
    public static $instance;
    public $config;
    protected $all_configs = [];

    public const TRANSIENT_KEY = 'coop_overdrive';

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

        $this->all_configs = defined('OVERDRIVE_CONFIG') ? OVERDRIVE_CONFIG : [];
        $this->setConfig();

        add_filter('option_sidebars_widgets', [$this, 'legacySidebarConfig']);
        add_filter('option_widget_carousel-overdrive', [$this, 'legacyWidgetInstance']);

        add_shortcode('overdrive_carousel', [$this, 'odShortcode']);
        add_action('widgets_init', [$this, 'widgetsInit']);
        add_action('wp_enqueue_scripts', [$this, 'frontsideEnqueueStylesScripts']);
    }

    private function setConfig()
    {
        // Get province from library shortcode 1st letter
        $shortcode = get_option('_coop_sitka_lib_shortname', '');

        if (preg_match('%(^[A-Z]{1})%', $shortcode, $matches)) {
            $shortcode_prov = $matches[1];

            foreach ($this->all_configs as $province => $config) {
                if ($shortcode_prov === $province[0]) {
                    $this->config = $config;
                    break;
                }
            }
        }
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

    private function shouldEnqueueAssets()
    {
        global $post;

        return (!empty($post) && has_shortcode($post->post_content, 'overdrive_carousel'))
            || (is_front_page() && has_shortcode(CoopHighlights::allHighlightsContent(), 'sitka_carousel'))
            || is_active_widget(false, false, 'carousel-overdrive');
    }

    public function frontsideEnqueueStylesScripts()
    {
        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

        /**
         * All Coop plugins will include their own copy of flickity, but
         * only the first one actually enqued should be needed/registered.
         * Assuming we keep versions in sync, this shouldn't be an issue.
         */
        if ($this->shouldEnqueueAssets()) {
            /* flickity */
            wp_enqueue_script(
                'flickity',
                plugins_url('/assets/js/flickity.pkgd' . $suffix . '.js', COOP_OVERDRIVE_PLUGIN_FILE),
                [
                    'jquery',
                ],
                '2.3.0-accessible',
                true
            );

            wp_enqueue_script(
                'flickity-fade',
                plugins_url('/assets/js/flickity-fade.js', COOP_OVERDRIVE_PLUGIN_FILE),
                [
                    'flickity',
                ],
                '1.0.0',
                true
            );

            wp_register_style(
                'flickity',
                plugins_url('/assets/css/flickity' . $suffix . '.css', COOP_OVERDRIVE_PLUGIN_FILE),
                [],
                '2.3.0-accessible'
            );

            wp_register_style(
                'flickity-fade',
                plugins_url('/assets/css/flickity-fade.css', COOP_OVERDRIVE_PLUGIN_FILE),
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
    }

    /**
     * Filter formats from the shortcode to only those we have defined as valid
     */
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

    /**
     * Retrieve the list of most recent products/titles
     *
     * Not currently using async method, as there's not a straightforward way
     * to have it save a network-wide transient
     */
    public function getProducts($cover_count = 20, $formats = '')
    {
        // Get only allowed formats
        $formats = $this->handleFormats($formats);

        // MD5 to keep our transients a fixed length
        $formats_key = md5($formats);
        $transient_key = self::TRANSIENT_KEY . "_{$this->config['libID']}_$formats_key";

        // $products = get_async_transient(
        //     $transient_key,
        //     [$this, 'realGetProductsAsync'],
        //     [$transient_key, $cover_count, $formats]
        // );
        $products = $this->realGetProducts($transient_key, $cover_count, $formats);

        return array_filter((array) $products);
    }

    /**
     * Do the actual work of getting titles, checking for a cached transient
     */
    public function realGetProducts($transient_key, $cover_count, $formats)
    {
        $products = get_site_transient($transient_key);

        if ($products === false) {
            try {
                $products = (new OverdriveAPI($this->config))->getNewestN($cover_count, $formats);
            } catch (\Exception $e) {
                $products = [];
            }

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

                // If we get results, persist for 1 day
                $transient_time = DAY_IN_SECONDS;
            } else {
                // Otherwise, cache an empty array for a minute so we don't hammer overdrive
                $transient_time = MINUTE_IN_SECONDS;
                $products = [];
            }

            set_site_transient($transient_key, $products, $transient_time);
        }

        return $products;
    }

    /**
     * Version of above to be used with async transients, including locking
     */
    public function realGetProductsAsync($transient_key, $cover_count, $formats)
    {
        if (get_site_transient("{$transient_key}_lock")) {
            return;
        }

        // Set a lock, 5 minutes max
        set_site_transient("{$transient_key}_lock", true, MINUTE_IN_SECONDS * 5);

        // Default to only persisting for 1 minute
        $transient_time = MINUTE_IN_SECONDS;

        try {
            $products = (new OverdriveAPI($this->config))->getNewestN($cover_count, $formats);
        } catch (\Exception $e) {
            $products = [];
        }

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

            // If we get results, persist for 1 day
            $transient_time = DAY_IN_SECONDS;
        }

        // Cache the results
        set_async_transient(
            $transient_key,
            $products,
            $transient_time
        );

        // Delete the lock once we have finished running
        delete_transient("{$transient_key}_lock");
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

        if (!empty($this->config)) {
            $products = $this->getProducts($cover_count, $formats);
        } else {
            $products = [];
        }

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
