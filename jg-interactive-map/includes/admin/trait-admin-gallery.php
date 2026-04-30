<?php
/**
 * Trait: Gallery admin page
 *
 * @package JG_Interactive_Map
 */

trait JG_Map_Admin_Gallery {

    public function render_gallery_page() {
        global $wpdb;
        $points_table = JG_Map_Database::get_points_table();

        // Get all points with images
        $points = $wpdb->get_results(
            "SELECT id, title, images, type, author_id, created_at FROM $points_table
            WHERE status = 'publish' AND images IS NOT NULL AND images != '[]'
            ORDER BY created_at DESC LIMIT 200",
            ARRAY_A
        );

        ?>
        <div class="wrap">
            <?php $this->render_page_header('Galeria wszystkich zdjęć'); ?>

            <div class="jg-card jg-card-body" style="margin-bottom:20px">
                <p style="margin:0"><strong>Łącznie miejsc ze zdjęciami:</strong> <?php echo count($points); ?></p>
            </div>

            <?php if (!empty($points)): ?>
                <div class="jg-card">
                <div class="jg-gallery-grid">
                    <?php foreach ($points as $point):
                        $images = json_decode($point['images'], true);
                        if (empty($images)) continue;

                        $author = get_userdata($point['author_id']);
                        ?>
                        <div class="jg-gallery-card">
                            <div style="position:relative;height:200px;background:#f5f5f5">
                                <img src="<?php echo esc_url($images[0]['thumb'] ?? $images[0]['full']); ?>"
                                     style="width:100%;height:100%;object-fit:cover"
                                     alt="<?php echo esc_attr($point['title']); ?>">
                                <?php if (count($images) > 1): ?>
                                    <span style="position:absolute;top:8px;right:8px;background:rgba(0,0,0,0.7);color:#fff;padding:4px 8px;border-radius:4px;font-size:calc(12 * var(--jg));font-weight:700">
                                        +<?php echo count($images) - 1; ?> zdjęć
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="jg-gallery-card-body">
                                <h3><?php echo esc_html($point['title']); ?></h3>
                                <p>
                                    <strong><?php echo esc_html($point['type']); ?></strong> •
                                    <?php echo $author ? esc_html($author->display_name) : 'Nieznany'; ?> •
                                    <?php echo human_time_diff(strtotime($point['created_at'] . ' UTC'), time()); ?> temu
                                </p>
                                <div style="display:flex;gap:8px;flex-wrap:wrap">
                                    <a href="<?php echo get_site_url(); ?>?jg_view_point=<?php echo $point['id']; ?>"
                                       class="button button-small" target="_blank">Zobacz miejsce</a>
                                    <button class="button button-small jg-view-all-images"
                                            data-images='<?php echo esc_attr(json_encode($images)); ?>'
                                            data-title="<?php echo esc_attr($point['title']); ?>"
                                            data-point-id="<?php echo $point['id']; ?>">
                                        Wszystkie zdjęcia
                                    </button>
                                    <button class="button button-small button-link-delete jg-delete-all-images"
                                            data-point-id="<?php echo $point['id']; ?>"
                                            style="color:#dc2626">
                                        Usuń wszystkie
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                </div>

                <!-- Lightbox modal -->
                <div id="jg-gallery-lightbox" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.9);z-index:9999;align-items:center;justify-content:center;padding:20px">
                    <div style="position:relative;max-width:1200px;width:100%">
                        <button id="jg-gallery-close" style="position:absolute;top:-40px;right:0;background:#fff;border:none;border-radius:4px;padding:8px 16px;cursor:pointer;font-weight:700">✕ Zamknij</button>
                        <h2 id="jg-gallery-title" style="color:#fff;margin-bottom:20px"></h2>
                        <div id="jg-gallery-images" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px"></div>
                    </div>
                </div>

