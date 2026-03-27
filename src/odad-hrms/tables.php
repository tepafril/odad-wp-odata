<?php
/**
 * HR domain — database table creation.
 * Runs on plugin activation via register_activation_hook.
 */

defined( 'ABSPATH' ) || exit;

register_activation_hook( ODAD_PLUGIN_DIR . 'wp-odata-suite.php', function (): void {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hr_departments (
        id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name        VARCHAR(200)    NOT NULL DEFAULT '',
        manager_id  BIGINT UNSIGNED          DEFAULT NULL,
        PRIMARY KEY (id)
    ) $charset;" );

    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hr_positions (
        id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        title         VARCHAR(200)    NOT NULL DEFAULT '',
        department_id BIGINT UNSIGNED          DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_dept (department_id)
    ) $charset;" );

    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hr_employees (
        id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        wp_user_id    BIGINT UNSIGNED          DEFAULT NULL,
        full_name     VARCHAR(200)    NOT NULL DEFAULT '',
        email         VARCHAR(200)    NOT NULL DEFAULT '',
        department_id BIGINT UNSIGNED          DEFAULT NULL,
        position_id   BIGINT UNSIGNED          DEFAULT NULL,
        manager_id    BIGINT UNSIGNED          DEFAULT NULL,
        hired_at      DATE                     DEFAULT NULL,
        is_active     TINYINT(1)      NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        KEY idx_dept    (department_id),
        KEY idx_manager (manager_id),
        KEY idx_active  (is_active)
    ) $charset;" );

    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hr_leave_requests (
        id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        employee_id BIGINT UNSIGNED NOT NULL,
        type        VARCHAR(50)     NOT NULL DEFAULT 'annual',
        start_date  DATE            NOT NULL,
        end_date    DATE            NOT NULL,
        days        DECIMAL(4,1)    NOT NULL DEFAULT 0,
        reason      TEXT                     DEFAULT NULL,
        status      VARCHAR(20)     NOT NULL DEFAULT 'pending',
        approved_by BIGINT UNSIGNED          DEFAULT NULL,
        created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_employee (employee_id),
        KEY idx_status   (status)
    ) $charset;" );

    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hr_timesheets (
        id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        employee_id BIGINT UNSIGNED NOT NULL,
        work_date   DATE            NOT NULL,
        hours       DECIMAL(4,2)    NOT NULL DEFAULT 0,
        note        TEXT                     DEFAULT NULL,
        status      VARCHAR(20)     NOT NULL DEFAULT 'draft',
        submitted_at DATETIME                DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_employee (employee_id),
        KEY idx_date     (work_date)
    ) $charset;" );
} );
