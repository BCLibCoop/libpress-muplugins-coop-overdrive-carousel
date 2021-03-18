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
 * @copyright         2013-2021 BC Libraries Cooperative
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Coop OverDrive Carousel Widget
 * Description:       Carousel of new titles on OverDrive
 * Version:           1.0.0
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
    private static $instance;
    protected $odauth;

    public function __construct()
    {
        if (isset(self::$instance)) {
            return;
        }

        self::$instance = $this;

        add_action('init', [&$this, 'init'], 24);
    }

    public function init()
    {
        // Prepare the ODauth dependency.
        require_once 'inc/ODauth.php';
        $this->odauth = new ODauth();

        add_shortcode('overdrive_carousel', [&$this, 'odShortcode']);

        wp_register_sidebar_widget(
            'carousel-overdrive',
            'OverDrive Carousel',
            [&$this, 'odWidget']
        );

        wp_register_widget_control(
            'carousel-overdrive',
            'OverDrive Carousel',
            [&$this, 'odWidgetControl']
        );

        add_action('wp_enqueue_scripts', [&$this, 'frontsideEnqueueStylesScripts']);
        add_action('admin_enqueue_scripts', [&$this, 'adminEnqueueStylesScripts']);
    }

    public function frontsideEnqueueStylesScripts()
    {
        wp_enqueue_style('coop-overdrive', plugins_url('/css/overdrive.css', __FILE__));
        wp_enqueue_script('tinycarousel', plugins_url('/js/tinycarousel.js', __FILE__), ['jquery'], false, true);
    }

    public function adminEnqueueStylesScripts($hook)
    {
        if ($hook !== 'widgets.php') {
            return;
        }

        wp_enqueue_style('coop-overdrive-admin', plugins_url('/css/overdrive-admin.css', __FILE__));
        wp_enqueue_script('coop-overdrive-admin-js', plugins_url('/js/overdrive-admin.js', __FILE__), ['jquery']);
    }

    public function getCovers($cover_count = 20)
    {
        if (empty($this->odauth)) {
            wp_die('no OD auth library found');
        }

        /**
         * Start making OverDrive API calls:
         * 1. Generate token
         * 2. Use token to get_product_link
         * 3. Use both to grab covers and data
         */

        $data = [
            'covers' => get_transient('coop_overdrive_daily_results_' . $this->odauth->province),
            'msg' => "Currently using CACHED OD DATA for {$this->odauth->province}",
        ];

        // If the transient does not exist or is expired, refresh the data
        if (empty($data['covers'])) {
            $token = $this->odauth->getToken();
            $link = $this->odauth->getProductLink($token);
            $newest_data = $this->odauth->getNewestN($token, $link, $cover_count);

            $data['msg'] = "Transient OD DATA EXPIRED for {$this->odauth->province} and we made an API call.";
            set_transient('coop_overdrive_daily_results_' . $this->odauth->province, $newest_data, WEEK_IN_SECONDS);
        }

        return $data;
    }

    public function odShortcode($atts)
    {
        extract(shortcode_atts([], $atts));

        $cover_count = (int) get_option('coop-od-covers', '20');
        $dwell = 800;
        $transition = 400;

        $data = $this->getCovers($cover_count);

        $out = [];
        $out[] = $data['covers'];

        $out[] = '<script type="text/javascript">';
        $out[] = 'jQuery().ready(function($) { ';
        $out[] = '   $(".carousel-container").tinycarousel({ ';
        $out[] = '       display: 1, ';
        $out[] = '       controls: true, ';
        $out[] = '       interval: true, ';
        $out[] = '       intervalTime: ' . $dwell . ', ';
        $out[] = '       duration:     ' . $transition . ' ';
        $out[] = '  }) ';
        $out[] = '}); ';

        if (!empty($data['msg'])) {
            $out[] = "console.log('{$data['msg']}')";
        }

        $out[] = '</script>';

        return implode("\n", $out);
    }


    public function odWidget($args)
    {
        $heading = get_option('coop-od-title', 'Fresh eBooks/Audio');
        $cover_count = (int) get_option('coop-od-covers', '20');
        $dwell = (int) get_option('coop-od-dwell', '800');
        $transition = (int) get_option('coop-od-transition', '400');

        $data = $this->getCovers($cover_count);

        $out = [];

        extract($args);
        /*  widget-declaration:
            id
            name
            before_widget
            after_widget
            before_title
            after_title
        */

        $out[] = $before_widget;

        $out[] = $before_title;
        $out[] = '<a href="//downloads.bclibrary.ca/">';
        $out[] = $heading;
        $out[] = '</a>';
        $out[] = $after_title;

        // returning HTML currently
        $out[] = $data['covers'];

        $out[] = $after_widget;

        $out[] = '<script type="text/javascript">';
        $out[] = 'jQuery().ready(function($) { ';
        $out[] = '   $(".carousel-container").tinycarousel({ ';
        $out[] = '       display: 1, ';
        $out[] = '       controls: true, ';
        $out[] = '       interval: true, ';
        $out[] = '       intervalTime: ' . $dwell . ', ';
        $out[] = '       duration:     ' . $transition . ' ';
        $out[] = '  }) ';
        $out[] = '}); ';

        if (!empty($data['msg'])) {
            $out[] = "console.log('{$data['msg']}')";
        }

        $out[] = '</script>';

        echo implode("\n", $out);
    }

    public function odWidgetControl()
    {
        if (!get_option('coop-od-title')) {
            add_option('coop-od-title', 'Fresh eBooks & audioBooks');
        }

        $coop_od_title = $coop_od_title_new = get_option('coop-od-title');

        if (array_key_exists('coop-od-title', $_POST)) {
            $coop_od_title_new = sanitize_text_field($_POST['coop-od-title']);
        }

        if ($coop_od_title != $coop_od_title_new) {
            $coop_od_title = $coop_od_title_new;
            update_option('coop-od-title', $coop_od_title);
        }

        if (!get_option('coop-od-covers')) {
            add_option('coop-od-covers', 20);
        }

        $coop_od_covers = $coop_od_covers_new = get_option('coop-od-covers');
        if (array_key_exists('coop-od-covers', $_POST)) {
            $coop_od_covers_new = sanitize_text_field($_POST['coop-od-covers']);
        }

        if ($coop_od_covers != $coop_od_covers_new) {
            $coop_od_covers = $coop_od_covers_new;
            update_option('coop-od-covers', $coop_od_covers);
        }

        if (!get_option('coop-od-dwell')) {
            add_option('coop-od-dwell', 800);
        }

        $coop_od_dwell = $coop_od_dwell_new = get_option('coop-od-dwell');

        if (array_key_exists('coop-od-dwell', $_POST)) {
            $coop_od_dwell_new = sanitize_text_field($_POST['coop-od-dwell']);
        }

        if ($coop_od_dwell != $coop_od_dwell_new) {
            $coop_od_dwell = $coop_od_dwell_new;
            update_option('coop-od-dwell', $coop_od_dwell);
        }

        if (!get_option('coop-od-transition')) {
            add_option('coop-od-transition', 400);
        }

        $coop_od_transition = $coop_od_transition_new = get_option('coop-od-transition');
        if (array_key_exists('coop-od-transition', $_POST)) {
            $coop_od_transition_new = sanitize_text_field($_POST['coop-od-transition']);
        }

        if ($coop_od_transition != $coop_od_transition_new) {
            $coop_od_transition = $coop_od_transition_new;
            update_option('coop-od-transition', $coop_od_transition);
        }

        $out = [];

        $out[] = '<p>';
        $out[] = '<label for="coop-od-title">Heading:</label>';
        $out[] = '<input id="coop-od-title" type="text" value="' . $coop_od_title . '" name="coop-od-title">';
        $out[] = '</p>';

        $out[] = '<p>';
        $out[] = '<label for="coop-od-covers">Number of covers:</label>';
        $out[] = '<input id="coop-od-covers" type="text" value="' . $coop_od_covers . '" name="coop-od-covers">';
        $out[] = '</p>';

        $out[] = '<p>';
        $out[] = '<label for="coop-od-dwell">Dwell time (ms):</label>';
        $out[] = '<input id="coop-od-dwell" type="text" value="' . $coop_od_dwell . '" name="coop-od-dwell">';
        $out[] = '</p>';

        $out[] = '<p>';
        $out[] = '<label for="coop-od-transition">Transition time (ms):</label>';
        $out[] = '<input id="coop-od-transition" type="text" value="' . $coop_od_transition
                 . '" name="coop-od-transition">';
        $out[] = '</p>';

        echo implode("\n", $out);
    }
}

defined('ABSPATH') || die(-1);

new OverdriveCarousel();
