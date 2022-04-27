<div class="overdrive-carousel-container">
    <div class="overdrive-carousel" data-flickity='<?= $flickity_options ?>'>
        <?php foreach ($products as $product) : ?>
            <div class="carousel-item">
                <a href="<?= $product->contentDetails[0]->href ?>">
                    <div class="carousel-item-cover">
                        <img alt="" data-flickity-lazyload="<?= $product->images->cover150Wide->href ?>">
                    </div>
                    <div class="carousel-item-info">
                        <span class="carousel-item-title"><?= $product->title ?? '' ?></span>
                        <br/>
                        <span class="carousel-item-author"><?= $product->primaryCreator->name ?? '' ?></span>
                    </div><!-- .carousel-item-assoc -->
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>
