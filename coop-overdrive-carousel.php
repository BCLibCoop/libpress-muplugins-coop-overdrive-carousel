<?php

/**
 * Coop OverDrive Carousel Widget
 *
 * Carousel of new titles on OverDrive
 *
 * PHP Version 7
 *
 * @package           BCLibCoop\OverdriveCarousel
 * @author            Erik Stainsby <eric.stainsby@roaringsky.ca>
 * @author            Jon Whipple <jon.whipple@roaringsky.ca>
 * @author            Jonathan Schatz <jonathan.schatz@bc.libraries.coop>
 * @author            Sam Edwards <sam.edwards@bc.libraries.coop>
 * @copyright         2013-2022 BC Libraries Cooperative
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Coop OverDrive Carousel Widget
 * Description:       Carousel of new titles on OverDrive
 * Version:           3.0.0
 * Network:           true
 * Requires at least: 5.2
 * Requires PHP:      7.0
 * Author:            BC Libraries Cooperative
 * Author URI:        https://bc.libraries.coop
 * Text Domain:       coop-overdrive-carousel
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

namespace BCLibCoop;

class OverdriveCarousel
{
    public static $instance;
    protected $odauth;
    protected $config;
    public $caturl;

    public function __construct()
    {
        if (isset(self::$instance)) {
            return;
        }

        self::$instance = $this;

        $this->config = defined('OVERDRIVE_CONFIG') ? \OVERDRIVE_CONFIG : [];

        add_action('init', [&$this, 'init']);
        add_action('widgets_init', [&$this, 'widgetsInit']);

        add_filter('option_sidebars_widgets', [&$this, 'legacySidebarConfig']);
        add_filter('option_widget_carousel-overdrive', [&$this, 'legacyWidgetInstance']);
    }

    public function init()
    {
        // Prepare the ODauth dependency.
        require_once 'inc/ODauth.php';

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
        require 'inc/OverdriveCarouselWidget.php';

        register_widget(__NAMESPACE__ . '\OverdriveCarouselWidget');
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
                        && ! preg_match('/-\d$/', $widget)
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
            plugins_url('/assets/js/flickity.pkgd' . $suffix . '.js', dirname(__FILE__)),
            [
                'jquery',
            ],
            '2.3.0',
            true
        );

        wp_enqueue_script(
            'flickity-fade',
            plugins_url('/assets/js/flickity-fade.js', dirname(__FILE__)),
            [
                'flickity',
            ],
            '1.0.0',
            true
        );

        wp_register_style(
            'flickity',
            plugins_url('/assets/css/flickity' . $suffix . '.css', dirname(__FILE__)),
            [],
            '2.3.0'
        );

        wp_register_style(
            'flickity-fade',
            plugins_url('/assets/css/flickity-fade.css', dirname(__FILE__)),
            ['flickity'],
            '1.0.0'
        );

        wp_enqueue_style(
            'coop-overdrive',
            plugins_url('/assets/css/overdrive.css', __FILE__),
            [
                'flickity',
                'flickity-fade'
            ],
            get_plugin_data(__FILE__, false, false)['Version']
        );
    }

    public function getProducts($cover_count = 20)
    {
        if (empty($this->odauth)) {
            return [
                'products' => [],
                'msg' => 'could not load odAuth library',
            ];
        }

        $products = get_site_transient('coop_overdrive_daily_results_' . $this->odauth->province);

        if (!empty($products)) {
            return [
                'products' => $products,
                'msg' => "Currently using CACHED OD DATA for {$this->odauth->province}",
            ];
        }

        // If the transient does not exist or is expired, refresh the data

        /**
         * Start making OverDrive API calls:
         * 1. Generate token
         * 2. Use token to get_product_link
         * 3. Use both to grab covers and data
         */
        $token = $this->odauth->getToken();
        $link = $this->odauth->getProductLink($token);
        $products = $this->odauth->getNewestN($token, $link, $cover_count);

        if (!empty($products)) {
            set_site_transient('coop_overdrive_daily_results_' . $this->odauth->province, $products, DAY_IN_SECONDS);

            return [
                'products' => $products,
                'msg' => "Transient OD DATA EXPIRED for {$this->odauth->province} and we made an API call.",
            ];
        } else {
            return [
                'products' => [],
                'msg' => 'Transient expired, unable to load any cover data for ' . $this->odauth->province,
            ];
        }
    }

    public function odShortcode($atts)
    {
        // If there's an old saved dwell time that's too short, pad it out
        $dwell = (int) get_option('coop-od-dwell', '4000');
        $dwell += ($dwell < 1000) ? 2000 : 0;

        extract(shortcode_atts([
            'cover_count' => (int) get_option('coop-od-covers', '20'),
            'dwell' => $dwell,
        ], $atts));

        $data = $this->getProducts($cover_count);
        $products = $data['products'];

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

        require 'inc/views/shortcode.php';

        return ob_get_clean();
    }
}

defined('ABSPATH') || die(-1);

new OverdriveCarousel();
