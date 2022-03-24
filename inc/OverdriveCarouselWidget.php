<?php

namespace BCLibCoop;

class OverdriveCarouselWidget extends \WP_Widget
{
    public function __construct()
    {
        parent::__construct(
            'carousel-overdrive',
            'OverDrive Carousel',
        );
    }

    public function form($instance)
    {
        $coop_od_title = get_option('coop-od-title', 'Fresh eBooks & audioBooks');

        if (array_key_exists('coop-od-title', $_POST)) {
            $coop_od_title_new = sanitize_text_field($_POST['coop-od-title']);

            if ($coop_od_title != $coop_od_title_new) {
                $coop_od_title = $coop_od_title_new;
                update_option('coop-od-title', $coop_od_title);
            }
        }

        $coop_od_covers = get_option('coop-od-covers', '20');

        if (array_key_exists('coop-od-covers', $_POST)) {
            $coop_od_covers_new = sanitize_text_field($_POST['coop-od-covers']);

            if ($coop_od_covers != $coop_od_covers_new) {
                $coop_od_covers = $coop_od_covers_new;
                update_option('coop-od-covers', $coop_od_covers);
            }
        }


        $coop_od_dwell = get_option('coop-od-dwell', '4000');

        if (array_key_exists('coop-od-dwell', $_POST)) {
            $coop_od_dwell_new = sanitize_text_field($_POST['coop-od-dwell']);

            if ($coop_od_dwell != $coop_od_dwell_new) {
                $coop_od_dwell = $coop_od_dwell_new;
                update_option('coop-od-dwell', $coop_od_dwell);
            }
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

        echo implode("\n", $out);
    }

    public function widget($args, $instance)
    {
        // If there's an old saved dwell time that's too short, pad it out
        $dwell = (int) get_option('coop-od-dwell', '4000');
        $dwell += ($dwell < 1000) ? 2000 : 0;

        extract(shortcode_atts([
            'heading' => get_option('coop-od-title', 'Fresh eBooks/Audio'),
            'cover_count' => (int) get_option('coop-od-covers', '20'),
            'dwell' => $dwell,
        ], $instance));

        $data = OverdriveCarousel::$instance->getProducts($cover_count);
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

        extract($args);
        /*  widget-declaration:
            id
            name
            before_widget
            after_widget
            before_title
            after_title
        */

        echo $before_widget;

        echo sprintf(
            '%s<a href="%s">%s</a>%s',
            $before_title,
            OverdriveCarousel::$instance->caturl,
            $heading,
            $after_title
        );

        require 'views/shortcode.php';

        echo $after_widget;
    }
}
