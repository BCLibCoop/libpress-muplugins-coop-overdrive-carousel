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
 * Version:           3.1.0
 * Network:           true
 * Requires at least: 5.2
 * Requires PHP:      7.0
 * Author:            BC Libraries Cooperative
 * Author URI:        https://bc.libraries.coop
 * Text Domain:       coop-overdrive-carousel
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

namespace BCLibCoop\OverdriveCarousel;

/**
 * Require Composer autoloader if installed on it's own
 */
if (file_exists($composer = __DIR__ . '/vendor/autoload.php')) {
    require_once $composer;
}

defined('ABSPATH') || die(-1);

define('COOP_OVERDRIVE_PLUGIN_FILE', __FILE__);

add_action('plugins_loaded', function () {
    new OverdriveCarousel();
});
