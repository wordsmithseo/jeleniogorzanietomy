<?php
/**
 * Testy akceptacyjne – przepływy administratora.
 *
 * Weryfikują kompletność narzędzi administracyjnych.
 */

namespace JGMap\Tests\Acceptance;

use PHPUnit\Framework\TestCase;

class AdminFlowTest extends TestCase
{
    private string $ajaxContent;
    private string $adminContent;

    protected function setUp(): void
    {
        $this->ajaxContent = file_get_contents(dirname(__DIR__, 2) . '/includes/class-ajax-handlers.php');
        $this->adminContent = file_get_contents(dirname(__DIR__, 2) . '/includes/class-admin.php');
    }

    // ─── Panel admina ────────────────────────────────────────────────

    public function test_admin_menu_is_registered(): void
    {
        $this->assertStringContainsString('add_menu_page', $this->adminContent);
    }

    public function test_admin_has_dashboard(): void
    {
        $this->assertStringContainsString('render_main_page', $this->adminContent);
    }

    // ─── Zarządzanie punktami ────────────────────────────────────────

    public function test_admin_can_approve_points(): void
    {
        $this->assertStringContainsString('function admin_approve_point', $this->ajaxContent);
    }

    public function test_admin_can_reject_points(): void
    {
        $this->assertStringContainsString('function admin_reject_point', $this->ajaxContent);
    }

    public function test_admin_can_delete_points(): void
    {
        $this->assertStringContainsString('function admin_delete_point', $this->ajaxContent);
    }

    public function test_admin_can_change_point_status(): void
    {
        $this->assertStringContainsString('function admin_change_status', $this->ajaxContent);
    }

    public function test_admin_can_edit_and_resolve_reports(): void
    {
        $this->assertStringContainsString('function admin_edit_and_resolve_reports', $this->ajaxContent);
    }

    // ─── Zarządzanie użytkownikami ───────────────────────────────────

    public function test_admin_can_ban_users(): void
    {
        $this->assertStringContainsString('function admin_ban_user', $this->ajaxContent);
        $this->assertStringContainsString('function admin_unban_user', $this->ajaxContent);
    }

    public function test_admin_can_delete_users(): void
    {
        $this->assertStringContainsString('function admin_delete_user', $this->ajaxContent);
    }

    public function test_admin_can_manage_user_restrictions(): void
    {
        $this->assertStringContainsString('function admin_toggle_user_restriction', $this->ajaxContent);
        $this->assertStringContainsString('function get_user_restrictions', $this->ajaxContent);
    }

    public function test_admin_can_manage_user_limits(): void
    {
        $this->assertStringContainsString('function admin_get_user_limits', $this->ajaxContent);
        $this->assertStringContainsString('function admin_set_user_limits', $this->ajaxContent);
    }

    // ─── Zarządzanie edycjami ────────────────────────────────────────

    public function test_admin_can_approve_edits(): void
    {
        $this->assertStringContainsString('function admin_approve_edit', $this->ajaxContent);
    }

    public function test_admin_can_reject_edits(): void
    {
        $this->assertStringContainsString('function admin_reject_edit', $this->ajaxContent);
    }

    public function test_admin_can_revert_to_history(): void
    {
        $this->assertStringContainsString('function admin_revert_to_history', $this->ajaxContent);
    }

    // ─── Zarządzanie promocjami ──────────────────────────────────────

    public function test_admin_can_toggle_promo(): void
    {
        $this->assertStringContainsString('function admin_toggle_promo', $this->ajaxContent);
    }

    public function test_admin_can_update_promo_date(): void
    {
        $this->assertStringContainsString('function admin_update_promo_date', $this->ajaxContent);
    }

    // ─── Zarządzanie kategoriami ─────────────────────────────────────

    public function test_admin_can_manage_place_categories(): void
    {
        $this->assertStringContainsString('function save_place_category', $this->ajaxContent);
        $this->assertStringContainsString('function update_place_category', $this->ajaxContent);
        $this->assertStringContainsString('function delete_place_category', $this->ajaxContent);
    }

    public function test_admin_can_manage_curiosity_categories(): void
    {
        $this->assertStringContainsString('function save_curiosity_category', $this->ajaxContent);
        $this->assertStringContainsString('function update_curiosity_category', $this->ajaxContent);
        $this->assertStringContainsString('function delete_curiosity_category', $this->ajaxContent);
    }

    public function test_admin_can_manage_report_categories(): void
    {
        $this->assertStringContainsString('function save_report_category', $this->ajaxContent);
        $this->assertStringContainsString('function update_report_category', $this->ajaxContent);
        $this->assertStringContainsString('function delete_report_category', $this->ajaxContent);
    }

    // ─── Zarządzanie tagami ──────────────────────────────────────────

    public function test_admin_can_manage_tags(): void
    {
        $this->assertStringContainsString('function admin_get_tags_paginated', $this->ajaxContent);
        $this->assertStringContainsString('function admin_rename_tag', $this->ajaxContent);
        $this->assertStringContainsString('function admin_delete_tag', $this->ajaxContent);
    }

    // ─── Edycja blokowanie ──────────────────────────────────────────

    public function test_admin_can_lock_edits(): void
    {
        $this->assertStringContainsString('function admin_toggle_edit_lock', $this->ajaxContent);
    }

    // ─── Zmiana właściciela ──────────────────────────────────────────

    public function test_admin_can_change_owner(): void
    {
        $this->assertStringContainsString('function admin_change_owner', $this->ajaxContent);
    }

    // ─── IP unblock ──────────────────────────────────────────────────

    public function test_admin_can_unblock_ip(): void
    {
        $this->assertStringContainsString('function admin_unblock_ip', $this->ajaxContent);
    }
}