                <script>
                jQuery(document).ready(function($) {
                    var lightbox = $('#jg-gallery-lightbox');
                    var imagesContainer = $('#jg-gallery-images');
                    var titleEl = $('#jg-gallery-title');
                    var currentPointId = null;

                    $('.jg-view-all-images').on('click', function() {
                        var images = $(this).data('images');
                        var title = $(this).data('title');
                        currentPointId = $(this).data('point-id');

                        titleEl.text(title);
                        imagesContainer.empty();

                        images.forEach(function(img, idx) {
                            var container = $('<div>').css({
                                position: 'relative',
                                borderRadius: '8px',
                                overflow: 'hidden'
                            });

                            var deleteBtn = $('<button>')
                                .text('×')
                                .addClass('jg-delete-single-image')
                                .attr('data-point-id', currentPointId)
                                .attr('data-image-index', idx)
                                .css({
                                    position: 'absolute',
                                    top: '8px',
                                    right: '8px',
                                    background: 'rgba(220,38,38,0.9)',
                                    color: '#fff',
                                    border: 'none',
                                    borderRadius: '4px',
                                    width: '32px',
                                    height: '32px',
                                    cursor: 'pointer',
                                    fontWeight: '700',
                                    fontSize: '20px',
                                    zIndex: 10
                                })
                                .attr('title', 'Usuń zdjęcie');

                            container.append(deleteBtn);
                            container.append(
                                $('<a>').attr({
                                    href: img.full,
                                    target: '_blank'
                                }).css({
                                    display: 'block'
                                }).append(
                                    $('<img>').attr('src', img.thumb || img.full).css({
                                        width: '100%',
                                        height: '250px',
                                        objectFit: 'cover',
                                        display: 'block'
                                    })
                                )
                            );

                            imagesContainer.append(container);
                        });

                        lightbox.css('display', 'flex');
                    });

                    // Delete single image
                    $(document).on('click', '.jg-delete-single-image', function(e) {
                        e.preventDefault();
                        e.stopPropagation();

                        if (!confirm('Czy na pewno chcesz usunąć to zdjęcie?')) {
                            return;
                        }

                        var btn = $(this);
                        var pointId = btn.data('point-id');
                        var imageIndex = btn.data('image-index');

                        btn.prop('disabled', true).text('...');

                        $.post(ajaxurl, {
                            action: 'jg_delete_image',
                            point_id: pointId,
                            image_index: imageIndex,
                            _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                        }, function(response) {
                            if (response.success) {
                                alert('Zdjęcie usunięte');
                                location.reload();
                            } else {
                                alert('Błąd: ' + (response.data.message || 'Nie udało się usunąć'));
                                btn.prop('disabled', false).text('×');
                            }
                        });
                    });

                    // Delete all images
                    $('.jg-delete-all-images').on('click', function(e) {
                        e.preventDefault();

                        if (!confirm('Czy na pewno chcesz usunąć WSZYSTKIE zdjęcia z tego miejsca? Tej operacji nie można cofnąć!')) {
                            return;
                        }

                        var btn = $(this);
                        var pointId = btn.data('point-id');

                        btn.prop('disabled', true).text('Usuwanie...');

                        // Delete images one by one from the end
                        function deleteNextImage(index) {
                            if (index < 0) {
                                alert('Wszystkie zdjęcia zostały usunięte');
                                location.reload();
                                return;
                            }

                            $.post(ajaxurl, {
                                action: 'jg_delete_image',
                                point_id: pointId,
                                image_index: index,
                                _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                            }, function(response) {
                                if (response.success) {
                                    // Continue with next image (always delete index 0 since array shrinks)
                                    deleteNextImage(index - 1);
                                } else {
                                    alert('Błąd: ' + (response.data.message || 'Nie udało się usunąć'));
                                    btn.prop('disabled', false).text('Usuń wszystkie');
                                }
                            });
                        }

                        // Start from the last image
                        $.get(ajaxurl, {
                            action: 'jg_get_points'
                        }, function(response) {
                            if (response.success && response.data) {
                                var point = response.data.find(function(p) { return p.id == pointId; });
                                if (point && point.images) {
                                    deleteNextImage(point.images.length - 1);
                                }
                            }
                        });
                    });

                    $('#jg-gallery-close, #jg-gallery-lightbox').on('click', function(e) {
                        if (e.target === this) {
                            lightbox.hide();
                        }
                    });
                });
                </script>
            <?php else: ?>
                <p>Brak miejsc ze zdjęciami.</p>
            <?php endif; ?>
        </div>
        <?php
    }
}
