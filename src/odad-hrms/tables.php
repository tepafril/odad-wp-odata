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

    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hr_skills (
        id       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name     VARCHAR(100)    NOT NULL DEFAULT '',
        category VARCHAR(100)    NOT NULL DEFAULT '',
        PRIMARY KEY (id)
    ) $charset;" );

    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hr_employee_skills (
        id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        employee_id       BIGINT UNSIGNED NOT NULL,
        skill_id          BIGINT UNSIGNED NOT NULL,
        proficiency_level VARCHAR(20)     NOT NULL DEFAULT 'intermediate',
        PRIMARY KEY (id),
        UNIQUE KEY uq_emp_skill (employee_id, skill_id),
        KEY idx_skill (skill_id)
    ) $charset;" );

    // ── Organisation structure ─────────────────────────────────────────────────

    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hr_companies (
        id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name                VARCHAR(200)    NOT NULL DEFAULT '',
        short_name          VARCHAR(50)              DEFAULT NULL,
        industry            VARCHAR(100)             DEFAULT NULL,
        registration_no     VARCHAR(100)             DEFAULT NULL,
        tax_id              VARCHAR(100)             DEFAULT NULL,
        default_currency    CHAR(3)         NOT NULL DEFAULT 'USD',
        fiscal_year_start   TINYINT         NOT NULL DEFAULT 1,
        address_line_1      VARCHAR(255)             DEFAULT NULL,
        address_line_2      VARCHAR(255)             DEFAULT NULL,
        city                VARCHAR(100)             DEFAULT NULL,
        state               VARCHAR(100)             DEFAULT NULL,
        country             CHAR(2)                  DEFAULT NULL,
        postal_code         VARCHAR(20)              DEFAULT NULL,
        phone               VARCHAR(30)              DEFAULT NULL,
        email               VARCHAR(150)             DEFAULT NULL,
        created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset;" );

    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hr_branches (
        id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        company_id      BIGINT UNSIGNED NOT NULL,
        name            VARCHAR(200)    NOT NULL DEFAULT '',
        branch_code     VARCHAR(20)              DEFAULT NULL,
        address_line_1  VARCHAR(255)             DEFAULT NULL,
        address_line_2  VARCHAR(255)             DEFAULT NULL,
        city            VARCHAR(100)             DEFAULT NULL,
        state           VARCHAR(100)             DEFAULT NULL,
        country         CHAR(2)                  DEFAULT NULL,
        postal_code     VARCHAR(20)              DEFAULT NULL,
        phone           VARCHAR(30)              DEFAULT NULL,
        is_head_office  TINYINT(1)      NOT NULL DEFAULT 0,
        status          VARCHAR(20)     NOT NULL DEFAULT 'active',
        created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_branch_code (branch_code),
        KEY idx_company (company_id)
    ) $charset;" );

    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hr_employment_types (
        id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name        VARCHAR(100)    NOT NULL DEFAULT '',
        slug        VARCHAR(100)    NOT NULL DEFAULT '',
        description TEXT                     DEFAULT NULL,
        status      VARCHAR(20)     NOT NULL DEFAULT 'active',
        created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_slug (slug)
    ) $charset;" );

    // ── Employee detail tables ─────────────────────────────────────────────────

    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hr_employee_education (
        id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        employee_id     BIGINT UNSIGNED NOT NULL,
        institution     VARCHAR(255)    NOT NULL DEFAULT '',
        degree          VARCHAR(150)             DEFAULT NULL,
        field_of_study  VARCHAR(150)             DEFAULT NULL,
        start_date      DATE                     DEFAULT NULL,
        end_date        DATE                     DEFAULT NULL,
        grade_or_gpa    VARCHAR(20)              DEFAULT NULL,
        notes           TEXT                     DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_employee (employee_id)
    ) $charset;" );

    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hr_employee_work_history (
        id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        employee_id  BIGINT UNSIGNED NOT NULL,
        company_name VARCHAR(255)    NOT NULL DEFAULT '',
        job_title    VARCHAR(150)             DEFAULT NULL,
        start_date   DATE            NOT NULL,
        end_date     DATE                     DEFAULT NULL,
        description  TEXT                     DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_employee (employee_id)
    ) $charset;" );

    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hr_employee_bank (
        id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        employee_id     BIGINT UNSIGNED NOT NULL,
        bank_name       VARCHAR(200)    NOT NULL DEFAULT '',
        branch_name     VARCHAR(200)             DEFAULT NULL,
        account_name    VARCHAR(200)    NOT NULL DEFAULT '',
        account_number  VARCHAR(255)    NOT NULL DEFAULT '',
        routing_number  VARCHAR(255)             DEFAULT NULL,
        is_primary      TINYINT(1)      NOT NULL DEFAULT 1,
        created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_employee (employee_id)
    ) $charset;" );

    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hr_employee_movement (
        id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        employee_id          BIGINT UNSIGNED NOT NULL,
        movement_type        VARCHAR(30)     NOT NULL DEFAULT '',
        effective_date       DATE            NOT NULL,
        from_department_id   BIGINT UNSIGNED          DEFAULT NULL,
        to_department_id     BIGINT UNSIGNED          DEFAULT NULL,
        from_position_id     BIGINT UNSIGNED          DEFAULT NULL,
        to_position_id       BIGINT UNSIGNED          DEFAULT NULL,
        from_branch_id       BIGINT UNSIGNED          DEFAULT NULL,
        to_branch_id         BIGINT UNSIGNED          DEFAULT NULL,
        reason               TEXT                     DEFAULT NULL,
        approved_by          BIGINT UNSIGNED          DEFAULT NULL,
        status               VARCHAR(20)     NOT NULL DEFAULT 'draft',
        created_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_employee (employee_id),
        KEY idx_status   (status)
    ) $charset;" );

    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hr_employee_documents (
        id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        employee_id   BIGINT UNSIGNED NOT NULL,
        document_type VARCHAR(50)     NOT NULL DEFAULT '',
        title         VARCHAR(255)    NOT NULL DEFAULT '',
        attachment_id BIGINT UNSIGNED NOT NULL,
        issue_date    DATE                     DEFAULT NULL,
        expiry_date   DATE                     DEFAULT NULL,
        is_verified   TINYINT(1)      NOT NULL DEFAULT 0,
        notes         TEXT                     DEFAULT NULL,
        uploaded_by   BIGINT UNSIGNED NOT NULL,
        created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_employee      (employee_id),
        KEY idx_document_type (document_type)
    ) $charset;" );

    // ── System / audit tables ──────────────────────────────────────────────────

    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hr_audit_log (
        id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id     BIGINT UNSIGNED NOT NULL,
        action      VARCHAR(50)     NOT NULL DEFAULT '',
        object_type VARCHAR(50)     NOT NULL DEFAULT '',
        object_id   BIGINT UNSIGNED NOT NULL,
        old_values  LONGTEXT                 DEFAULT NULL,
        new_values  LONGTEXT                 DEFAULT NULL,
        ip_address  VARCHAR(45)              DEFAULT NULL,
        created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_user        (user_id),
        KEY idx_object      (object_type, object_id)
    ) $charset;" );

    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hr_notifications (
        id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id    BIGINT UNSIGNED NOT NULL,
        type       VARCHAR(50)     NOT NULL DEFAULT '',
        title      VARCHAR(255)    NOT NULL DEFAULT '',
        message    TEXT            NOT NULL,
        link       VARCHAR(500)             DEFAULT NULL,
        is_read    TINYINT(1)      NOT NULL DEFAULT 0,
        created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_user_read (user_id, is_read)
    ) $charset;" );

    // ── Holiday management ─────────────────────────────────────────────────────

    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hr_holiday_lists (
        id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        company_id BIGINT UNSIGNED NOT NULL,
        name       VARCHAR(150)    NOT NULL DEFAULT '',
        year       YEAR            NOT NULL,
        status     VARCHAR(20)     NOT NULL DEFAULT 'active',
        created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_company (company_id),
        KEY idx_year    (year)
    ) $charset;" );

    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hr_holidays (
        id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        holiday_list_id  BIGINT UNSIGNED NOT NULL,
        name             VARCHAR(150)    NOT NULL DEFAULT '',
        date             DATE            NOT NULL,
        is_restricted    TINYINT(1)      NOT NULL DEFAULT 0,
        description      VARCHAR(255)             DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_list (holiday_list_id),
        KEY idx_date (date)
    ) $charset;" );

    // ── Leave management ───────────────────────────────────────────────────────

    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hr_leave_types (
        id                           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        company_id                   BIGINT UNSIGNED NOT NULL,
        name                         VARCHAR(100)    NOT NULL DEFAULT '',
        code                         VARCHAR(20)     NOT NULL DEFAULT '',
        max_days_per_year            DECIMAL(5,1)    NOT NULL DEFAULT 0.0,
        is_paid                      TINYINT(1)      NOT NULL DEFAULT 1,
        is_carry_forward             TINYINT(1)      NOT NULL DEFAULT 0,
        max_carry_forward_days       DECIMAL(5,1)             DEFAULT NULL,
        is_encashable                TINYINT(1)      NOT NULL DEFAULT 0,
        allow_negative_balance       TINYINT(1)      NOT NULL DEFAULT 0,
        include_holidays_within      TINYINT(1)      NOT NULL DEFAULT 0,
        allow_half_day               TINYINT(1)      NOT NULL DEFAULT 1,
        requires_attachment          TINYINT(1)      NOT NULL DEFAULT 0,
        requires_attachment_after_days TINYINT                DEFAULT NULL,
        accrual_enabled              TINYINT(1)      NOT NULL DEFAULT 0,
        accrual_frequency            VARCHAR(20)              DEFAULT NULL,
        prorate_on_joining           TINYINT(1)      NOT NULL DEFAULT 1,
        applicable_gender            VARCHAR(20)     NOT NULL DEFAULT 'all',
        category                     VARCHAR(30)     NOT NULL DEFAULT 'general',
        color                        CHAR(7)                  DEFAULT NULL,
        sort_order                   SMALLINT        NOT NULL DEFAULT 0,
        status                       VARCHAR(20)     NOT NULL DEFAULT 'active',
        PRIMARY KEY (id),
        KEY idx_company (company_id),
        KEY idx_status  (status)
    ) $charset;" );

    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hr_leave_policies (
        id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        company_id        BIGINT UNSIGNED NOT NULL,
        name              VARCHAR(150)    NOT NULL DEFAULT '',
        effective_from    DATE            NOT NULL,
        effective_to      DATE                     DEFAULT NULL,
        status            VARCHAR(20)     NOT NULL DEFAULT 'active',
        conflict_strategy VARCHAR(30)     NOT NULL DEFAULT 'highest_priority',
        PRIMARY KEY (id),
        KEY idx_company (company_id),
        KEY idx_status  (status)
    ) $charset;" );

    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hr_leave_policy_details (
        id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        leave_policy_id  BIGINT UNSIGNED NOT NULL,
        leave_type_id    BIGINT UNSIGNED NOT NULL,
        annual_allocation DECIMAL(5,1)   NOT NULL DEFAULT 0.0,
        PRIMARY KEY (id),
        KEY idx_policy (leave_policy_id),
        KEY idx_type   (leave_type_id)
    ) $charset;" );

    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hr_leave_policy_assignments (
        id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        leave_policy_id BIGINT UNSIGNED NOT NULL,
        assignment_type VARCHAR(30)     NOT NULL DEFAULT '',
        assignment_id   VARCHAR(100)    NOT NULL DEFAULT '',
        effective_from  DATE            NOT NULL,
        priority        SMALLINT        NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY idx_policy          (leave_policy_id),
        KEY idx_assignment_type (assignment_type, assignment_id)
    ) $charset;" );

    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hr_leave_balances (
        id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        employee_id       BIGINT UNSIGNED NOT NULL,
        leave_type_id     BIGINT UNSIGNED NOT NULL,
        year              YEAR            NOT NULL,
        total_allocated   DECIMAL(5,1)    NOT NULL DEFAULT 0.0,
        total_taken       DECIMAL(5,1)    NOT NULL DEFAULT 0.0,
        total_pending     DECIMAL(5,1)    NOT NULL DEFAULT 0.0,
        carry_forwarded   DECIMAL(5,1)    NOT NULL DEFAULT 0.0,
        manual_adjustment DECIMAL(5,1)    NOT NULL DEFAULT 0.0,
        last_accrual_date DATE                     DEFAULT NULL,
        updated_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_emp_type_year (employee_id, leave_type_id, year)
    ) $charset;" );

    // ── Attendance ─────────────────────────────────────────────────────────────

    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hr_shifts (
        id                              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        company_id                      BIGINT UNSIGNED NOT NULL,
        name                            VARCHAR(100)    NOT NULL DEFAULT '',
        start_time                      TIME            NOT NULL,
        end_time                        TIME            NOT NULL,
        grace_period_minutes            SMALLINT        NOT NULL DEFAULT 15,
        early_exit_threshold_minutes    SMALLINT        NOT NULL DEFAULT 15,
        working_hours                   DECIMAL(4,2)    NOT NULL DEFAULT 8.00,
        is_overnight                    TINYINT(1)      NOT NULL DEFAULT 0,
        status                          VARCHAR(20)     NOT NULL DEFAULT 'active',
        PRIMARY KEY (id),
        KEY idx_company (company_id)
    ) $charset;" );

    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hr_shift_assignments (
        id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        employee_id    BIGINT UNSIGNED NOT NULL,
        shift_id       BIGINT UNSIGNED NOT NULL,
        effective_from DATE            NOT NULL,
        effective_to   DATE                     DEFAULT NULL,
        created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_employee       (employee_id),
        KEY idx_shift          (shift_id),
        KEY idx_effective_from (effective_from)
    ) $charset;" );

    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hr_attendance (
        id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        employee_id           BIGINT UNSIGNED NOT NULL,
        date                  DATE            NOT NULL,
        status                VARCHAR(30)     NOT NULL DEFAULT 'present',
        check_in              DATETIME                 DEFAULT NULL,
        check_out             DATETIME                 DEFAULT NULL,
        total_working_hours   DECIMAL(4,2)             DEFAULT NULL,
        late_entry            TINYINT(1)      NOT NULL DEFAULT 0,
        early_exit            TINYINT(1)      NOT NULL DEFAULT 0,
        overtime_hours        DECIMAL(4,2)             DEFAULT NULL,
        leave_request_id      BIGINT UNSIGNED          DEFAULT NULL,
        source                VARCHAR(20)     NOT NULL DEFAULT 'manual',
        remarks               VARCHAR(255)             DEFAULT NULL,
        created_at            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_emp_date (employee_id, date),
        KEY idx_date   (date),
        KEY idx_status (status)
    ) $charset;" );

    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hr_attendance_requests (
        id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        employee_id           BIGINT UNSIGNED NOT NULL,
        attendance_id         BIGINT UNSIGNED          DEFAULT NULL,
        date                  DATE            NOT NULL,
        requested_check_in    DATETIME                 DEFAULT NULL,
        requested_check_out   DATETIME                 DEFAULT NULL,
        requested_status      VARCHAR(30)     NOT NULL DEFAULT 'present',
        reason                TEXT            NOT NULL,
        status                VARCHAR(20)     NOT NULL DEFAULT 'pending',
        approved_by           BIGINT UNSIGNED          DEFAULT NULL,
        created_at            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_employee (employee_id),
        KEY idx_status   (status)
    ) $charset;" );

    dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hr_compensatory_requests (
        id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        employee_id      BIGINT UNSIGNED NOT NULL,
        leave_type_id    BIGINT UNSIGNED NOT NULL,
        work_date        DATE            NOT NULL,
        days             DECIMAL(3,1)    NOT NULL DEFAULT 1.0,
        reason           TEXT                     DEFAULT NULL,
        status           VARCHAR(20)     NOT NULL DEFAULT 'pending',
        approved_by      BIGINT UNSIGNED          DEFAULT NULL,
        approval_comment TEXT                     DEFAULT NULL,
        approved_at      DATETIME                 DEFAULT NULL,
        expires_at       DATE                     DEFAULT NULL,
        posted_by        BIGINT UNSIGNED NOT NULL,
        created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_employee  (employee_id),
        KEY idx_status    (status),
        KEY idx_work_date (work_date)
    ) $charset;" );
} );
