<div class="overdrive-carousel" data-flickity='<?= $flickity_options ?>'>
    <?php if (!empty($products)) : ?>
        <?php foreach ($products as $product) : ?>
            <div class="carousel-item">
                <a href="<?= $product->contentDetails[0]->href ?>">
                    <img alt="" src="<?= $product->images->cover150Wide->href ?>">
                    <div class="carousel-item-assoc">
                        <span class="carousel-item-title"><?= $product->title ?? '' ?></span>
                        <br/>
                        <span class="carousel-item-author"><?= $product->primaryCreator->name ?? '' ?></span>
                    </div><!-- .carousel-item-assoc -->
                </a>
            </div>
        <?php endforeach; ?>
    <?php else : ?>
        <!-- No OD products returned -->
    <?php endif; ?>
</div><!-- .carousel-container -->
