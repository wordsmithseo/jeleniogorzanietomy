<?php
/**
 * Trait JG_Map_Admin_Other
 * Role, usunięcia, tagi, menu nawigacyjne, SEO, konserwacja.
 */
trait JG_Map_Admin_Other {

    /**
     * Render roles page
     */
    public function render_roles_page() {
        // Handle role update
        if (isset($_POST['jg_update_roles']) && check_admin_referer('jg_roles_update', 'jg_roles_nonce')) {
            $user_id = intval($_POST['user_id'] ?? 0);
            $action = sanitize_text_field($_POST['role_action'] ?? '');
            $role_type = sanitize_text_field($_POST['role_type'] ?? 'moderator');

            if ($user_id && in_array($action, array('add', 'remove')) && in_array($role_type, array('moderator', 'test_user', 'plugin_admin'))) {
                // Only full WP admins can assign the plugin_admin role
                if ($role_type === 'plugin_admin' && !current_user_can('manage_options')) {
                    echo '<div class="notice notice-error"><p>Tylko administratorzy WordPress mogą nadawać uprawnienia administratora pluginu.</p></div>';
                } else {
                    $user = get_userdata($user_id);
                    if ($user) {
                        if ($role_type === 'moderator') {
                            if ($action === 'add') {
                                $user->add_cap('jg_map_moderate');
                                echo '<div class="notice notice-success"><p>Uprawnienia moderatora dodane!</p></div>';
                            } else {
                                $user->remove_cap('jg_map_moderate');
                                echo '<div class="notice notice-success"><p>Uprawnienia moderatora usunięte!</p></div>';
                            }
                        } elseif ($role_type === 'plugin_admin') {
                            if ($action === 'add') {
                                $user->add_cap('jg_map_admin');
                                // Plugin admin also gets moderator capability
                                $user->add_cap('jg_map_moderate');
                                echo '<div class="notice notice-success"><p>Uprawnienia administratora pluginu dodane!</p></div>';
                            } else {
                                $user->remove_cap('jg_map_admin');
                                $user->remove_cap('jg_map_moderate');
                                echo '<div class="notice notice-success"><p>Uprawnienia administratora pluginu usunięte!</p></div>';
                            }
                        } else { // test_user
                            if ($action === 'add') {
                                $user->add_cap('jg_map_bypass_maintenance');
                                echo '<div class="notice notice-success"><p>Użytkownik oznaczony jako testowy!</p></div>';
                            } else {
                                $user->remove_cap('jg_map_bypass_maintenance');
                                echo '<div class="notice notice-success"><p>Użytkownik przestał być testowym!</p></div>';
                            }
                        }
                    }
                }
            }
        }

        // Get all users
        $users = get_users(array('orderby' => 'registered', 'order' => 'DESC'));

        ?>
        <div class="wrap">
            <?php $this->render_page_header('Zarządzanie rolami użytkowników'); ?>

            <div class="jg-info-box">
                <h3>ℹ️ O rolach:</h3>
                <ul>
                    <li><strong>WP Admin</strong> - pełny dostęp do WordPressa i wszystkich funkcji pluginu (nadawany przez WP, nie można tu zmienić)</li>
                    <li><strong>Admin pluginu ⭐</strong> - pełny dostęp do wszystkich funkcji pluginu JG Map; nie ma dostępu do rdzennego WP admina</li>
                    <li><strong>Moderator JG Map 🛡️</strong> - może moderować miejsca, zgłoszenia i edycje; widzi tylko Dashboard i zarządzanie użytkownikami</li>
                    <li><strong>Użytkownik testowy</strong> - może logować się pomimo trybu konserwacji w Elementorze</li>
                    <li><strong>Użytkownik</strong> - może dodawać i edytować swoje miejsca</li>
                </ul>
                <p><strong>Uwaga:</strong> Tytuł "Admin pluginu" może nadawać tylko administrator WordPress.</p>
            </div>

            <div class="jg-admin-table-wrap"><div class="jg-table-scroll">
            <table class="jg-admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nazwa użytkownika</th>
                        <th>Email</th>
                        <th>Rola WordPress</th>
                        <th>Admin pluginu</th>
                        <th>Moderator</th>
                        <th>Użytkownik testowy</th>
                        <th>Poziom użytkownika</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user):
                        $is_wp_admin    = user_can($user->ID, 'manage_options');
                        $is_plugin_admin = !$is_wp_admin && user_can($user->ID, 'jg_map_admin');
                        $is_moderator   = user_can($user->ID, 'jg_map_moderate') && !$is_wp_admin && !$is_plugin_admin;
                        $is_test_user   = user_can($user->ID, 'jg_map_bypass_maintenance');
                        $roles = implode(', ', $user->roles);
                        $user_xp_data = JG_Map_Levels_Achievements::get_user_xp_data($user->ID);
                        $user_level = $user_xp_data['level'];
                        ?>
                        <tr>
                            <td><?php echo $user->ID; ?></td>
                            <td><strong><?php echo esc_html($user->display_name); ?></strong> (<?php echo esc_html($user->user_login); ?>)</td>
                            <td><?php echo esc_html($user->user_email); ?></td>
                            <td><?php echo esc_html(ucfirst($roles)); ?></td>
                            <td>
                                <?php if ($is_wp_admin): ?>
                                    <span style="background:#6b7280;color:#fff;padding:4px 8px;border-radius:4px;font-size:calc(12 * var(--jg))">WP Admin</span>
                                <?php elseif ($is_plugin_admin): ?>
                                    <span style="background:#7c3aed;color:#fff;padding:4px 8px;border-radius:4px;font-size:calc(12 * var(--jg))">✓ Tak</span>
                                <?php else: ?>
                                    <span style="background:#e5e7eb;color:#6b7280;padding:4px 8px;border-radius:4px;font-size:calc(12 * var(--jg))">Nie</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($is_wp_admin || $is_plugin_admin): ?>
                                    <span style="background:#10b981;color:#fff;padding:4px 8px;border-radius:4px;font-size:calc(12 * var(--jg))">✓ Auto</span>
                                <?php elseif ($is_moderator): ?>
                                    <span style="background:#3b82f6;color:#fff;padding:4px 8px;border-radius:4px;font-size:calc(12 * var(--jg))">✓ Tak</span>
                                <?php else: ?>
                                    <span style="background:#e5e7eb;color:#6b7280;padding:4px 8px;border-radius:4px;font-size:calc(12 * var(--jg))">Nie</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($is_wp_admin || $is_plugin_admin): ?>
                                    <span style="background:#10b981;color:#fff;padding:4px 8px;border-radius:4px;font-size:calc(12 * var(--jg))">✓ Auto</span>
                                <?php elseif ($is_test_user): ?>
                                    <span style="background:#f59e0b;color:#fff;padding:4px 8px;border-radius:4px;font-size:calc(12 * var(--jg))">✓ Tak</span>
                                <?php else: ?>
                                    <span style="background:#e5e7eb;color:#6b7280;padding:4px 8px;border-radius:4px;font-size:calc(12 * var(--jg))">Nie</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="background:#fbbf24;color:#78350f;padding:4px 10px;border-radius:4px;font-size:calc(12 * var(--jg));font-weight:700">Poz. <?php echo $user_level; ?></span>
                            </td>
                            <td>
                                <?php if ($is_wp_admin): ?>
                                    <em style="color:#6b7280">WP Admin (automatycznie)</em>
                                <?php else: ?>
                                    <?php if (current_user_can('manage_options')): ?>
                                    <!-- Plugin admin buttons (only full WP admins can assign) -->
                                    <form method="post" style="display:inline;margin-right:5px">
                                        <?php wp_nonce_field('jg_roles_update', 'jg_roles_nonce'); ?>
                                        <input type="hidden" name="user_id" value="<?php echo $user->ID; ?>">
                                        <input type="hidden" name="jg_update_roles" value="1">
                                        <input type="hidden" name="role_type" value="plugin_admin">
                                        <?php if ($is_plugin_admin): ?>
                                            <input type="hidden" name="role_action" value="remove">
                                            <button type="submit" class="button button-small" style="border-color:#7c3aed;color:#7c3aed" title="Usuń uprawnienia admina pluginu">❌ Admin pluginu</button>
                                        <?php else: ?>
                                            <input type="hidden" name="role_action" value="add">
                                            <button type="submit" class="button button-small" style="background:#7c3aed;border-color:#7c3aed;color:#fff" title="Nadaj uprawnienia admina pluginu">⭐ Admin pluginu</button>
                                        <?php endif; ?>
                                    </form>
                                    <?php endif; ?>

