<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait JG_Map_Admin_Users {

    /**
     * Get blocked IPs from rate limiting
     */
    private function get_blocked_ips() {
        global $wpdb;

        // Get all transients for login rate limiting
        $transients = $wpdb->get_results(
            "SELECT option_name, option_value
             FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_jg_rate_limit_login_%'
             AND option_name NOT LIKE '%_time_%'
             AND option_name NOT LIKE '%_userdata_%'",
            ARRAY_A
        );

        $blocked_ips = array();

        foreach ($transients as $transient) {
            $key = str_replace('_transient_jg_rate_limit_login_', '', $transient['option_name']);
            $attempts = intval($transient['option_value']);

            // Only show if blocked (5+ attempts)
            if ($attempts >= 5) {
                // Get time transient
                $time_key = '_transient_jg_rate_limit_time_login_' . $key;
                $first_attempt_time = $wpdb->get_var($wpdb->prepare(
                    "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
                    $time_key
                ));

                // Get user data transient
                $userdata_key = '_transient_jg_rate_limit_userdata_login_' . $key;
                $user_data = $wpdb->get_var($wpdb->prepare(
                    "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
                    $userdata_key
                ));

                if ($first_attempt_time) {
                    $time_elapsed = time() - intval($first_attempt_time);
                    $time_remaining = max(0, 900 - $time_elapsed); // 900 seconds = 15 minutes

                    // Unserialize user data
                    $user_info = $user_data ? maybe_unserialize($user_data) : array();

                    $blocked_ips[] = array(
                        'hash' => $key,
                        'ip' => isset($user_info['ip']) ? $user_info['ip'] : 'Nieznany',
                        'username' => isset($user_info['username']) ? $user_info['username'] : 'Nieznany',
                        'email' => isset($user_info['email']) ? $user_info['email'] : '',
                        'attempts' => $attempts,
                        'blocked_at' => intval($first_attempt_time),
                        'time_remaining' => $time_remaining,
                        'type' => 'login'
                    );
                }
            }
        }

        return $blocked_ips;
    }

    /**
     * Get blocked IPs from registration rate limiting
     */
    private function get_blocked_registration_ips() {
        global $wpdb;

        // Get all transients for registration rate limiting
        $transients = $wpdb->get_results(
            "SELECT option_name, option_value
             FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_jg_rate_limit_register_%'
             AND option_name NOT LIKE '%_time_%'
             AND option_name NOT LIKE '%_userdata_%'",
            ARRAY_A
        );

        $blocked_ips = array();

        foreach ($transients as $transient) {
            $key = str_replace('_transient_jg_rate_limit_register_', '', $transient['option_name']);
            $attempts = intval($transient['option_value']);

            // Only show if blocked (3+ attempts for registration)
            if ($attempts >= 3) {
                // Get time transient
                $time_key = '_transient_jg_rate_limit_time_register_' . $key;
                $first_attempt_time = $wpdb->get_var($wpdb->prepare(
                    "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
                    $time_key
                ));

                // Get user data transient
                $userdata_key = '_transient_jg_rate_limit_userdata_register_' . $key;
                $user_data = $wpdb->get_var($wpdb->prepare(
                    "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
                    $userdata_key
                ));

                if ($first_attempt_time) {
                    $time_elapsed = time() - intval($first_attempt_time);
                    $time_remaining = max(0, 3600 - $time_elapsed); // 3600 seconds = 1 hour

                    // Unserialize user data
                    $user_info = $user_data ? maybe_unserialize($user_data) : array();

                    $blocked_ips[] = array(
                        'hash' => $key,
                        'ip' => isset($user_info['ip']) ? $user_info['ip'] : 'Nieznany',
                        'username' => isset($user_info['username']) ? $user_info['username'] : 'Nieznany',
                        'email' => isset($user_info['email']) ? $user_info['email'] : '',
                        'attempts' => $attempts,
                        'blocked_at' => intval($first_attempt_time),
                        'time_remaining' => $time_remaining,
                        'type' => 'register'
                    );
                }
            }
        }

        return $blocked_ips;
    }

    /**
     * Render users management page
     */
    public function render_users_page() {
        global $wpdb;
        $points_table = JG_Map_Database::get_points_table();

        // Get blocked IPs (login and registration)
        $blocked_ips = $this->get_blocked_ips();
        $blocked_registration_ips = $this->get_blocked_registration_ips();

        // Get all users with their statistics
        $users = get_users(array('orderby' => 'registered', 'order' => 'DESC'));

        // Tables for activity tracking
        $table_history = $wpdb->prefix . 'jg_map_history';
        $table_votes = $wpdb->prefix . 'jg_map_votes';
        $table_reports = $wpdb->prefix . 'jg_map_reports';

        // Build stats for each user
        $user_stats = array();
        foreach ($users as $user) {
            $points_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $points_table WHERE author_id = %d AND status = 'publish'",
                $user->ID
            ));

            $pending_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $points_table WHERE author_id = %d AND status = 'pending'",
                $user->ID
            ));

            // Get ban status
            $ban_status = get_user_meta($user->ID, 'jg_map_banned', true);
            $ban_until = get_user_meta($user->ID, 'jg_map_ban_until', true);

            // Get restrictions
            $restrictions = array();
            $restriction_types = array('voting', 'add_places', 'add_events', 'add_trivia', 'edit_places', 'photo_upload');
            foreach ($restriction_types as $type) {
                if (get_user_meta($user->ID, 'jg_map_ban_' . $type, true)) {
                    $restrictions[] = $type;
                }
            }

            // Get last login (from user meta)
            $last_login = get_user_meta($user->ID, 'jg_map_last_login', true);

            // Get last action (most recent activity across tables)
            $last_actions = array();

            // Last point added by user (use created_at only — updated_at changes on every
            // admin action such as approval and would incorrectly appear as user activity)
            $last_point = $wpdb->get_var($wpdb->prepare(
                "SELECT created_at FROM $points_table WHERE author_id = %d ORDER BY created_at DESC LIMIT 1",
                $user->ID
            ));
            if ($last_point && $last_point !== '1970-01-01') $last_actions[] = $last_point;

            // Last edit submitted
            $last_edit = $wpdb->get_var($wpdb->prepare(
                "SELECT created_at FROM $table_history WHERE user_id = %d ORDER BY created_at DESC LIMIT 1",
                $user->ID
            ));
            if ($last_edit) $last_actions[] = $last_edit;

            // Last vote
            $last_vote = $wpdb->get_var($wpdb->prepare(
                "SELECT created_at FROM $table_votes WHERE user_id = %d ORDER BY created_at DESC LIMIT 1",
                $user->ID
            ));
            if ($last_vote) $last_actions[] = $last_vote;

            // Last report
            $last_report = $wpdb->get_var($wpdb->prepare(
                "SELECT created_at FROM $table_reports WHERE user_id = %d ORDER BY created_at DESC LIMIT 1",
                $user->ID
            ));
            if ($last_report) $last_actions[] = $last_report;

            // Get the most recent action
            $last_action = !empty($last_actions) ? max($last_actions) : null;

            $account_status = get_user_meta($user->ID, 'jg_map_account_status', true);
            $email_sent_at  = get_user_meta($user->ID, 'jg_map_email_sent_at', true);
            $activated_at   = get_user_meta($user->ID, 'jg_map_activated_at', true);

            $user_stats[$user->ID] = array(
                'points' => $points_count,
                'pending' => $pending_count,
                'ban_status' => $ban_status,
                'ban_until' => $ban_until,
                'restrictions' => $restrictions,
                'last_login' => $last_login,
                'last_action' => $last_action,
                'account_status' => $account_status,
                'email_sent_at'  => $email_sent_at,
                'activated_at'   => $activated_at,
            );
        }

        ?>
        <style>
        /* ===== JG Admin — zarządzanie użytkownikami ===== */

        /* Info box */
        .jg-info-box{background:#fff7e6;border:2px solid #f59e0b;padding:15px 18px;border-radius:10px;margin:16px 0}
        .jg-info-box h3{margin:0 0 8px}

        /* Blocked IPs — scroll on mobile */
        .jg-table-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch;border-radius:8px}

        /* Main users table */
        .jg-user-table{width:100%;border-collapse:collapse;font-size:13px}
        .jg-user-table th{background:#f8fafc;padding:10px 12px;text-align:left;font-size:11px;font-weight:700;color:#374151;border-bottom:2px solid #e5e7eb;white-space:nowrap;text-transform:uppercase;letter-spacing:.4px}
        .jg-user-table td{padding:10px 12px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
        .jg-user-table tbody tr:last-child td{border-bottom:none}
        .jg-user-table tbody tr:hover{background:#f8fafc}
        .jg-user-table-wrap{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.06)}

        /* Action buttons */
        .jg-action-btns{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
        .jg-action-btns form{margin:0}

        /* ===== MOBILE — karty ===== */
        @media(max-width:782px){
            /* ukryj header tabeli, każdy <tr> staje się kartą */
            .jg-user-table thead{display:none}
            .jg-user-table tbody{display:flex;flex-direction:column;gap:10px;padding:10px}
            .jg-user-table tbody tr{
                display:grid;
                grid-template-columns:1fr 1fr;
                gap:0;
                background:#fff;
                border:1px solid #e5e7eb;
                border-radius:12px;
                overflow:hidden;
                box-shadow:0 1px 3px rgba(0,0,0,.06)
            }
            .jg-user-table-wrap{border-radius:12px;overflow:visible;box-shadow:none;border:none;background:transparent}
            .jg-user-table{background:transparent}

            /* każda komórka = wiersz z etykietą */
            .jg-user-table td{
                display:flex;
                flex-direction:column;
                padding:10px 12px;
                border-bottom:1px solid #f1f5f9;
                border-right:none;
                font-size:13px;
                line-height:1.4
            }
            .jg-user-table td::before{
                content:attr(data-label);
                font-weight:700;
                font-size:10px;
                color:#9ca3af;
                text-transform:uppercase;
                letter-spacing:.5px;
                margin-bottom:3px
            }

            /* Użytkownik i Akcje — pełna szerokość */
            .jg-td-user,.jg-td-actions{grid-column:1 / -1}
            .jg-td-user{background:#f8fafc;border-bottom:2px solid #e5e7eb}
            .jg-td-actions{background:#f8fafc;border-top:2px solid #e5e7eb;border-bottom:none}

            /* ID — ukryj na mobile (mały i nieważny) */
            .jg-td-id{display:none}

            /* Przyciski na pełną szerokość */
            .jg-action-btns{flex-direction:column;width:100%}
            .jg-action-btns .button,.jg-action-btns form,.jg-action-btns form button{width:100%;box-sizing:border-box;text-align:center;justify-content:center}
            .jg-action-btns .button,.jg-action-btns form button{padding:10px;font-size:14px}
        }
        </style>
        <div class="wrap">
            <?php $this->render_page_header('Zarządzanie użytkownikami'); ?>

            <div class="jg-info-box">
                <h3>ℹ️ Zarządzanie użytkownikami:</h3>
                <ul>
                    <li>Zobacz statystyki aktywności użytkowników</li>
                    <li>Zarządzaj banami i blokadami</li>
                    <li>Przypisuj role moderatorów</li>
                </ul>
            </div>

            <?php if (!empty($blocked_ips)): ?>
                <div style="background:#fee2e2;border:2px solid #dc2626;padding:15px;border-radius:8px;margin:20px 0">
                    <h2 style="margin-top:0;color:#991b1b">🔒 Zablokowane adresy IP (nieudane próby logowania)</h2>
                    <p style="color:#7f1d1d;margin-bottom:15px">
                        Poniżej znajdują się adresy IP zablokowane po 5 nieudanych próbach logowania.
                        Blokada trwa 15 minut od pierwszej próby.
                    </p>
                    <div class="jg-table-scroll">
                    <table class="wp-list-table widefat fixed striped" style="margin-top:10px;min-width:560px">
                        <thead>
                            <tr>
                                <th style="width:15%">Adres IP</th>
                                <th style="width:20%">Nazwa użytkownika</th>
                                <th style="width:20%">Email</th>
                                <th style="width:10%">Próby</th>
                                <th style="width:15%">Zablokowano</th>
                                <th style="width:12%">Pozostało</th>
                                <th style="width:8%">Akcje</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($blocked_ips as $ip):
                                $minutes_remaining = ceil($ip['time_remaining'] / 60);
                                $blocked_time = get_date_from_gmt(date('Y-m-d H:i:s', $ip['blocked_at']), 'Y-m-d H:i:s');
                            ?>
                                <tr data-ip-hash="<?php echo esc_attr($ip['hash']); ?>" data-ip-type="login">
                                    <td><code style="background:#fff;padding:4px 8px;border-radius:4px;font-size:calc(11 * var(--jg));font-weight:700"><?php echo esc_html($ip['ip']); ?></code></td>
                                    <td><strong><?php echo esc_html($ip['username']); ?></strong></td>
                                    <td><?php echo esc_html($ip['email']); ?></td>
                                    <td><strong style="color:#dc2626"><?php echo $ip['attempts']; ?></strong></td>
                                    <td><?php echo esc_html($blocked_time); ?></td>
                                    <td>
                                        <?php if ($ip['time_remaining'] > 0): ?>
                                            <span style="background:#fbbf24;color:#000;padding:4px 8px;border-radius:4px;font-size:calc(12 * var(--jg));font-weight:700">
                                                <?php echo $minutes_remaining; ?> min
                                            </span>
                                        <?php else: ?>
                                            <span style="background:#10b981;color:#fff;padding:4px 8px;border-radius:4px;font-size:calc(12 * var(--jg))">
                                                Wygasło
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="button button-small jg-unblock-ip"
                                                data-ip-hash="<?php echo esc_attr($ip['hash']); ?>"
                                                data-ip-type="login"
                                                style="background:#10b981;color:#fff;border-color:#10b981">
                                            Odblokuj
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($blocked_registration_ips)): ?>
                <div style="background:#fef3c7;border:2px solid #f59e0b;padding:15px;border-radius:8px;margin:20px 0">
                    <h2 style="margin-top:0;color:#92400e">⚠️ Zablokowane adresy IP (nieudane próby rejestracji)</h2>
                    <p style="color:#78350f;margin-bottom:15px">
                        Poniżej znajdują się adresy IP zablokowane po 3 nieudanych próbach rejestracji.
                        Blokada trwa 1 godzinę od pierwszej próby.
                    </p>
                    <div class="jg-table-scroll">
                    <table class="wp-list-table widefat fixed striped" style="margin-top:10px;min-width:560px">
                        <thead>
                            <tr>
                                <th style="width:15%">Adres IP</th>
                                <th style="width:20%">Nazwa użytkownika</th>
                                <th style="width:20%">Email</th>
                                <th style="width:10%">Próby</th>
                                <th style="width:15%">Zablokowano</th>
                                <th style="width:12%">Pozostało</th>
                                <th style="width:8%">Akcje</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($blocked_registration_ips as $ip):
                                $minutes_remaining = ceil($ip['time_remaining'] / 60);
                                $blocked_time = get_date_from_gmt(date('Y-m-d H:i:s', $ip['blocked_at']), 'Y-m-d H:i:s');
                            ?>
                                <tr data-ip-hash="<?php echo esc_attr($ip['hash']); ?>" data-ip-type="register">
                                    <td><code style="background:#fff;padding:4px 8px;border-radius:4px;font-size:calc(11 * var(--jg));font-weight:700"><?php echo esc_html($ip['ip']); ?></code></td>
                                    <td><strong><?php echo esc_html($ip['username']); ?></strong></td>
                                    <td><?php echo esc_html($ip['email']); ?></td>
                                    <td><strong style="color:#f59e0b"><?php echo $ip['attempts']; ?></strong></td>
                                    <td><?php echo esc_html($blocked_time); ?></td>
                                    <td>
                                        <?php if ($ip['time_remaining'] > 0): ?>
                                            <span style="background:#fbbf24;color:#000;padding:4px 8px;border-radius:4px;font-size:calc(12 * var(--jg));font-weight:700">
                                                <?php echo $minutes_remaining; ?> min
                                            </span>
                                        <?php else: ?>
                                            <span style="background:#10b981;color:#fff;padding:4px 8px;border-radius:4px;font-size:calc(12 * var(--jg))">
                                                Wygasło
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="button button-small jg-unblock-ip"
                                                data-ip-hash="<?php echo esc_attr($ip['hash']); ?>"
                                                data-ip-type="register"
                                                style="background:#10b981;color:#fff;border-color:#10b981">
                                            Odblokuj
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            <?php endif; ?>

            <h2>Wszyscy użytkownicy</h2>
            <div class="jg-user-table-wrap">
            <table class="jg-user-table">
                <thead>
                    <tr>
                        <th style="width:3%">ID</th>
                        <th style="width:14%">Użytkownik</th>
                        <th style="width:7%">Miejsca</th>
                        <th style="width:8%">Rejestracja</th>
                        <th style="width:8%">Ostatnie log.</th>
                        <th style="width:8%">Ost. akcja</th>
                        <th style="width:11%">Status konta</th>
                        <th style="width:8%">Email</th>
                        <th style="width:8%">Aktywacja</th>
                        <th style="width:9%">Ban</th>
                        <th style="width:8%">Blokady</th>
                        <th style="width:8%">Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user):
                        $stats = $user_stats[$user->ID];
                        $is_banned = JG_Map_Ajax_Handlers::is_user_banned($user->ID);
                        ?>
                        <tr>
                            <td class="jg-td-id" data-label="ID"><?php echo $user->ID; ?></td>
                            <td class="jg-td-user" data-label="Użytkownik">
                                <strong><?php echo esc_html($user->display_name); ?></strong>
                                <br><small style="color:#666"><?php echo esc_html($user->user_email); ?></small>
                                <br><small style="color:#aaa">#<?php echo $user->ID; ?></small>
                            </td>
                            <td data-label="Miejsca">
                                <span style="background:#e5e7eb;padding:3px 7px;border-radius:4px"><?php echo $stats['points']; ?> opubl.</span>
                                <?php if ($stats['pending'] > 0): ?>
                                    <br><span style="background:#fbbf24;padding:3px 7px;border-radius:4px;margin-top:4px;display:inline-block"><?php echo $stats['pending']; ?> oczek.</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Rejestracja">
                                <?php echo get_date_from_gmt($user->user_registered, 'd.m.Y'); ?>
                                <br><small style="color:#666"><?php echo get_date_from_gmt($user->user_registered, 'H:i'); ?></small>
                            </td>
                            <td data-label="Ostatnie log.">
                                <?php if (!empty($stats['last_login'])): ?>
                                    <?php echo get_date_from_gmt($stats['last_login'], 'd.m.Y'); ?>
                                    <br><small style="color:#666"><?php echo get_date_from_gmt($stats['last_login'], 'H:i'); ?></small>
                                <?php else: ?>
                                    <span style="color:#bbb">—</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Ost. akcja">
                                <?php if (!empty($stats['last_action'])): ?>
                                    <?php echo get_date_from_gmt($stats['last_action'], 'd.m.Y'); ?>
                                    <br><small style="color:#666"><?php echo get_date_from_gmt($stats['last_action'], 'H:i'); ?></small>
                                <?php else: ?>
                                    <span style="color:#bbb">—</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Status konta">
                                <?php
                                $acc_status = $stats['account_status'];
                                if ($acc_status === 'active') {
                                    echo '<span style="color:#16a34a;font-weight:600">✔ Aktywny</span>';
                                } elseif ($acc_status === 'pending') {
                                    echo '<span style="color:#d97706;font-weight:600">⏳ Oczekuje</span>';
                                } else {
                                    echo '<span style="color:#6b7280" title="Konto istniało przed wprowadzeniem weryfikacji email">✔ Aktywny*</span>';
                                }
                                ?>
                            </td>
                            <td data-label="Email wysłany">
                                <?php if (!empty($stats['email_sent_at'])): ?>
                                    <?php echo get_date_from_gmt(date('Y-m-d H:i:s', (int)$stats['email_sent_at']), 'd.m.Y'); ?>
                                    <br><small style="color:#666"><?php echo get_date_from_gmt(date('Y-m-d H:i:s', (int)$stats['email_sent_at']), 'H:i'); ?></small>
                                <?php else: ?>
                                    <span style="color:#bbb">—</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Aktywacja">
                                <?php if (!empty($stats['activated_at'])): ?>
                                    <?php echo get_date_from_gmt(date('Y-m-d H:i:s', (int)$stats['activated_at']), 'd.m.Y'); ?>
                                    <br><small style="color:#666"><?php echo get_date_from_gmt(date('Y-m-d H:i:s', (int)$stats['activated_at']), 'H:i'); ?></small>
                                <?php else: ?>
                                    <span style="color:#bbb">—</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Ban">
                                <?php if ($is_banned): ?>
                                    <?php if ($stats['ban_status'] === 'permanent'): ?>
                                        <span style="background:#dc2626;color:#fff;padding:3px 7px;border-radius:4px;font-weight:700;font-size:12px">🚫 Perm.</span>
                                    <?php else: ?>
                                        <span style="background:#dc2626;color:#fff;padding:3px 7px;border-radius:4px;font-weight:700;font-size:12px">🚫 <?php echo get_date_from_gmt($stats['ban_until'], 'd.m.Y'); ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="background:#10b981;color:#fff;padding:3px 7px;border-radius:4px;font-size:12px">✓ OK</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Blokady">
                                <?php if (!empty($stats['restrictions'])): ?>
                                    <?php
                                    $restriction_labels = array(
                                        'voting' => 'głosowanie',
                                        'add_places' => 'dodawanie miejsc',
                                        'add_events' => 'wydarzenia',
                                        'add_trivia' => 'ciekawostki',
                                        'edit_places' => 'edycja'
                                    );
                                    foreach ($stats['restrictions'] as $r): ?>
                                        <span style="background:#f59e0b;color:#fff;padding:2px 6px;border-radius:4px;font-size:11px;margin:2px;display:inline-block">⚠️ <?php echo $restriction_labels[$r] ?? $r; ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span style="color:#bbb">Brak</span>
                                <?php endif; ?>
                            </td>
                            <td class="jg-td-actions">
                                <div class="jg-action-btns">
                                    <?php
                                    $is_protected = user_can($user->ID, 'manage_options') || user_can($user->ID, 'jg_map_moderate');
                                    ?>
                                    <button class="button button-small jg-manage-user"
                                            data-user-id="<?php echo $user->ID; ?>"
                                            data-user-name="<?php echo esc_attr($user->display_name); ?>"
                                            data-ban-status="<?php echo esc_attr($stats['ban_status']); ?>"
                                            data-restrictions='<?php echo esc_attr(json_encode($stats['restrictions'])); ?>'
                                            data-is-protected="<?php echo $is_protected ? '1' : '0'; ?>">
                                        Zarządzaj
                                    </button>
                                    <?php if ($stats['account_status'] === 'pending'): ?>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                            <input type="hidden" name="action" value="jg_map_activate_user">
                                            <input type="hidden" name="user_id" value="<?php echo $user->ID; ?>">
                                            <?php wp_nonce_field('jg_map_activate_user_' . $user->ID, 'jg_map_activate_nonce'); ?>
                                            <button type="submit" class="button button-small" style="background:#16a34a;color:#fff;border-color:#16a34a">
                                                ✔ Aktywuj ręcznie
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div><!-- /.jg-user-table-wrap -->

            <!-- Modal for user management -->
            <div id="jg-user-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:9999;align-items:center;justify-content:center;">
                <div style="background:#fff;padding:20px;border-radius:8px;max-width:600px;width:90%;max-height:80vh;overflow:auto;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                        <h2 id="jg-user-modal-title" style="margin:0">Zarządzanie użytkownikiem</h2>
                        <button id="jg-user-modal-close" style="background:#dc2626;color:#fff;border:none;border-radius:4px;padding:8px 16px;cursor:pointer;font-weight:700;">✕</button>
                    </div>

                    <div id="jg-user-current-status" style="margin-bottom:20px;padding:12px;background:#f5f5f5;border-radius:8px;"></div>

                    <div id="jg-ban-section" style="margin-bottom:20px;">
                        <h3>Bany</h3>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <button class="button jg-ban-permanent">Ban permanentny</button>
                            <button class="button jg-ban-temporary">Ban czasowy</button>
                            <button class="button jg-unban" style="background:#10b981;color:#fff;border-color:#10b981;">Usuń ban</button>
                        </div>
                    </div>

                    <div id="jg-restriction-section" style="margin-bottom:20px;">
                        <h3>Blokady</h3>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                            <button class="button jg-toggle-restriction" data-type="voting">Głosowanie</button>
                            <button class="button jg-toggle-restriction" data-type="add_places">Dodawanie miejsc</button>
                            <button class="button jg-toggle-restriction" data-type="add_events">Wydarzenia</button>
                            <button class="button jg-toggle-restriction" data-type="add_trivia">Ciekawostki</button>
                            <button class="button jg-toggle-restriction" data-type="edit_places">Edycja miejsc</button>
                            <button class="button jg-toggle-restriction" data-type="photo_upload">Przesyłanie zdjęć</button>
                        </div>
                    </div>

                    <div style="margin-bottom:20px;">
                        <h3>Limity dzienne (tymczasowe)</h3>
                        <p style="font-size:calc(12 * var(--jg));color:#666;margin:8px 0">Zmiany obowiązują tylko do północy. O północy limity są automatycznie resetowane do domyślnych wartości (5/5).</p>
                        <div id="jg-current-limits" style="background:#f0f9ff;padding:12px;border-radius:8px;margin-bottom:12px;border:2px solid #3b82f6;">
                            <strong>Aktualne limity:</strong>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:8px;">
                                <div style="text-align:center;background:#fff;padding:8px;border-radius:6px;">
                                    <div style="font-size:calc(24 * var(--jg));font-weight:700;color:#3b82f6;" id="limit-places-display">-</div>
                                    <div style="font-size:calc(11 * var(--jg));color:#666;">miejsc/ciekawostek</div>
                                </div>
                                <div style="text-align:center;background:#fff;padding:8px;border-radius:6px;">
                                    <div style="font-size:calc(24 * var(--jg));font-weight:700;color:#3b82f6;" id="limit-reports-display">-</div>
                                    <div style="font-size:calc(11 * var(--jg));color:#666;">zgłoszeń</div>
                                </div>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:8px;">
                            <div>
                                <label style="display:block;font-weight:600;margin-bottom:4px;">Miejsca/Ciekawostki:</label>
                                <input type="number" id="limit-places-input" min="0" max="999" value="5" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:4px;">
                            </div>
                            <div>
                                <label style="display:block;font-weight:600;margin-bottom:4px;">Zgłoszenia:</label>
                                <input type="number" id="limit-reports-input" min="0" max="999" value="5" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:4px;">
                            </div>
                        </div>
                        <div style="display:flex;gap:8px;">
                            <button class="button button-primary jg-set-limits">Ustaw limity</button>
                            <button class="button jg-reset-limits">Reset do domyślnych (5/5)</button>
                        </div>
                    </div>

                    <!-- Daily Edit Limit -->
                    <div style="background:#fef3c7;padding:16px;border-radius:8px;margin-top:16px;border:2px solid #f59e0b;">
                        <h3 style="margin:0 0 12px 0;font-size:calc(14 * var(--jg));color:#78350f;">✏️ Dzienny limit edycji miejsc</h3>
                        <p style="font-size:calc(12 * var(--jg));color:#92400e;margin:0 0 12px 0">Użytkownik może wykonać maksymalnie 2 edycje na dobę (wszystkie miejsca łącznie). Licznik resetuje się o północy.</p>
                        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px;margin-bottom:16px;">
                            <div style="text-align:center;background:#fff;padding:8px;border-radius:6px;">
                                <div style="font-size:calc(24 * var(--jg));font-weight:700;color:#f59e0b;" id="edit-count-display">-</div>
                                <div style="font-size:calc(11 * var(--jg));color:#666;">wykorzystano dzisiaj</div>
                            </div>
                            <div style="text-align:center;background:#fff;padding:8px;border-radius:6px;">
                                <div style="font-size:calc(24 * var(--jg));font-weight:700;color:#78350f;">2</div>
                                <div style="font-size:calc(11 * var(--jg));color:#666;">limit dzienny</div>
                            </div>
                        </div>
                        <div style="display:flex;gap:8px;">
                            <button class="button jg-reset-edit-limit" style="background:#10b981;color:#fff;border-color:#10b981;">Zresetuj licznik</button>
                        </div>
                    </div>

                    <!-- Monthly Photo Upload Limit -->
                    <div style="background:#f8fafc;padding:16px;border-radius:8px;margin-top:16px;">
                        <h3 style="margin:0 0 12px 0;font-size:calc(14 * var(--jg));color:#334155;">📸 Miesięczny limit przesyłania zdjęć</h3>
                        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px;margin-bottom:16px;">
                            <div style="text-align:center;background:#fff;padding:8px;border-radius:6px;">
                                <div style="font-size:calc(24 * var(--jg));font-weight:700;color:#8b5cf6;" id="photo-used-display">-</div>
                                <div style="font-size:calc(11 * var(--jg));color:#666;">wykorzystano (MB)</div>
                            </div>
                            <div style="text-align:center;background:#fff;padding:8px;border-radius:6px;">
                                <div style="font-size:calc(24 * var(--jg));font-weight:700;color:#3b82f6;" id="photo-limit-display">-</div>
                                <div style="font-size:calc(11 * var(--jg));color:#666;">limit (MB)</div>
                            </div>
                        </div>
                        <div style="margin-bottom:8px;">
                            <label style="display:block;font-weight:600;margin-bottom:4px;">Ustaw nowy limit (MB):</label>
                            <input type="number" id="photo-limit-input" min="1" max="10000" value="100" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:4px;">
                        </div>
                        <div style="display:flex;gap:8px;">
                            <button class="button button-primary jg-set-photo-limit">Ustaw limit</button>
                            <button class="button jg-reset-photo-limit">Reset do domyślnych (100MB)</button>
                        </div>
                    </div>

                    <!-- Delete User Profile -->
                    <div style="background:#fee2e2;padding:16px;border-radius:8px;margin-top:16px;border:2px solid #dc2626;">
                        <h3 style="margin:0 0 12px 0;font-size:calc(14 * var(--jg));color:#7f1d1d;">🗑️ Usuń profil użytkownika</h3>
                        <p style="font-size:calc(12 * var(--jg));color:#991b1b;margin:0 0 12px 0">
                            <strong>UWAGA:</strong> Ta operacja jest nieodwracalna! Zostaną usunięte wszystkie pinezki użytkownika, wszystkie przesłane zdjęcia oraz profil ze wszystkimi danymi.
                        </p>
                        <button class="button jg-delete-user-profile" style="background:#dc2626;color:#fff;border-color:#dc2626;font-weight:700;">
                            Usuń profil użytkownika
                        </button>
                    </div>

                    <div id="jg-user-message" style="margin-top:16px;padding:12px;border-radius:8px;display:none;"></div>
                </div>
            </div>

            <script>
            jQuery(document).ready(function($) {
                var modal = $('#jg-user-modal');
                var modalTitle = $('#jg-user-modal-title');
                var currentStatus = $('#jg-user-current-status');
                var message = $('#jg-user-message');
                var currentUserId = null;
                var currentRestrictions = [];
                var currentIsProtected = false;

                function applyProtectedState(isProtected) {
                    if (isProtected) {
                        var unlimitedBadge = '<div id="jg-unlimited-badge" style="background:#f0fdf4;border:2px solid #16a34a;padding:12px 16px;border-radius:8px;margin-bottom:16px;display:flex;align-items:center;gap:10px">' +
                            '<span style="font-size:20px">🛡️</span>' +
                            '<div><strong style="color:#15803d;font-size:14px">Administrator / Moderator</strong>' +
                            '<br><span style="color:#166534;font-size:12px">Ten użytkownik jest poza wszelkimi limitami i restrykcjami. Nie można go zbanować, blokować ani usunąć.</span></div>' +
                            '</div>';
                        if (!$('#jg-unlimited-badge').length) {
                            $('#jg-user-current-status').after(unlimitedBadge);
                        }
                        // Hide ban, restriction and delete sections
                        $('#jg-ban-section').hide();
                        $('#jg-restriction-section').hide();
                        $('.jg-delete-user-profile').closest('div[style*="fee2e2"]').hide();
                        // Show "unlimited" in limit displays
                        $('#limit-places-display').text('∞');
                        $('#limit-reports-display').text('∞');
                        $('#photo-used-display').text('0');
                        $('#photo-limit-display').text('∞');
                        $('#edit-count-display').text('∞');
                    } else {
                        $('#jg-unlimited-badge').remove();
                        $('#jg-ban-section').show();
                        $('#jg-restriction-section').show();
                        $('.jg-delete-user-profile').closest('div[style*="fee2e2"]').show();
                    }
                }

                $('.jg-manage-user').on('click', function() {
                    currentUserId = $(this).data('user-id');
                    var userName = $(this).data('user-name');
                    var banStatus = $(this).data('ban-status');
                    currentRestrictions = $(this).data('restrictions') || [];
                    currentIsProtected = $(this).data('is-protected') == '1';

                    modalTitle.text('Zarządzanie: ' + userName);

                    // Update current status display
                    var statusHtml = '<strong>Aktualny status:</strong><br>';
                    if (banStatus === 'permanent') {
                        statusHtml += '<span style="color:#dc2626">🚫 Ban permanentny</span>';
                    } else if (banStatus === 'temporary') {
                        statusHtml += '<span style="color:#dc2626">🚫 Ban czasowy</span>';
                    } else {
                        statusHtml += '<span style="color:#10b981">✓ Aktywny</span>';
                    }

                    if (currentRestrictions.length > 0) {
                        statusHtml += '<br><strong>Aktywne blokady:</strong> ' + currentRestrictions.join(', ');
                    }

                    currentStatus.html(statusHtml);

                    // Apply or remove protected (admin/mod) UI state
                    applyProtectedState(currentIsProtected);

                    // Update restriction button states
                    $('.jg-toggle-restriction').each(function() {
                        var type = $(this).data('type');
                        if (currentRestrictions.indexOf(type) !== -1) {
                            $(this).css({
                                'background': '#dc2626',
                                'color': '#fff',
                                'border-color': '#dc2626'
                            }).text($(this).text() + ' ✓');
                        } else {
                            $(this).css({
                                'background': '',
                                'color': '',
                                'border-color': ''
                            });
                        }
                    });

                    if (!currentIsProtected) {
                        // Fetch current daily limits
                        $.ajax({
                            url: ajaxurl,
                            method: 'POST',
                            data: {
                                action: 'jg_admin_get_user_limits',
                                user_id: currentUserId,
                                _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    var data = response.data;
                                    $('#limit-places-display').text(data.places_remaining + ' / ' + data.places_limit);
                                    $('#limit-reports-display').text(data.reports_remaining + ' / ' + data.reports_limit);
                                    $('#limit-places-input').val(data.places_limit);
                                    $('#limit-reports-input').val(data.reports_limit);
                                }
                            }
                        });

                        // Fetch monthly photo limits
                        $.ajax({
                            url: ajaxurl,
                            method: 'POST',
                            data: {
                                action: 'jg_admin_get_user_photo_limit',
                                user_id: currentUserId,
                                _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    var data = response.data;
                                    $('#photo-used-display').text(data.used_mb);
                                    $('#photo-limit-display').text(data.limit_mb);
                                    $('#photo-limit-input').val(data.limit_mb);
                                }
                            }
                        });

                        // Fetch daily edit limit
                        $.ajax({
                            url: ajaxurl,
                            method: 'POST',
                            data: {
                                action: 'jg_admin_get_user_edit_limit',
                                user_id: currentUserId,
                                _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    var data = response.data;
                                    $('#edit-count-display').text(data.edit_count + ' / 2');
                                }
                            }
                        });
                    }

                    modal.css('display', 'flex');
                });

                $('#jg-user-modal-close, #jg-user-modal').on('click', function(e) {
                    if (e.target === this) {
                        modal.hide();
                        message.hide();
                    }
                });

                function showMessage(text, isError) {
                    message.text(text)
                        .css('background', isError ? '#fee' : '#d1fae5')
                        .css('color', isError ? '#dc2626' : '#10b981')
                        .show();
                    setTimeout(function() {
                        if (!isError) {
                            location.reload();
                        }
                    }, 1500);
                }

                $('.jg-ban-permanent').on('click', function() {
                    if (!confirm('Czy na pewno zbanować użytkownika permanentnie?')) return;

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'jg_admin_ban_user',
                            user_id: currentUserId,
                            ban_type: 'permanent',
                            _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                        },
                        success: function(response) {
                            showMessage(response.success ? 'Użytkownik zbanowany permanentnie!' : response.data.message, !response.success);
                        }
                    });
                });

                $('.jg-ban-temporary').on('click', function() {
                    var days = prompt('Na ile dni zbanować użytkownika?', '7');
                    if (!days) return;

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'jg_admin_ban_user',
                            user_id: currentUserId,
                            ban_type: 'temporary',
                            ban_days: parseInt(days),
                            _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                        },
                        success: function(response) {
                            showMessage(response.success ? 'Użytkownik zbanowany na ' + days + ' dni!' : response.data.message, !response.success);
                        }
                    });
                });

                $('.jg-unban').on('click', function() {
                    if (!confirm('Czy na pewno usunąć ban?')) return;

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'jg_admin_unban_user',
                            user_id: currentUserId,
                            _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                        },
                        success: function(response) {
                            showMessage(response.success ? 'Ban usunięty!' : response.data.message, !response.success);
                        }
                    });
                });

                $('.jg-toggle-restriction').on('click', function() {
                    var type = $(this).data('type');
                    var btn = $(this);

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'jg_admin_toggle_user_restriction',
                            user_id: currentUserId,
                            restriction_type: type,
                            _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                showMessage(response.data.message, false);
                            } else {
                                showMessage(response.data.message, true);
                            }
                        }
                    });
                });

                // Set custom limits
                $('.jg-set-limits').on('click', function() {
                    var placesLimit = parseInt($('#limit-places-input').val());
                    var reportsLimit = parseInt($('#limit-reports-input').val());

                    if (isNaN(placesLimit) || isNaN(reportsLimit) || placesLimit < 0 || reportsLimit < 0) {
                        showMessage('Nieprawidłowe wartości limitów', true);
                        return;
                    }

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'jg_admin_set_user_limits',
                            user_id: currentUserId,
                            places_limit: placesLimit,
                            reports_limit: reportsLimit,
                            _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#limit-places-display').text(response.data.places_remaining + ' / ' + response.data.places_limit);
                                $('#limit-reports-display').text(response.data.reports_remaining + ' / ' + response.data.reports_limit);
                                showMessage('Limity ustawione pomyślnie!', false);
                            } else {
                                showMessage(response.data.message || 'Błąd', true);
                            }
                        }
                    });
                });

                // Reset limits to default
                $('.jg-reset-limits').on('click', function() {
                    if (!confirm('Zresetować limity do domyślnych wartości (5/5)?')) return;

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'jg_admin_set_user_limits',
                            user_id: currentUserId,
                            places_limit: 5,
                            reports_limit: 5,
                            _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#limit-places-display').text(response.data.places_remaining + ' / 5');
                                $('#limit-reports-display').text(response.data.reports_remaining + ' / 5');
                                $('#limit-places-input').val(5);
                                $('#limit-reports-input').val(5);
                                showMessage('Limity zresetowane do domyślnych!', false);
                            } else {
                                showMessage(response.data.message || 'Błąd', true);
                            }
                        }
                    });
                });

                // Set custom photo limit
                $('.jg-set-photo-limit').on('click', function() {
                    var photoLimit = parseInt($('#photo-limit-input').val());

                    if (isNaN(photoLimit) || photoLimit < 1) {
                        showMessage('Nieprawidłowa wartość limitu zdjęć', true);
                        return;
                    }

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'jg_admin_set_user_photo_limit',
                            user_id: currentUserId,
                            limit_mb: photoLimit,
                            _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#photo-limit-display').text(response.data.limit_mb);
                                showMessage('Limit zdjęć ustawiony pomyślnie!', false);
                            } else {
                                showMessage(response.data.message || 'Błąd', true);
                            }
                        }
                    });
                });

                // Reset photo limit to default
                $('.jg-reset-photo-limit').on('click', function() {
                    if (!confirm('Zresetować limit zdjęć do domyślnej wartości (100MB)?')) return;

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'jg_admin_reset_user_photo_limit',
                            user_id: currentUserId,
                            _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#photo-used-display').text(response.data.used_mb);
                                $('#photo-limit-display').text(response.data.limit_mb);
                                $('#photo-limit-input').val(response.data.limit_mb);
                                showMessage('Limit zdjęć zresetowany do domyślnego (100MB)!', false);
                            } else {
                                showMessage(response.data.message || 'Błąd', true);
                            }
                        }
                    });
                });

                // Reset daily edit limit
                $('.jg-reset-edit-limit').on('click', function() {
                    if (!confirm('Zresetować licznik edycji użytkownika?')) return;

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'jg_admin_reset_user_edit_limit',
                            user_id: currentUserId,
                            _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#edit-count-display').text(response.data.edit_count + ' / 2');
                                showMessage('Licznik edycji zresetowany!', false);
                            } else {
                                showMessage(response.data.message || 'Błąd', true);
                            }
                        }
                    });
                });

                // Delete user profile
                $('.jg-delete-user-profile').on('click', function() {
                    if (!currentUserId) {
                        showMessage('Nieprawidłowe ID użytkownika', true);
                        return;
                    }

                    var userName = modalTitle.text().replace('Zarządzanie: ', '');
                    if (!confirm('CZY NA PEWNO chcesz usunąć profil użytkownika "' + userName + '"?\n\nZostaną usunięte:\n• Wszystkie pinezki użytkownika\n• Wszystkie przesłane zdjęcia\n• Profil ze wszystkimi danymi\n\nTa operacja jest NIEODWRACALNA!')) {
                        return;
                    }

                    // Second confirmation with prompt
                    var confirmation = prompt('To jest ostatnie ostrzeżenie!\n\nUsunięcie użytkownika "' + userName + '" spowoduje trwałe usunięcie wszystkich jego danych.\n\nWpisz "TAK" aby potwierdzić:');
                    if (confirmation !== 'TAK') {
                        showMessage('Anulowano usuwanie użytkownika', false);
                        return;
                    }

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'jg_admin_delete_user',
                            user_id: currentUserId,
                            _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                modal.hide();
                                alert('Użytkownik został pomyślnie usunięty');
                                location.reload();
                            } else {
                                showMessage(response.data.message || 'Błąd podczas usuwania użytkownika', true);
                            }
                        },
                        error: function() {
                            showMessage('Wystąpił błąd podczas komunikacji z serwerem', true);
                        }
                    });
                });

                // Unblock IP address (login or registration)
                $('.jg-unblock-ip').on('click', function() {
                    var btn = $(this);
                    var ipHash = btn.data('ip-hash');
                    var ipType = btn.data('ip-type') || 'login'; // Default to login if not specified
                    var row = btn.closest('tr');

                    if (!confirm('Czy na pewno odblokować ten adres IP?')) return;

                    btn.prop('disabled', true).text('Odblokowywanie...');

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'jg_admin_unblock_ip',
                            ip_hash: ipHash,
                            ip_type: ipType,
                            _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                row.fadeOut(300, function() {
                                    $(this).remove();
                                    // If no more blocked IPs of this type, reload page to hide the section
                                    if ($('.jg-unblock-ip[data-ip-type="' + ipType + '"]').length === 0) {
                                        location.reload();
                                    }
                                });
                            } else {
                                alert(response.data.message || 'Błąd podczas odblokowywania');
                                btn.prop('disabled', false).text('Odblokuj');
                            }
                        },
                        error: function() {
                            alert('Błąd podczas odblokowywania');
                            btn.prop('disabled', false).text('Odblokuj');
                        }
                    });
                });
            });
            </script>
        </div>
        <?php
    }

}
