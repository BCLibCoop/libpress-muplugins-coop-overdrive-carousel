<?php

namespace BCLibCoop\OverdriveCarousel;

use BCLibCoop\CoopHighlights\CoopHighlights;

class OverdriveCarousel
{
    public static $instance;
    public $config = ['libID' => ''];
    protected $all_configs = [];

    public const TRANSIENT_KEY = 'coop_overdrive';

    public const FORMATS = [
        'ebook' => [
            'ebook-overdrive',
            'ebook-mediado',
        ],
        'magazine' => [
            'magazine-overdrive',
        ],
        'audiobook' => [
            'audiobook-overdrive',
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

        add_shortcode('overdrive_carousel', [$this, 'odShortcode']);

        add_action('widgets_init', [$this, 'widgetsInit']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueShortcodeAssets']);
    }

    /**
     * Set the appropriate province config from the sitka shortname, matching
     * on the first character
     */
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

    private function shouldEnqueueAssets()
    {
        global $post;

        return (!empty($post) && has_shortcode($post->post_content, 'overdrive_carousel'))
            || (is_front_page() && has_shortcode(CoopHighlights::allHighlightsContent(), 'sitka_carousel'));
    }

    public function enqueueShortcodeAssets()
    {
        if ($this->shouldEnqueueAssets()) {
            $this->frontsideEnqueueStylesScripts();
        }
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
            plugins_url('/assets/js/flickity.pkgd' . $suffix . '.js', COOP_OVERDRIVE_PLUGIN_FILE),
            [
                'jquery',
            ],
            '2.3.0-accessible',
            ['strategy' => 'defer']
        );

        wp_enqueue_script(
            'flickity-fade',
            plugins_url('/assets/js/flickity-fade.js', COOP_OVERDRIVE_PLUGIN_FILE),
            [
                'flickity',
            ],
            '1.0.0',
            ['strategy' => 'defer']
        );

        wp_enqueue_style(
            'flickity',
            plugins_url('/assets/css/flickity' . $suffix . '.css', COOP_OVERDRIVE_PLUGIN_FILE),
            [],
            '2.3.0-accessible'
        );

        wp_enqueue_style(
            'flickity-fade',
            plugins_url('/assets/css/flickity-fade.css', COOP_OVERDRIVE_PLUGIN_FILE),
            ['flickity'],
            '1.0.0'
        );
        /**
         * Add support for native inlining
         *
         * @see wp_maybe_inline_styles()
         */
        wp_style_add_data('flickity-fade', 'path', dirname(COOP_OVERDRIVE_PLUGIN_FILE) . '/assets/css/flickity-fade.css');

        wp_enqueue_style(
            'coop-overdrive',
            plugins_url('/assets/css/overdrive.css', COOP_OVERDRIVE_PLUGIN_FILE),
            [
                'flickity',
                'flickity-fade'
            ],
            get_plugin_data(COOP_OVERDRIVE_PLUGIN_FILE, false, false)['Version']
        );
        wp_style_add_data('coop-overdrive', 'path', dirname(COOP_OVERDRIVE_PLUGIN_FILE) . '/assets/css/overdrive.css');
    }

    /**
     * Filter formats from the shortcode to only those we have defined as valid
     */
    private function handleFormats($formats = '')
    {
        // Make sure we have a nice clean array of possible formats
        if (!is_array($formats)) {
            $formats = explode(',', $formats);
            $formats = array_filter(array_filter($formats, 'trim'));
        }

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
     * Retrieve the list of most recent products/titles, caching where possible
     */
    public function getProducts($cover_count = 20, $formats = '')
    {
        // Get only allowed formats
        $formats = $this->handleFormats($formats);

        // MD5 to keep our transients a fixed length
        $formats_key = md5($formats);
        $transient_key = self::TRANSIENT_KEY . "_{$this->config['libID']}_$formats_key";

        $products = get_site_transient($transient_key);

        /**
         * Specific check for false so we can tell an empty array from no cached data
         */
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
                $products = [];
                $transient_time = MINUTE_IN_SECONDS;
            }

            set_site_transient($transient_key, $products, $transient_time);
        }

        return array_filter((array) $products);
    }

    public function odShortcode($atts = [])
    {
        // If there's an old saved dwell time that's too short, pad it out
        $dwell = (int) get_option('coop-od-dwell', '4000');
        $dwell += ($dwell < 1000) ? 2000 : 0;

        $atts = shortcode_atts(
            [
                'cover_count' => (int) get_option('coop-od-covers', 20),
                'dwell' => $dwell,
                'formats' => '',
            ],
            array_change_key_case((array) $atts, CASE_LOWER),
            'overdrive_carousel'
        );

        return $this->render($atts);
    }

    public function render($atts)
    {
        $products = $this->getProducts($atts['cover_count'], $atts['formats']);
        $atts['transition'] = $atts['transition'] ?? 'fade';

        $flickity_options = json_encode([
            'autoPlay' => $atts['dwell'],
            'wrapAround' => true,
            'pageDots' => false,
            'groupCells' => ($atts['transition'] !== 'swipe'),
            'fade' => ($atts['transition'] !== 'swipe'),
        ]);

        ob_start();
        require dirname(COOP_OVERDRIVE_PLUGIN_FILE) . '/inc/views/shortcode.php';
        return ob_get_clean();
    }
}
