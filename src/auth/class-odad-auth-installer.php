<?php
defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin activation/deactivation tasks for the auth layer:
 * creates the refresh-token table, generates the JWT secret, and
 * schedules the daily token-cleanup cron event.
 */
class ODAD_Auth_Installer {

    public static function activate(): void {
        self::create_table();
        self::generate_jwt_secret();
        self::schedule_cron();
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        wp_clear_scheduled_hook( 'odad_purge_expired_tokens' );
        flush_rewrite_rules();
    }

    // ── Table ─────────────────────────────────────────────────────────────

    private static function create_table(): void {
        global $wpdb;

        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}odad_refresh_tokens (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id     BIGINT UNSIGNED NOT NULL,
            token_hash  VARCHAR(64)     NOT NULL,
            device_name VARCHAR(100)    NOT NULL DEFAULT '',
            expires_at  DATETIME        NOT NULL,
            created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_token  (token_hash),
            KEY idx_user    (user_id),
            KEY idx_expires (expires_at)
        ) $charset;" );
    }

    // ── JWT secret ────────────────────────────────────────────────────────

    private static function generate_jwt_secret(): void {
        if ( ! get_option( 'odad_jwt_secret' ) ) {
            update_option(
                'odad_jwt_secret',
                wp_generate_password( 64, true, true ),
                false // not autoloaded
            );
        }
    }

    // ── Cron ──────────────────────────────────────────────────────────────

    private static function schedule_cron(): void {
        if ( ! wp_next_scheduled( 'odad_purge_expired_tokens' ) ) {
            wp_schedule_event( time(), 'daily', 'odad_purge_expired_tokens' );
        }
    }
}
