<?php
/**
 * Info Bar - Pasek informacyjny
 * Wyświetla pasek z ważnymi komunikatami nad głównym paskiem nawigacyjnym.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class JG_Map_Info_Bar {

    /**
     * Initialize hooks
     */
    public static function init() {
        // Render on frontend before nav bars (priority 1)
        add_action('wp_body_open', array(__CLASS__, 'render_frontend'), 1);

        // Admin menu item
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));

        // Handle form save
        add_action('admin_post_jg_save_info_bar', array(__CLASS__, 'handle_save'));
    }

    /**
     * Get current active content (checks expiry)
     * Returns empty string if no content or if expired.
     */
    public static function get_active_content() {
        $content = get_option('jg_info_bar_content', '');

        if (empty(trim($content))) {
            return '';
        }

        $expires = get_option('jg_info_bar_expires', '');

        if (!empty($expires)) {
            $expires_ts = strtotime($expires);
            if ($expires_ts !== false && current_time('timestamp') > $expires_ts) {
                // Expired — clear the content so the bar disappears
                update_option('jg_info_bar_content', '');
                update_option('jg_info_bar_expires', '');
                return '';
            }
        }

        return $content;
    }

    /**
     * Render the info bar on the frontend
     */
    public static function render_frontend() {
        $content = self::get_active_content();

        if (empty(trim($content))) {
            return;
        }

        // Allow basic inline HTML + links only
        $allowed_html = array(
            'a'      => array('href' => array(), 'target' => array(), 'rel' => array(), 'title' => array()),
            'b'      => array(),
            'strong' => array(),
            'em'     => array(),
            'i'      => array(),
            'span'   => array('style' => array()),
            'br'     => array(),
        );
        $safe_content = wp_kses($content, $allowed_html);
        ?>
        <div id="jg-info-bar" class="jg-info-bar" role="status" aria-live="polite">
            <div class="jg-info-bar-track">
                <span class="jg-info-bar-text"><?php echo $safe_content; ?></span>
            </div>
        </div>
        <script>
        (function () {
            'use strict';
            var bar   = document.getElementById('jg-info-bar');
            if (!bar) return;
            var track = bar.querySelector('.jg-info-bar-track');
            var text  = bar.querySelector('.jg-info-bar-text');
            if (!track || !text) return;

            function applyScroll() {
                // Reset to static state first so scrollWidth is measured without transform offset
                bar.classList.remove('jg-info-bar--scroll');
                bar.style.removeProperty('--jg-info-bar-dur');

                // Force reflow so the browser recalculates layout after class removal
                void bar.offsetWidth;

                // Total pixels the text must travel: enter from right + cross full track + exit left
                var textW  = text.scrollWidth;
                var trackW = track.clientWidth;

                if (textW > trackW) {
                    // Speed: 120 px/s — smooth enough to read, fast enough not to feel stuck
                    // Total travel = 100vw (enter) + textW (exit)
                    var travel   = window.innerWidth + textW;
                    var duration = Math.max(6, Math.round(travel / 120));

                    // Pass duration via CSS custom property — the only way to override
                    // a value that is set with !important in the stylesheet, because
                    // CSS custom properties are NOT affected by !important cascading.
                    bar.style.setProperty('--jg-info-bar-dur', duration + 's');
                    bar.classList.add('jg-info-bar--scroll');
                }
            }

            // Run after fonts/layout settle
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', applyScroll);
            } else {
                applyScroll();
            }

            // Re-evaluate on resize (orientation change on mobile, window resize on desktop)
            var resizeTimer;
            window.addEventListener('resize', function () {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(applyScroll, 150);
            });
        }());
        </script>
        <?php
    }

    /**
     * Register submenu page
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'jg-map-dashboard',
            'Pasek informacyjny',
            'Pasek informacyjny',
            'jg_map_manage',
            'jg-info-bar',
            array(__CLASS__, 'render_admin_page')
        );
    }

    /**
     * Handle form submission (admin-post.php action)
     */
    public static function handle_save() {
        if (!current_user_can('jg_map_manage')) {
            wp_die('Brak uprawnień.', 403);
        }
        check_admin_referer('jg_info_bar_nonce', 'jg_info_bar_nonce_field');

        $content = isset($_POST['jg_info_bar_content']) ? wp_kses_post(wp_unslash($_POST['jg_info_bar_content'])) : '';
        $expires = isset($_POST['jg_info_bar_expires']) ? sanitize_text_field(wp_unslash($_POST['jg_info_bar_expires'])) : '';

        // Validate datetime format (YYYY-MM-DDTHH:MM or empty)
        if (!empty($expires) && !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $expires)) {
            $expires = '';
        }

        update_option('jg_info_bar_content', $content);
        update_option('jg_info_bar_expires', $expires);

        wp_redirect(add_query_arg(
            array('page' => 'jg-info-bar', 'jg_info_bar_saved' => '1'),
            admin_url('admin.php')
        ));
        exit;
    }

    /**
     * Render admin page
     */
    public static function render_admin_page() {
        if (!current_user_can('jg_map_manage')) {
            wp_die('Brak uprawnień.');
        }

        $content      = get_option('jg_info_bar_content', '');
        $expires      = get_option('jg_info_bar_expires', '');
        $saved        = isset($_GET['jg_info_bar_saved']) && $_GET['jg_info_bar_saved'] === '1';
        $active       = !empty(trim(self::get_active_content()));

        // Format expiry for datetime-local input
        $expires_input = '';
        if (!empty($expires)) {
            // Convert "Y-m-d H:i:s" → "Y-m-d\TH:i" if needed
            $expires_input = str_replace(' ', 'T', substr($expires, 0, 16));
        }
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Pasek informacyjny</h1>
            <hr class="wp-header-end">

            <?php if ($saved) : ?>
                <div class="notice notice-success is-dismissible"><p>Ustawienia paska informacyjnego zostały zapisane.</p></div>
            <?php endif; ?>

            <div style="margin-top:16px;margin-bottom:20px;padding:12px 16px;border-left:4px solid <?php echo $active ? '#00a32a' : '#dba617'; ?>;background:<?php echo $active ? '#f0fff4' : '#fffbea'; ?>">
                <strong>Status paska:</strong>
                <?php if ($active) : ?>
                    <span style="color:#00a32a">Aktywny i widoczny na stronie</span>
                <?php elseif (!empty($content)) : ?>
                    <span style="color:#b45309">Treść wygasła — pasek jest ukryty</span>
                <?php else : ?>
                    <span style="color:#6b7280">Brak treści — pasek jest ukryty</span>
                <?php endif; ?>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('jg_info_bar_nonce', 'jg_info_bar_nonce_field'); ?>
                <input type="hidden" name="action" value="jg_save_info_bar">

                <table class="form-table" role="presentation">
                    <tbody>

                        <tr>
                            <th scope="row">
                                <label for="jg_info_bar_content">Treść komunikatu</label>
                            </th>
                            <td>
                                <textarea
                                    id="jg_info_bar_content"
                                    name="jg_info_bar_content"
                                    rows="4"
                                    style="width:100%;max-width:600px;font-family:monospace;font-size:13px"
                                    placeholder="Wpisz treść komunikatu. Możesz używać HTML, np. &lt;a href=&quot;https://...&quot;&gt;link&lt;/a&gt;"
                                ><?php echo esc_textarea($content); ?></textarea>
                                <p class="description">
                                    Dozwolone tagi HTML: <code>&lt;a&gt;</code>, <code>&lt;b&gt;</code>, <code>&lt;strong&gt;</code>, <code>&lt;em&gt;</code>, <code>&lt;i&gt;</code>, <code>&lt;span&gt;</code>, <code>&lt;br&gt;</code>.<br>
                                    Pozostaw puste, aby ukryć pasek.
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="jg_info_bar_expires">Widoczność ograniczona czasowo</label>
                            </th>
                            <td>
                                <label style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
                                    <input type="checkbox" id="jg_info_bar_use_expiry" name="jg_info_bar_use_expiry"
                                        <?php checked(!empty($expires_input)); ?>>
                                    Włącz datę wygaśnięcia
                                </label>

                                <div id="jg-info-bar-expiry-wrap" style="<?php echo empty($expires_input) ? 'display:none' : ''; ?>">
                                    <input
                                        type="datetime-local"
                                        id="jg_info_bar_expires"
                                        name="jg_info_bar_expires"
                                        value="<?php echo esc_attr($expires_input); ?>"
                                        style="font-size:14px"
                                    >
                                    <p class="description">
                                        Po osiągnięciu tej daty treść paska zostanie automatycznie usunięta i pasek zniknie ze strony.
                                        Czas lokalny WordPress (strefa: <?php echo esc_html(get_option('timezone_string') ?: get_option('gmt_offset') . 'h UTC'); ?>).
                                    </p>
                                </div>
                            </td>
                        </tr>

                    </tbody>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">Zapisz pasek informacyjny</button>
                    <?php if (!empty($content)) : ?>
                        <button type="button" class="button button-secondary" id="jg-info-bar-clear-btn" style="margin-left:8px">
                            Wyczyść i ukryj pasek
                        </button>
                    <?php endif; ?>
                </p>
            </form>

            <!-- Live preview -->
            <?php if (!empty($content)) :
                $allowed_html = array(
                    'a'      => array('href' => array(), 'target' => array(), 'rel' => array(), 'title' => array()),
                    'b'      => array(), 'strong' => array(), 'em' => array(),
                    'i'      => array(), 'span'   => array('style' => array()), 'br' => array(),
                );
                $preview = wp_kses($content, $allowed_html);
            ?>
            <hr>
            <h2>Podgląd</h2>
            <div style="border:1px solid #ddd;border-radius:6px;overflow:hidden;max-width:900px">
                <div style="background:#f3f4f6;padding:8px 16px;overflow:hidden;white-space:nowrap;text-align:center;color:#8d2324;font-size:14px;font-family:sans-serif">
                    <?php echo $preview; ?>
                </div>
                <div style="background:#8d2324;height:6px"></div>
            </div>
            <p class="description" style="margin-top:8px">Podgląd paska — górny szary element to pasek informacyjny, czerwony to nawigacja.</p>
            <?php endif; ?>
        </div>

        <script>
        (function () {
            // Toggle expiry date field
            var cb   = document.getElementById('jg_info_bar_use_expiry');
            var wrap = document.getElementById('jg-info-bar-expiry-wrap');
            var inp  = document.getElementById('jg_info_bar_expires');
            if (cb && wrap) {
                cb.addEventListener('change', function () {
                    wrap.style.display = this.checked ? '' : 'none';
                    if (!this.checked && inp) inp.value = '';
                });
            }

            // Clear button — empties textarea and submits
            var clearBtn = document.getElementById('jg-info-bar-clear-btn');
            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    var ta = document.getElementById('jg_info_bar_content');
                    if (ta) ta.value = '';
                    var expCb = document.getElementById('jg_info_bar_use_expiry');
                    if (expCb) expCb.checked = false;
                    if (inp) inp.value = '';
                    if (wrap) wrap.style.display = 'none';
                    clearBtn.closest('form').submit();
                });
            }
        }());
        </script>
        <?php
    }
}
