<div class="overdrive-carousel-container">
    <?php if (!empty($products)) : ?>
        <div class="overdrive-carousel" data-flickity='<?= $flickity_options ?>'>
            <?php foreach ($products as $index => $product) : ?>
                <div class="carousel-item">
                    <a href="<?= esc_url($product['link']) ?>">
                        <div class="carousel-item-cover">
                            <img alt="" src="<?= $product['image'] ?>" width="150" class="carousel-item-image" decoding="async" <?= $index == 0 ? '' : 'loading="lazy"' ?>>
                        </div>
                        <div class="carousel-item-info">
                            <span class="carousel-item-title"><?= esc_html($product['title']) ?></span>
                            <br>
                            <span class="carousel-item-author"><?= esc_html($product['author']) ?></span>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else : ?>
        <!-- Could not find any carousel items to display -->
    <?php endif; ?>
</div>
