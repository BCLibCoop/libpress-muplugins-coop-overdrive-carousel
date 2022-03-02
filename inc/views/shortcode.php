<div class="carousel-container">
    <?php if (!empty($products)) : ?>
        <div class="carousel-viewport">
            <ul class="carousel-tray">
                <?php foreach ($products as $product) : ?>
                    <li class="carousel-item">
                        <a href="<?= $product->contentDetails[0]->href ?>">
                            <img alt="" src="<?= $product->images->cover150Wide->href ?>">
                            <div class="carousel-item-assoc">
                                <span class="carousel-item-title"><?= $product->title ?? '' ?></span>
                                <br/>
                                <span class="carousel-item-author"><?= $product->primaryCreator->name ?? '' ?></span>
                            </div><!-- .carousel-item-assoc -->
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul><!-- .carousel-tray -->
        </div><!-- .carousel-viewport -->

        <div class="carousel-button-box">
            <a class="carousel-buttons prev" href="#">left</a>
            <a class="carousel-buttons next" href="#">right</a>
        </div><!-- .carousel-button-box -->
    <?php else : ?>
        <!-- No OD products returned -->
    <?php endif; ?>
</div><!-- .carousel-container -->

<script>
    <?php if (!empty($products)) : ?>
        jQuery().ready(function($) {
            $(".carousel-container").tinycarousel({
                display: 1,
                controls: true,
                interval: true,
                intervalTime: '<?= $dwell ?>',
                duration:     '<?= $transition ?>',
            })
        });
    <?php endif; ?>

    <?php if (!empty($data['msg'])) : ?>
        console.log('<?= sanitize_text_field($data['msg']) ?>');
    <?php endif; ?>
</script>
