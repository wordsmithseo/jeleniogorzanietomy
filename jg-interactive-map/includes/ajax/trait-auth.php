<?php
/**
 * Trait: user authentication and profile
 * validate_password_strength, normalize_social_url,
 * get_current_user, get_my_stats, update_profile, delete_profile,
 * resend_activation_email, login_user, register_user, forgot_password,
 * check_user_session_status, logout_user, check_registration_status,
 * get_notification_counts,
 * google_oauth_callback, facebook_oauth_callback,
 * find_or_create_social_user, generate_social_username, output_oauth_result
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

trait JG_Ajax_Auth {

    /**
     * Validate password strength
     */
    private function validate_password_strength($password) {
        // Minimum 12 characters
        if (strlen($password) < 12) {
            return array(
                'valid' => false,
                'error' => 'Hasło musi mieć co najmniej 12 znaków'
            );
        }

        // Must contain uppercase letter
        if (!preg_match('/[A-Z]/', $password)) {
            return array(
                'valid' => false,
                'error' => 'Hasło musi zawierać co najmniej jedną wielką literę'
            );
        }

        // Must contain lowercase letter
        if (!preg_match('/[a-z]/', $password)) {
            return array(
                'valid' => false,
                'error' => 'Hasło musi zawierać co najmniej jedną małą literę'
            );
        }

        // Must contain digit
        if (!preg_match('/[0-9]/', $password)) {
            return array(
                'valid' => false,
                'error' => 'Hasło musi zawierać co najmniej jedną cyfrę'
            );
        }

        // Must contain special character
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            return array(
                'valid' => false,
                'error' => 'Hasło musi zawierać co najmniej jeden znak specjalny (np. !@#$%^&*)'
            );
        }

        return array('valid' => true);
    }

    /**
     * Normalize social media URLs
     * Accepts: full URL, domain URL, or profile name
     * Returns: full valid URL or empty string
     */
    private function normalize_social_url($input, $platform) {
        if (empty($input)) {
            return '';
        }

        // Sanitize input
        $input = sanitize_text_field(trim($input));

        // Platform base URLs
        $base_urls = array(
            'facebook' => 'https://facebook.com/',
            'instagram' => 'https://instagram.com/',
            'linkedin' => 'https://linkedin.com/in/',
            'tiktok' => 'https://tiktok.com/@'
        );

        // Already a full URL starting with http(s)
        if (preg_match('/^https?:\/\//i', $input)) {
            return esc_url_raw($input);
        }

        // Remove @ if present (common for TikTok/Instagram)
        $input = ltrim($input, '@');

        // Remove domain if user pasted it
        $patterns = array(
            'facebook' => array('facebook.com/', 'fb.com/', 'fb.me/', 'm.facebook.com/'),
            'instagram' => array('instagram.com/', 'instagr.am/', 'm.instagram.com/'),
            'linkedin' => array('linkedin.com/in/', 'linkedin.com/company/', 'lnkd.in/'),
            'tiktok' => array('tiktok.com/@', 'tiktok.com/', 'vm.tiktok.com/')
        );

        if (isset($patterns[$platform])) {
            foreach ($patterns[$platform] as $pattern) {
                $input = preg_replace('/^' . preg_quote($pattern, '/') . '/i', '', $input);
            }
        }

        // LinkedIn company pages need different base
        if ($platform === 'linkedin' && stripos($input, 'company/') === 0) {
            $input = preg_replace('/^company\//i', '', $input);
            return 'https://linkedin.com/company/' . urlencode($input);
        }

        // Build full URL - don't use esc_url_raw as it may strip valid social media URLs
        return $base_urls[$platform] . urlencode($input);
    }

    /**
     * Get current user info
     */
    public function get_current_user() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Musisz być zalogowany');
            exit;
        }

        $current_user = wp_get_current_user();
        $is_admin = user_can($current_user->ID, 'manage_options');
        $is_moderator = user_can($current_user->ID, 'jg_map_moderate');

        $is_oauth_user = !empty(get_user_meta($current_user->ID, 'jg_google_id', true))
                      || !empty(get_user_meta($current_user->ID, 'jg_facebook_id', true));

        wp_send_json_success(array(
            'display_name' => $current_user->display_name,
            'email' => $current_user->user_email,
            'is_admin' => $is_admin,
            'is_moderator' => $is_moderator,
            'can_delete_profile' => !$is_admin && !$is_moderator,
            'is_oauth_user' => $is_oauth_user,
        ));
    }

    /**
     * Get current user statistics for profile modal
     */
    public function get_my_stats() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Musisz być zalogowany');
            exit;
        }

        global $wpdb;
        $user_id = get_current_user_id();
        $current_user = wp_get_current_user();

        $table_points = $wpdb->prefix . 'jg_map_points';
        $table_votes = $wpdb->prefix . 'jg_map_votes';
        $table_reports = $wpdb->prefix . 'jg_map_reports';
        $table_history = $wpdb->prefix . 'jg_map_history';
        $table_point_visits = $wpdb->prefix . 'jg_map_point_visits';

        // Count added places (published)
        $places_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_points WHERE author_id = %d AND status = 'publish'",
            $user_id
        ));

        // Count pending places
        $pending_places_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_points WHERE author_id = %d AND status = 'pending'",
            $user_id
        ));

        // Count edits submitted (including menu edits)
        $edits_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_history WHERE user_id = %d AND action_type IN ('edit','edit_menu')",
            $user_id
        ));

        // Count approved edits (including menu edits)
        $approved_edits_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_history WHERE user_id = %d AND action_type IN ('edit','edit_menu') AND status = 'approved'",
            $user_id
        ));

        // Count photos added
        $photos_data = $wpdb->get_results($wpdb->prepare(
            "SELECT images FROM $table_points WHERE author_id = %d AND status = 'publish' AND images IS NOT NULL AND images != ''",
            $user_id
        ), ARRAY_A);

        $photos_count = 0;
        foreach ($photos_data as $point_data) {
            if (!empty($point_data['images'])) {
                $images = json_decode($point_data['images'], true);
                if (is_array($images)) {
                    $photos_count += count($images);
                }
            }
        }

        // Count star ratings given by user
        $ratings_given = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_votes WHERE user_id = %d",
            $user_id
        ));

        // Count reports submitted
        $reports_submitted = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_reports WHERE user_id = %d",
            $user_id
        ));

        // Count visits to places
        $places_visited = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT point_id) FROM $table_point_visits WHERE user_id = %d",
            $user_id
        ));

        // Average star rating received on user's places
        $ratings_received_row = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) as cnt, AVG(CAST(v.vote_type AS DECIMAL(3,1))) as avg_rating
             FROM $table_votes v
             INNER JOIN $table_points p ON v.point_id = p.id
             WHERE p.author_id = %d",
            $user_id
        ), ARRAY_A);
        $ratings_received = $ratings_received_row ? intval($ratings_received_row['cnt']) : 0;
        $avg_rating_received = ($ratings_received > 0) ? round(floatval($ratings_received_row['avg_rating']), 1) : 0.0;

        // User metadata
        $is_admin = current_user_can('manage_options');
        $is_moderator = current_user_can('jg_map_moderate');

        // Check if user has sponsored places
        $has_sponsored = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_points WHERE author_id = %d AND is_promo = 1 AND status = 'publish'",
            $user_id
        )) > 0;

        $role = 'Użytkownik';
        if ($is_admin) {
            $role = 'Administrator';
        } elseif ($is_moderator) {
            $role = 'Moderator';
        }

        wp_send_json_success(array(
            'user_id' => $user_id,
            'display_name' => $current_user->display_name,
            'member_since' => $current_user->user_registered . ' UTC',
            'role' => $role,
            'is_admin' => $is_admin,
            'is_moderator' => $is_moderator,
            'has_sponsored' => $has_sponsored,
            'stats' => array(
                'places_added' => intval($places_count),
                'places_pending' => intval($pending_places_count),
                'edits_submitted' => intval($edits_count),
                'edits_approved' => intval($approved_edits_count),
                'photos_added' => intval($photos_count),
                'ratings_given' => intval($ratings_given),
                'ratings_received' => $ratings_received,
                'avg_rating_received' => $avg_rating_received,
                'reports_submitted' => intval($reports_submitted),
                'places_visited' => intval($places_visited)
            )
        ));
    }

    /**
     * Update user profile (password only)
     */
    public function update_profile() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Musisz być zalogowany');
            exit;
        }
        $this->verify_nonce();

        $user_id = get_current_user_id();
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        if (empty($password)) {
            wp_send_json_error('Proszę podać nowe hasło');
            exit;
        }

        $password_check = $this->validate_password_strength($password);
        if (!$password_check['valid']) {
            wp_send_json_error($password_check['error']);
            exit;
        }

        // Update user data (only password)
        $user_data = array(
            'ID' => $user_id,
            'user_pass' => $password
        );

        $result = wp_update_user($user_data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            exit;
        }

        wp_send_json_success('Hasło zostało zmienione');
    }

    /**
     * Delete user profile - removes all user data including pins, photos, and account
     */
    public function delete_profile() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Musisz być zalogowany');
            exit;
        }
        $this->verify_nonce();

        $user_id = get_current_user_id();
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        // Check if user is admin or moderator - they cannot delete their own profiles this way
        $is_admin = user_can($user_id, 'manage_options');
        $is_moderator = user_can($user_id, 'jg_map_moderate');

        if ($is_admin || $is_moderator) {
            wp_send_json_error('Administratorzy i moderatorzy nie mogą usunąć swoich profili przez tę opcję');
            exit;
        }

        $is_oauth_user = !empty(get_user_meta($user_id, 'jg_google_id', true))
                      || !empty(get_user_meta($user_id, 'jg_facebook_id', true));

        if (!$is_oauth_user) {
            if (empty($password)) {
                wp_send_json_error('Proszę podać hasło w celu potwierdzenia');
                exit;
            }
            $user = wp_get_current_user();
            if (!wp_check_password($password, $user->user_pass, $user_id)) {
                wp_send_json_error('Nieprawidłowe hasło');
                exit;
            }
        }

        // Get all user's points (pins)
        $user_places = JG_Map_Database::get_all_places_with_status('', '', $user_id);

        // Delete all user's points with their images
        if (!empty($user_places)) {
            foreach ($user_places as $place) {
                JG_Map_Database::delete_point($place['id']);
            }
        }

        // Delete all user meta data related to the plugin
        $meta_keys = array(
            'jg_map_ban_until',
            'jg_map_restrict_edit',
            'jg_map_restrict_delete',
            'jg_map_restrict_add',
            'jg_map_restrict_voting',
            'jg_map_restrict_add_events',
            'jg_map_restrict_add_trivia',
            'jg_map_restrict_photo_upload',
            'jg_map_daily_reset',
            'jg_map_daily_places',
            'jg_map_daily_reports',
            'jg_map_edits_count',
            'jg_map_edits_date',
            'jg_map_photo_month',
            'jg_map_photo_used_bytes',
            'jg_map_photo_custom_limit',
            'jg_map_activation_key',
            'jg_map_activation_key_time',
            'jg_map_account_status',
            'jg_map_reset_key',
            'jg_map_reset_key_time'
        );

        foreach ($meta_keys as $meta_key) {
            delete_user_meta($user_id, $meta_key);
        }

        // Log user action before deletion
        JG_Map_Activity_Log::log_user_action(
            'delete_profile',
            'user',
            $user_id,
            sprintf('Użytkownik %s usunął swoje konto (wraz z %d miejscami)', $user->display_name, count($user_places))
        );

        // Log user out before deletion
        wp_logout();

        // Delete the user account
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        $deleted = wp_delete_user($user_id);

        if (!$deleted) {
            wp_send_json_error('Wystąpił błąd podczas usuwania profilu');
            exit;
        }

        wp_send_json_success('Profil został pomyślnie usunięty');
    }

    /**
     * Resend activation email
     */
    public function resend_activation_email() {
        $this->verify_nonce();

        $username = isset($_POST['username']) ? sanitize_text_field($_POST['username']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

        if (empty($username) && empty($email)) {
            wp_send_json_error('Proszę podać nazwę użytkownika lub email');
            exit;
        }

        // Find user by username or email
        $user = null;
        if (!empty($username)) {
            $user = get_user_by('login', $username);
            if (!$user) {
                $user = get_user_by('email', $username);
            }
        } elseif (!empty($email)) {
            $user = get_user_by('email', $email);
        }

        // Always return the same generic message to prevent user enumeration
        $generic_success = 'Jeśli konto z podanymi danymi istnieje i oczekuje na aktywację, wysłaliśmy nowy link aktywacyjny.';

        // Rate limiting before we reveal anything about user existence
        $ip = $this->get_user_ip();
        $rate_check = $this->check_rate_limit('resend_activation', $ip, 3, 3600, array(), false);
        if (!$rate_check['allowed']) {
            $minutes = isset($rate_check['minutes_remaining']) ? $rate_check['minutes_remaining'] : 60;
            wp_send_json_error('Zbyt wiele prób wysłania linku aktywacyjnego. Spróbuj ponownie za ' . $minutes . ' ' . ($minutes === 1 ? 'minutę' : ($minutes < 5 ? 'minuty' : 'minut')) . '.');
            exit;
        }

        if (!$user) {
            // Increment rate limit even for non-existent users to prevent enumeration via timing
            $this->check_rate_limit('resend_activation', $ip, 3, 3600, array(), true);
            wp_send_json_success($generic_success);
            exit;
        }

        // Check if account is already activated — return generic message so as not to leak account state
        $account_status = get_user_meta($user->ID, 'jg_map_account_status', true);
        if ($account_status === 'active') {
            $this->check_rate_limit('resend_activation', $ip, 3, 3600, array(), true);
            wp_send_json_success($generic_success);
            exit;
        }

        $user_data = array(
            'ip' => $ip,
            'username' => $user->user_login,
            'email' => $user->user_email
        );

        // Generate new activation key — use same ?jg_activate= format as initial registration
        $activation_key = wp_generate_password(32, false);
        update_user_meta($user->ID, 'jg_map_activation_key', $activation_key);
        update_user_meta($user->ID, 'jg_map_activation_key_time', time());

        $activation_link = home_url('/?jg_activate=' . $activation_key);

        $subject = 'Aktywacja konta - ' . get_bloginfo('name');
        $message = "Witaj " . $user->user_login . ",\n\n";
        $message .= "Aby aktywować swoje konto w serwisie " . get_bloginfo('name') . ", kliknij w poniższy link:\n\n";
        $message .= $activation_link . "\n\n";
        $message .= "Link aktywacyjny jest ważny przez 48 godzin.\n\n";
        $message .= "Jeśli nie rejestrowałeś się w naszym serwisie, zignoruj tę wiadomość.\n\n";
        $message .= "Pozdrawiamy,\n";
        $message .= "Zespół " . get_bloginfo('name');

        $email_sent = $this->send_plugin_email($user->user_email, $subject, $message);

        // Record when activation email was sent (overwrites previous send time)
        if ($email_sent) {
            update_user_meta($user->ID, 'jg_map_email_sent_at', time());
        }

        // Increment rate limit after attempt
        $this->check_rate_limit('resend_activation', $ip, 3, 3600, $user_data, true);

        wp_send_json_success($generic_success);
    }

    /**
     * Login user via AJAX
     */
    public function login_user() {
        $this->verify_nonce();

        // Honeypot check - if filled, it's a bot
        $honeypot = isset($_POST['honeypot']) ? $_POST['honeypot'] : '';
        if (!empty($honeypot)) {
            // Bot detected - silently fail
            wp_send_json_error('Nieprawidłowa nazwa użytkownika lub hasło');
            exit;
        }

        $username = isset($_POST['username']) ? sanitize_text_field($_POST['username']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        // Check if user exists and is admin/moderator - they bypass rate limiting
        $user_check = get_user_by('login', $username);
        if (!$user_check) {
            $user_check = get_user_by('email', $username);
        }

        $bypass_rate_limit = false;
        if ($user_check) {
            $is_admin = user_can($user_check->ID, 'manage_options');
            $is_moderator = user_can($user_check->ID, 'jg_map_moderate');
            $bypass_rate_limit = $is_admin || $is_moderator;
        }

        // Rate limiting check (skip for admins and moderators)
        $ip = $this->get_user_ip();
        if (!$bypass_rate_limit) {
            // Prepare user data for rate limiting tracking
            $user_data = array(
                'ip' => $ip,
                'username' => $username,
                'email' => $user_check ? $user_check->user_email : ''
            );

            // Check rate limit without incrementing (3 max attempts for login)
            $rate_check = $this->check_rate_limit('login', $ip, 3, 900, $user_data, false);
            if (!$rate_check['allowed']) {
                $minutes = isset($rate_check['minutes_remaining']) ? $rate_check['minutes_remaining'] : 15;
                $seconds = $minutes * 60;
                wp_send_json_error(array(
                    'message' => 'Zbyt wiele prób logowania. Spróbuj ponownie za ' . $minutes . ' ' . ($minutes === 1 ? 'minutę' : ($minutes < 5 ? 'minuty' : 'minut')) . '.',
                    'type' => 'rate_limit',
                    'seconds_remaining' => $seconds,
                    'action' => 'login'
                ));
                exit;
            }
        }

        if (empty($username) || empty($password)) {
            wp_send_json_error('Proszę wypełnić wszystkie pola');
            exit;
        }

        $credentials = array(
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => true
        );

        $user = wp_signon($credentials, false);

        if (is_wp_error($user)) {
            // Increment rate limit only on failed login (skip for admins/moderators)
            if (!$bypass_rate_limit) {
                $user_data = array(
                    'ip' => $ip,
                    'username' => $username,
                    'email' => $user_check ? $user_check->user_email : ''
                );
                $this->check_rate_limit('login', $ip, 3, 900, $user_data, true);

                // Check if we just hit the rate limit
                $rate_check_after = $this->check_rate_limit('login', $ip, 3, 900, $user_data, false);
                if (!$rate_check_after['allowed']) {
                    // Now blocked - return rate limit error with countdown
                    $minutes = isset($rate_check_after['minutes_remaining']) ? $rate_check_after['minutes_remaining'] : 15;
                    $seconds = $minutes * 60;
                    wp_send_json_error(array(
                        'message' => 'Wykorzystałeś wszystkie próby logowania. Spróbuj ponownie za ' . $minutes . ' ' . ($minutes === 1 ? 'minutę' : ($minutes < 5 ? 'minuty' : 'minut')) . '.',
                        'type' => 'rate_limit',
                        'seconds_remaining' => $seconds,
                        'action' => 'login'
                    ));
                    exit;
                } else {
                    // Still have attempts remaining - show warning
                    $attempts_remaining = isset($rate_check_after['attempts_remaining']) ? $rate_check_after['attempts_remaining'] : 0;
                    $attempts_used = isset($rate_check_after['attempts_used']) ? $rate_check_after['attempts_used'] : 0;

                    if ($attempts_remaining === 1) {
                        // Last attempt warning
                        wp_send_json_error(array(
                            'message' => 'Nieprawidłowa nazwa użytkownika lub hasło.',
                            'type' => 'attempts_warning',
                            'attempts_remaining' => $attempts_remaining,
                            'attempts_used' => $attempts_used,
                            'is_last_attempt' => true,
                            'warning' => 'UWAGA: To była Twoja przedostatnia próba! Jeśli kolejna próba się nie powiedzie, logowanie zostanie zablokowane na 15 minut.',
                            'action' => 'login'
                        ));
                        exit;
                    } else if ($attempts_remaining > 0) {
                        // Regular warning
                        wp_send_json_error(array(
                            'message' => 'Nieprawidłowa nazwa użytkownika lub hasło.',
                            'type' => 'attempts_warning',
                            'attempts_remaining' => $attempts_remaining,
                            'attempts_used' => $attempts_used,
                            'is_last_attempt' => false,
                            'action' => 'login'
                        ));
                        exit;
                    }
                }
            }
            wp_send_json_error('Nieprawidłowa nazwa użytkownika lub hasło');
            exit;
        }

        // Clear rate limit on successful login
        if (!$bypass_rate_limit) {
            $this->clear_rate_limit('login', $ip);
        }

        // Set authentication cookie for wp-admin access
        wp_set_auth_cookie($user->ID, true, is_ssl());

        // Set current user
        wp_set_current_user($user->ID);
        do_action('wp_login', $user->user_login, $user);

        // Check if email is verified
        $account_status = get_user_meta($user->ID, 'jg_map_account_status', true);
        if ($account_status === 'pending') {
            wp_logout(); // Logout the user
            wp_send_json_error(array(
                'message' => 'Twoje konto nie zostało jeszcze aktywowane. Sprawdź swoją skrzynkę email i kliknij w link aktywacyjny.',
                'type' => 'pending_activation',
                'username' => $user->user_login,
                'email' => $user->user_email
            ));
            exit;
        }

        // Check Elementor maintenance mode for users without bypass permission
        $is_admin = user_can($user->ID, 'manage_options');
        $is_moderator = user_can($user->ID, 'jg_map_moderate');
        $can_bypass_maintenance = user_can($user->ID, 'jg_map_bypass_maintenance');

        if (!$is_admin && !$is_moderator && !$can_bypass_maintenance) {
            $maintenance_mode = get_option('elementor_maintenance_mode_mode');

            if ($maintenance_mode === 'maintenance' || $maintenance_mode === 'coming_soon') {
                // Log user out
                wp_logout();

                wp_send_json_error('Trwa konserwacja serwisu. Zapraszamy później. Przepraszamy za utrudnienia.');
                exit;
            }
        }

        wp_send_json_success('Zalogowano pomyślnie');
    }

    /**
     * Register user via AJAX
     */
    public function register_user() {
        $this->verify_nonce();

        // Check if registration is enabled - server-side validation
        $registration_enabled = get_option('jg_map_registration_enabled', 1);
        if (!$registration_enabled || $registration_enabled === '0' || $registration_enabled === 0) {
            $message = get_option('jg_map_registration_disabled_message', 'Rejestracja jest obecnie wyłączona. Spróbuj ponownie później.');
            wp_send_json_error($message);
            exit;
        }

        // Get form data first for rate limiting tracking
        $username = isset($_POST['username']) ? sanitize_text_field($_POST['username']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        // Rate limiting check with user data for admin panel display (check only, don't increment yet)
        // 5 max attempts for registration
        $ip = $this->get_user_ip();
        $user_data = array(
            'ip' => $ip,
            'username' => $username,
            'email' => $email
        );
        $rate_check = $this->check_rate_limit('register', $ip, 5, 3600, $user_data, false);
        if (!$rate_check['allowed']) {
            $minutes = isset($rate_check['minutes_remaining']) ? $rate_check['minutes_remaining'] : 60;
            $seconds = $minutes * 60;
            $hours = ceil($minutes / 60);
            $message = '';
            if ($hours >= 1) {
                $message = 'Wykorzystałeś wszystkie próby rejestracji. Spróbuj ponownie za ' . $hours . ' ' . ($hours === 1 ? 'godzinę' : 'godzin') . '.';
            } else {
                $message = 'Wykorzystałeś wszystkie próby rejestracji. Spróbuj ponownie za ' . $minutes . ' ' . ($minutes === 1 ? 'minutę' : ($minutes < 5 ? 'minuty' : 'minut')) . '.';
            }
            wp_send_json_error(array(
                'message' => $message,
                'type' => 'rate_limit',
                'seconds_remaining' => $seconds,
                'action' => 'register'
            ));
            exit;
        }

        // Honeypot check - if filled, it's a bot
        $honeypot = isset($_POST['honeypot']) ? $_POST['honeypot'] : '';
        if (!empty($honeypot)) {
            // Bot detected - silently fail with generic error
            wp_send_json_error('Wystąpił błąd podczas rejestracji. Spróbuj ponownie.');
            exit;
        }

        // Check Elementor maintenance mode - block registration completely
        $maintenance_mode = get_option('elementor_maintenance_mode_mode');

        if ($maintenance_mode === 'maintenance' || $maintenance_mode === 'coming_soon') {
            wp_send_json_error('Trwają prace konserwacyjne. Rejestracja nowych kont została tymczasowo wstrzymana. Zapraszamy później.');
            exit;
        }

        // Increment rate limit counter HERE - counts all non-bot registration attempts
        // This protects against spam/flood even with invalid data (5 max attempts)
        $this->check_rate_limit('register', $ip, 5, 3600, $user_data, true);

        // Check if we just hit the rate limit after incrementing
        $rate_check_after = $this->check_rate_limit('register', $ip, 5, 3600, $user_data, false);
        $attempts_remaining = isset($rate_check_after['attempts_remaining']) ? $rate_check_after['attempts_remaining'] : 0;
        $attempts_used = isset($rate_check_after['attempts_used']) ? $rate_check_after['attempts_used'] : 0;

        if (!$rate_check_after['allowed']) {
            $minutes = isset($rate_check_after['minutes_remaining']) ? $rate_check_after['minutes_remaining'] : 60;
            $seconds = $minutes * 60;
            $hours = ceil($minutes / 60);
            $message = '';
            if ($hours >= 1) {
                $message = 'Wykorzystałeś wszystkie próby rejestracji. Spróbuj ponownie za ' . $hours . ' ' . ($hours === 1 ? 'godzinę' : 'godzin') . '.';
            } else {
                $message = 'Wykorzystałeś wszystkie próby rejestracji. Spróbuj ponownie za ' . $minutes . ' ' . ($minutes === 1 ? 'minutę' : ($minutes < 5 ? 'minuty' : 'minut')) . '.';
            }
            wp_send_json_error(array(
                'message' => $message,
                'type' => 'rate_limit',
                'seconds_remaining' => $seconds,
                'action' => 'register'
            ));
            exit;
        }

        // Helper function for error response with attempts remaining
        $send_validation_error = function($message) use ($attempts_remaining, $attempts_used) {
            if ($attempts_remaining === 1) {
                // Last attempt warning
                wp_send_json_error(array(
                    'message' => $message,
                    'type' => 'attempts_warning',
                    'attempts_remaining' => $attempts_remaining,
                    'attempts_used' => $attempts_used,
                    'is_last_attempt' => true,
                    'warning' => 'UWAGA: To była Twoja przedostatnia próba! Jeśli kolejna próba się nie powiedzie, rejestracja zostanie zablokowana na 1 godzinę.',
                    'action' => 'register'
                ));
            } else if ($attempts_remaining > 0) {
                // Regular warning
                wp_send_json_error(array(
                    'message' => $message,
                    'type' => 'attempts_warning',
                    'attempts_remaining' => $attempts_remaining,
                    'attempts_used' => $attempts_used,
                    'is_last_attempt' => false,
                    'action' => 'register'
                ));
            } else {
                // No attempts info
                wp_send_json_error($message);
            }
        };

        if (empty($username) || empty($email) || empty($password)) {
            $send_validation_error('Proszę wypełnić wszystkie pola');
            exit;
        }

        // Validate email
        if (!is_email($email)) {
            $send_validation_error('Nieprawidłowy adres email');
            exit;
        }

        // Validate password strength
        $password_check = $this->validate_password_strength($password);
        if (!$password_check['valid']) {
            $send_validation_error($password_check['error']);
            exit;
        }

        // Check if username exists
        if (username_exists($username)) {
            $send_validation_error('Ta nazwa użytkownika jest już zajęta');
            exit;
        }

        // Check if email exists
        if (email_exists($email)) {
            $send_validation_error('Ten adres email jest już zarejestrowany');
            exit;
        }

        // Create user
        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            $send_validation_error($user_id->get_error_message());
            exit;
        }

        // Generate activation key
        $activation_key = wp_generate_password(32, false);
        update_user_meta($user_id, 'jg_map_activation_key', $activation_key);
        update_user_meta($user_id, 'jg_map_activation_key_time', time());
        update_user_meta($user_id, 'jg_map_account_status', 'pending');

        // Send activation email
        $activation_link = home_url('/?jg_activate=' . $activation_key);
        $subject = 'Aktywacja konta - ' . get_bloginfo('name');
        $message = "Witaj {$username}!\n\n";
        $message .= "Dziękujemy za rejestrację na " . get_bloginfo('name') . ".\n\n";
        $message .= "Aby aktywować swoje konto, kliknij w poniższy link:\n";
        $message .= $activation_link . "\n\n";
        $message .= "Link jest ważny przez 48 godzin.\n\n";
        $message .= "Jeśli to nie Ty zarejestrowałeś to konto, zignoruj tę wiadomość.\n\n";
        $message .= "Pozdrawiamy,\n";
        $message .= "Zespół Jeleniórzanie to my";

        $this->send_plugin_email($email, $subject, $message);

        // Record when activation email was sent (persists even after activation)
        update_user_meta($user_id, 'jg_map_email_sent_at', time());

        // Don't auto login - user must verify email first
        wp_send_json_success('Rejestracja zakończona pomyślnie! Sprawdź swoją skrzynkę email i kliknij w link aktywacyjny.');
    }

    public function forgot_password() {
        $this->verify_nonce();

        // Rate limiting check (check only, don't increment yet)
        $ip = $this->get_user_ip();
        $rate_check = $this->check_rate_limit('forgot_password', $ip, 3, 1800, array(), false);
        if (!$rate_check['allowed']) {
            $minutes = isset($rate_check['minutes_remaining']) ? $rate_check['minutes_remaining'] : 30;
            wp_send_json_error('Zbyt wiele prób resetowania hasła. Spróbuj ponownie za ' . $minutes . ' ' . ($minutes === 1 ? 'minutę' : ($minutes < 5 ? 'minuty' : 'minut')) . '.');
            exit;
        }

        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

        if (empty($email)) {
            wp_send_json_error('Proszę podać adres email');
            exit;
        }

        // Validate email format
        if (!is_email($email)) {
            wp_send_json_error('Nieprawidłowy adres email');
            exit;
        }

        // Check if user exists with this email
        $user = get_user_by('email', $email);

        if (!$user) {
            // Don't reveal if email exists or not for security
            // Send success message anyway
            // Increment rate limit counter even if user doesn't exist
            $this->check_rate_limit('forgot_password', $ip, 3, 1800, array(), true);
            wp_send_json_success('Jeśli konto z tym adresem email istnieje, wysłaliśmy link do resetowania hasła.');
            exit;
        }

        // Generate reset key
        $reset_key = wp_generate_password(32, false);
        update_user_meta($user->ID, 'jg_map_reset_key', $reset_key);
        update_user_meta($user->ID, 'jg_map_reset_key_time', time());

        // Send reset email
        $reset_link = home_url('/?jg_reset=' . $reset_key);
        $subject = 'Resetowanie hasła - ' . get_bloginfo('name');
        $message = "Witaj {$user->user_login}!\n\n";
        $message .= "Otrzymaliśmy prośbę o zresetowanie hasła do Twojego konta na " . get_bloginfo('name') . ".\n\n";
        $message .= "Aby ustawić nowe hasło, kliknij w poniższy link:\n";
        $message .= $reset_link . "\n\n";
        $message .= "Link jest ważny przez 24 godziny.\n\n";
        $message .= "Jeśli to nie Ty zleciłeś resetowanie hasła, zignoruj tę wiadomość.\n\n";
        $message .= "Pozdrawiamy,\n";
        $message .= "Zespół Jeleniórzanie to my";

        $this->send_plugin_email($email, $subject, $message);

        // Increment rate limit counter after successful password reset request
        $this->check_rate_limit('forgot_password', $ip, 3, 1800, array(), true);

        wp_send_json_success('Link do resetowania hasła został wysłany na Twój adres email.');
    }

    /**
     * Check if current user should be logged out due to maintenance mode or permission changes
     */
    public function check_user_session_status() {
        // Get user ID from session (even if user doesn't exist anymore)
        $user_id = get_current_user_id();

        // If no user ID in session, not logged in
        if (!$user_id) {
            wp_send_json_success(array(
                'should_logout' => false,
                'reason' => 'not_logged_in'
            ));
            return;
        }

        // Check if user still exists (might have been deleted by admin)
        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_success(array(
                'should_logout' => true,
                'reason' => 'user_deleted',
                'message' => 'Twoje konto zostało usunięte przez administratora.',
                'requires_confirmation' => true
            ));
            return;
        }

        // Get current permissions
        $is_admin = user_can($user_id, 'manage_options');
        $is_moderator = user_can($user_id, 'jg_map_moderate');
        $can_bypass_maintenance = user_can($user_id, 'jg_map_bypass_maintenance');

        // Get sponsored places count for premium status
        global $wpdb;
        $points_table = $wpdb->prefix . 'jg_map_points';
        $sponsored_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $points_table WHERE author_id = %d AND is_promo = 1 AND status = 'publish'",
            $user_id
        ));

        // Check if maintenance mode is active
        $maintenance_mode = get_option('elementor_maintenance_mode_mode');
        $is_maintenance = ($maintenance_mode === 'maintenance' || $maintenance_mode === 'coming_soon');

        // Users who can bypass don't need to be logged out during maintenance
        if ($is_admin || $is_moderator || $can_bypass_maintenance) {
            wp_send_json_success(array(
                'should_logout' => false,
                'reason' => 'has_permissions',
                'user_data' => array(
                    'is_admin' => $is_admin,
                    'is_moderator' => $is_moderator,
                    'sponsored_count' => (int)$sponsored_count
                )
            ));
            return;
        }

        // Regular user during maintenance = should be logged out
        if ($is_maintenance) {
            wp_send_json_success(array(
                'should_logout' => true,
                'reason' => 'maintenance_mode',
                'message' => 'Strona przechodzi w tryb konserwacji. Zapraszamy później. Przepraszamy za utrudnienia.'
            ));
            return;
        }

        // All good - return user data for change detection
        wp_send_json_success(array(
            'should_logout' => false,
            'reason' => 'ok',
            'user_data' => array(
                'is_admin' => $is_admin,
                'is_moderator' => $is_moderator,
                'sponsored_count' => (int)$sponsored_count
            )
        ));
    }

    /**
     * Logout current user via AJAX
     */
    public function logout_user() {
        wp_logout();
        wp_send_json_success('Wylogowano pomyślnie');
    }

    /**
     * Check registration status - returns current registration availability
     */
    public function check_registration_status() {
        $enabled = get_option('jg_map_registration_enabled', 1);
        $message = get_option('jg_map_registration_disabled_message', 'Rejestracja jest obecnie wyłączona. Spróbuj ponownie później.');

        wp_send_json_success(array(
            'enabled' => (bool) $enabled,
            'message' => $message
        ));
    }

    /**
     * Get notification counts for admins/moderators (real-time updates)
     */
    public function get_notification_counts() {
        $this->verify_nonce();

        // Only for admins and moderators
        if (!current_user_can('manage_options') && !current_user_can('jg_map_moderate')) {
            wp_send_json_error(array('message' => 'Brak uprawnień'));
            exit;
        }

        global $wpdb;
        $points_table = JG_Map_Database::get_points_table();
        $reports_table = JG_Map_Database::get_reports_table();
        $history_table = JG_Map_Database::get_history_table();

        // Ensure history table exists
        JG_Map_Database::ensure_history_table();

        $pending_points = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $points_table WHERE status = %s",
            'pending'
        ));
        $pending_edits = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $history_table WHERE status = %s AND action_type = %s",
            'pending',
            'edit'
        ));
        $pending_reports = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT r.point_id)
             FROM $reports_table r
             INNER JOIN $points_table p ON r.point_id = p.id
             WHERE r.status = %s AND p.status = %s",
            'pending',
            'publish'
        ));
        $pending_deletions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $points_table WHERE is_deletion_requested = %d AND status = %s",
            1,
            'publish'
        ));

        wp_send_json_success(array(
            'points' => intval($pending_points),
            'edits' => intval($pending_edits),
            'reports' => intval($pending_reports),
            'deletions' => intval($pending_deletions),
            'total' => intval($pending_points) + intval($pending_edits) + intval($pending_reports) + intval($pending_deletions)
        ));
    }

    public function google_oauth_callback() {
        $code  = sanitize_text_field($_GET['code'] ?? '');
        $state = sanitize_text_field($_GET['state'] ?? '');

        if (empty($code)) {
            $this->output_oauth_result(false, 'google', 'Brak kodu autoryzacji', $state);
            return;
        }

        $client_id     = get_option('jg_map_google_client_id', '');
        $client_secret = get_option('jg_map_google_client_secret', '');
        $redirect_uri  = admin_url('admin-ajax.php') . '?action=jg_google_oauth_callback';

        if (empty($client_id) || empty($client_secret)) {
            $this->output_oauth_result(false, 'google', 'OAuth Google nie jest skonfigurowane', $state);
            return;
        }

        $token_resp = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'code'          => $code,
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri'  => $redirect_uri,
                'grant_type'    => 'authorization_code',
            ),
        ));

        if (is_wp_error($token_resp)) {
            $this->output_oauth_result(false, 'google', 'Błąd wymiany tokena', $state);
            return;
        }

        $token_data   = json_decode(wp_remote_retrieve_body($token_resp), true);
        $access_token = $token_data['access_token'] ?? '';

        if (empty($access_token)) {
            $this->output_oauth_result(false, 'google', 'Nie uzyskano tokena dostępu', $state);
            return;
        }

        $user_resp = wp_remote_get('https://www.googleapis.com/oauth2/v3/userinfo', array(
            'headers' => array('Authorization' => 'Bearer ' . $access_token),
        ));

        if (is_wp_error($user_resp)) {
            $this->output_oauth_result(false, 'google', 'Błąd pobierania danych użytkownika', $state);
            return;
        }

        $user_data    = json_decode(wp_remote_retrieve_body($user_resp), true);
        $google_id    = sanitize_text_field($user_data['sub'] ?? '');
        $email        = sanitize_email($user_data['email'] ?? '');
        $display_name = sanitize_text_field($user_data['name'] ?? '');

        if (empty($google_id) || empty($email)) {
            $this->output_oauth_result(false, 'google', 'Nie uzyskano danych użytkownika z Google', $state);
            return;
        }

        $user_id = $this->find_or_create_social_user($email, $display_name, $google_id, 'google');
        if (is_wp_error($user_id)) {
            $this->output_oauth_result(false, 'google', $user_id->get_error_message(), $state);
            return;
        }

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        $this->output_oauth_result(true, 'google', '', $state);
    }

    public function facebook_oauth_callback() {
        $code  = sanitize_text_field($_GET['code'] ?? '');
        $state = sanitize_text_field($_GET['state'] ?? '');

        if (empty($code)) {
            $this->output_oauth_result(false, 'facebook', 'Brak kodu autoryzacji', $state);
            return;
        }

        $app_id     = get_option('jg_map_facebook_app_id', '');
        $app_secret = get_option('jg_map_facebook_app_secret', '');
        $redirect_uri = admin_url('admin-ajax.php') . '?action=jg_facebook_oauth_callback';

        if (empty($app_id) || empty($app_secret)) {
            $this->output_oauth_result(false, 'facebook', 'OAuth Facebook nie jest skonfigurowane', $state);
            return;
        }

        $token_url  = add_query_arg(array(
            'client_id'     => $app_id,
            'redirect_uri'  => $redirect_uri,
            'client_secret' => $app_secret,
            'code'          => $code,
        ), 'https://graph.facebook.com/v18.0/oauth/access_token');

        $token_resp = wp_remote_get($token_url);
        if (is_wp_error($token_resp)) {
            $this->output_oauth_result(false, 'facebook', 'Błąd wymiany tokena', $state);
            return;
        }

        $token_data   = json_decode(wp_remote_retrieve_body($token_resp), true);
        $access_token = $token_data['access_token'] ?? '';

        if (empty($access_token)) {
            $this->output_oauth_result(false, 'facebook', 'Nie uzyskano tokena dostępu', $state);
            return;
        }

        $user_url  = add_query_arg(array(
            'fields'       => 'id,name,email',
            'access_token' => $access_token,
        ), 'https://graph.facebook.com/v18.0/me');

        $user_resp = wp_remote_get($user_url);
        if (is_wp_error($user_resp)) {
            $this->output_oauth_result(false, 'facebook', 'Błąd pobierania danych użytkownika', $state);
            return;
        }

        $user_data    = json_decode(wp_remote_retrieve_body($user_resp), true);
        $fb_id        = sanitize_text_field($user_data['id'] ?? '');
        $email        = sanitize_email($user_data['email'] ?? '');
        $display_name = sanitize_text_field($user_data['name'] ?? '');

        if (empty($fb_id)) {
            $this->output_oauth_result(false, 'facebook', 'Nie uzyskano danych użytkownika z Facebook', $state);
            return;
        }

        if (empty($email)) {
            $this->output_oauth_result(false, 'facebook', 'Twoje konto Facebook nie udostępniło adresu email. Zarejestruj się przez formularz lub użyj konta Google.', $state);
            return;
        }

        $user_id = $this->find_or_create_social_user($email, $display_name, $fb_id, 'facebook');
        if (is_wp_error($user_id)) {
            $this->output_oauth_result(false, 'facebook', $user_id->get_error_message(), $state);
            return;
        }

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        $this->output_oauth_result(true, 'facebook', '', $state);
    }

    private function find_or_create_social_user($email, $display_name, $provider_id, $provider) {
        $meta_key = 'jg_' . $provider . '_id';

        $existing = get_users(array(
            'meta_key'   => $meta_key,
            'meta_value' => $provider_id,
            'number'     => 1,
            'fields'     => 'ID',
        ));

        if (!empty($existing)) {
            return $existing[0];
        }

        $by_email = get_user_by('email', $email);
        if ($by_email) {
            update_user_meta($by_email->ID, $meta_key, $provider_id);
            return $by_email->ID;
        }

        $username = $this->generate_social_username($display_name, $email);

        $user_id = wp_insert_user(array(
            'user_login'   => $username,
            'user_email'   => $email,
            'display_name' => $display_name ?: $username,
            'user_pass'    => wp_generate_password(32, true, true),
            'role'         => 'subscriber',
        ));

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        update_user_meta($user_id, $meta_key, $provider_id);
        update_user_meta($user_id, 'account_status', 'active');

        return $user_id;
    }

    private function generate_social_username($display_name, $email) {
        $base = $display_name
            ? preg_replace('/[^a-z0-9_]/i', '', strtolower(str_replace(' ', '_', $display_name)))
            : strstr($email, '@', true);
        $base = preg_replace('/[^a-z0-9_]/i', '', $base);
        $base = $base ?: 'user';
        $base = substr($base, 0, 40);

        $candidate = $base;
        $i = 1;
        while (username_exists($candidate)) {
            $candidate = $base . $i;
            $i++;
        }
        return $candidate;
    }

    private function output_oauth_result($success, $provider, $message = '', $state = '') {
        $type         = $success ? 'jg_oauth_success' : 'jg_oauth_error';
        $msg_js       = esc_js($message);
        $prov_js      = esc_js($provider);
        $state_js     = esc_js($state);
        $parsed       = wp_parse_url(home_url());
        $parent_origin = $parsed['scheme'] . '://' . $parsed['host'] . (isset($parsed['port']) ? ':' . $parsed['port'] : '');
        $origin_js    = esc_js($parent_origin);
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body><script>';
        if ($success) {
            echo 'window.opener&&window.opener.postMessage({type:"' . $type . '",jg_provider:"' . $prov_js . '",state:"' . $state_js . '"},"' . $origin_js . '");';
        } else {
            echo 'window.opener&&window.opener.postMessage({type:"' . $type . '",jg_provider:"' . $prov_js . '",message:"' . $msg_js . '",state:"' . $state_js . '"},"' . $origin_js . '");';
        }
        echo 'window.close();';
        echo '</script></body></html>';
        exit;
    }

}
