<?php
/**
 * Trait: email notifications
 * send_plugin_email, get_plugin_email_from_name, get_plugin_email_from,
 * notify_admin_new_point, notify_admin_new_report, notify_reporter_confirmation,
 * notify_reporters_decision, notify_author_approved, notify_author_rejected,
 * notify_admin_edit, notify_owner_edit
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

trait JG_Ajax_Notifications {

    /**
     * Send email with proper headers for spam prevention
     */
    public function send_plugin_email($to, $subject, $message) {
        // Temporarily override email sender for this email
        add_filter('wp_mail_from_name', array($this, 'get_plugin_email_from_name'), 99);
        add_filter('wp_mail_from', array($this, 'get_plugin_email_from'), 99);

        // Set up headers for better deliverability and spam prevention
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'Reply-To: powiadomienia@jeleniogorzanietomy.pl',
            'X-Mailer: PHP/' . phpversion(),
            'X-Priority: 3',
            'Importance: Normal'
        );

        // Send email
        $result = wp_mail($to, $subject, $message, $headers);

        // Remove temporary filters
        remove_filter('wp_mail_from_name', array($this, 'get_plugin_email_from_name'), 99);
        remove_filter('wp_mail_from', array($this, 'get_plugin_email_from'), 99);

        return $result;
    }

    /**
     * Get plugin email sender name
     */
    public function get_plugin_email_from_name($from_name) {
        return 'Jeleniogorzanie to my';
    }

    /**
     * Get plugin email sender address
     */
    public function get_plugin_email_from($from_email) {
        return 'powiadomienia@jeleniogorzanietomy.pl';
    }

    /**
     * Notify admin about new point
     */
    private function notify_admin_new_point($point_id) {
        $admin_email = get_option('admin_email');
        $point = JG_Map_Database::get_point($point_id);
        if (!$point) return;

        $subject = 'Portal Jeleniogórzanie to my - Nowy punkt do moderacji';
        $message = "Nowy punkt został dodany i czeka na moderację:\n\n";
        $message .= "Tytuł: {$point['title']}\n";
        $message .= "Typ: {$point['type']}\n";
        $message .= "Link do panelu: " . admin_url('admin.php?page=jg-map-places') . "\n";

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Notify admin about new report
     */
    private function notify_admin_new_report($point_id, $reporter_user_id = 0) {
        $admin_email = get_option('admin_email');
        $point = JG_Map_Database::get_point($point_id);
        if (!$point) return;

        // Get reporter name
        $reporter_name = 'Nieznany';
        if ($reporter_user_id > 0) {
            $reporter = get_userdata($reporter_user_id);
            $reporter_name = $reporter ? $reporter->display_name : 'Nieznany';
        }

        $subject = 'Portal Jeleniogórzanie to my - Nowe zgłoszenie miejsca';
        $message = "Miejsce zostało zgłoszone:\n\n";
        $message .= "Tytuł: {$point['title']}\n";
        $message .= "Zgłoszone przez: {$reporter_name}\n";
        $message .= "Link do panelu: " . admin_url('admin.php?page=jg-map-places') . "\n";

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Notify reporter about confirmation of report
     */
    private function notify_reporter_confirmation($point_id, $email) {
        if (empty($email)) {
            return;
        }

        $point = JG_Map_Database::get_point($point_id);
        if (!$point) return;

        $subject = 'Portal Jeleniogórzanie to my - Potwierdzenie zgłoszenia miejsca';
        $message = "Dziękujemy za zgłoszenie miejsca \"{$point['title']}\".\n\n";
        $message .= "Twoje zgłoszenie zostało przyjęte i zostanie rozpatrzone przez moderatorów.\n";
        $message .= "Otrzymasz powiadomienie email o decyzji moderatora.\n\n";
        $message .= "Dziękujemy za pomoc w utrzymaniu jakości naszej mapy!\n";

        wp_mail($email, $subject, $message);
    }

    /**
     * Notify reporters about decision
     */
    private function notify_reporters_decision($point_id, $decision, $admin_reason) {
        $point = JG_Map_Database::get_point($point_id);
        $reports = JG_Map_Database::get_reports($point_id);

        if (!$point || empty($reports)) {
            return;
        }

        $subject = 'Portal Jeleniogórzanie to my - Decyzja dotycząca zgłoszonego miejsca';
        $message = "Zgłoszone przez Ciebie miejsce \"{$point['title']}\" zostało {$decision}.\n\n";

        if ($admin_reason) {
            $message .= "Uzasadnienie moderatora: {$admin_reason}\n\n";
        }

        $message .= "Dziękujemy za zgłoszenie!\n";

        // Send email to all unique reporters
        $sent_emails = array();
        foreach ($reports as $report) {
            // Get email from user account if user_id exists, otherwise use email field
            $email = null;
            if (!empty($report['user_id'])) {
                $user = get_userdata($report['user_id']);
                if ($user && $user->user_email) {
                    $email = $user->user_email;
                }
            } elseif (!empty($report['email'])) {
                $email = $report['email'];
            }

            if ($email && !in_array($email, $sent_emails)) {
                wp_mail($email, $subject, $message);
                $sent_emails[] = $email;
            }
        }
    }

    /**
     * Notify author about approved point
     */
    private function notify_author_approved($point_id) {
        $point = JG_Map_Database::get_point($point_id);
        if (!$point) return;
        $author = get_userdata($point['author_id']);

        if ($author && $author->user_email) {
            $subject = 'Portal Jeleniogórzanie to my - Twój punkt został zaakceptowany';
            $message = "Twój punkt \"{$point['title']}\" został zaakceptowany i jest teraz widoczny na mapie.";

            wp_mail($author->user_email, $subject, $message);
        }
    }

    /**
     * Notify author about rejected point
     */
    private function notify_author_rejected($point_id, $reason) {
        $point = JG_Map_Database::get_point($point_id);
        if (!$point) return;
        $author = get_userdata($point['author_id']);

        if ($author && $author->user_email) {
            $subject = 'Portal Jeleniogórzanie to my - Twój punkt został odrzucony';
            $message = "Twój punkt \"{$point['title']}\" został odrzucony.\n\n";
            if ($reason) {
                $message .= "Powód: $reason\n";
            }

            wp_mail($author->user_email, $subject, $message);
        }
    }

    /**
     * Notify admin about edit
     */
    private function notify_admin_edit($point_id) {
        $admin_email = get_option('admin_email');
        $point = JG_Map_Database::get_point($point_id);
        if (!$point) return;

        $subject = 'Portal Jeleniogórzanie to my - Edycja miejsca do zatwierdzenia';
        $message = "Użytkownik zaktualizował miejsce:\n\n";
        $message .= "Tytuł: {$point['title']}\n";
        $message .= "Link do panelu: " . admin_url('admin.php?page=jg-map-places') . "\n";

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Notify owner of edit suggestion
     */
    private function notify_owner_edit($point_id, $owner_id) {
        $owner = get_userdata($owner_id);
        if (!$owner || empty($owner->user_email)) {
            return;
        }

        $point = JG_Map_Database::get_point($point_id);
        if (!$point) return;
        $editor = wp_get_current_user();

        $subject = 'Portal Jeleniogórzanie to my - Propozycja edycji twojego miejsca';
        $message = "Użytkownik {$editor->display_name} zaproponował zmiany w twoim miejscu:\n\n";
        $message .= "Tytuł miejsca: {$point['title']}\n";
        $message .= "Link do strony: " . home_url('/mapa/?point=' . $point_id) . "\n\n";
        $message .= "Zaloguj się, aby przejrzeć i zatwierdzić lub odrzucić proponowane zmiany.";

        wp_mail($owner->user_email, $subject, $message);
    }

}