                                    <!-- Moderator buttons (hidden when user is plugin admin) -->
                                    <?php if (!$is_plugin_admin): ?>
                                    <form method="post" style="display:inline;margin-right:5px">
                                        <?php wp_nonce_field('jg_roles_update', 'jg_roles_nonce'); ?>
                                        <input type="hidden" name="user_id" value="<?php echo $user->ID; ?>">
                                        <input type="hidden" name="jg_update_roles" value="1">
                                        <input type="hidden" name="role_type" value="moderator">
                                        <?php if ($is_moderator): ?>
                                            <input type="hidden" name="role_action" value="remove">
                                            <button type="submit" class="button button-small" title="Usuń uprawnienia moderatora">❌ Moderator</button>
                                        <?php else: ?>
                                            <input type="hidden" name="role_action" value="add">
                                            <button type="submit" class="button button-small button-primary" title="Dodaj uprawnienia moderatora">➕ Moderator</button>
                                        <?php endif; ?>
                                    </form>
                                    <?php endif; ?>

                                    <!-- Test user buttons -->
                                    <form method="post" style="display:inline">
                                        <?php wp_nonce_field('jg_roles_update', 'jg_roles_nonce'); ?>
                                        <input type="hidden" name="user_id" value="<?php echo $user->ID; ?>">
                                        <input type="hidden" name="jg_update_roles" value="1">
                                        <input type="hidden" name="role_type" value="test_user">
                                        <?php if ($is_test_user): ?>
                                            <input type="hidden" name="role_action" value="remove">
                                            <button type="submit" class="button button-small" title="Usuń status testowy">❌ Testowy</button>
                                        <?php else: ?>
                                            <input type="hidden" name="role_action" value="add">
                                            <button type="submit" class="button button-small button-primary" title="Oznacz jako użytkownika testowego">➕ Testowy</button>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div></div>
        </div>
        <?php
    }

    /**
     * Render deletions page
     */
    public function render_deletions_page() {
        global $wpdb;
        $points_table = JG_Map_Database::get_points_table();

        // Get all deletion requests
        $deletions = $wpdb->get_results(
            "SELECT * FROM $points_table WHERE is_deletion_requested = 1 ORDER BY deletion_requested_at DESC",
            ARRAY_A
        );

        // PERFORMANCE OPTIMIZATION: Prime user cache to avoid N+1 queries
        if (!empty($deletions) && function_exists('wp_prime_user_cache')) {
            $author_ids = array_unique(array_filter(array_column($deletions, 'author_id')));
            if (!empty($author_ids)) {
                wp_prime_user_cache($author_ids);
            }
        }

        ?>
        <div class="wrap">
            <h1>Żądania usunięcia miejsc</h1>

            <?php if (!empty($deletions)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Tytuł</th>
                        <th>Typ</th>
                        <th>Autor</th>
                        <th>Powód</th>
                        <th>Data żądania</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deletions as $point):
                        $author = get_userdata($point['author_id']);
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($point['title']); ?></strong></td>
                            <td><?php echo esc_html($point['type']); ?></td>
                            <td><?php echo $author ? esc_html($author->display_name) : 'Nieznany'; ?></td>
                            <td><?php echo $point['deletion_reason'] ? esc_html($point['deletion_reason']) : '<em>Brak powodu</em>'; ?></td>
                            <td><?php echo human_time_diff(strtotime($point['deletion_requested_at'] . ' UTC'), time()); ?> temu</td>
                            <td>
                                <a href="<?php echo get_site_url(); ?>?jg_view_point=<?php echo $point['id']; ?>" class="button" target="_blank">Zobacz miejsce</a>
                                <button class="button button-primary jg-approve-deletion" data-id="<?php echo $point['id']; ?>">Zatwierdź usunięcie</button>
                                <button class="button jg-reject-deletion" data-id="<?php echo $point['id']; ?>">Odrzuć</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <script>
            jQuery(document).ready(function($) {
                // Approve deletion
                $('.jg-approve-deletion').on('click', function() {
                    if (!confirm('Na pewno usunąć to miejsce? Tej operacji nie można cofnąć!')) return;

                    var btn = $(this);
                    var pointId = btn.data('id');
                    btn.prop('disabled', true).text('Usuwanie...');

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'jg_admin_approve_deletion',
                            post_id: pointId,
                            _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Miejsce zostało usunięte!');
                                location.reload();
                            } else {
                                alert('Błąd: ' + (response.data.message || 'Nieznany błąd'));
                                btn.prop('disabled', false).text('Zatwierdź usunięcie');
                            }
                        },
                        error: function() {
                            alert('Błąd połączenia');
                            btn.prop('disabled', false).text('Zatwierdź usunięcie');
                        }
                    });
                });

                // Reject deletion
                $('.jg-reject-deletion').on('click', function() {
                    if (!confirm('Odrzucić żądanie usunięcia?')) return;

                    var btn = $(this);
                    var pointId = btn.data('id');
                    btn.prop('disabled', true).text('Odrzucanie...');

                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'jg_admin_reject_deletion',
                            post_id: pointId,
                            _ajax_nonce: '<?php echo wp_create_nonce('jg_map_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Żądanie usunięcia zostało odrzucone!');
                                location.reload();
                            } else {
                                alert('Błąd: ' + (response.data.message || 'Nieznany błąd'));
                                btn.prop('disabled', false).text('Odrzuć');
                            }
                        },
                        error: function() {
                            alert('Błąd połączenia');
                            btn.prop('disabled', false).text('Odrzuć');
                        }
                    });
                });
            });
            </script>
            <?php else: ?>
            <p>Brak żądań usunięcia! 🎉</p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render maintenance page
     */
    public function render_maintenance_page() {
        // Check if manual run was successful
        if (isset($_GET['maintenance_done'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Konserwacja bazy danych została uruchomiona pomyślnie!</p></div>';
        }

        // Get last maintenance info
        $last_maintenance = get_option('jg_map_last_maintenance', null);
        $next_scheduled = wp_next_scheduled(JG_Map_Maintenance::CRON_HOOK);

        ?>
        <div class="wrap">
            <?php $this->render_page_header('Konserwacja bazy danych'); ?>

            <div class="jg-card jg-card-body">
                <h2>Status automatycznej konserwacji</h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">Status crona:</th>
                        <td>
                            <?php if ($next_scheduled): ?>
                                <span style="color:#15803d;font-weight:700;">✓ Aktywny</span>
                            <?php else: ?>
                                <span style="color:#dc2626;font-weight:700;">✗ Nieaktywny</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Następne uruchomienie:</th>
                        <td>
                            <?php if ($next_scheduled): ?>
                                <?php echo date('Y-m-d H:i:s', $next_scheduled); ?> (za <?php echo human_time_diff($next_scheduled); ?>)
                            <?php else: ?>
                                Brak zaplanowanego uruchomienia
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Częstotliwość:</th>
                        <td>Raz dziennie (codziennie o tej samej porze)</td>
                    </tr>
                </table>

                <h3>Ostatnie uruchomienie</h3>
                <?php if ($last_maintenance): ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Data:</th>
                            <td><?php echo $last_maintenance['time']; ?> (<?php echo human_time_diff(strtotime($last_maintenance['time']), time()); ?> temu)</td>
                        </tr>
                        <tr>
                            <th scope="row">Czas wykonania:</th>
                            <td><?php echo $last_maintenance['execution_time']; ?> sekund</td>
                        </tr>
                        <tr>
                            <th scope="row">Wyniki:</th>
                            <td>
                                <ul style="margin:0;padding-left:20px;">
                                    <li>Usunięto <strong><?php echo $last_maintenance['results']['orphaned_votes']; ?></strong> osieroconych głosów</li>
                                    <li>Usunięto <strong><?php echo $last_maintenance['results']['orphaned_reports']; ?></strong> osieroconych raportów</li>
                                    <li>Usunięto <strong><?php echo $last_maintenance['results']['orphaned_history']; ?></strong> osieroconych wpisów historii</li>
                                    <li>Znaleziono <strong><?php echo $last_maintenance['results']['invalid_coords']; ?></strong> miejsc z nieprawidłowymi współrzędnymi</li>
                                    <li>Znaleziono <strong><?php echo $last_maintenance['results']['empty_content']; ?></strong> miejsc bez treści</li>
                                    <li>Wyłączono <strong><?php echo $last_maintenance['results']['expired_sponsors']; ?></strong> wygasłych sponsorowanych miejsc</li>
                                    <li>Usunięto <strong><?php echo $last_maintenance['results']['old_pending']; ?></strong> starych miejsc oczekujących (>30 dni)</li>
                                    <li>Zoptymalizowano <strong><?php echo $last_maintenance['results']['tables_optimized']; ?></strong> tabel bazy danych</li>
                                </ul>
                            </td>
                        </tr>
                    </table>
                <?php else: ?>
                    <p style="color:#666;">Konserwacja nie była jeszcze uruchamiana.</p>
                <?php endif; ?>

                <h3>Zadania konserwacyjne</h3>
                <p>Automatyczna konserwacja wykonuje następujące zadania:</p>
                <ul style="padding-left:20px;">
                    <li><strong>Czyszczenie osieroconych danych:</strong> Usuwanie głosów, raportów i historii dla usuniętych miejsc</li>
                    <li><strong>Walidacja współrzędnych:</strong> Sprawdzanie miejsc z nieprawidłowymi współrzędnymi (poza Polską: lat 49-55, lng 14-24)</li>
                    <li><strong>Walidacja treści:</strong> Oznaczanie miejsc bez tytułu lub opisu</li>
                    <li><strong>Wyłączanie wygasłych sponsorowań:</strong> Automatyczne wyłączanie miejsc sponsorowanych po terminie</li>
                    <li><strong>Czyszczenie starych pending:</strong> Usuwanie miejsc oczekujących dłużej niż 30 dni (z powiadomieniem autora)</li>
                    <li><strong>Optymalizacja bazy:</strong> Czyszczenie cache i optymalizacja tabel MySQL</li>
                </ul>

                <h3>Ręczne uruchomienie</h3>
                <p>Możesz ręcznie uruchomić konserwację w dowolnym momencie:</p>
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=jg-map-maintenance&jg_run_maintenance=1'), 'jg_maintenance'); ?>"
                   class="button button-primary"
                   onclick="return confirm('Czy na pewno chcesz uruchomić konserwację? Operacja może potrwać kilka sekund.');">
                    🔧 Uruchom konserwację teraz
                </a>

                <p style="margin-top:20px;padding:15px;background:#fef3c7;border-left:4px solid #f59e0b;color:#92400e;">
                    <strong>Uwaga:</strong> Ręczne uruchomienie konserwacji może chwilę potrwać. Strona zostanie automatycznie przeładowana po zakończeniu.
                </p>
            </div>

            <?php
            // XP sync success notice
            if (isset($_GET['xp_sync_done'])) {
                echo '<div class="notice notice-success is-dismissible" style="margin-top:20px"><p>Synchronizacja doświadczenia i osiągnięć zakończona pomyślnie!</p></div>';
            }
            $last_sync = get_option('jg_map_last_xp_sync', null);
            ?>

            <div class="jg-card jg-card-body">
                <h2>Synchronizacja doświadczenia i osiągnięć</h2>
                <p>Przelicz XP i odblokuj osiągnięcia na podstawie rzeczywistych akcji użytkowników w bazie danych.
                Używaj tej opcji gdy:</p>
                <ul style="padding-left:20px;">
                    <li>System poziomów został dodany do istniejącej instalacji (użytkownicy mieli konta przed wprowadzeniem poziomów)</li>
                    <li>Zmieniono ilość XP przyznawanych za poszczególne akcje i chcesz przeliczyć</li>
                    <li>Dodano nowe osiągnięcia i chcesz sprawdzić, kto już je spełnia</li>
                    <li>Dane XP wyglądają na niespójne z rzeczywistą aktywnością użytkowników</li>
                </ul>

                <?php if ($last_sync): ?>
                <h3>Ostatnia synchronizacja</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">Data:</th>
                        <td><?php echo $last_sync['time']; ?> (<?php echo human_time_diff(strtotime($last_sync['time']), time()); ?> temu)</td>
                    </tr>
                    <tr>
                        <th scope="row">Przeliczenie XP:</th>
                        <td>
                            Przetworzono <strong><?php echo $last_sync['xp']['users_processed']; ?></strong> użytkowników,
                            zaktualizowano <strong><?php echo $last_sync['xp']['users_updated']; ?></strong>,
                            przyznano łącznie <strong><?php echo number_format($last_sync['xp']['total_xp_awarded']); ?></strong> XP
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Osiągnięcia:</th>
                        <td>
                            Sprawdzono <strong><?php echo $last_sync['achievements']['users_checked']; ?></strong> użytkowników,
                            odblokowano <strong><?php echo $last_sync['achievements']['new_achievements_awarded']; ?></strong> nowych osiągnięć
                        </td>
                    </tr>
                </table>
                <?php endif; ?>

                <h3>Uruchom synchronizację</h3>
                <p>Przelicza XP od nowa na podstawie rzeczywistych danych (punkty, głosy, zdjęcia, raporty, edycje), a następnie odblokuje wszystkie osiągnięcia, których warunki użytkownicy już spełniają.</p>
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=jg-map-maintenance&jg_sync_xp=1'), 'jg_sync_xp'); ?>"
                   class="button button-primary"
                   onclick="return confirm('Czy na pewno chcesz przeliczyć XP i osiągnięcia dla wszystkich użytkowników? Istniejące dane XP zostaną nadpisane obliczeniami na podstawie rzeczywistych akcji.');">
                    Przelicz XP i osiągnięcia
                </a>

                <p style="margin-top:20px;padding:15px;background:#eff6ff;border-left:4px solid #3b82f6;color:#1e40af;">
                    <strong>Info:</strong> Przeliczenie nadpisze obecne XP użytkowników wartościami obliczonymi z ich rzeczywistych akcji.
                    Osiągnięcia odblokowane retroaktywnie nie wyświetlą powiadomień (aby nie spamować użytkowników).
                    XP za „codzienny login" nie jest możliwe do odtworzenia retroaktywnie.
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Render tag management page
     */
    public function render_tags_page() {
        ?>
        <div class="wrap">
            <?php $this->render_page_header('Zarządzanie tagami'); ?>

            <style>
                .jg-tags-manager { max-width: 900px; margin-top: 20px; }
                .jg-tags-manager .card { background: #fff; padding: 18px 20px; border-radius: 12px; border: 1px solid #e5e7eb; box-shadow: 0 1px 4px rgba(0,0,0,.06); margin-bottom: 20px; }
                .jg-tags-manager h2 { margin-top: 0; padding-bottom: 12px; border-bottom: 1px solid #e5e7eb; margin-bottom: 16px; font-size: 15px; font-weight: 700; color: #111827; }
                .jg-tags-search-wrap { position: relative; margin-bottom: 20px; }
                .jg-tags-search-input {
                    width: 100%; padding: 10px 14px; font-size: calc(14 * var(--jg));
                    border: 1px solid #ddd; border-radius: 6px;
                    box-sizing: border-box; transition: border-color 0.2s;
                }
                .jg-tags-search-input:focus { outline: none; border-color: #8d2324; box-shadow: 0 0 0 2px rgba(141,35,36,0.1); }
                .jg-tags-suggestions {
                    position: absolute; top: 100%; left: 0; right: 0; z-index: 100;
                    background: #fff; border: 1px solid #ddd; border-top: none;
                    border-radius: 0 0 6px 6px; max-height: 200px; overflow-y: auto;
                    display: none; box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                }
                .jg-tags-suggestions.visible { display: block; }
                .jg-tags-suggestion-item {
                    padding: 8px 14px; cursor: pointer; font-size: calc(13 * var(--jg));
                    border-bottom: 1px solid #f3f4f6; transition: background 0.15s;
                }
                .jg-tags-suggestion-item:last-child { border-bottom: none; }
                .jg-tags-suggestion-item:hover, .jg-tags-suggestion-item.active { background: #f3f4f6; }
                .jg-tags-suggestion-item mark { background: #fef3c7; padding: 0; border-radius: 2px; }
                .jg-tags-stats { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
                .jg-tags-stat {
                    background: #f9fafb; padding: 10px 16px; border-radius: 6px;
                    border: 1px solid #e5e7eb; font-size: calc(13 * var(--jg)); color: #666;
                }
                .jg-tags-stat strong { color: #333; font-size: calc(16 * var(--jg)); display: block; margin-bottom: 2px; }
                .jg-tags-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
                .jg-tags-table th {
                    text-align: left; padding: 10px 12px; background: #f9fafb;
                    border-bottom: 2px solid #e5e7eb; font-size: calc(13 * var(--jg)); color: #666;
                    font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;
                }
                .jg-tags-table td { padding: 10px 12px; border-bottom: 1px solid #f3f4f6; font-size: calc(14 * var(--jg)); }
                .jg-tags-table tr:hover td { background: #f9fafb; }
                .jg-tag-name { font-weight: 500; color: #333; }
                .jg-tag-count {
                    display: inline-flex; align-items: center; justify-content: center;
                    background: #e5e7eb; color: #374151; padding: 2px 10px;
                    border-radius: 12px; font-size: calc(12 * var(--jg)); font-weight: 600; min-width: 24px;
                }
                .jg-tag-actions { display: flex; gap: 6px; }
                .jg-tag-btn {
                    background: none; border: 1px solid #ddd; cursor: pointer;
                    padding: 5px 10px; border-radius: 4px; font-size: calc(12 * var(--jg));
                    transition: all 0.2s; display: inline-flex; align-items: center; gap: 4px;
                }
                .jg-tag-btn:hover { background: #f3f4f6; border-color: #999; }
                .jg-tag-btn.edit:hover { background: #eff6ff; border-color: #3b82f6; color: #1d4ed8; }
                .jg-tag-btn.delete:hover { background: #fef2f2; border-color: #ef4444; color: #dc2626; }
                .jg-tags-pagination {
                    display: flex; align-items: center; justify-content: center;
                    gap: 4px; margin-top: 16px;
                }
                .jg-tags-pagination button {
                    padding: 6px 12px; border: 1px solid #ddd; background: #fff;
                    border-radius: 4px; cursor: pointer; font-size: calc(13 * var(--jg)); transition: all 0.2s;
                    min-width: 36px;
                }
                .jg-tags-pagination button:hover:not(:disabled) { background: #f3f4f6; border-color: #999; }
                .jg-tags-pagination button:disabled { opacity: 0.4; cursor: not-allowed; }
                .jg-tags-pagination button.current { background: #8d2324; color: #fff; border-color: #8d2324; }
                .jg-tags-pagination .page-info { font-size: calc(13 * var(--jg)); color: #666; margin: 0 8px; }
                .jg-tags-empty { text-align: center; padding: 40px 20px; color: #999; font-size: calc(14 * var(--jg)); }
                .jg-tags-loading { text-align: center; padding: 30px; color: #666; }
                .jg-tag-edit-row td { background: #fffbeb !important; }
                .jg-tag-edit-input {
                    padding: 6px 10px; border: 1px solid #f59e0b; border-radius: 4px;
                    font-size: calc(14 * var(--jg)); width: 250px; outline: none;
                }
                .jg-tag-edit-input:focus { border-color: #d97706; box-shadow: 0 0 0 2px rgba(245,158,11,0.2); }
                .jg-tag-btn.save { background: #059669; color: #fff; border-color: #059669; }
                .jg-tag-btn.save:hover { background: #047857; }
                .jg-tag-btn.cancel { background: #6b7280; color: #fff; border-color: #6b7280; }
                .jg-tag-btn.cancel:hover { background: #4b5563; }
                .jg-tags-toast {
                    position: fixed; bottom: 30px; right: 30px; z-index: 9999;
                    padding: 12px 20px; border-radius: 8px; color: #fff; font-size: calc(14 * var(--jg));
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15); opacity: 0;
                    transform: translateY(10px); transition: all 0.3s;
                }
                .jg-tags-toast.visible { opacity: 1; transform: translateY(0); }
                .jg-tags-toast.success { background: #059669; }
                .jg-tags-toast.error { background: #dc2626; }
                .jg-confirm-overlay {
                    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
                    background: rgba(0,0,0,0.5); z-index: 9998;
                    display: flex; align-items: center; justify-content: center;
                }
                .jg-confirm-box {
                    background: #fff; border-radius: 12px; padding: 24px; max-width: 420px;
                    width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                }
                .jg-confirm-box h3 { margin: 0 0 8px; font-size: calc(16 * var(--jg)); color: #333; }
                .jg-confirm-box p { margin: 0 0 20px; font-size: calc(14 * var(--jg)); color: #666; line-height: 1.5; }
                .jg-confirm-box .jg-btn-row { display: flex; gap: 10px; justify-content: flex-end; }
                .jg-confirm-box button {
                    padding: 8px 18px; border-radius: 6px; font-size: calc(13 * var(--jg));
                    cursor: pointer; border: 1px solid #ddd; transition: all 0.2s;
                }
                .jg-confirm-box .btn-cancel { background: #f3f4f6; color: #333; }
                .jg-confirm-box .btn-cancel:hover { background: #e5e7eb; }
                .jg-confirm-box .btn-danger { background: #dc2626; color: #fff; border-color: #dc2626; }
                .jg-confirm-box .btn-danger:hover { background: #b91c1c; }
            </style>

            <div class="jg-tags-manager">
                <div class="card">
                    <h2>Tagi</h2>
                    <p class="description">Zarządzaj tagami przypisanymi do miejsc na mapie. Możesz wyszukiwać, edytować nazwy i usuwać tagi.</p>

                    <div class="jg-tags-stats" id="jg-tags-stats"></div>

                    <div class="jg-tags-search-wrap">
                        <input type="text" class="jg-tags-search-input" id="jg-tags-search"
                               placeholder="Szukaj tagów..." autocomplete="off">
                        <div class="jg-tags-suggestions" id="jg-tags-suggestions"></div>
                    </div>

                    <div id="jg-tags-content">
                        <div class="jg-tags-loading">Ładowanie tagów...</div>
                    </div>

                    <div class="jg-tags-pagination" id="jg-tags-pagination"></div>
                </div>
            </div>

            <div id="jg-tags-toast" class="jg-tags-toast"></div>

            <script>
            (function() {
                const ajaxUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
                const nonce = '<?php echo wp_create_nonce('jg_map_nonce'); ?>';

                let currentPage = 1;
                let currentSearch = '';
                let allTagNames = [];
                let editingTag = null;
                let suggestionsActive = -1;
                let debounceTimer = null;

                // Initialize
                loadTags();
                loadSuggestions();

                // Search input
                const searchInput = document.getElementById('jg-tags-search');
                const suggestionsEl = document.getElementById('jg-tags-suggestions');

                searchInput.addEventListener('input', function() {
                    const val = this.value.trim();
                    showSuggestions(val);

                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(function() {
                        currentSearch = val;
                        currentPage = 1;
                        loadTags();
                    }, 300);
                });

                searchInput.addEventListener('keydown', function(e) {
                    const items = suggestionsEl.querySelectorAll('.jg-tags-suggestion-item');
                    if (!items.length) return;

                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        suggestionsActive = Math.min(suggestionsActive + 1, items.length - 1);
                        updateActiveSuggestion(items);
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        suggestionsActive = Math.max(suggestionsActive - 1, 0);
                        updateActiveSuggestion(items);
                    } else if (e.key === 'Enter' && suggestionsActive >= 0) {
                        e.preventDefault();
                        items[suggestionsActive].click();
                    } else if (e.key === 'Escape') {
                        hideSuggestions();
                    }
                });

                searchInput.addEventListener('focus', function() {
                    if (this.value.trim()) showSuggestions(this.value.trim());
                });

                document.addEventListener('click', function(e) {
                    if (!e.target.closest('.jg-tags-search-wrap')) {
                        hideSuggestions();
                    }
                });

                function updateActiveSuggestion(items) {
                    items.forEach(function(item, i) {
                        item.classList.toggle('active', i === suggestionsActive);
                    });
                    if (suggestionsActive >= 0 && items[suggestionsActive]) {
                        items[suggestionsActive].scrollIntoView({ block: 'nearest' });
                    }
                }

                function removeDiacritics(str) {
                    return str.normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/ł/g, 'l').replace(/Ł/g, 'L');
                }

                function showSuggestions(query) {
                    if (!query || query.length < 1) {
                        hideSuggestions();
                        return;
                    }
                    const normalizedQuery = removeDiacritics(query.toLowerCase());
                    const matches = allTagNames.filter(function(t) {
                        return removeDiacritics(t.toLowerCase()).indexOf(normalizedQuery) !== -1;
                    }).slice(0, 10);

                    if (!matches.length) {
                        hideSuggestions();
                        return;
                    }

                    suggestionsEl.innerHTML = matches.map(function(tag) {
                        var normalizedTag = removeDiacritics(tag.toLowerCase());
                        var idx = normalizedTag.indexOf(normalizedQuery);
                        let html = '';
                        if (idx >= 0) {
                            html = escHtml(tag.substring(0, idx)) +
                                   '<mark>' + escHtml(tag.substring(idx, idx + query.length)) + '</mark>' +
                                   escHtml(tag.substring(idx + query.length));
                        } else {
                            html = escHtml(tag);
                        }
                        return '<div class="jg-tags-suggestion-item" data-tag="' + escAttr(tag) + '">' + html + '</div>';
                    }).join('');

                    suggestionsEl.classList.add('visible');
                    suggestionsActive = -1;

                    suggestionsEl.querySelectorAll('.jg-tags-suggestion-item').forEach(function(item) {
                        item.addEventListener('click', function() {
                            searchInput.value = this.dataset.tag;
                            currentSearch = this.dataset.tag;
                            currentPage = 1;
                            hideSuggestions();
                            loadTags();
                        });
                    });
                }

                function hideSuggestions() {
                    suggestionsEl.classList.remove('visible');
                    suggestionsActive = -1;
                }

                function loadSuggestions() {
                    jQuery.post(ajaxUrl, {
                        action: 'jg_admin_get_tag_suggestions',
                        _ajax_nonce: nonce
                    }, function(res) {
                        if (res.success) {
                            allTagNames = res.data;
                        }
                    });
                }

                function loadTags() {
                    const content = document.getElementById('jg-tags-content');
                    content.innerHTML = '<div class="jg-tags-loading">Ładowanie tagów...</div>';

                    jQuery.post(ajaxUrl, {
                        action: 'jg_admin_get_tags_paginated',
                        _ajax_nonce: nonce,
                        search: currentSearch,
                        page: currentPage,
                        per_page: 20
                    }, function(res) {
                        if (!res.success) {
                            content.innerHTML = '<div class="jg-tags-empty">Błąd ładowania tagów</div>';
                            return;
                        }

                        const data = res.data;
                        renderStats(data.total);
                        renderTable(data.tags);
                        renderPagination(data.page, data.pages, data.total);
                    });
                }

                function renderStats(total) {
                    document.getElementById('jg-tags-stats').innerHTML =
                        '<div class="jg-tags-stat"><strong>' + total + '</strong>' +
                        (currentSearch ? 'znalezionych tagów' : 'tagów łącznie') + '</div>';
                }

                function renderTable(tags) {
                    const content = document.getElementById('jg-tags-content');

                    if (!tags.length) {
                        content.innerHTML = '<div class="jg-tags-empty">' +
                            (currentSearch ? 'Nie znaleziono tagów pasujących do "' + escHtml(currentSearch) + '"' : 'Brak tagów') +
                            '</div>';
                        return;
                    }

                    let html = '<table class="jg-tags-table"><thead><tr>' +
                        '<th>Tag</th><th>Liczba miejsc</th><th>Akcje</th>' +
                        '</tr></thead><tbody>';

                    tags.forEach(function(tag) {
                        html += '<tr data-tag="' + escAttr(tag.name) + '">' +
                            '<td><span class="jg-tag-name">#' + escHtml(tag.name) + '</span></td>' +
                            '<td><span class="jg-tag-count">' + tag.count + '</span></td>' +
                            '<td class="jg-tag-actions">' +
                                '<button class="jg-tag-btn edit" onclick="jgEditTag(\'' + escJs(tag.name) + '\')" title="Edytuj">' +
                                    '✏️ Edytuj</button>' +
                                '<button class="jg-tag-btn delete" onclick="jgDeleteTag(\'' + escJs(tag.name) + '\', ' + tag.count + ')" title="Usuń">' +
                                    '🗑️ Usuń</button>' +
                            '</td></tr>';
                    });

                    html += '</tbody></table>';
                    content.innerHTML = html;
                }

                function renderPagination(page, pages, total) {
                    const pag = document.getElementById('jg-tags-pagination');
                    if (pages <= 1) {
                        pag.innerHTML = '';
                        return;
                    }

                    let html = '<button ' + (page <= 1 ? 'disabled' : 'onclick="jgTagsPage(1)"') + ' title="Pierwsza strona">&laquo;</button>';
                    html += '<button ' + (page <= 1 ? 'disabled' : 'onclick="jgTagsPage(' + (page - 1) + ')"') + '>&lsaquo;</button>';

                    // Page numbers
                    let startPage = Math.max(1, page - 2);
                    let endPage = Math.min(pages, page + 2);

                    if (startPage > 1) {
                        html += '<button onclick="jgTagsPage(1)">1</button>';
                        if (startPage > 2) html += '<span class="page-info">...</span>';
                    }

                    for (let i = startPage; i <= endPage; i++) {
                        html += '<button class="' + (i === page ? 'current' : '') + '" onclick="jgTagsPage(' + i + ')">' + i + '</button>';
                    }

                    if (endPage < pages) {
                        if (endPage < pages - 1) html += '<span class="page-info">...</span>';
                        html += '<button onclick="jgTagsPage(' + pages + ')">' + pages + '</button>';
                    }

                    html += '<button ' + (page >= pages ? 'disabled' : 'onclick="jgTagsPage(' + (page + 1) + ')"') + '>&rsaquo;</button>';
                    html += '<button ' + (page >= pages ? 'disabled' : 'onclick="jgTagsPage(' + pages + ')"') + ' title="Ostatnia strona">&raquo;</button>';

                    pag.innerHTML = html;
                }

                // Global functions
                window.jgTagsPage = function(page) {
                    currentPage = page;
                    loadTags();
                    document.querySelector('.jg-tags-manager .card').scrollIntoView({ behavior: 'smooth' });
                };

                window.jgEditTag = function(tagName) {
                    // Switch the row to edit mode
                    const row = document.querySelector('tr[data-tag="' + CSS.escape(tagName) + '"]');
                    if (!row) return;

                    editingTag = tagName;
                    row.classList.add('jg-tag-edit-row');
                    const nameCell = row.querySelector('td:first-child');
                    const actionsCell = row.querySelector('.jg-tag-actions');

                    nameCell.innerHTML = '<input type="text" class="jg-tag-edit-input" id="jg-tag-edit-input" value="' + escAttr(tagName) + '" maxlength="50">';
                    actionsCell.innerHTML =
                        '<button class="jg-tag-btn save" onclick="jgSaveTag()">Zapisz</button>' +
                        '<button class="jg-tag-btn cancel" onclick="jgCancelEdit()">Anuluj</button>';

                    const input = document.getElementById('jg-tag-edit-input');
                    input.focus();
                    input.select();

                    input.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter') jgSaveTag();
                        if (e.key === 'Escape') jgCancelEdit();
                    });
                };

                window.jgSaveTag = function() {
                    const input = document.getElementById('jg-tag-edit-input');
                    if (!input || !editingTag) return;

                    const newName = input.value.trim();
                    if (!newName) {
                        showToast('Nazwa tagu nie może być pusta', 'error');
                        return;
                    }
                    if (newName === editingTag) {
                        jgCancelEdit();
                        return;
                    }

                    input.disabled = true;

                    jQuery.post(ajaxUrl, {
                        action: 'jg_admin_rename_tag',
                        _ajax_nonce: nonce,
                        old_name: editingTag,
                        new_name: newName
                    }, function(res) {
                        if (res.success) {
                            showToast(res.data.message, 'success');
                            editingTag = null;
                            loadTags();
                            loadSuggestions();
                        } else {
                            showToast(res.data.message || 'Błąd', 'error');
                            input.disabled = false;
                        }
                    }).fail(function() {
                        showToast('Błąd połączenia', 'error');
                        input.disabled = false;
                    });
                };

                window.jgCancelEdit = function() {
                    editingTag = null;
                    loadTags();
                };

                window.jgDeleteTag = function(tagName, count) {
                    const overlay = document.createElement('div');
                    overlay.className = 'jg-confirm-overlay';
                    overlay.innerHTML =
                        '<div class="jg-confirm-box">' +
                            '<h3>Usuń tag</h3>' +
                            '<p>Czy na pewno chcesz usunąć tag <strong>#' + escHtml(tagName) + '</strong>?<br>' +
                            'Tag zostanie usunięty z <strong>' + count + '</strong> ' + pluralize(count) + '.</p>' +
                            '<div class="jg-btn-row">' +
                                '<button class="btn-cancel" id="jg-confirm-cancel">Anuluj</button>' +
                                '<button class="btn-danger" id="jg-confirm-delete">Usuń tag</button>' +
                            '</div>' +
                        '</div>';

                    document.body.appendChild(overlay);

                    document.getElementById('jg-confirm-cancel').addEventListener('click', function() {
                        overlay.remove();
                    });
                    overlay.addEventListener('click', function(e) {
                        if (e.target === overlay) overlay.remove();
                    });

                    document.getElementById('jg-confirm-delete').addEventListener('click', function() {
                        this.disabled = true;
                        this.textContent = 'Usuwanie...';

                        jQuery.post(ajaxUrl, {
                            action: 'jg_admin_delete_tag',
                            _ajax_nonce: nonce,
                            tag_name: tagName
                        }, function(res) {
                            overlay.remove();
                            if (res.success) {
                                showToast(res.data.message, 'success');
                                loadTags();
                                loadSuggestions();
                            } else {
                                showToast(res.data.message || 'Błąd', 'error');
                            }
                        }).fail(function() {
                            overlay.remove();
                            showToast('Błąd połączenia', 'error');
                        });
                    });
                };

                function pluralize(count) {
                    if (count === 1) return 'miejsca';
                    return 'miejsc';
                }

                function showToast(message, type) {
                    const toast = document.getElementById('jg-tags-toast');
                    toast.textContent = message;
                    toast.className = 'jg-tags-toast ' + type + ' visible';
                    setTimeout(function() {
                        toast.classList.remove('visible');
                    }, 3000);
                }

                function escHtml(str) {
                    var div = document.createElement('div');
                    div.appendChild(document.createTextNode(str));
                    return div.innerHTML;
                }

                function escAttr(str) {
                    return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                }

                function escJs(str) {
                    return str.replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"');
                }
            })();
            </script>
        </div>
        <?php
    }

    /**
     * Render admin page for configuring the mobile hamburger-menu navigation items.
     * Items are stored as JSON in wp_options under 'jg_map_nav_menu'.
     */
    public function render_nav_menu_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień.');
        }

        /* ── Handle form save ── */
        if (isset($_POST['jg_nav_menu_save']) && check_admin_referer('jg_nav_menu_nonce')) {
            $json = isset($_POST['jg_nav_menu_json']) ? wp_unslash($_POST['jg_nav_menu_json']) : '[]';
            $raw  = json_decode($json, true);
            if (!is_array($raw)) $raw = array();

            $items = array();
            foreach ($raw as $item) {
                if (!is_array($item)) continue;
                $label = sanitize_text_field(isset($item['label']) ? $item['label'] : '');
                $url   = esc_url_raw(isset($item['url']) ? $item['url'] : '');
                if ($label === '' || $url === '') continue;

                $children = array();
                if (!empty($item['children']) && is_array($item['children'])) {
                    foreach ($item['children'] as $child) {
                        if (!is_array($child)) continue;
                        $clabel = sanitize_text_field(isset($child['label']) ? $child['label'] : '');
                        $curl   = esc_url_raw(isset($child['url']) ? $child['url'] : '');
                        if ($clabel === '' || $curl === '') continue;
                        $children[] = array(
                            'label'   => $clabel,
                            'url'     => $curl,
                            'new_tab' => !empty($child['new_tab']),
                        );
                    }
                }

                $items[] = array(
                    'label'    => $label,
                    'url'      => $url,
                    'new_tab'  => !empty($item['new_tab']),
                    'children' => $children,
                );
            }

            update_option('jg_map_nav_menu', $items);
            echo '<div class="notice notice-success is-dismissible"><p>Menu zostało zapisane.</p></div>';
        }

        $items = get_option('jg_map_nav_menu', array());
        ?>
        <div class="wrap">
            <?php $this->render_page_header('Menu nawigacyjne (mobilny pasek)'); ?>
            <p class="description" style="margin-bottom:16px;color:#6b7280">
                Pozycje wyświetlane w rozwijanym menu hamburgerowym na pasku z logo portalu (widocznym na urządzeniach mobilnych).
                Przeciągaj wiersze za uchwyt <strong>⠿</strong> aby zmieniać kolejność.
            </p>

            <style>
            .jg-nav-row.jg-drag-over { outline: 2px dashed #2271b1 !important; background: #f0f7ff !important; }
            .jg-nav-row .jg-drag-handle { cursor: grab !important; }
            .jg-nav-row .jg-drag-handle:active { cursor: grabbing !important; }
            .jg-nav-subrow td { background: #fafafa; }
            .jg-nav-subrow td:first-child { border-left: 3px solid #e5e7eb; }
            </style>

            <form method="post" action="" id="jg-nav-menu-form">
                <?php wp_nonce_field('jg_nav_menu_nonce'); ?>
                <input type="hidden" name="jg_nav_menu_json" id="jg-nav-menu-json" value="<?php echo esc_attr(wp_json_encode($items)); ?>">

                <div class="jg-card jg-card-body" style="max-width:900px">
                    <h2>Pozycje menu</h2>

                    <div class="jg-admin-table-wrap"><div class="jg-table-scroll">
                    <table class="jg-admin-table" id="jg-nav-menu-table">
                        <thead>
                            <tr>
                                <th style="width:30px"></th>
                                <th style="width:36px">#</th>
                                <th>Etykieta</th>
                                <th>URL</th>
                                <th style="width:110px;text-align:center">Nowa karta</th>
                                <th style="width:190px"></th>
                            </tr>
                        </thead>
                        <tbody id="jg-nav-menu-body">
                            <?php foreach ($items as $idx => $item) : ?>
                            <tr class="jg-nav-row" data-idx="<?php echo esc_attr($idx); ?>" draggable="true">
                                <td class="jg-drag-handle" title="Przeciągnij aby zmienić kolejność" style="text-align:center;color:#9ca3af;font-size:18px;user-select:none;padding:4px 8px">⠿</td>
                                <td class="jg-row-num" style="color:#9ca3af;font-size:calc(13 * var(--jg))"><?php echo $idx + 1; ?></td>
                                <td>
                                    <input type="text" class="jg-nav-label regular-text"
                                           value="<?php echo esc_attr($item['label']); ?>"
                                           placeholder="np. Aktualności">
                                </td>
                                <td>
                                    <input type="url" class="jg-nav-url regular-text"
                                           value="<?php echo esc_attr($item['url']); ?>"
                                           placeholder="https://...">
                                </td>
                                <td style="text-align:center">
                                    <input type="checkbox" class="jg-nav-new-tab" value="1"
                                           <?php checked(!empty($item['new_tab'])); ?>>
                                </td>
                                <td>
                                    <button type="button" class="button jg-nav-add-sub" style="margin-right:4px">+ Podmenu</button>
                                    <button type="button" class="button jg-nav-remove-row" style="color:#dc2626;border-color:#dc2626">Usuń</button>
                                </td>
                            </tr>
                            <?php if (!empty($item['children'])) : ?>
                                <?php foreach ($item['children'] as $child) : ?>
                                <tr class="jg-nav-subrow" data-parent="<?php echo esc_attr($idx); ?>">
                                    <td></td>
                                    <td style="color:#9ca3af;font-size:12px;text-align:center">↳</td>
                                    <td>
                                        <input type="text" class="jg-nav-sub-label regular-text"
                                               value="<?php echo esc_attr($child['label']); ?>"
                                               placeholder="np. Podstrona"
                                               style="margin-left:8px">
                                    </td>
                                    <td>
                                        <input type="url" class="jg-nav-sub-url regular-text"
                                               value="<?php echo esc_attr($child['url']); ?>"
                                               placeholder="https://...">
                                    </td>
                                    <td style="text-align:center">
                                        <input type="checkbox" class="jg-nav-sub-new-tab" value="1"
                                               <?php checked(!empty($child['new_tab'])); ?>>
                                    </td>
                                    <td>
                                        <button type="button" class="button jg-nav-remove-subrow" style="color:#dc2626;border-color:#dc2626">Usuń</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div></div>

                    <p style="margin-top:12px">
                        <button type="button" id="jg-nav-add-row" class="button">+ Dodaj pozycję</button>
                    </p>
                </div>

                <p>
                    <input type="submit" name="jg_nav_menu_save" class="button button-primary button-large" value="Zapisz menu">
                </p>
            </form>

            <script>
            (function () {
                var tbody   = document.getElementById('jg-nav-menu-body');
                var addBtn  = document.getElementById('jg-nav-add-row');
                var form    = document.getElementById('jg-nav-menu-form');
                var jsonFld = document.getElementById('jg-nav-menu-json');

                if (!tbody || !addBtn || !form || !jsonFld) return;

                /* ── Refresh row numbers for parent rows ── */
                function refreshNumbers() {
                    var n = 0;
                    tbody.querySelectorAll('.jg-nav-row').forEach(function (row) {
                        row.querySelector('.jg-row-num').textContent = ++n;
                    });
                }

                /* ── Get subrows belonging to a parent row ── */
                function getSubrows(parentRow) {
                    var idx  = parentRow.dataset.idx;
                    var subs = [];
                    var next = parentRow.nextElementSibling;
                    while (next && next.classList.contains('jg-nav-subrow') && next.dataset.parent === idx) {
                        subs.push(next);
                        next = next.nextElementSibling;
                    }
                    return subs;
                }

                /* ── Build JSON from current DOM state ── */
                function collectJSON() {
                    var items = [];
                    tbody.querySelectorAll('.jg-nav-row').forEach(function (row) {
                        var label  = row.querySelector('.jg-nav-label').value.trim();
                        var url    = row.querySelector('.jg-nav-url').value.trim();
                        var newTab = row.querySelector('.jg-nav-new-tab').checked;
                        var children = [];
                        getSubrows(row).forEach(function (sr) {
                            var sl = sr.querySelector('.jg-nav-sub-label').value.trim();
                            var su = sr.querySelector('.jg-nav-sub-url').value.trim();
                            var sn = sr.querySelector('.jg-nav-sub-new-tab').checked;
                            if (sl && su) children.push({ label: sl, url: su, new_tab: sn });
                        });
                        if (label && url) items.push({ label: label, url: url, new_tab: newTab, children: children });
                    });
                    return JSON.stringify(items);
                }

                /* ── Keep hidden field in sync with DOM at all times ── */
                function syncJSON() {
                    jsonFld.value = collectJSON();
                }

                /* ── Add parent row ── */
                addBtn.addEventListener('click', function () {
                    var uid = String(Date.now());
                    var tr  = document.createElement('tr');
                    tr.className   = 'jg-nav-row';
                    tr.dataset.idx = uid;
                    tr.draggable   = true;
                    tr.innerHTML =
                        '<td class="jg-drag-handle" title="Przeciągnij aby zmienić kolejność" style="text-align:center;color:#9ca3af;font-size:18px;user-select:none;padding:4px 8px">⠿</td>' +
                        '<td class="jg-row-num" style="color:#9ca3af;font-size:calc(13 * var(--jg))"></td>' +
                        '<td><input type="text" class="jg-nav-label regular-text" placeholder="np. Aktualności"></td>' +
                        '<td><input type="url"  class="jg-nav-url regular-text"   placeholder="https://..."></td>' +
                        '<td style="text-align:center"><input type="checkbox" class="jg-nav-new-tab" value="1"></td>' +
                        '<td>' +
                            '<button type="button" class="button jg-nav-add-sub" style="margin-right:4px">+ Podmenu</button>' +
                            '<button type="button" class="button jg-nav-remove-row" style="color:#dc2626;border-color:#dc2626">Usuń</button>' +
                        '</td>';
                    tbody.appendChild(tr);
                    bindDrag(tr);
                    bindRemove(tr.querySelector('.jg-nav-remove-row'));
                    bindAddSub(tr.querySelector('.jg-nav-add-sub'));
                    tr.querySelector('.jg-nav-label').focus();
                    refreshNumbers();
                    syncJSON();
                });

                /* ── Add subrow under a parent ── */
                function addSubrow(parentRow) {
                    var idx    = parentRow.dataset.idx;
                    var subs   = getSubrows(parentRow);
                    var anchor = subs.length ? subs[subs.length - 1] : parentRow;
                    var tr     = document.createElement('tr');
                    tr.className      = 'jg-nav-subrow';
                    tr.dataset.parent = idx;
                    tr.innerHTML =
                        '<td></td>' +
                        '<td style="color:#9ca3af;font-size:12px;text-align:center">↳</td>' +
                        '<td><input type="text" class="jg-nav-sub-label regular-text" placeholder="np. Podstrona" style="margin-left:8px"></td>' +
                        '<td><input type="url"  class="jg-nav-sub-url regular-text"   placeholder="https://..."></td>' +
                        '<td style="text-align:center"><input type="checkbox" class="jg-nav-sub-new-tab" value="1"></td>' +
                        '<td><button type="button" class="button jg-nav-remove-subrow" style="color:#dc2626;border-color:#dc2626">Usuń</button></td>';
                    anchor.insertAdjacentElement('afterend', tr);
                    bindRemoveSub(tr.querySelector('.jg-nav-remove-subrow'));
                    tr.querySelector('.jg-nav-sub-label').focus();
                    syncJSON();
                }

                function bindAddSub(btn) {
                    btn.addEventListener('click', function () {
                        addSubrow(btn.closest('.jg-nav-row'));
                    });
                }

                function bindRemoveSub(btn) {
                    btn.addEventListener('click', function () {
                        btn.closest('.jg-nav-subrow').remove();
                        syncJSON();
                    });
                }

                function bindRemove(btn) {
                    btn.addEventListener('click', function () {
                        var row = btn.closest('.jg-nav-row');
                        getSubrows(row).forEach(function (sr) { sr.remove(); });
                        row.remove();
                        refreshNumbers();
                        syncJSON();
                    });
                }

                /* ── Drag & drop reorder ── */
                var dragSrc = null;

                function bindDrag(row) {
                    row.addEventListener('dragstart', function (e) {
                        dragSrc = row;
                        e.dataTransfer.effectAllowed = 'move';
                        setTimeout(function () { row.style.opacity = '0.5'; }, 0);
                    });
                    row.addEventListener('dragend', function () {
                        row.style.opacity = '';
                        dragSrc = null;
                        tbody.querySelectorAll('.jg-nav-row').forEach(function (r) {
                            r.classList.remove('jg-drag-over');
                        });
                    });
                    row.addEventListener('dragover', function (e) {
                        if (!dragSrc || dragSrc === row) return;
                        e.preventDefault();
                        e.dataTransfer.dropEffect = 'move';
                        tbody.querySelectorAll('.jg-nav-row').forEach(function (r) {
                            r.classList.remove('jg-drag-over');
                        });
                        row.classList.add('jg-drag-over');
                    });
                    row.addEventListener('drop', function (e) {
                        e.preventDefault();
                        if (!dragSrc || dragSrc === row) return;

                        var srcSubs    = getSubrows(dragSrc);
                        var targetSubs = getSubrows(row);
                        var rect       = row.getBoundingClientRect();
                        var before     = e.clientY < rect.top + rect.height / 2;

                        if (before) {
                            tbody.insertBefore(dragSrc, row);
                        } else {
                            var anchor = targetSubs.length ? targetSubs[targetSubs.length - 1] : row;
                            anchor.insertAdjacentElement('afterend', dragSrc);
                        }

                        /* Re-attach dragSrc's subrows immediately after it */
                        var after = dragSrc;
                        srcSubs.forEach(function (sr) {
                            after.insertAdjacentElement('afterend', sr);
                            after = sr;
                        });

                        row.classList.remove('jg-drag-over');
                        refreshNumbers();
                        syncJSON();
                    });
                }

                tbody.querySelectorAll('.jg-nav-row').forEach(bindDrag);
                tbody.querySelectorAll('.jg-nav-remove-row').forEach(bindRemove);
                tbody.querySelectorAll('.jg-nav-add-sub').forEach(bindAddSub);
                tbody.querySelectorAll('.jg-nav-remove-subrow').forEach(bindRemoveSub);

                /* ── Sync on any value change in the table ── */
                tbody.addEventListener('input', syncJSON);
                tbody.addEventListener('change', syncJSON);

                /* ── Final sync before submit (safety net) ── */
                form.addEventListener('submit', function () {
                    jsonFld.value = collectJSON();
                });
            })();
            </script>
        </div>
        <?php
    }

    /**
     * SEO Pinezek — admin page for setting canonical URL and noindex on map pins
     */
    public function render_seo_page() {
        global $wpdb;
        $table = JG_Map_Database::get_points_table();

        $per_page     = 50;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset       = ($current_page - 1) * $per_page;
        $search       = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

        $where = "status = 'publish' AND type IN ('miejsce','ciekawostka')";
        if ($search) {
            $where .= $wpdb->prepare(' AND title LIKE %s', '%' . $wpdb->esc_like($search) . '%');
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total       = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE $where");
        $total_pages = max(1, ceil($total / $per_page));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $points = $wpdb->get_results(
            "SELECT id, title, slug, type, seo_canonical, seo_noindex FROM $table WHERE $where ORDER BY title ASC LIMIT $per_page OFFSET $offset",
            ARRAY_A
        );

        $type_labels = array('miejsce' => 'Miejsce', 'ciekawostka' => 'Ciekawostka');
        ?>
        <style>
        .jg-seo-canonical-input{width:100%;max-width:360px;padding:5px 8px;border:1px solid #d1d5db;border-radius:4px;font-size:12px}
        .jg-seo-canonical-input.has-value{border-color:#2563eb;background:#eff6ff}
        .jg-seo-badge{display:inline-block;padding:2px 8px;border-radius:12px;font-size:10px;font-weight:700;text-transform:uppercase}
        .jg-seo-badge-noindex{background:#fee2e2;color:#b91c1c}
        .jg-seo-badge-canonical{background:#dbeafe;color:#1e40af}
        .jg-seo-saved{color:#16a34a;font-weight:700;font-size:11px;display:none;margin-left:6px}
        </style>
        <div class="wrap">
            <?php $this->render_page_header('SEO Pinezek'); ?>

            <p style="color:#6b7280;margin-bottom:20px">Ustaw niestandardowy canonical URL lub flagę noindex dla pinezek mapy. Przydatne przy cannibalizacji między pinezką a artykułem.</p>

            <!-- Search -->
            <div class="jg-admin-table-wrap" style="padding:16px;margin-bottom:16px">
                <form method="get" action="">
                    <input type="hidden" name="page" value="jg-map-seo">
                    <div style="display:flex;gap:10px;align-items:center">
                        <input type="text" name="search" value="<?php echo esc_attr($search); ?>"
                               placeholder="Szukaj po nazwie..."
                               style="flex:1;max-width:340px;padding:8px 12px;border:1px solid #ddd;border-radius:4px">
                        <button type="submit" class="button button-primary">🔍 Szukaj</button>
                        <?php if ($search): ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=jg-map-seo')); ?>" class="button">✕ Wyczyść</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <?php if (!empty($points)): ?>
            <div class="jg-admin-table-wrap">
              <div class="jg-table-scroll">
                <table class="jg-admin-table">
                    <thead>
                        <tr>
                            <th>Nazwa</th>
                            <th>Typ</th>
                            <th>Status SEO</th>
                            <th>Canonical URL <span style="font-weight:400;text-transform:none">(puste = auto)</span></th>
                            <th style="text-align:center">Noindex</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($points as $p):
                        $pin_url      = home_url('/' . ($p['type'] === 'ciekawostka' ? 'ciekawostka' : 'miejsce') . '/' . $p['slug'] . '/');
                        $has_canonical = !empty($p['seo_canonical']);
                        $has_noindex   = !empty($p['seo_noindex']);
                    ?>
                        <tr data-id="<?php echo intval($p['id']); ?>">
                            <td data-label="Nazwa">
                                <a href="<?php echo esc_url($pin_url); ?>" target="_blank"><?php echo esc_html($p['title']); ?></a>
                            </td>
                            <td data-label="Typ"><?php echo esc_html($type_labels[$p['type']] ?? $p['type']); ?></td>
                            <td data-label="Status SEO">
                                <?php if ($has_noindex): ?>
                                    <span class="jg-seo-badge jg-seo-badge-noindex">noindex</span>
                                <?php elseif ($has_canonical): ?>
                                    <span class="jg-seo-badge jg-seo-badge-canonical">canonical</span>
                                <?php else: ?>
                                    <span style="color:#9ca3af;font-size:11px">—</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Canonical URL">
                                <input type="text"
                                       class="jg-seo-canonical-input<?php echo $has_canonical ? ' has-value' : ''; ?>"
                                       value="<?php echo esc_attr($p['seo_canonical'] ?? ''); ?>"
                                       placeholder="<?php echo esc_attr($pin_url); ?>">
                            </td>
                            <td data-label="Noindex" style="text-align:center">
                                <input type="checkbox" class="jg-seo-noindex" <?php checked($has_noindex); ?>>
                            </td>
                            <td data-label="" class="jg-td-actions">
                                <div class="jg-action-btns">
                                    <button class="button jg-seo-save-btn">Zapisz</button>
                                    <span class="jg-seo-saved">✓ Zapisano</span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
              </div>
            </div>

            <div class="tablenav bottom" style="padding-top:10px">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo number_format($total); ?> pinezek</span>
                    <?php if ($total_pages > 1): ?>
                    <span class="pagination-links">
                        <?php for ($i = 1; $i <= $total_pages; $i++):
                            $page_url = add_query_arg(array('page' => 'jg-map-seo', 'paged' => $i, 'search' => $search), admin_url('admin.php'));
                        ?>
                            <?php if ($i === $current_page): ?>
                                <span class="tablenav-pages-navspan button disabled"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a class="button" href="<?php echo esc_url($page_url); ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>

            <?php else: ?>
            <p style="color:#9ca3af;padding:20px 0"><?php echo $search ? 'Brak wyników dla podanej frazy.' : 'Brak opublikowanych pinezek.'; ?></p>
            <?php endif; ?>
        </div>

        <script>
        (function(){
            var nonce = '<?php echo wp_create_nonce('jg_admin_save_seo'); ?>';
            document.querySelectorAll('.jg-seo-save-btn').forEach(function(btn){
                btn.addEventListener('click', function(){
                    var row       = btn.closest('tr');
                    var id        = row.dataset.id;
                    var canonical = row.querySelector('.jg-seo-canonical-input').value.trim();
                    var noindex   = row.querySelector('.jg-seo-noindex').checked ? 1 : 0;
                    btn.disabled  = true;
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {'Content-Type':'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({action:'jg_admin_save_seo', nonce:nonce, point_id:id, seo_canonical:canonical, seo_noindex:noindex})
                    })
                    .then(function(r){ return r.json(); })
                    .then(function(res){
                        btn.disabled = false;
                        if (res.success) {
                            var saved = row.querySelector('.jg-seo-saved');
                            saved.style.display = 'inline';
                            setTimeout(function(){ saved.style.display = 'none'; }, 2000);
                            row.querySelector('.jg-seo-canonical-input').classList.toggle('has-value', canonical !== '');
                        }
                    });
                });
            });
        })();
        </script>
        <?php
    }
}
