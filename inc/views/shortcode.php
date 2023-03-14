<div class="overdrive-carousel-container">
    <?php if (!empty($products)) : ?>
        <div class="overdrive-carousel" data-flickity='<?= $flickity_options ?>'>
            <?php foreach ($products as $product) : ?>
                <div class="carousel-item">
                    <a href="<?= $product['link'] ?>">
                        <div class="carousel-item-cover">
                            <img alt="" data-flickity-lazyload="<?= $product['image'] ?>">
                        </div>
                        <div class="carousel-item-info">
                            <span class="carousel-item-title"><?= $product['title'] ?></span>
                            <br/>
                            <span class="carousel-item-author"><?= $product['author'] ?></span>
                        </div><!-- .carousel-item-assoc -->
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else : ?>
        <!-- Could not find any carousel items to display -->
    <?php endif; ?>
</div>
