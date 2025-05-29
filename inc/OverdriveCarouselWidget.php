<?php

namespace BCLibCoop\OverdriveCarousel;

class OverdriveCarouselWidget extends \WP_Widget
{
    /**
     * Sets up a new OverDrive Carousel widget instance.
     *
     * @since 2.8.0
     */
    public function __construct()
    {
        parent::__construct(
            'carousel-overdrive',
            'OverDrive Carousel',
            [
                'description' => 'Displays the selected OverDrive carousel',
            ]
        );
    }

    private function is_block_preview()
    {
        return (defined('IFRAME_REQUEST') && IFRAME_REQUEST && !empty($_GET['legacy-widget-preview'])) || wp_is_rest_endpoint();
    }

    /**
     * Display the settings form
     *
     * @param array $instance Current settings.
     */
    public function form($instance)
    {
        $coop_od_title = get_option('coop-od-title', 'Fresh eBooks & audioBooks');
        $coop_od_covers = get_option('coop-od-covers', 20);
        $coop_od_dwell = 4000;

        $title = isset($instance['title']) ? esc_attr($instance['title']) : $coop_od_title;
        $formats = isset($instance['formats']) ? absint($instance['formats']) : 'all';
        $number = isset($instance['cover_count']) ? absint($instance['cover_count']) : $coop_od_covers;
        $dwell = isset($instance['dwell']) ? absint($instance['dwell']) : $coop_od_dwell;

        $format_options = array_merge(['all'], array_keys(OverdriveCarousel::FORMATS));
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
        </p>

        <p class="description">If set, will link to the OverDrive Homepage</p>

        <p>
            <label for="<?= $this->get_field_id('formats'); ?>"><?php _e('Format to display:'); ?></label>
            <select class="widefat" id="<?= $this->get_field_id('formats'); ?>" name="<?= $this->get_field_name('formats'); ?>">
                <?php foreach ($format_options as $format_option) : ?>
                    <option value="<?= esc_attr($format_option); ?>" <?php selected($formats, $format_option); ?>>
                        <?= esc_html(ucwords($format_option)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="<?php echo $this->get_field_id('cover_count'); ?>"><?php _e('Number of covers to show:'); ?></label>
            <input class="tiny-text" id="<?php echo $this->get_field_id('cover_count'); ?>" name="<?php echo $this->get_field_name('cover_count'); ?>" type="number" step="1" min="1" value="<?php echo $number; ?>" size="3" />
        </p>

        <p>
            <label for="<?php echo $this->get_field_id('dwell'); ?>"><?php _e('Dwell time (ms):'); ?></label>
            <input class="tiny-text" id="<?php echo $this->get_field_id('dwell'); ?>" name="<?php echo $this->get_field_name('dwell'); ?>" type="number" step="1" min="1" value="<?php echo $dwell; ?>" size="5" />
        </p>
        <?php
    }

    /**
     * Updates instance settings
     *
     * @param array $new_instance New settings for this instance as input by the user via
     *                            WP_Widget::form().
     * @param array $old_instance Old settings for this instance.
     * @return array Updated settings to save.
     */
    public function update($new_instance, $old_instance)
    {
        $instance = $old_instance;
        $instance['title'] = sanitize_text_field($new_instance['title']);
        $instance['cover_count'] = (int) $new_instance['cover_count'];
        $instance['dwell'] = (int) $new_instance['dwell'];
        $instance['formats'] = sanitize_text_field($new_instance['formats']);

        return $instance;
    }

    /**
     * Outputs the widget
     *
     * @param array $args     Display arguments including 'before_title', 'after_title',
     *                        'before_widget', and 'after_widget'.
     * @param array $instance Settings for the current Recent Posts widget instance.
     */
    public function widget($args, $instance)
    {
        OverdriveCarousel::$instance->frontsideEnqueueStylesScripts();

        $coop_od_covers = absint(get_option('coop-od-covers', 20));
        $coop_od_dwell = 4000;

        $title = ! empty($instance['title']) ? $instance['title'] : '';
        $instance['cover_count'] = ! empty($instance['cover_count']) ? absint($instance['cover_count']) : $coop_od_covers;
        $instance['dwell'] = ! empty($instance['dwell']) ? absint($instance['dwell']) : $coop_od_dwell;
        $instance['formats'] = ! empty($instance['formats']) ? $instance['formats'] : '';

        // Output widget

        echo $args['before_widget'];

        if (!empty($title)) {
            echo sprintf(
                '%s<a href="%s">%s</a>%s',
                $args['before_title'],
                OverdriveCarousel::$instance->config['caturl'],
                $title,
                $args['after_title']
            );
        }

        $widget = OverdriveCarousel::$instance->render($instance);

        echo $widget;

        if ($this->is_block_preview() && strpos($widget, '<!-- Could not find') !== false) {
            echo '<code>Unable to find any slides, please check the carousel settings.</code>';
        }

        echo $args['after_widget'];
    }
}
