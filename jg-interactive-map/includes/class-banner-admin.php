<?php
/**
 * Banner Admin Panel
 * Manages banner CRUD operations and displays statistics
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class JG_Map_Banner_Admin {

    /**
     * Initialize admin panel
     */
    public static function init() {
        // Add admin menu
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));

        // Handle form submissions
        add_action('admin_post_jg_save_banner', array(__CLASS__, 'handle_save_banner'));
        add_action('admin_post_jg_delete_banner', array(__CLASS__, 'handle_delete_banner'));
        add_action('admin_post_jg_toggle_banner', array(__CLASS__, 'handle_toggle_banner'));
        add_action('admin_post_jg_export_banner_stats', array(__CLASS__, 'handle_export_banner_stats'));
        add_action('admin_post_jg_save_banner_settings', array(__CLASS__, 'handle_save_banner_settings'));

        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_assets'));
    }

    /**
     * Add admin menu item
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'jg-map-places',
            'Banery reklamowe',
            'Banery 728x90',
            'manage_options',
            'jg-map-banners',
            array(__CLASS__, 'render_admin_page')
        );
    }

    /**
     * Enqueue admin assets
     */
    public static function enqueue_admin_assets($hook) {
        if ($hook !== 'jg-map_page_jg-map-banners') {
            return;
        }

        // Enqueue WordPress media uploader
        wp_enqueue_media();

        // Enqueue custom admin script (with timestamp to force cache refresh)
        wp_enqueue_script(
            'jg-banner-admin',
            JG_MAP_PLUGIN_URL . 'assets/js/jg-banner-admin.js',
            array('jquery', 'media-upload', 'media-views'),
            JG_MAP_VERSION . '-' . time(),
            true
        );

        // Add custom admin styles
        wp_add_inline_style('wp-admin', self::get_admin_styles());
    }

    /**
     * Get custom admin styles
     */
    private static function get_admin_styles() {
        return <<<'CSS'
        .jg-banner-admin-wrap {
            background: #fff;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .jg-banner-list {
            margin-top: 30px;
        }
        .jg-banner-item {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            margin-bottom: 15px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        .jg-banner-item.inactive {
            opacity: 0.6;
            background: #f0f0f0;
        }
        .jg-banner-preview {
            flex-shrink: 0;
            width: 200px;
            height: 25px;
            border: 1px solid #ccc;
            overflow: hidden;
            background: #fff;
        }
        .jg-banner-preview img {
            width: 100%;
            height: auto;
            display: block;
        }
        .jg-banner-info {
            flex-grow: 1;
        }
        .jg-banner-title {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 5px;
        }
        .jg-banner-stats {
            display: flex;
            gap: 20px;
            margin-top: 8px;
            font-size: 13px;
            color: #666;
        }
        .jg-banner-stat {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .jg-banner-stat strong {
            color: #000;
        }
        .jg-banner-actions {
            display: flex;
            gap: 10px;
            flex-shrink: 0;
        }
        .jg-banner-form {
            background: #f9f9f9;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 30px;
        }
        .jg-banner-form table {
            width: 100%;
        }
        .jg-banner-form th {
            text-align: left;
            padding: 10px 10px 10px 0;
            vertical-align: top;
            width: 200px;
        }
        .jg-banner-form td {
            padding: 10px 0;
        }
        .jg-banner-form input[type='text'],
        .jg-banner-form input[type='url'],
        .jg-banner-form input[type='number'],
        .jg-banner-form input[type='datetime-local'] {
            width: 100%;
            max-width: 500px;
        }
        .jg-banner-image-preview {
            margin-top: 10px;
            max-width: 728px;
            border: 1px solid #ddd;
            padding: 10px;
            background: #fff;
        }
        .jg-banner-image-preview img {
            max-width: 100%;
            height: auto;
            display: block;
        }
        .jg-stats-highlight {
            background: #fff3cd;
            padding: 3px 8px;
            border-radius: 3px;
            font-weight: 600;
        }
        .jg-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .jg-badge-active {
            background: #d1e7dd;
            color: #0f5132;
        }
        .jg-badge-inactive {
            background: #f8d7da;
            color: #842029;
        }
        .jg-banner-section-title {
            font-size: 18px;
            font-weight: 600;
            margin: 30px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #0073aa;
        }
CSS;
    }

    /**
     * Render admin page
     */
    public static function render_admin_page() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień do zarządzania banerami.');
        }

        // Get all banners
        $banners = JG_Map_Banner_Manager::get_all_banners();

        // Check for messages
        $message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';

        ?>
        <div class="wrap">
            <h1>Zarządzanie banerami 728x90</h1>

            <?php if ($message === 'saved') : ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>Baner został zapisany pomyślnie!</strong></p>
                </div>
            <?php elseif ($message === 'deleted') : ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>Baner został usunięty.</strong></p>
                </div>
            <?php elseif ($message === 'toggled') : ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>Status banneru został zmieniony.</strong></p>
                </div>
            <?php elseif ($message === 'error') : ?>
                <div class="notice notice-error is-dismissible">
                    <p><strong>Wystąpił błąd. Spróbuj ponownie.</strong></p>
                </div>
            <?php elseif ($message === 'settings_saved') : ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>Ustawienia zostały zapisane!</strong></p>
                </div>
            <?php endif; ?>

            <div class="jg-banner-admin-wrap" style="margin-bottom:20px;">
                <h2 class="jg-banner-section-title">⚙️ Ustawienia cenowe</h2>
                <?php self::render_settings_form(); ?>
            </div>

            <div class="jg-banner-admin-wrap">
                <h2 class="jg-banner-section-title">➕ Dodaj nowy baner</h2>
                <?php self::render_banner_form(); ?>

                <h2 class="jg-banner-section-title">📊 Lista banerów</h2>
                <?php if (empty($banners)) : ?>
                    <p>Nie dodano jeszcze żadnych banerów. Użyj formularza powyżej, aby dodać pierwszy baner.</p>
                <?php else : ?>
                    <div class="jg-banner-list">
                        <?php foreach ($banners as $banner) : ?>
                            <?php self::render_banner_item($banner); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render banner form (add new)
     */
    private static function render_banner_form($banner = null) {
        $is_edit = !empty($banner);
        $banner_id = $is_edit ? $banner['id'] : 0;
        $title = $is_edit ? esc_attr($banner['title']) : '';
        $image_url = $is_edit ? esc_url($banner['image_url']) : '';
        $link_url = $is_edit ? esc_url($banner['link_url']) : '';
        $impressions_bought = $is_edit ? intval($banner['impressions_bought']) : 0;
        $active = $is_edit ? intval($banner['active']) : 1;
        $start_date = $is_edit && $banner['start_date'] ? date('Y-m-d\TH:i', strtotime($banner['start_date'])) : '';
        $end_date = $is_edit && $banner['end_date'] ? date('Y-m-d\TH:i', strtotime($banner['end_date'])) : '';
        $display_order = $is_edit ? intval($banner['display_order']) : 0;

        ?>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="jg-banner-form">
            <?php wp_nonce_field('jg_save_banner', 'jg_banner_nonce'); ?>
            <input type="hidden" name="action" value="jg_save_banner">
            <?php if ($is_edit) : ?>
                <input type="hidden" name="banner_id" value="<?php echo $banner_id; ?>">
            <?php endif; ?>

            <table>
                <tr>
                    <th><label for="banner_title">Nazwa banneru *</label></th>
                    <td>
                        <input type="text" id="banner_title" name="banner_title" value="<?php echo $title; ?>" required placeholder="np. Sklep XYZ - Promocja letnia">
                        <p class="description">Nazwa widoczna tylko w panelu admina (dla Twojej organizacji)</p>
                    </td>
                </tr>

                <tr>
                    <th><label for="banner_image_url">Obrazek banneru (728x90) *</label></th>
                    <td>
                        <input type="url" id="banner_image_url" name="banner_image_url" value="<?php echo $image_url; ?>" required placeholder="https://...">
                        <button type="button" id="jg-upload-banner-image" class="button">Wybierz z biblioteki mediów</button>
                        <p class="description">Rozmiar: 728x90 pikseli (leaderboard). Kliknij przycisk aby wybrać z biblioteki.</p>
                        <?php if ($image_url) : ?>
                            <div id="jg-banner-image-preview-container" class="jg-banner-image-preview">
                                <img src="<?php echo $image_url; ?>" alt="Preview">
                            </div>
                        <?php else : ?>
                            <div id="jg-banner-image-preview-container" class="jg-banner-image-preview" style="display:none;"></div>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <th><label for="banner_link_url">Link docelowy *</label></th>
                    <td>
                        <input type="url" id="banner_link_url" name="banner_link_url" value="<?php echo $link_url; ?>" required placeholder="https://...">
                        <p class="description">URL strony, do której ma prowadzić baner po kliknięciu</p>
                    </td>
                </tr>

                <tr>
                    <th><label for="impressions_bought">Limit wyświetleń unikalnych</label></th>
                    <td>
                        <input type="number" id="impressions_bought" name="impressions_bought" value="<?php echo $impressions_bought; ?>" min="0" placeholder="0">
                        <p class="description">Zostaw 0 dla nielimitowanych wyświetleń. <strong>System liczy tylko unikalne wyświetlenia</strong> (1 na użytkownika w ciągu 24h). Ten sam użytkownik nie zużyje budżetu wielokrotnym odświeżaniem strony.</p>
                    </td>
                </tr>

                <tr>
                    <th><label for="start_date">Data rozpoczęcia</label></th>
                    <td>
                        <input type="datetime-local" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                        <p class="description">Opcjonalnie: od kiedy baner ma się wyświetlać</p>
                    </td>
                </tr>

                <tr>
                    <th><label for="end_date">Data zakończenia</label></th>
                    <td>
                        <input type="datetime-local" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                        <p class="description">Opcjonalnie: do kiedy baner ma się wyświetlać</p>
                    </td>
                </tr>

                <tr>
                    <th><label for="display_order">Kolejność wyświetlania</label></th>
                    <td>
                        <input type="number" id="display_order" name="display_order" value="<?php echo $display_order; ?>" min="0" placeholder="0">
                        <p class="description">Kolejność w rotacji (0 = pierwszeństwo). Banery są pokazywane po kolei według tej wartości.</p>
                    </td>
                </tr>

                <tr>
                    <th><label for="active">Status</label></th>
                    <td>
                        <label>
                            <input type="checkbox" id="active" name="active" value="1" <?php checked($active, 1); ?>>
                            Aktywny (baner będzie się wyświetlał)
                        </label>
                    </td>
                </tr>
            </table>

            <p>
                <button type="submit" class="button button-primary button-large">
                    <?php echo $is_edit ? '💾 Zapisz zmiany' : '➕ Dodaj baner'; ?>
                </button>
            </p>
        </form>
        <?php
    }

    /**
     * Render banner item in list
     */
    private static function render_banner_item($banner) {
        $stats = JG_Map_Banner_Manager::get_banner_stats($banner['id']);
        $is_active = intval($banner['active']) === 1;
        $impressions_remaining = $stats['impressions_remaining'];
        $impressions_bought = intval($banner['impressions_bought']);
        $impressions_used = intval($banner['impressions_used']);

        // Get price per impression
        $price_per_impression = floatval(get_option('jg_banner_price_per_impression', '0.10'));

        // Calculate financial data
        $budget_total = $impressions_bought > 0 ? $impressions_bought * $price_per_impression : 0;
        $budget_used = $impressions_used * $price_per_impression;
        $budget_remaining = $budget_total - $budget_used;

        // Calculate progress percentage
        $progress_percent = 0;
        if ($impressions_bought > 0) {
            $progress_percent = min(100, round(($impressions_used / $impressions_bought) * 100));
        }

        ?>
        <div class="jg-banner-item <?php echo !$is_active ? 'inactive' : ''; ?>">
            <div class="jg-banner-preview">
                <img src="<?php echo esc_url($banner['image_url']); ?>" alt="<?php echo esc_attr($banner['title']); ?>">
            </div>

            <div class="jg-banner-info">
                <div class="jg-banner-title">
                    <?php echo esc_html($banner['title']); ?>
                    <span class="jg-badge <?php echo $is_active ? 'jg-badge-active' : 'jg-badge-inactive'; ?>">
                        <?php echo $is_active ? 'Aktywny' : 'Nieaktywny'; ?>
                    </span>
                </div>

                <div class="jg-banner-stats">
                    <div class="jg-banner-stat">
                        <span>👁️ Wyświetlenia unikalne (24h):</span>
                        <strong><?php echo number_format($banner['impressions_used'], 0, ',', ' '); ?></strong>
                        <?php if ($impressions_bought > 0) : ?>
                            / <?php echo number_format($impressions_bought, 0, ',', ' '); ?>
                            (pozostało: <span class="jg-stats-highlight"><?php echo is_numeric($impressions_remaining) ? number_format($impressions_remaining, 0, ',', ' ') : $impressions_remaining; ?></span>)
                        <?php else : ?>
                            <span style="color:#0a7e07">(nielimitowane)</span>
                        <?php endif; ?>
                    </div>

                    <div class="jg-banner-stat">
                        <span>🖱️ Kliknięcia:</span>
                        <strong><?php echo number_format($banner['clicks'], 0, ',', ' '); ?></strong>
                    </div>

                    <div class="jg-banner-stat">
                        <span>📊 CTR:</span>
                        <strong><?php echo $stats['ctr']; ?></strong>
                    </div>
                </div>

                <?php if ($impressions_bought > 0) : ?>
                    <div style="margin-top:15px;padding:12px;background:#f0f7ff;border-left:4px solid #0073aa;border-radius:4px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px;">
                            <span style="font-weight:600;color:#555;">💰 Budżet kampanii:</span>
                            <span style="font-weight:700;color:#0073aa;font-size:16px;"><?php echo number_format($budget_total, 2, ',', ' '); ?> PLN</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;font-size:13px;margin-top:5px;">
                            <span style="color:#666;">Wykorzystano:</span>
                            <span style="font-weight:600;color:#d63638;"><?php echo number_format($budget_used, 2, ',', ' '); ?> PLN</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;font-size:13px;margin-top:5px;">
                            <span style="color:#666;">Pozostało:</span>
                            <span style="font-weight:600;color:#0a7e07;"><?php echo number_format($budget_remaining, 2, ',', ' '); ?> PLN</span>
                        </div>
                    </div>

                    <div style="margin-top:10px;background:#e0e0e0;height:8px;border-radius:4px;overflow:hidden;">
                        <div style="background:#0073aa;height:100%;width:<?php echo $progress_percent; ?>%;transition:width 0.3s;"></div>
                    </div>
                <?php endif; ?>

                <?php if ($banner['start_date'] || $banner['end_date']) : ?>
                    <div style="margin-top:8px;font-size:12px;color:#666;">
                        <?php if ($banner['start_date']) : ?>
                            📅 Od: <?php echo date('d.m.Y H:i', strtotime($banner['start_date'])); ?>
                        <?php endif; ?>
                        <?php if ($banner['end_date']) : ?>
                            <?php if ($banner['start_date']) echo ' | '; ?>
                            📅 Do: <?php echo date('d.m.Y H:i', strtotime($banner['end_date'])); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div style="margin-top:8px;font-size:12px;color:#666;">
                    🔗 <a href="<?php echo esc_url($banner['link_url']); ?>" target="_blank"><?php echo esc_html($banner['link_url']); ?></a>
                </div>
            </div>

            <div class="jg-banner-actions">
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline;" target="_blank">
                    <?php wp_nonce_field('jg_export_banner_stats', 'jg_banner_nonce'); ?>
                    <input type="hidden" name="action" value="jg_export_banner_stats">
                    <input type="hidden" name="banner_id" value="<?php echo $banner['id']; ?>">
                    <button type="submit" class="button" style="background:#0073aa;color:#fff;border-color:#0073aa;">
                        📊 Raport PDF
                    </button>
                </form>

                <a href="#" class="button jg-edit-banner" data-id="<?php echo $banner['id']; ?>">Edytuj</a>

                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline;">
                    <?php wp_nonce_field('jg_toggle_banner', 'jg_banner_nonce'); ?>
                    <input type="hidden" name="action" value="jg_toggle_banner">
                    <input type="hidden" name="banner_id" value="<?php echo $banner['id']; ?>">
                    <button type="submit" class="button">
                        <?php echo $is_active ? '⏸️ Dezaktywuj' : '▶️ Aktywuj'; ?>
                    </button>
                </form>

                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline;">
                    <?php wp_nonce_field('jg_delete_banner', 'jg_banner_nonce'); ?>
                    <input type="hidden" name="action" value="jg_delete_banner">
                    <input type="hidden" name="banner_id" value="<?php echo $banner['id']; ?>">
                    <button type="submit" class="button jg-delete-banner" style="color:#a00;">🗑️ Usuń</button>
                </form>
            </div>
        </div>

        <!-- Edit form (hidden by default) -->
        <div id="edit-form-<?php echo $banner['id']; ?>" style="display:none;margin-top:15px;padding:20px;background:#fff;border:1px solid #ddd;border-radius:4px;">
            <h3>Edytuj baner: <?php echo esc_html($banner['title']); ?></h3>
            <?php self::render_banner_form($banner); ?>
        </div>
        <?php
    }

    /**
     * Handle save banner (add or edit)
     */
    public static function handle_save_banner() {
        // Check nonce
        if (!isset($_POST['jg_banner_nonce']) || !wp_verify_nonce($_POST['jg_banner_nonce'], 'jg_save_banner')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień');
        }

        // Get form data
        $banner_id = isset($_POST['banner_id']) ? intval($_POST['banner_id']) : 0;
        $title = sanitize_text_field($_POST['banner_title']);
        $image_url = esc_url_raw($_POST['banner_image_url']);
        $link_url = esc_url_raw($_POST['banner_link_url']);
        $impressions_bought = intval($_POST['impressions_bought']);
        $active = isset($_POST['active']) ? 1 : 0;
        $start_date = !empty($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : null;
        $end_date = !empty($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : null;
        $display_order = intval($_POST['display_order']);

        // Convert datetime-local to MySQL format
        if ($start_date) {
            $start_date = date('Y-m-d H:i:s', strtotime($start_date));
        }
        if ($end_date) {
            $end_date = date('Y-m-d H:i:s', strtotime($end_date));
        }

        $data = array(
            'title' => $title,
            'image_url' => $image_url,
            'link_url' => $link_url,
            'impressions_bought' => $impressions_bought,
            'active' => $active,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'display_order' => $display_order
        );

        if ($banner_id > 0) {
            // Update existing banner
            $result = JG_Map_Banner_Manager::update_banner($banner_id, $data);
        } else {
            // Insert new banner
            $result = JG_Map_Banner_Manager::insert_banner($data);
        }

        if ($result !== false) {
            wp_redirect(admin_url('admin.php?page=jg-map-banners&message=saved'));
        } else {
            wp_redirect(admin_url('admin.php?page=jg-map-banners&message=error'));
        }
        exit;
    }

    /**
     * Handle delete banner
     */
    public static function handle_delete_banner() {
        // Check nonce
        if (!isset($_POST['jg_banner_nonce']) || !wp_verify_nonce($_POST['jg_banner_nonce'], 'jg_delete_banner')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień');
        }

        $banner_id = intval($_POST['banner_id']);

        if ($banner_id > 0) {
            $result = JG_Map_Banner_Manager::delete_banner($banner_id);

            if ($result !== false) {
                wp_redirect(admin_url('admin.php?page=jg-map-banners&message=deleted'));
            } else {
                wp_redirect(admin_url('admin.php?page=jg-map-banners&message=error'));
            }
        } else {
            wp_redirect(admin_url('admin.php?page=jg-map-banners&message=error'));
        }
        exit;
    }

    /**
     * Handle toggle banner active status
     */
    public static function handle_toggle_banner() {
        // Check nonce
        if (!isset($_POST['jg_banner_nonce']) || !wp_verify_nonce($_POST['jg_banner_nonce'], 'jg_toggle_banner')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień');
        }

        $banner_id = intval($_POST['banner_id']);

        if ($banner_id > 0) {
            $banner = JG_Map_Banner_Manager::get_banner($banner_id);

            if ($banner) {
                $new_status = $banner['active'] == 1 ? 0 : 1;
                $result = JG_Map_Banner_Manager::update_banner($banner_id, array('active' => $new_status));

                if ($result !== false) {
                    wp_redirect(admin_url('admin.php?page=jg-map-banners&message=toggled'));
                } else {
                    wp_redirect(admin_url('admin.php?page=jg-map-banners&message=error'));
                }
            } else {
                wp_redirect(admin_url('admin.php?page=jg-map-banners&message=error'));
            }
        } else {
            wp_redirect(admin_url('admin.php?page=jg-map-banners&message=error'));
        }
        exit;
    }

    /**
     * Handle export banner stats to PDF
     */
    public static function handle_export_banner_stats() {
        // Check nonce
        if (!isset($_POST['jg_banner_nonce']) || !wp_verify_nonce($_POST['jg_banner_nonce'], 'jg_export_banner_stats')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień');
        }

        $banner_id = isset($_POST['banner_id']) ? intval($_POST['banner_id']) : 0;

        if ($banner_id <= 0) {
            wp_die('Nieprawidłowy ID banneru');
        }

        $banner = JG_Map_Banner_Manager::get_banner($banner_id);
        if (!$banner) {
            wp_die('Baner nie został znaleziony');
        }

        $stats = JG_Map_Banner_Manager::get_banner_stats($banner_id);

        // Generate HTML report
        $html = self::generate_pdf_report($banner, $stats);

        // Output HTML (browser can save as PDF)
        echo $html;
        exit;
    }

    /**
     * Generate PDF report HTML
     */
    private static function generate_pdf_report($banner, $stats) {
        $site_name = get_bloginfo('name');
        $report_date = date('d.m.Y H:i');
        $campaign_status = intval($banner['active']) === 1 ? 'Aktywna' : 'Nieaktywna';
        $campaign_status_color = intval($banner['active']) === 1 ? '#0a7e07' : '#d63638';

        $start_date = $banner['start_date'] ? date('d.m.Y H:i', strtotime($banner['start_date'])) : 'Brak';
        $end_date = $banner['end_date'] ? date('d.m.Y H:i', strtotime($banner['end_date'])) : 'Brak';

        $impressions_bought = intval($banner['impressions_bought']);
        $impressions_used = intval($banner['impressions_used']);
        $impressions_text = $impressions_bought > 0 ? number_format($impressions_bought, 0, ',', ' ') : 'Nielimitowane';

        // Calculate financial data
        $price_per_impression = floatval(get_option('jg_banner_price_per_impression', '0.10'));
        $budget_total = $impressions_bought > 0 ? $impressions_bought * $price_per_impression : 0;
        $budget_used = $impressions_used * $price_per_impression;
        $budget_remaining = $budget_total - $budget_used;

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raport - <?php echo esc_html($banner['title']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f5f5;
            padding: 40px 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        .header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 3px solid #0073aa;
        }
        .header h1 {
            font-size: 28px;
            color: #0073aa;
            margin-bottom: 10px;
        }
        .header .subtitle {
            font-size: 14px;
            color: #666;
        }
        .banner-info {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 30px;
        }
        .banner-info h2 {
            font-size: 20px;
            margin-bottom: 15px;
            color: #333;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #555;
        }
        .info-value {
            color: #333;
            text-align: right;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 25px;
            border-radius: 8px;
            color: #fff;
            text-align: center;
        }
        .stat-card:nth-child(2) {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .stat-card:nth-child(3) {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .stat-card:nth-child(4) {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 8px;
        }
        .stat-value {
            font-size: 32px;
            font-weight: 700;
        }
        .campaign-preview {
            text-align: center;
            margin: 30px 0;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 6px;
        }
        .campaign-preview img {
            max-width: 100%;
            height: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            font-size: 13px;
            color: #666;
        }
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #0073aa;
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,115,170,0.3);
            z-index: 1000;
        }
        .print-button:hover {
            background: #005a87;
        }
        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .container {
                box-shadow: none;
                padding: 20px;
            }
            .print-button {
                display: none;
            }
        }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">🖨️ Drukuj / Zapisz PDF</button>

    <div class="container">
        <div class="header">
            <h1>Raport kampanii reklamowej</h1>
            <div class="subtitle"><?php echo esc_html($site_name); ?> • Wygenerowano: <?php echo $report_date; ?></div>
        </div>

        <div class="banner-info">
            <h2><?php echo esc_html($banner['title']); ?></h2>
            <div class="info-row">
                <span class="info-label">Status kampanii:</span>
                <span class="info-value" style="color:<?php echo $campaign_status_color; ?>;font-weight:600;"><?php echo $campaign_status; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Data rozpoczęcia:</span>
                <span class="info-value"><?php echo $start_date; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Data zakończenia:</span>
                <span class="info-value"><?php echo $end_date; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Zakupione wyświetlenia:</span>
                <span class="info-value"><?php echo $impressions_text; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Link docelowy:</span>
                <span class="info-value" style="font-size:12px;word-break:break-all;"><?php echo esc_html($banner['link_url']); ?></span>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Wyświetlenia unikalne (24h)</div>
                <div class="stat-value"><?php echo number_format($banner['impressions_used'], 0, ',', ' '); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pozostało wyświetleń</div>
                <div class="stat-value"><?php echo is_numeric($stats['impressions_remaining']) ? number_format($stats['impressions_remaining'], 0, ',', ' ') : $stats['impressions_remaining']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Kliknięcia</div>
                <div class="stat-value"><?php echo number_format($banner['clicks'], 0, ',', ' '); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">CTR (Click-Through Rate)</div>
                <div class="stat-value"><?php echo $stats['ctr']; ?></div>
            </div>
        </div>

        <?php if ($impressions_bought > 0) : ?>
        <div class="banner-info" style="background: linear-gradient(135deg, #f0f7ff 0%, #e6f2ff 100%);border-left:4px solid #0073aa;">
            <h2 style="color:#0073aa;margin-bottom:20px;">💰 Podsumowanie finansowe</h2>
            <div class="info-row">
                <span class="info-label">Budżet kampanii (łącznie):</span>
                <span class="info-value" style="font-size:18px;font-weight:700;color:#0073aa;"><?php echo number_format($budget_total, 2, ',', ' '); ?> PLN</span>
            </div>
            <div class="info-row">
                <span class="info-label">Wykorzystano:</span>
                <span class="info-value" style="font-size:16px;font-weight:600;color:#d63638;"><?php echo number_format($budget_used, 2, ',', ' '); ?> PLN</span>
            </div>
            <div class="info-row">
                <span class="info-label">Pozostało do wykorzystania:</span>
                <span class="info-value" style="font-size:16px;font-weight:600;color:#0a7e07;"><?php echo number_format($budget_remaining, 2, ',', ' '); ?> PLN</span>
            </div>
            <div class="info-row" style="border-bottom:none;margin-top:10px;padding-top:15px;border-top:2px solid #0073aa;">
                <span class="info-label">Cena za wyświetlenie:</span>
                <span class="info-value" style="font-weight:600;"><?php echo number_format($price_per_impression, 2, ',', ' '); ?> PLN</span>
            </div>
        </div>
        <?php endif; ?>

        <div class="campaign-preview">
            <h3 style="margin-bottom:15px;color:#555;">Podgląd banneru:</h3>
            <img src="<?php echo esc_url($banner['image_url']); ?>" alt="<?php echo esc_attr($banner['title']); ?>">
        </div>

        <div class="footer">
            <p style="font-size:12px;color:#999;">© <?php echo date('Y'); ?> <?php echo esc_html($site_name); ?> • Raport wygenerowany automatycznie</p>
        </div>
    </div>

    <script>
        // Auto-print dialog after page load (optional)
        // window.onload = function() { window.print(); };
    </script>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    /**
     * Render settings form
     */
    private static function render_settings_form() {
        $price_per_impression = get_option('jg_banner_price_per_impression', '0.10');
        ?>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="jg-banner-form">
            <?php wp_nonce_field('jg_save_banner_settings', 'jg_settings_nonce'); ?>
            <input type="hidden" name="action" value="jg_save_banner_settings">

            <table style="width:auto;">
                <tr>
                    <th style="width:250px;"><label for="price_per_impression">Cena za 1 wyświetlenie unikalne (PLN)</label></th>
                    <td>
                        <input type="number" id="price_per_impression" name="price_per_impression" value="<?php echo esc_attr($price_per_impression); ?>" min="0" step="0.01" style="width:150px;" required>
                        <p class="description">Domyślna cena za jedno unikalne wyświetlenie banneru (np. 0.10 PLN = 10 groszy za wyświetlenie)</p>
                    </td>
                </tr>
            </table>

            <p>
                <button type="submit" class="button button-primary">💾 Zapisz ustawienia</button>
            </p>
        </form>
        <?php
    }

    /**
     * Handle save banner settings
     */
    public static function handle_save_banner_settings() {
        // Check nonce
        if (!isset($_POST['jg_settings_nonce']) || !wp_verify_nonce($_POST['jg_settings_nonce'], 'jg_save_banner_settings')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień');
        }

        $price_per_impression = isset($_POST['price_per_impression']) ? floatval($_POST['price_per_impression']) : 0.10;

        update_option('jg_banner_price_per_impression', $price_per_impression);

        wp_redirect(admin_url('admin.php?page=jg-map-banners&message=settings_saved'));
        exit;
    }
}
