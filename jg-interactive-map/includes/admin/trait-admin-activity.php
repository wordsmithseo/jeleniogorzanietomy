<?php
/**
 * Trait: Activity Log admin page
 *
 * @package JG_Interactive_Map
 */

trait JG_Map_Admin_Activity {

    public function render_activity_log_page() {
        global $wpdb;
        $log_table = $wpdb->prefix . 'jg_map_activity_log';

        // Pagination
        $per_page = 50;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;

        // Filters
        $action_filter = isset($_GET['action_filter']) ? sanitize_text_field($_GET['action_filter']) : '';
        $user_filter = isset($_GET['user_filter']) ? intval($_GET['user_filter']) : 0;
        $role_filter = isset($_GET['role_filter']) ? sanitize_text_field($_GET['role_filter']) : '';

        // Build query
        $where = array('1=1');
        if ($action_filter) {
            $where[] = $wpdb->prepare('action = %s', $action_filter);
        }
        if ($user_filter) {
            $where[] = $wpdb->prepare('user_id = %d', $user_filter);
        }

        // Role filter - get user IDs by role (queries run once and shared between both branches)
        if ($role_filter === 'admin' || $role_filter === 'user') {
            $admin_users   = get_users(array('role__in' => array('administrator'), 'fields' => 'ID'));
            $mod_users     = get_users(array('capability' => 'jg_map_moderate', 'fields' => 'ID'));
            $admin_mod_ids = array_unique(array_merge($admin_users, $mod_users));
            if ($role_filter === 'admin') {
                if (!empty($admin_mod_ids)) {
                    $placeholders = implode(',', array_fill(0, count($admin_mod_ids), '%d'));
                    $where[] = $wpdb->prepare("user_id IN ($placeholders)", $admin_mod_ids);
                } else {
                    $where[] = '1=0';
                }
            } else { // 'user'
                if (!empty($admin_mod_ids)) {
                    $placeholders = implode(',', array_fill(0, count($admin_mod_ids), '%d'));
                    $where[] = $wpdb->prepare("user_id NOT IN ($placeholders)", $admin_mod_ids);
                }
            }
        }

        $where_clause = implode(' AND ', $where);

        // Get logs (LIMIT/OFFSET are intval-sanitized above, safe to interpolate)
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $logs = $wpdb->get_results(
            "SELECT * FROM $log_table WHERE $where_clause ORDER BY created_at DESC LIMIT $per_page OFFSET $offset",
            ARRAY_A
        );

        // PERFORMANCE OPTIMIZATION: Prime user cache for log authors to avoid N+1 queries
        if (!empty($logs) && function_exists('wp_prime_user_cache')) {
            $log_user_ids = array_unique(array_filter(array_column($logs, 'user_id')));
            if (!empty($log_user_ids)) {
                wp_prime_user_cache($log_user_ids);
            }
        }

        // Get total count
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $log_table WHERE $where_clause");
        $total_pages = ceil($total / $per_page);

        // Get unique actions for filter dropdown (capped at 200 — more than enough)
        $actions = $wpdb->get_col("SELECT DISTINCT action FROM $log_table ORDER BY action LIMIT 200");

        // Get users who have logged actions (capped at 500)
        $users_with_logs = $wpdb->get_results(
            "SELECT DISTINCT user_id FROM $log_table ORDER BY user_id LIMIT 500"
        );

        // PERFORMANCE OPTIMIZATION: Prime user cache for filter dropdown to avoid N+1 queries
        if (!empty($users_with_logs) && function_exists('wp_prime_user_cache')) {
            $filter_user_ids = array_unique(array_filter(array_column($users_with_logs, 'user_id')));
            if (!empty($filter_user_ids)) {
                wp_prime_user_cache($filter_user_ids);
            }
        }

        ?>
        <div class="wrap">
            <?php $this->render_page_header('Activity Log'); ?>

            <div class="jg-card jg-card-body" style="margin-bottom:20px">
                <form method="get" style="display:flex;gap:15px;align-items:flex-end;flex-wrap:wrap">
                    <input type="hidden" name="page" value="jg-map-activity-log">

                    <div>
                        <label style="display:block;margin-bottom:5px;font-weight:600">Filtruj po akcji:</label>
                        <select name="action_filter" style="padding:5px">
                            <option value="">Wszystkie akcje</option>
                            <?php foreach ($actions as $action): ?>
                                <option value="<?php echo esc_attr($action); ?>" <?php selected($action_filter, $action); ?>>
                                    <?php echo esc_html($action); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label style="display:block;margin-bottom:5px;font-weight:600">Filtruj po roli:</label>
                        <select name="role_filter" style="padding:5px">
                            <option value="">Wszystkie role</option>
                            <option value="admin" <?php selected($role_filter, 'admin'); ?>>Admin / Moderator</option>
                            <option value="user" <?php selected($role_filter, 'user'); ?>>Zwykli użytkownicy</option>
                        </select>
                    </div>

                    <div>
                        <label style="display:block;margin-bottom:5px;font-weight:600">Filtruj po użytkowniku:</label>
                        <select name="user_filter" style="padding:5px">
                            <option value="0">Wszyscy użytkownicy</option>
                            <?php foreach ($users_with_logs as $u):
                                $user = get_userdata($u->user_id);
                                if ($user):
                            ?>
                                <option value="<?php echo $u->user_id; ?>" <?php selected($user_filter, $u->user_id); ?>>
                                    <?php echo esc_html($user->display_name); ?> (ID: <?php echo $u->user_id; ?>)
                                </option>
                            <?php endif; endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="button">Filtruj</button>
                    <?php if ($action_filter || $user_filter || $role_filter): ?>
                        <a href="<?php echo admin_url('admin.php?page=jg-map-activity-log'); ?>" class="button">Wyczyść filtry</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (!empty($logs)): ?>
            <div class="jg-admin-table-wrap">
            <div class="jg-table-scroll">
            <table class="jg-admin-table">
                <thead>
                    <tr>
                        <th style="width:150px">Data</th>
                        <th style="width:120px">Użytkownik</th>
                        <th style="width:150px">Akcja</th>
                        <th style="width:100px">Typ obiektu</th>
                        <th style="width:80px">ID obiektu</th>
                        <th>Opis</th>
                        <th style="width:120px">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log):
                        $user = get_userdata($log['user_id']);
                        $user_name = $user ? $user->display_name : 'Użytkownik #' . $log['user_id'];
                        $is_admin_user = $user && (user_can($user->ID, 'manage_options') || user_can($user->ID, 'jg_map_moderate'));
                        $role_badge = $is_admin_user
                            ? '<span style="background:#d63638;color:#fff;padding:1px 6px;border-radius:3px;font-size:calc(11 * var(--jg));margin-left:4px">Admin</span>'
                            : '<span style="background:#2271b1;color:#fff;padding:1px 6px;border-radius:3px;font-size:calc(11 * var(--jg));margin-left:4px">User</span>';
                    ?>
                        <tr>
                            <td><?php echo esc_html(get_date_from_gmt($log['created_at'], 'Y-m-d H:i:s')); ?></td>
                            <td><?php echo esc_html($user_name); ?> <?php echo $role_badge; ?></td>
                            <td><strong><?php echo esc_html($log['action']); ?></strong></td>
                            <td><?php echo esc_html($log['object_type']); ?></td>
                            <td><?php echo esc_html($log['object_id'] ?: '-'); ?></td>
                            <td><?php echo esc_html($log['description']); ?></td>
                            <td><code><?php echo esc_html($log['ip_address']); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            </div>

            <div class="tablenav bottom" style="padding-top:10px">
                <div class="tablenav-pages">
                    <?php if ($total_pages > 1): ?>
                        <span class="displaying-num"><?php echo number_format($total); ?> wpisów</span>
                        <span class="pagination-links">
                            <?php for ($i = 1; $i <= $total_pages; $i++):
                                $url = add_query_arg(array(
                                    'page' => 'jg-map-activity-log',
                                    'paged' => $i,
                                    'action_filter' => $action_filter,
                                    'user_filter' => $user_filter,
                                    'role_filter' => $role_filter
                                ), admin_url('admin.php'));
                            ?>
                                <?php if ($i === $current_page): ?>
                                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a class="button" href="<?php echo esc_url($url); ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <p>Brak wpisów w activity log.</p>
            <?php endif; ?>
        </div>
        <?php
    }
}
