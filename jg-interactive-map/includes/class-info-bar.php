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
        add_action('admin_post_jg_save_info_bar',  array(__CLASS__, 'handle_save'));

        // Handle "show to everyone again" reset
        add_action('admin_post_jg_reset_info_bar', array(__CLASS__, 'handle_reset'));
    }

    /**
     * Get current active content (checks expiry).
     * Returns empty string if no content or expired.
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
                // Expired — clear content so the bar disappears
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

        $closable = (bool) get_option('jg_info_bar_closable', 0);

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

        // Hash = content + reset_count.
        // Changing either (new message OR admin clicking "show to everyone again")
        // invalidates all existing localStorage dismissal keys.
        $reset_count  = (int) get_option('jg_info_bar_reset_count', 0);
        $content_hash = substr(md5($safe_content . '|' . $reset_count), 0, 10);

        $bar_classes = 'jg-info-bar' . ($closable ? ' jg-info-bar--closable' : '');
        ?>
        <div id="jg-info-bar"
             class="<?php echo esc_attr($bar_classes); ?>"
             role="status"
             aria-live="polite"
             data-hash="<?php echo esc_attr($content_hash); ?>"
             data-closable="<?php echo $closable ? '1' : '0'; ?>">
            <div class="jg-info-bar-track">
                <span class="jg-info-bar-text"><?php echo $safe_content; ?></span>
            </div>
            <?php if ($closable) : ?>
            <button class="jg-info-bar-close" type="button" aria-label="Zamknij komunikat">&#x2715;</button>
            <?php endif; ?>
        </div>
        <script>
        (function () {
            'use strict';

            var bar   = document.getElementById('jg-info-bar');
            if (!bar) return;
            var track = bar.querySelector('.jg-info-bar-track');
            var text  = bar.querySelector('.jg-info-bar-text');
            if (!track || !text) return;

            // ── Dismissal check (localStorage) ───────────────────────────────
            // Only active when the bar is currently marked as closable.
            // If admin unchecks "closable", any previous dismissal is ignored
            // and the bar is shown to everyone regardless.
            var closable   = bar.dataset.closable === '1';
            var storageKey = 'jg_info_bar_dismissed_' + (bar.dataset.hash || '');
            if (closable) {
                try {
                    if (localStorage.getItem(storageKey) === '1') {
                        bar.style.setProperty('display', 'none', 'important');
                        return;
                    }
                } catch (e) { /* localStorage unavailable — show bar normally */ }
            }

            // ── Close button ─────────────────────────────────────────────────
            var closeBtn = bar.querySelector('.jg-info-bar-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function () {
                    // Persist dismissal
                    try { localStorage.setItem(storageKey, '1'); } catch (e) {}

                    // Slide bar upward: fix current height → animate to 0
                    var h = bar.offsetHeight;
                    bar.style.setProperty('max-height', h + 'px', 'important');
                    bar.style.setProperty('overflow',   'hidden',  'important');
                    bar.style.setProperty('opacity',    '1',       'important');
                    bar.style.setProperty('transition',
                        'max-height 0.4s ease, opacity 0.3s ease', 'important');

                    // Double RAF ensures starting values are painted before transition
                    requestAnimationFrame(function () {
                        requestAnimationFrame(function () {
                            bar.style.setProperty('max-height', '0px', 'important');
                            bar.style.setProperty('opacity',    '0',   'important');
                            setTimeout(function () {
                                bar.style.setProperty('display', 'none', 'important');
                                /* Notify nav/modal offset JS to recalculate positions */
                                window.dispatchEvent(new Event('jg-info-bar-changed'));
                            }, 420);
                        });
                    });
                });
            }

            // ── Tap-to-pause (touch devices) ─────────────────────────────────
            // Click/tap on the bar pauses scrolling for 3 s then resumes.
            // setProperty(..., 'important') beats animation-play-state:running !important
            // in the stylesheet because inline !important has higher specificity.
            var tapTimer = null;
            bar.addEventListener('click', function (e) {
                // Don't interfere with the close button
                if (closeBtn && closeBtn.contains(e.target)) return;
                if (!bar.classList.contains('jg-info-bar--scroll')) return;

                text.style.setProperty('animation-play-state', 'paused', 'important');
                clearTimeout(tapTimer);
                tapTimer = setTimeout(function () {
                    text.style.removeProperty('animation-play-state');
                    tapTimer = null;
                }, 3000);
            });

            // ── Ticker / scroll ───────────────────────────────────────────────
            function applyScroll() {
                // Cancel any pending tap-pause so measurement is clean
                clearTimeout(tapTimer);
                tapTimer = null;
                text.style.removeProperty('animation-play-state');

                bar.classList.remove('jg-info-bar--scroll');
                bar.style.removeProperty('--jg-info-bar-dur');

                // Force layout reflow so measurements are clean after class removal
                void bar.offsetWidth;

                // text.offsetWidth = full natural text width (flex-shrink:0 keeps it unconstrained)
                var textW    = text.offsetWidth;
                var style    = window.getComputedStyle(track);
                var padL     = parseFloat(style.paddingLeft)  || 0;
                var padR     = parseFloat(style.paddingRight) || 0;
                var contentW = track.clientWidth - padL - padR;

                if (textW > contentW) {
                    // Speed: 120 px/s. Total travel = enter from right (vw) + exit left (textW).
                    var travel   = window.innerWidth + textW;
                    var duration = Math.max(6, Math.round(travel / 120));

                    // Pass duration via CSS custom property — the ONLY way to provide a
                    // dynamic value to a CSS rule that uses !important, since inline styles
                    // cannot override !important but custom properties propagate through it.
                    bar.style.setProperty('--jg-info-bar-dur', duration + 's');
                    bar.classList.add('jg-info-bar--scroll');
                }
            }

            // Run at window.load + double RAF: ensures Elementor and all other
            // scripts have finished modifying the DOM before we measure.
            if (document.readyState === 'complete') {
                requestAnimationFrame(function () { requestAnimationFrame(applyScroll); });
            } else {
                window.addEventListener('load', function () {
                    requestAnimationFrame(function () { requestAnimationFrame(applyScroll); });
                });
            }

            // Re-check after orientation change / window resize
            var resizeTimer;
            window.addEventListener('resize', function () {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(applyScroll, 200);
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
     * Handle "show to everyone again" — increments reset counter which changes
     * the content hash → all existing localStorage dismissal keys are invalidated.
     */
    public static function handle_reset() {
        if (!current_user_can('jg_map_manage')) {
            wp_die('Brak uprawnień.', 403);
        }
        check_admin_referer('jg_reset_info_bar_nonce', 'jg_reset_info_bar_nonce_field');

        $count = (int) get_option('jg_info_bar_reset_count', 0);
        update_option('jg_info_bar_reset_count', $count + 1);

        wp_redirect(add_query_arg(
            array('page' => 'jg-info-bar', 'jg_info_bar_reset' => '1'),
            admin_url('admin.php')
        ));
        exit;
    }

    /**
     * Handle form submission (admin-post.php action)
     */
    public static function handle_save() {
        if (!current_user_can('jg_map_manage')) {
            wp_die('Brak uprawnień.', 403);
        }
        check_admin_referer('jg_info_bar_nonce', 'jg_info_bar_nonce_field');

        $content  = isset($_POST['jg_info_bar_content'])  ? wp_kses_post(wp_unslash($_POST['jg_info_bar_content'])) : '';
        $expires  = isset($_POST['jg_info_bar_expires'])  ? sanitize_text_field(wp_unslash($_POST['jg_info_bar_expires'])) : '';
        $closable = isset($_POST['jg_info_bar_closable']) ? 1 : 0;

        // Validate datetime format (YYYY-MM-DDTHH:MM) or empty
        if (!empty($expires) && !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $expires)) {
            $expires = '';
        }

        update_option('jg_info_bar_content',  $content);
        update_option('jg_info_bar_expires',  $expires);
        update_option('jg_info_bar_closable', $closable);

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

        $content  = get_option('jg_info_bar_content',  '');
        $expires  = get_option('jg_info_bar_expires',  '');
        $closable = (bool) get_option('jg_info_bar_closable', 0);
        $saved    = isset($_GET['jg_info_bar_saved']) && $_GET['jg_info_bar_saved'] === '1';
        $active   = !empty(trim(self::get_active_content()));

        // Convert stored "Y-m-d H:i:s" or "Y-m-d\TH:i" to datetime-local value
        $expires_input = '';
        if (!empty($expires)) {
            $expires_input = str_replace(' ', 'T', substr($expires, 0, 16));
        }

        $tz_label = get_option('timezone_string') ?: (get_option('gmt_offset') . 'h UTC');
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Pasek informacyjny</h1>
            <hr class="wp-header-end">

            <?php if ($saved) : ?>
                <div class="notice notice-success is-dismissible"><p>Ustawienia paska informacyjnego zostały zapisane.</p></div>
            <?php endif; ?>
            <?php if (isset($_GET['jg_info_bar_reset']) && $_GET['jg_info_bar_reset'] === '1') : ?>
                <div class="notice notice-success is-dismissible"><p>Pasek zostanie wyświetlony ponownie u wszystkich użytkowników.</p></div>
            <?php endif; ?>

            <!-- Status indicator -->
            <div style="margin-top:16px;margin-bottom:20px;padding:12px 16px;border-left:4px solid <?php echo $active ? '#00a32a' : '#dba617'; ?>;background:<?php echo $active ? '#f0fff4' : '#fffbea'; ?>">
                <strong>Status paska:</strong>
                <?php if ($active) : ?>
                    <span style="color:#00a32a">&#10003; Aktywny i widoczny na stronie</span>
                <?php elseif (!empty($content)) : ?>
                    <span style="color:#b45309">Treść wygasła &mdash; pasek jest ukryty</span>
                <?php else : ?>
                    <span style="color:#6b7280">Brak treści &mdash; pasek jest ukryty</span>
                <?php endif; ?>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('jg_info_bar_nonce', 'jg_info_bar_nonce_field'); ?>
                <input type="hidden" name="action" value="jg_save_info_bar">

                <table class="form-table" role="presentation">
                    <tbody>

                        <!-- Content -->
                        <tr>
                            <th scope="row"><label for="jg_info_bar_content">Treść komunikatu</label></th>
                            <td>
                                <textarea
                                    id="jg_info_bar_content"
                                    name="jg_info_bar_content"
                                    rows="4"
                                    style="width:100%;max-width:620px;font-family:monospace;font-size:13px"
                                    placeholder="Wpisz treść. Możesz używać HTML np. &lt;a href=&quot;https://...&quot;&gt;link&lt;/a&gt;"
                                ><?php echo esc_textarea($content); ?></textarea>
                                <p class="description">
                                    Dozwolone tagi: <code>&lt;a&gt;</code> <code>&lt;b&gt;</code> <code>&lt;strong&gt;</code>
                                    <code>&lt;em&gt;</code> <code>&lt;i&gt;</code> <code>&lt;span&gt;</code> <code>&lt;br&gt;</code>.
                                    Pozostaw puste, aby ukryć pasek.
                                </p>
                            </td>
                        </tr>

                        <!-- Closable -->
                        <tr>
                            <th scope="row">Komunikat zamykalny</th>
                            <td>
                                <label style="display:flex;align-items:center;gap:8px">
                                    <input type="checkbox" name="jg_info_bar_closable" id="jg_info_bar_closable"
                                           value="1" <?php checked($closable); ?>>
                                    Pokaż przycisk &#x2715; (zamknij) po prawej stronie paska
                                </label>
                                <p class="description" style="margin-top:6px">
                                    Po kliknięciu pasek płynnie zwija się ku górze. Decyzja jest zapamiętywana
                                    w przeglądarce &mdash; użytkownik nie zobaczy ponownie <em>tego samego</em>
                                    komunikatu. Zmiana treści spowoduje ponowne wyświetlenie paska.
                                </p>
                            </td>
                        </tr>

                        <!-- Expiry -->
                        <tr>
                            <th scope="row"><label for="jg_info_bar_expires">Widoczność ograniczona czasowo</label></th>
                            <td>
                                <label style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
                                    <input type="checkbox" id="jg_info_bar_use_expiry"
                                           <?php checked(!empty($expires_input)); ?>>
                                    Włącz datę wygaśnięcia
                                </label>
                                <div id="jg-info-bar-expiry-wrap" style="<?php echo empty($expires_input) ? 'display:none' : ''; ?>">
                                    <input type="datetime-local"
                                           id="jg_info_bar_expires"
                                           name="jg_info_bar_expires"
                                           value="<?php echo esc_attr($expires_input); ?>"
                                           style="font-size:14px">
                                    <p class="description">
                                        Po tej dacie treść zostanie automatycznie usunięta i pasek zniknie ze strony.<br>
                                        Strefa czasowa WordPress: <strong><?php echo esc_html($tz_label); ?></strong>
                                    </p>
                                </div>
                            </td>
                        </tr>

                    </tbody>
                </table>

                <p class="submit" style="display:flex;align-items:center;flex-wrap:wrap;gap:8px">
                    <button type="submit" class="button button-primary">Zapisz pasek informacyjny</button>
                    <?php if (!empty($content)) : ?>
                        <button type="button" class="button button-secondary" id="jg-info-bar-clear-btn">
                            Wyczyść i ukryj pasek
                        </button>
                    <?php endif; ?>
                </p>
            </form>

            <?php if (!empty($content)) : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:4px">
                <?php wp_nonce_field('jg_reset_info_bar_nonce', 'jg_reset_info_bar_nonce_field'); ?>
                <input type="hidden" name="action" value="jg_reset_info_bar">
                <button type="submit" class="button button-secondary"
                        onclick="return confirm('Czy na pewno? Pasek pojawi się ponownie u wszystkich użytkowników, którzy go zamknęli.')">
                    &#x21BA; Wyświetl u wszystkich ponownie
                </button>
                <p class="description" style="margin-top:6px">
                    Resetuje preferencje zamknięcia u wszystkich odwiedzających &mdash; pasek pojawi się ponownie
                    nawet u tych, którzy kliknęli&nbsp;&#x2715;. Zmiana treści robi to samo automatycznie.
                </p>
            </form>
            <?php endif; ?>

            <!-- Preview -->
            <?php if (!empty($content)) :
                $allowed_html = array(
                    'a'      => array('href' => array(), 'target' => array(), 'rel' => array(), 'title' => array()),
                    'b'      => array(), 'strong' => array(), 'em' => array(),
                    'i'      => array(), 'span'   => array('style' => array()), 'br' => array(),
                );
                $preview = wp_kses($content, $allowed_html);
            ?>
            <hr>
            <h2 style="margin-top:20px">Podgląd</h2>
            <div style="border:1px solid #ddd;border-radius:6px;overflow:hidden;max-width:900px">
                <div style="display:flex;align-items:stretch;background:#f3f4f6;font-family:system-ui,sans-serif">
                    <div style="flex:1;min-width:0;overflow:hidden;white-space:nowrap;text-align:center;color:#8d2324;font-size:14px;padding:8px 16px">
                        <?php echo $preview; ?>
                    </div>
                    <?php if ($closable) : ?>
                        <span style="display:flex;align-items:center;justify-content:center;flex-shrink:0;width:32px;padding:0 6px 0 0;opacity:.6;font-size:15px;color:#8d2324;cursor:default">&#x2715;</span>
                    <?php endif; ?>
                </div>
                <div style="background:#8d2324;height:6px"></div>
            </div>
            <p class="description" style="margin-top:8px">Górny szary pasek = pasek informacyjny &nbsp;|&nbsp; Czerwony pasek = nawigacja.</p>
            <?php endif; ?>
        </div>

        <script>
        (function () {
            // Toggle expiry wrapper
            var cb   = document.getElementById('jg_info_bar_use_expiry');
            var wrap = document.getElementById('jg-info-bar-expiry-wrap');
            var inp  = document.getElementById('jg_info_bar_expires');
            if (cb && wrap) {
                cb.addEventListener('change', function () {
                    wrap.style.display = this.checked ? '' : 'none';
                    if (!this.checked && inp) inp.value = '';
                });
            }

            // "Wyczyść" button: empty textarea and submit
            var clearBtn = document.getElementById('jg-info-bar-clear-btn');
            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    var ta   = document.getElementById('jg_info_bar_content');
                    var expCb = document.getElementById('jg_info_bar_use_expiry');
                    if (ta)   ta.value = '';
                    if (expCb) expCb.checked = false;
                    if (inp)  inp.value = '';
                    if (wrap) wrap.style.display = 'none';
                    clearBtn.closest('form').submit();
                });
            }
        }());
        </script>
        <?php
    }
}
