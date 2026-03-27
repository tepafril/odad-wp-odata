<?php
/**
 * ODAD-HRMS Seeder
 *
 * Populates all HR tables with realistic sample data for relationship testing.
 *
 * Trigger options:
 *   WP-CLI : wp eval-file wp-content/plugins/wp-odata-suite/src/odad-hrms/seeder.php
 *   Browser: WP Admin → Tools → ODAD HRMS Seeder  (admin-only page)
 */

defined( 'ABSPATH' ) || exit;

// ── Seeder class ──────────────────────────────────────────────────────────────

class ODAD_HRMS_Seeder {

    private wpdb $db;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
    }

    // ── Public entry point ────────────────────────────────────────────────

    public function run(): array {
        $this->truncate_all();

        $dept_ids     = $this->seed_departments();
        $position_ids = $this->seed_positions( $dept_ids );
        $employee_ids = $this->seed_employees( $dept_ids, $position_ids );

        $this->assign_department_managers( $dept_ids, $employee_ids );

        $this->seed_leave_requests( $employee_ids );
        $this->seed_timesheets( $employee_ids );

        $skill_ids            = $this->seed_skills();
        $employee_skill_count = $this->seed_employee_skills( $employee_ids, $skill_ids );

        // New tables.
        $company_ids         = $this->seed_companies();
        $branch_ids          = $this->seed_branches( $company_ids );
        $employment_type_ids = $this->seed_employment_types();

        $this->seed_employee_education( $employee_ids );
        $this->seed_employee_work_history( $employee_ids );
        $this->seed_employee_bank( $employee_ids );
        $this->seed_employee_movement( $employee_ids, $dept_ids, $position_ids, $branch_ids );
        $this->seed_employee_documents( $employee_ids );
        $this->seed_notifications( $employee_ids );

        $holiday_list_ids    = $this->seed_holiday_lists( $company_ids );
        $this->seed_holidays( $holiday_list_ids );

        $leave_type_ids      = $this->seed_leave_types( $company_ids );
        $leave_policy_ids    = $this->seed_leave_policies( $company_ids );
        $this->seed_leave_policy_details( $leave_policy_ids, $leave_type_ids );
        $this->seed_leave_policy_assignments( $leave_policy_ids, $dept_ids );
        $this->seed_leave_balances( $employee_ids, $leave_type_ids );

        $shift_ids           = $this->seed_shifts( $company_ids );
        $this->seed_shift_assignments( $employee_ids, $shift_ids );
        $this->seed_attendance( $employee_ids );
        $this->seed_compensatory_requests( $employee_ids, $leave_type_ids );

        return [
            'departments'            => count( $dept_ids ),
            'positions'              => count( $position_ids ),
            'employees'              => count( $employee_ids ),
            'leave_requests'         => 12,
            'timesheets'             => count( $employee_ids ) * 5,
            'skills'                 => count( $skill_ids ),
            'employee_skills'        => $employee_skill_count,
            'companies'              => count( $company_ids ),
            'branches'               => count( $branch_ids ),
            'employment_types'       => count( $employment_type_ids ),
            'leave_types'            => count( $leave_type_ids ),
            'leave_policies'         => count( $leave_policy_ids ),
            'shifts'                 => count( $shift_ids ),
        ];
    }

    // ── Truncate ──────────────────────────────────────────────────────────

    private function truncate_all(): void {
        // Disable FK checks so truncate order doesn't matter.
        $this->db->query( 'SET FOREIGN_KEY_CHECKS = 0' );

        $tables = [
            // New tables.
            'hr_compensatory_requests',
            'hr_attendance_requests',
            'hr_attendance',
            'hr_shift_assignments',
            'hr_shifts',
            'hr_leave_balances',
            'hr_leave_policy_assignments',
            'hr_leave_policy_details',
            'hr_leave_policies',
            'hr_leave_types',
            'hr_holidays',
            'hr_holiday_lists',
            'hr_notifications',
            'hr_audit_log',
            'hr_employee_documents',
            'hr_employee_movement',
            'hr_employee_bank',
            'hr_employee_work_history',
            'hr_employee_education',
            'hr_branches',
            'hr_employment_types',
            'hr_companies',
            // Core tables.
            'hr_employee_skills',
            'hr_skills',
            'hr_timesheets',
            'hr_leave_requests',
            'hr_employees',
            'hr_positions',
            'hr_departments',
        ];

        foreach ( $tables as $table ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table names are hardcoded literals.
            $this->db->query( 'TRUNCATE TABLE `' . $this->db->prefix . $table . '`' );
        }

        $this->db->query( 'SET FOREIGN_KEY_CHECKS = 1' );
    }

    // ── Departments ───────────────────────────────────────────────────────

    private function seed_departments(): array {
        $rows = [
            [ 'name' => 'Engineering' ],
            [ 'name' => 'Human Resources' ],
            [ 'name' => 'Sales' ],
            [ 'name' => 'Finance' ],
        ];

        $ids = [];
        foreach ( $rows as $row ) {
            $this->db->insert( $this->db->prefix . 'hr_departments', $row );
            $ids[ $row['name'] ] = $this->db->insert_id;
        }
        return $ids;
    }

    // ── Positions ─────────────────────────────────────────────────────────

    private function seed_positions( array $dept_ids ): array {
        $rows = [
            // Engineering
            [ 'title' => 'Software Engineer',        'department_id' => $dept_ids['Engineering'] ],
            [ 'title' => 'Senior Software Engineer', 'department_id' => $dept_ids['Engineering'] ],
            [ 'title' => 'Engineering Manager',      'department_id' => $dept_ids['Engineering'] ],
            // Human Resources
            [ 'title' => 'HR Officer',               'department_id' => $dept_ids['Human Resources'] ],
            [ 'title' => 'HR Manager',               'department_id' => $dept_ids['Human Resources'] ],
            // Sales
            [ 'title' => 'Sales Executive',          'department_id' => $dept_ids['Sales'] ],
            [ 'title' => 'Sales Manager',            'department_id' => $dept_ids['Sales'] ],
            // Finance
            [ 'title' => 'Accountant',               'department_id' => $dept_ids['Finance'] ],
            [ 'title' => 'Finance Manager',          'department_id' => $dept_ids['Finance'] ],
        ];

        $ids = [];
        foreach ( $rows as $row ) {
            $this->db->insert( $this->db->prefix . 'hr_positions', $row );
            $ids[ $row['title'] ] = $this->db->insert_id;
        }
        return $ids;
    }

    // ── Employees ─────────────────────────────────────────────────────────

    private function seed_employees( array $dept_ids, array $position_ids ): array {
        // Resolve the WP admin user to link at least one employee.
        $admin_id = (int) $this->db->get_var(
            "SELECT ID FROM {$this->db->users} WHERE user_login = 'admin' LIMIT 1"
        ) ?: null;

        $rows = [
            // Engineering — manager first so we can set manager_id on others
            [
                'full_name'     => 'David Chen',
                'email'         => 'david.chen@example.com',
                'department_id' => $dept_ids['Engineering'],
                'position_id'   => $position_ids['Engineering Manager'],
                'manager_id'    => null,
                'hired_at'      => '2019-03-10',
                'is_active'     => 1,
                'wp_user_id'    => $admin_id,
            ],
            [
                'full_name'     => 'Alice Johnson',
                'email'         => 'alice.johnson@example.com',
                'department_id' => $dept_ids['Engineering'],
                'position_id'   => $position_ids['Senior Software Engineer'],
                'manager_id'    => null, // filled after insert
                'hired_at'      => '2020-06-01',
                'is_active'     => 1,
                'wp_user_id'    => null,
            ],
            [
                'full_name'     => 'Bob Smith',
                'email'         => 'bob.smith@example.com',
                'department_id' => $dept_ids['Engineering'],
                'position_id'   => $position_ids['Software Engineer'],
                'manager_id'    => null,
                'hired_at'      => '2021-09-15',
                'is_active'     => 1,
                'wp_user_id'    => null,
            ],
            [
                'full_name'     => 'Carol White',
                'email'         => 'carol.white@example.com',
                'department_id' => $dept_ids['Engineering'],
                'position_id'   => $position_ids['Software Engineer'],
                'manager_id'    => null,
                'hired_at'      => '2022-01-20',
                'is_active'     => 1,
                'wp_user_id'    => null,
            ],
            // Human Resources
            [
                'full_name'     => 'Emma Davis',
                'email'         => 'emma.davis@example.com',
                'department_id' => $dept_ids['Human Resources'],
                'position_id'   => $position_ids['HR Manager'],
                'manager_id'    => null,
                'hired_at'      => '2018-11-05',
                'is_active'     => 1,
                'wp_user_id'    => null,
            ],
            [
                'full_name'     => 'Frank Lee',
                'email'         => 'frank.lee@example.com',
                'department_id' => $dept_ids['Human Resources'],
                'position_id'   => $position_ids['HR Officer'],
                'manager_id'    => null,
                'hired_at'      => '2021-04-12',
                'is_active'     => 1,
                'wp_user_id'    => null,
            ],
            // Sales
            [
                'full_name'     => 'Grace Kim',
                'email'         => 'grace.kim@example.com',
                'department_id' => $dept_ids['Sales'],
                'position_id'   => $position_ids['Sales Manager'],
                'manager_id'    => null,
                'hired_at'      => '2019-07-22',
                'is_active'     => 1,
                'wp_user_id'    => null,
            ],
            [
                'full_name'     => 'Henry Park',
                'email'         => 'henry.park@example.com',
                'department_id' => $dept_ids['Sales'],
                'position_id'   => $position_ids['Sales Executive'],
                'manager_id'    => null,
                'hired_at'      => '2022-03-08',
                'is_active'     => 1,
                'wp_user_id'    => null,
            ],
            [
                'full_name'     => 'Iris Tan',
                'email'         => 'iris.tan@example.com',
                'department_id' => $dept_ids['Sales'],
                'position_id'   => $position_ids['Sales Executive'],
                'manager_id'    => null,
                'hired_at'      => '2023-01-16',
                'is_active'     => 1,
                'wp_user_id'    => null,
            ],
            // Finance
            [
                'full_name'     => 'James Wong',
                'email'         => 'james.wong@example.com',
                'department_id' => $dept_ids['Finance'],
                'position_id'   => $position_ids['Finance Manager'],
                'manager_id'    => null,
                'hired_at'      => '2017-05-30',
                'is_active'     => 1,
                'wp_user_id'    => null,
            ],
            [
                'full_name'     => 'Karen Ng',
                'email'         => 'karen.ng@example.com',
                'department_id' => $dept_ids['Finance'],
                'position_id'   => $position_ids['Accountant'],
                'manager_id'    => null,
                'hired_at'      => '2020-10-01',
                'is_active'     => 1,
                'wp_user_id'    => null,
            ],
            // Inactive employee — tests $filter=IsActive eq false
            [
                'full_name'     => 'Leo Cruz',
                'email'         => 'leo.cruz@example.com',
                'department_id' => $dept_ids['Engineering'],
                'position_id'   => $position_ids['Software Engineer'],
                'manager_id'    => null,
                'hired_at'      => '2020-02-14',
                'is_active'     => 0,
                'wp_user_id'    => null,
            ],
        ];

        $ids = [];
        foreach ( $rows as $row ) {
            $this->db->insert( $this->db->prefix . 'hr_employees', $row );
            $ids[ $row['full_name'] ] = $this->db->insert_id;
        }

        // Wire up manager relationships (ICs report to their dept managers).
        $manager_map = [
            'Alice Johnson' => $ids['David Chen'],
            'Bob Smith'     => $ids['David Chen'],
            'Carol White'   => $ids['David Chen'],
            'Leo Cruz'      => $ids['David Chen'],
            'Frank Lee'     => $ids['Emma Davis'],
            'Henry Park'    => $ids['Grace Kim'],
            'Iris Tan'      => $ids['Grace Kim'],
            'Karen Ng'      => $ids['James Wong'],
        ];

        foreach ( $manager_map as $employee => $manager_id ) {
            $this->db->update(
                $this->db->prefix . 'hr_employees',
                [ 'manager_id' => $manager_id ],
                [ 'id'         => $ids[ $employee ] ],
            );
        }

        return $ids;
    }

    // ── Assign managers to departments ────────────────────────────────────

    private function assign_department_managers( array $dept_ids, array $employee_ids ): void {
        $map = [
            $dept_ids['Engineering']     => $employee_ids['David Chen'],
            $dept_ids['Human Resources'] => $employee_ids['Emma Davis'],
            $dept_ids['Sales']           => $employee_ids['Grace Kim'],
            $dept_ids['Finance']         => $employee_ids['James Wong'],
        ];

        foreach ( $map as $dept_id => $manager_id ) {
            $this->db->update(
                $this->db->prefix . 'hr_departments',
                [ 'manager_id' => $manager_id ],
                [ 'id'         => $dept_id ],
            );
        }
    }

    // ── Leave Requests ────────────────────────────────────────────────────

    private function seed_leave_requests( array $employee_ids ): void {
        $rows = [
            // Approved leaves
            [
                'employee_id' => $employee_ids['Alice Johnson'],
                'type'        => 'annual',
                'start_date'  => '2025-01-06',
                'end_date'    => '2025-01-10',
                'days'        => 5,
                'reason'      => 'Family vacation',
                'status'      => 'approved',
                'approved_by' => $employee_ids['David Chen'],
                'created_at'  => '2024-12-15 09:00:00',
            ],
            [
                'employee_id' => $employee_ids['Bob Smith'],
                'type'        => 'annual',
                'start_date'  => '2025-02-17',
                'end_date'    => '2025-02-21',
                'days'        => 5,
                'reason'      => 'Holiday trip',
                'status'      => 'approved',
                'approved_by' => $employee_ids['David Chen'],
                'created_at'  => '2025-01-20 10:30:00',
            ],
            [
                'employee_id' => $employee_ids['Frank Lee'],
                'type'        => 'sick',
                'start_date'  => '2025-03-03',
                'end_date'    => '2025-03-04',
                'days'        => 2,
                'reason'      => 'Flu',
                'status'      => 'approved',
                'approved_by' => $employee_ids['Emma Davis'],
                'created_at'  => '2025-03-03 08:00:00',
            ],
            [
                'employee_id' => $employee_ids['Henry Park'],
                'type'        => 'annual',
                'start_date'  => '2025-04-14',
                'end_date'    => '2025-04-18',
                'days'        => 5,
                'reason'      => 'Wedding anniversary',
                'status'      => 'approved',
                'approved_by' => $employee_ids['Grace Kim'],
                'created_at'  => '2025-03-28 14:00:00',
            ],
            [
                'employee_id' => $employee_ids['Karen Ng'],
                'type'        => 'annual',
                'start_date'  => '2025-05-05',
                'end_date'    => '2025-05-09',
                'days'        => 5,
                'reason'      => 'Personal travel',
                'status'      => 'approved',
                'approved_by' => $employee_ids['James Wong'],
                'created_at'  => '2025-04-10 11:00:00',
            ],
            // Pending leaves — good for testing approve action
            [
                'employee_id' => $employee_ids['Carol White'],
                'type'        => 'annual',
                'start_date'  => '2025-08-04',
                'end_date'    => '2025-08-08',
                'days'        => 5,
                'reason'      => 'Summer holiday',
                'status'      => 'pending',
                'approved_by' => null,
                'created_at'  => '2025-06-01 09:00:00',
            ],
            [
                'employee_id' => $employee_ids['Iris Tan'],
                'type'        => 'annual',
                'start_date'  => '2025-07-21',
                'end_date'    => '2025-07-25',
                'days'        => 5,
                'reason'      => 'Family trip',
                'status'      => 'pending',
                'approved_by' => null,
                'created_at'  => '2025-06-10 16:00:00',
            ],
            [
                'employee_id' => $employee_ids['Bob Smith'],
                'type'        => 'sick',
                'start_date'  => '2025-06-23',
                'end_date'    => '2025-06-23',
                'days'        => 1,
                'reason'      => 'Doctor appointment',
                'status'      => 'pending',
                'approved_by' => null,
                'created_at'  => '2025-06-22 20:00:00',
            ],
            // Rejected leave
            [
                'employee_id' => $employee_ids['Alice Johnson'],
                'type'        => 'annual',
                'start_date'  => '2025-03-17',
                'end_date'    => '2025-03-21',
                'days'        => 5,
                'reason'      => 'Travel',
                'status'      => 'rejected',
                'approved_by' => $employee_ids['David Chen'],
                'created_at'  => '2025-03-01 10:00:00',
            ],
            // Long leave — tests balance calculation
            [
                'employee_id' => $employee_ids['Grace Kim'],
                'type'        => 'annual',
                'start_date'  => '2024-12-23',
                'end_date'    => '2025-01-03',
                'days'        => 10,
                'reason'      => 'Year-end break',
                'status'      => 'approved',
                'approved_by' => null,
                'created_at'  => '2024-11-30 09:00:00',
            ],
            [
                'employee_id' => $employee_ids['David Chen'],
                'type'        => 'annual',
                'start_date'  => '2025-06-02',
                'end_date'    => '2025-06-06',
                'days'        => 5,
                'reason'      => 'Conference + vacation',
                'status'      => 'pending',
                'approved_by' => null,
                'created_at'  => '2025-05-15 08:30:00',
            ],
            [
                'employee_id' => $employee_ids['Karen Ng'],
                'type'        => 'maternity',
                'start_date'  => '2025-09-01',
                'end_date'    => '2025-11-28',
                'days'        => 60,
                'reason'      => 'Maternity leave',
                'status'      => 'approved',
                'approved_by' => $employee_ids['James Wong'],
                'created_at'  => '2025-07-01 10:00:00',
            ],
        ];

        foreach ( $rows as $row ) {
            $this->db->insert( $this->db->prefix . 'hr_leave_requests', $row );
        }
    }

    // ── Timesheets ────────────────────────────────────────────────────────

    private function seed_timesheets( array $employee_ids ): void {
        // Seed 5 working days of timesheets for each active employee.
        $active_employees = array_filter(
            $employee_ids,
            fn( $name ) => $name !== 'Leo Cruz',
            ARRAY_FILTER_USE_KEY
        );

        // Work week: Mon–Fri
        $work_dates = [ '2025-06-16', '2025-06-17', '2025-06-18', '2025-06-19', '2025-06-20' ];

        $hours_by_date = [
            '2025-06-16' => 8.0,
            '2025-06-17' => 8.5,
            '2025-06-18' => 7.5,
            '2025-06-19' => 8.0,
            '2025-06-20' => 6.0,
        ];

        foreach ( $active_employees as $name => $emp_id ) {
            foreach ( $work_dates as $date ) {
                // Mix of draft, submitted, and approved statuses for variety.
                if ( $date <= '2025-06-18' ) {
                    $status       = 'submitted';
                    $submitted_at = date( 'Y-m-d H:i:s', strtotime( $date . ' +1 day 09:00:00' ) );
                } else {
                    $status       = 'draft';
                    $submitted_at = null;
                }

                $this->db->insert(
                    $this->db->prefix . 'hr_timesheets',
                    [
                        'employee_id'  => $emp_id,
                        'work_date'    => $date,
                        'hours'        => $hours_by_date[ $date ],
                        'note'         => null,
                        'status'       => $status,
                        'submitted_at' => $submitted_at,
                    ]
                );
            }
        }
    }

    // ── Skills ────────────────────────────────────────────────────────────

    private function seed_skills(): array {
        $rows = [
            // Engineering
            [ 'name' => 'PHP',            'category' => 'Backend' ],
            [ 'name' => 'JavaScript',     'category' => 'Frontend' ],
            [ 'name' => 'React',          'category' => 'Frontend' ],
            [ 'name' => 'MySQL',          'category' => 'Database' ],
            [ 'name' => 'Docker',         'category' => 'DevOps' ],
            [ 'name' => 'Python',         'category' => 'Backend' ],
            // General / cross-dept
            [ 'name' => 'Excel',          'category' => 'Productivity' ],
            [ 'name' => 'Project Management', 'category' => 'Management' ],
            [ 'name' => 'Public Speaking','category' => 'Soft Skills' ],
            [ 'name' => 'Data Analysis',  'category' => 'Analytics' ],
            [ 'name' => 'Negotiation',    'category' => 'Soft Skills' ],
            [ 'name' => 'Accounting',     'category' => 'Finance' ],
        ];

        $ids = [];
        foreach ( $rows as $row ) {
            $this->db->insert( $this->db->prefix . 'hr_skills', $row );
            $ids[ $row['name'] ] = $this->db->insert_id;
        }
        return $ids;
    }

    // ── Employee ↔ Skill pivot ─────────────────────────────────────────────

    private function seed_employee_skills( array $employee_ids, array $skill_ids ): int {
        // Each tuple: [ employee_name, skill_name, proficiency_level ]
        $assignments = [
            // David Chen — Engineering Manager
            [ 'David Chen',    'PHP',                'expert' ],
            [ 'David Chen',    'Project Management', 'expert' ],
            [ 'David Chen',    'MySQL',              'advanced' ],
            [ 'David Chen',    'Docker',             'intermediate' ],
            // Alice Johnson — Senior SE
            [ 'Alice Johnson', 'PHP',                'expert' ],
            [ 'Alice Johnson', 'React',              'advanced' ],
            [ 'Alice Johnson', 'JavaScript',         'advanced' ],
            [ 'Alice Johnson', 'MySQL',              'intermediate' ],
            // Bob Smith — SE
            [ 'Bob Smith',     'PHP',                'intermediate' ],
            [ 'Bob Smith',     'JavaScript',         'intermediate' ],
            [ 'Bob Smith',     'Docker',             'beginner' ],
            // Carol White — SE
            [ 'Carol White',   'Python',             'advanced' ],
            [ 'Carol White',   'MySQL',              'advanced' ],
            [ 'Carol White',   'Data Analysis',      'intermediate' ],
            // Emma Davis — HR Manager
            [ 'Emma Davis',    'Project Management', 'expert' ],
            [ 'Emma Davis',    'Public Speaking',    'expert' ],
            [ 'Emma Davis',    'Excel',              'advanced' ],
            // Frank Lee — HR Officer
            [ 'Frank Lee',     'Excel',              'intermediate' ],
            [ 'Frank Lee',     'Public Speaking',    'intermediate' ],
            // Grace Kim — Sales Manager
            [ 'Grace Kim',     'Negotiation',        'expert' ],
            [ 'Grace Kim',     'Project Management', 'advanced' ],
            [ 'Grace Kim',     'Public Speaking',    'advanced' ],
            // Henry Park — Sales Executive
            [ 'Henry Park',    'Negotiation',        'intermediate' ],
            [ 'Henry Park',    'Excel',              'intermediate' ],
            // Iris Tan — Sales Executive
            [ 'Iris Tan',      'Negotiation',        'beginner' ],
            [ 'Iris Tan',      'Data Analysis',      'intermediate' ],
            // James Wong — Finance Manager
            [ 'James Wong',    'Accounting',         'expert' ],
            [ 'James Wong',    'Excel',              'expert' ],
            [ 'James Wong',    'Data Analysis',      'advanced' ],
            // Karen Ng — Accountant
            [ 'Karen Ng',      'Accounting',         'advanced' ],
            [ 'Karen Ng',      'Excel',              'advanced' ],
            // Leo Cruz — inactive engineer (still has skills)
            [ 'Leo Cruz',      'PHP',                'intermediate' ],
            [ 'Leo Cruz',      'JavaScript',         'beginner' ],
        ];

        foreach ( $assignments as [ $employee, $skill, $level ] ) {
            $this->db->insert(
                $this->db->prefix . 'hr_employee_skills',
                [
                    'employee_id'       => $employee_ids[ $employee ],
                    'skill_id'          => $skill_ids[ $skill ],
                    'proficiency_level' => $level,
                ]
            );
        }

        return count( $assignments );
    }

    // ── Companies ──────────────────────────────────────────────────────────

    private function seed_companies(): array {
        $rows = [
            [
                'name'              => 'Acme Corp',
                'short_name'        => 'ACME',
                'industry'          => 'Technology',
                'registration_no'   => 'REG-001',
                'tax_id'            => 'TAX-001',
                'default_currency'  => 'USD',
                'fiscal_year_start' => 1,
                'city'              => 'San Francisco',
                'country'           => 'US',
                'email'             => 'info@acme.example.com',
            ],
            [
                'name'              => 'Beta Solutions',
                'short_name'        => 'BETA',
                'industry'          => 'Consulting',
                'registration_no'   => 'REG-002',
                'tax_id'            => 'TAX-002',
                'default_currency'  => 'USD',
                'fiscal_year_start' => 4,
                'city'              => 'New York',
                'country'           => 'US',
                'email'             => 'info@beta.example.com',
            ],
        ];

        $ids = [];
        foreach ( $rows as $row ) {
            $this->db->insert( $this->db->prefix . 'hr_companies', $row );
            $ids[ $row['name'] ] = $this->db->insert_id;
        }
        return $ids;
    }

    // ── Branches ───────────────────────────────────────────────────────────

    private function seed_branches( array $company_ids ): array {
        $rows = [
            [
                'company_id'    => $company_ids['Acme Corp'],
                'name'          => 'HQ - San Francisco',
                'branch_code'   => 'ACME-HQ',
                'city'          => 'San Francisco',
                'country'       => 'US',
                'is_head_office'=> 1,
                'status'        => 'active',
            ],
            [
                'company_id'    => $company_ids['Acme Corp'],
                'name'          => 'Austin Office',
                'branch_code'   => 'ACME-AUS',
                'city'          => 'Austin',
                'country'       => 'US',
                'is_head_office'=> 0,
                'status'        => 'active',
            ],
            [
                'company_id'    => $company_ids['Beta Solutions'],
                'name'          => 'HQ - New York',
                'branch_code'   => 'BETA-NYC',
                'city'          => 'New York',
                'country'       => 'US',
                'is_head_office'=> 1,
                'status'        => 'active',
            ],
        ];

        $ids = [];
        foreach ( $rows as $row ) {
            $this->db->insert( $this->db->prefix . 'hr_branches', $row );
            $ids[ $row['branch_code'] ] = $this->db->insert_id;
        }
        return $ids;
    }

    // ── Employment types ───────────────────────────────────────────────────

    private function seed_employment_types(): array {
        $rows = [
            [ 'name' => 'Full Time',  'slug' => 'full_time',  'description' => 'Standard full-time employment' ],
            [ 'name' => 'Part Time',  'slug' => 'part_time',  'description' => 'Part-time hours' ],
            [ 'name' => 'Contract',   'slug' => 'contract',   'description' => 'Fixed-term contract' ],
            [ 'name' => 'Internship', 'slug' => 'intern',     'description' => 'Internship programme' ],
            [ 'name' => 'Probation',  'slug' => 'probation',  'description' => 'On probation period' ],
        ];

        $ids = [];
        foreach ( $rows as $row ) {
            $this->db->insert( $this->db->prefix . 'hr_employment_types', $row );
            $ids[ $row['slug'] ] = $this->db->insert_id;
        }
        return $ids;
    }

    // ── Employee education ─────────────────────────────────────────────────

    private function seed_employee_education( array $employee_ids ): void {
        $rows = [
            [
                'employee_id'   => $employee_ids['David Chen'],
                'institution'   => 'MIT',
                'degree'        => 'Bachelor of Science',
                'field_of_study'=> 'Computer Science',
                'start_date'    => '2011-09-01',
                'end_date'      => '2015-06-01',
                'grade_or_gpa'  => '3.9',
            ],
            [
                'employee_id'   => $employee_ids['Alice Johnson'],
                'institution'   => 'Stanford University',
                'degree'        => 'Master of Science',
                'field_of_study'=> 'Software Engineering',
                'start_date'    => '2016-09-01',
                'end_date'      => '2018-06-01',
                'grade_or_gpa'  => '3.8',
            ],
            [
                'employee_id'   => $employee_ids['Emma Davis'],
                'institution'   => 'Cornell University',
                'degree'        => 'Bachelor of Arts',
                'field_of_study'=> 'Human Resources Management',
                'start_date'    => '2012-09-01',
                'end_date'      => '2016-05-01',
                'grade_or_gpa'  => '3.7',
            ],
            [
                'employee_id'   => $employee_ids['James Wong'],
                'institution'   => 'Wharton School',
                'degree'        => 'Master of Business Administration',
                'field_of_study'=> 'Finance',
                'start_date'    => '2010-09-01',
                'end_date'      => '2012-05-01',
                'grade_or_gpa'  => '3.85',
            ],
        ];

        foreach ( $rows as $row ) {
            $this->db->insert( $this->db->prefix . 'hr_employee_education', $row );
        }
    }

    // ── Employee work history ──────────────────────────────────────────────

    private function seed_employee_work_history( array $employee_ids ): void {
        $rows = [
            [
                'employee_id' => $employee_ids['David Chen'],
                'company_name'=> 'Google LLC',
                'job_title'   => 'Software Engineer',
                'start_date'  => '2015-08-01',
                'end_date'    => '2019-02-28',
                'description' => 'Worked on search ranking infrastructure.',
            ],
            [
                'employee_id' => $employee_ids['Alice Johnson'],
                'company_name'=> 'Amazon Web Services',
                'job_title'   => 'Cloud Engineer',
                'start_date'  => '2018-07-01',
                'end_date'    => '2020-05-31',
                'description' => 'Developed internal cloud tooling.',
            ],
            [
                'employee_id' => $employee_ids['Grace Kim'],
                'company_name'=> 'Salesforce',
                'job_title'   => 'Account Executive',
                'start_date'  => '2015-03-01',
                'end_date'    => '2019-06-30',
                'description' => 'Enterprise account management for APAC region.',
            ],
        ];

        foreach ( $rows as $row ) {
            $this->db->insert( $this->db->prefix . 'hr_employee_work_history', $row );
        }
    }

    // ── Employee bank accounts ─────────────────────────────────────────────

    private function seed_employee_bank( array $employee_ids ): void {
        foreach ( $employee_ids as $name => $emp_id ) {
            $this->db->insert( $this->db->prefix . 'hr_employee_bank', [
                'employee_id'   => $emp_id,
                'bank_name'     => 'Chase Bank',
                'account_name'  => $name,
                'account_number'=> sprintf( '%010d', $emp_id * 1000 + 100 ),
                'routing_number'=> '021000021',
                'is_primary'    => 1,
            ] );
        }
    }

    // ── Employee movement ──────────────────────────────────────────────────

    private function seed_employee_movement( array $employee_ids, array $dept_ids, array $position_ids, array $branch_ids ): void {
        $rows = [
            // Alice Johnson promoted to Senior SE
            [
                'employee_id'       => $employee_ids['Alice Johnson'],
                'movement_type'     => 'promotion',
                'effective_date'    => '2022-01-01',
                'from_position_id'  => $position_ids['Software Engineer'],
                'to_position_id'    => $position_ids['Senior Software Engineer'],
                'reason'            => 'Outstanding performance review',
                'approved_by'       => $employee_ids['David Chen'],
                'status'            => 'approved',
            ],
            // Henry Park transferred to Austin branch
            [
                'employee_id'       => $employee_ids['Henry Park'],
                'movement_type'     => 'transfer',
                'effective_date'    => '2023-06-01',
                'from_branch_id'    => $branch_ids['ACME-HQ'],
                'to_branch_id'      => $branch_ids['ACME-AUS'],
                'reason'            => 'Business expansion requirement',
                'approved_by'       => $employee_ids['Emma Davis'],
                'status'            => 'approved',
            ],
        ];

        foreach ( $rows as $row ) {
            $this->db->insert( $this->db->prefix . 'hr_employee_movement', $row );
        }
    }

    // ── Employee documents ─────────────────────────────────────────────────

    private function seed_employee_documents( array $employee_ids ): void {
        foreach ( [ 'David Chen', 'Alice Johnson', 'Emma Davis' ] as $name ) {
            $this->db->insert( $this->db->prefix . 'hr_employee_documents', [
                'employee_id'   => $employee_ids[ $name ],
                'document_type' => 'passport',
                'title'         => $name . ' — Passport',
                'attachment_id' => 0,
                'is_verified'   => 1,
                'uploaded_by'   => 1,
            ] );
        }
    }

    // ── Notifications ──────────────────────────────────────────────────────

    private function seed_notifications( array $employee_ids ): void {
        $admin_id = (int) $this->db->get_var(
            "SELECT ID FROM {$this->db->users} WHERE user_login = 'admin' LIMIT 1"
        ) ?: 1;

        $rows = [
            [
                'user_id' => $admin_id,
                'type'    => 'leave_request',
                'title'   => 'New leave request',
                'message' => 'Carol White submitted a leave request for approval.',
                'is_read' => 0,
            ],
            [
                'user_id' => $admin_id,
                'type'    => 'document_expiry',
                'title'   => 'Document expiring soon',
                'message' => 'Alice Johnson\'s passport expires within 90 days.',
                'is_read' => 0,
            ],
            [
                'user_id' => $admin_id,
                'type'    => 'attendance',
                'title'   => 'Attendance correction request',
                'message' => 'Bob Smith requested an attendance correction.',
                'is_read' => 1,
            ],
        ];

        foreach ( $rows as $row ) {
            $this->db->insert( $this->db->prefix . 'hr_notifications', $row );
        }
    }

    // ── Holiday lists ──────────────────────────────────────────────────────

    private function seed_holiday_lists( array $company_ids ): array {
        $rows = [
            [ 'company_id' => $company_ids['Acme Corp'],      'name' => 'Acme Corp 2025',      'year' => 2025, 'status' => 'active' ],
            [ 'company_id' => $company_ids['Beta Solutions'],  'name' => 'Beta Solutions 2025', 'year' => 2025, 'status' => 'active' ],
        ];

        $ids = [];
        foreach ( $rows as $row ) {
            $this->db->insert( $this->db->prefix . 'hr_holiday_lists', $row );
            $ids[ $row['name'] ] = $this->db->insert_id;
        }
        return $ids;
    }

    // ── Holidays ───────────────────────────────────────────────────────────

    private function seed_holidays( array $holiday_list_ids ): void {
        $us_holidays = [
            [ 'name' => "New Year's Day",         'date' => '2025-01-01', 'is_restricted' => 0 ],
            [ 'name' => 'Martin Luther King Jr.', 'date' => '2025-01-20', 'is_restricted' => 0 ],
            [ 'name' => "Presidents' Day",        'date' => '2025-02-17', 'is_restricted' => 0 ],
            [ 'name' => 'Memorial Day',            'date' => '2025-05-26', 'is_restricted' => 0 ],
            [ 'name' => 'Independence Day',        'date' => '2025-07-04', 'is_restricted' => 0 ],
            [ 'name' => 'Labor Day',               'date' => '2025-09-01', 'is_restricted' => 0 ],
            [ 'name' => 'Thanksgiving Day',        'date' => '2025-11-27', 'is_restricted' => 0 ],
            [ 'name' => 'Christmas Day',           'date' => '2025-12-25', 'is_restricted' => 0 ],
        ];

        foreach ( $holiday_list_ids as $list_name => $list_id ) {
            foreach ( $us_holidays as $holiday ) {
                $this->db->insert( $this->db->prefix . 'hr_holidays', array_merge(
                    [ 'holiday_list_id' => $list_id ],
                    $holiday
                ) );
            }
        }
    }

    // ── Leave types ────────────────────────────────────────────────────────

    private function seed_leave_types( array $company_ids ): array {
        $company_id = $company_ids['Acme Corp'];

        $rows = [
            [
                'company_id'       => $company_id,
                'name'             => 'Annual Leave',
                'code'             => 'AL',
                'max_days_per_year'=> 20.0,
                'is_paid'          => 1,
                'is_carry_forward' => 1,
                'max_carry_forward_days' => 5.0,
                'allow_half_day'   => 1,
                'category'         => 'general',
                'color'            => '#4CAF50',
                'sort_order'       => 1,
                'status'           => 'active',
            ],
            [
                'company_id'       => $company_id,
                'name'             => 'Sick Leave',
                'code'             => 'SL',
                'max_days_per_year'=> 10.0,
                'is_paid'          => 1,
                'is_carry_forward' => 0,
                'requires_attachment' => 1,
                'requires_attachment_after_days' => 3,
                'allow_half_day'   => 1,
                'category'         => 'sick',
                'color'            => '#F44336',
                'sort_order'       => 2,
                'status'           => 'active',
            ],
            [
                'company_id'       => $company_id,
                'name'             => 'Maternity Leave',
                'code'             => 'ML',
                'max_days_per_year'=> 90.0,
                'is_paid'          => 1,
                'is_carry_forward' => 0,
                'applicable_gender'=> 'female',
                'category'         => 'parental',
                'color'            => '#E91E63',
                'sort_order'       => 3,
                'status'           => 'active',
            ],
            [
                'company_id'       => $company_id,
                'name'             => 'Paternity Leave',
                'code'             => 'PL',
                'max_days_per_year'=> 5.0,
                'is_paid'          => 1,
                'is_carry_forward' => 0,
                'applicable_gender'=> 'male',
                'category'         => 'parental',
                'color'            => '#2196F3',
                'sort_order'       => 4,
                'status'           => 'active',
            ],
            [
                'company_id'       => $company_id,
                'name'             => 'Compensatory Leave',
                'code'             => 'CL',
                'max_days_per_year'=> 5.0,
                'is_paid'          => 1,
                'is_carry_forward' => 0,
                'category'         => 'compensatory',
                'color'            => '#FF9800',
                'sort_order'       => 5,
                'status'           => 'active',
            ],
            [
                'company_id'       => $company_id,
                'name'             => 'Unpaid Leave',
                'code'             => 'UL',
                'max_days_per_year'=> 30.0,
                'is_paid'          => 0,
                'is_carry_forward' => 0,
                'allow_negative_balance' => 1,
                'category'         => 'unpaid',
                'color'            => '#9E9E9E',
                'sort_order'       => 6,
                'status'           => 'active',
            ],
        ];

        $ids = [];
        foreach ( $rows as $row ) {
            $this->db->insert( $this->db->prefix . 'hr_leave_types', $row );
            $ids[ $row['code'] ] = $this->db->insert_id;
        }
        return $ids;
    }

    // ── Leave policies ─────────────────────────────────────────────────────

    private function seed_leave_policies( array $company_ids ): array {
        $rows = [
            [
                'company_id'        => $company_ids['Acme Corp'],
                'name'              => 'Standard Policy 2025',
                'effective_from'    => '2025-01-01',
                'effective_to'      => '2025-12-31',
                'status'            => 'active',
                'conflict_strategy' => 'highest_priority',
            ],
            [
                'company_id'        => $company_ids['Acme Corp'],
                'name'              => 'Engineering Policy 2025',
                'effective_from'    => '2025-01-01',
                'effective_to'      => '2025-12-31',
                'status'            => 'active',
                'conflict_strategy' => 'highest_priority',
            ],
        ];

        $ids = [];
        foreach ( $rows as $row ) {
            $this->db->insert( $this->db->prefix . 'hr_leave_policies', $row );
            $ids[ $row['name'] ] = $this->db->insert_id;
        }
        return $ids;
    }

    // ── Leave policy details (M:N bridge) ─────────────────────────────────

    private function seed_leave_policy_details( array $policy_ids, array $leave_type_ids ): void {
        $standard_allocations = [
            'AL' => 20.0,
            'SL' => 10.0,
            'ML' => 90.0,
            'PL' => 5.0,
            'CL' => 5.0,
            'UL' => 30.0,
        ];

        $engineering_allocations = [
            'AL' => 25.0,  // Engineers get 5 extra days
            'SL' => 10.0,
            'CL' => 10.0,
            'UL' => 30.0,
        ];

        $standard_id    = $policy_ids['Standard Policy 2025'];
        $engineering_id = $policy_ids['Engineering Policy 2025'];

        foreach ( $standard_allocations as $code => $days ) {
            if ( ! isset( $leave_type_ids[ $code ] ) ) {
                continue;
            }
            $this->db->insert( $this->db->prefix . 'hr_leave_policy_details', [
                'leave_policy_id'  => $standard_id,
                'leave_type_id'    => $leave_type_ids[ $code ],
                'annual_allocation'=> $days,
            ] );
        }

        foreach ( $engineering_allocations as $code => $days ) {
            if ( ! isset( $leave_type_ids[ $code ] ) ) {
                continue;
            }
            $this->db->insert( $this->db->prefix . 'hr_leave_policy_details', [
                'leave_policy_id'  => $engineering_id,
                'leave_type_id'    => $leave_type_ids[ $code ],
                'annual_allocation'=> $days,
            ] );
        }
    }

    // ── Leave policy assignments ───────────────────────────────────────────

    private function seed_leave_policy_assignments( array $policy_ids, array $dept_ids ): void {
        // Standard policy applies to everyone.
        $this->db->insert( $this->db->prefix . 'hr_leave_policy_assignments', [
            'leave_policy_id' => $policy_ids['Standard Policy 2025'],
            'assignment_type' => 'department',
            'assignment_id'   => (string) $dept_ids['Human Resources'],
            'effective_from'  => '2025-01-01',
            'priority'        => 0,
        ] );

        $this->db->insert( $this->db->prefix . 'hr_leave_policy_assignments', [
            'leave_policy_id' => $policy_ids['Standard Policy 2025'],
            'assignment_type' => 'department',
            'assignment_id'   => (string) $dept_ids['Sales'],
            'effective_from'  => '2025-01-01',
            'priority'        => 0,
        ] );

        // Engineering overrides with their own policy.
        $this->db->insert( $this->db->prefix . 'hr_leave_policy_assignments', [
            'leave_policy_id' => $policy_ids['Engineering Policy 2025'],
            'assignment_type' => 'department',
            'assignment_id'   => (string) $dept_ids['Engineering'],
            'effective_from'  => '2025-01-01',
            'priority'        => 10,
        ] );
    }

    // ── Leave balances ─────────────────────────────────────────────────────

    private function seed_leave_balances( array $employee_ids, array $leave_type_ids ): void {
        $year = 2025;

        // Give each active employee a balance for Annual Leave and Sick Leave.
        $active_employees = array_filter(
            $employee_ids,
            fn( $name ) => $name !== 'Leo Cruz',
            ARRAY_FILTER_USE_KEY
        );

        foreach ( $active_employees as $name => $emp_id ) {
            // Annual Leave balance.
            if ( isset( $leave_type_ids['AL'] ) ) {
                $taken   = match ( $name ) {
                    'Alice Johnson' => 5.0,
                    'Bob Smith'     => 5.0,
                    'Grace Kim'     => 10.0,
                    'Karen Ng'      => 5.0,
                    default         => 0.0,
                };
                $pending = match ( $name ) {
                    'Carol White'   => 5.0,
                    'Iris Tan'      => 5.0,
                    'David Chen'    => 5.0,
                    default         => 0.0,
                };

                $this->db->insert( $this->db->prefix . 'hr_leave_balances', [
                    'employee_id'     => $emp_id,
                    'leave_type_id'   => $leave_type_ids['AL'],
                    'year'            => $year,
                    'total_allocated' => 20.0,
                    'total_taken'     => $taken,
                    'total_pending'   => $pending,
                    'carry_forwarded' => 0.0,
                    'manual_adjustment' => 0.0,
                ] );
            }

            // Sick Leave balance.
            if ( isset( $leave_type_ids['SL'] ) ) {
                $taken = match ( $name ) {
                    'Frank Lee' => 2.0,
                    'Bob Smith' => 1.0,
                    default     => 0.0,
                };

                $this->db->insert( $this->db->prefix . 'hr_leave_balances', [
                    'employee_id'     => $emp_id,
                    'leave_type_id'   => $leave_type_ids['SL'],
                    'year'            => $year,
                    'total_allocated' => 10.0,
                    'total_taken'     => $taken,
                    'total_pending'   => 0.0,
                    'carry_forwarded' => 0.0,
                    'manual_adjustment' => 0.0,
                ] );
            }
        }
    }

    // ── Shifts ─────────────────────────────────────────────────────────────

    private function seed_shifts( array $company_ids ): array {
        $company_id = $company_ids['Acme Corp'];

        $rows = [
            [
                'company_id'                   => $company_id,
                'name'                         => 'Morning Shift',
                'start_time'                   => '09:00:00',
                'end_time'                     => '17:00:00',
                'grace_period_minutes'         => 15,
                'early_exit_threshold_minutes' => 15,
                'working_hours'                => 8.00,
                'is_overnight'                 => 0,
                'status'                       => 'active',
            ],
            [
                'company_id'                   => $company_id,
                'name'                         => 'Afternoon Shift',
                'start_time'                   => '13:00:00',
                'end_time'                     => '21:00:00',
                'grace_period_minutes'         => 15,
                'early_exit_threshold_minutes' => 15,
                'working_hours'                => 8.00,
                'is_overnight'                 => 0,
                'status'                       => 'active',
            ],
            [
                'company_id'                   => $company_id,
                'name'                         => 'Flex Shift',
                'start_time'                   => '08:00:00',
                'end_time'                     => '18:00:00',
                'grace_period_minutes'         => 30,
                'early_exit_threshold_minutes' => 30,
                'working_hours'                => 8.00,
                'is_overnight'                 => 0,
                'status'                       => 'active',
            ],
        ];

        $ids = [];
        foreach ( $rows as $row ) {
            $this->db->insert( $this->db->prefix . 'hr_shifts', $row );
            $ids[ $row['name'] ] = $this->db->insert_id;
        }
        return $ids;
    }

    // ── Shift assignments ──────────────────────────────────────────────────

    private function seed_shift_assignments( array $employee_ids, array $shift_ids ): void {
        // Engineering gets Flex Shift; others get Morning.
        $engineering_employees = [ 'David Chen', 'Alice Johnson', 'Bob Smith', 'Carol White', 'Leo Cruz' ];

        foreach ( $employee_ids as $name => $emp_id ) {
            $shift = in_array( $name, $engineering_employees, true )
                ? $shift_ids['Flex Shift']
                : $shift_ids['Morning Shift'];

            $this->db->insert( $this->db->prefix . 'hr_shift_assignments', [
                'employee_id'    => $emp_id,
                'shift_id'       => $shift,
                'effective_from' => '2025-01-01',
                'effective_to'   => null,
            ] );
        }
    }

    // ── Attendance ─────────────────────────────────────────────────────────

    private function seed_attendance( array $employee_ids ): void {
        $active_employees = array_filter(
            $employee_ids,
            fn( $name ) => $name !== 'Leo Cruz',
            ARRAY_FILTER_USE_KEY
        );

        $work_dates = [ '2025-06-16', '2025-06-17', '2025-06-18', '2025-06-19', '2025-06-20' ];

        $hours_map = [
            '2025-06-16' => [ 'in' => '09:02:00', 'out' => '17:10:00', 'hours' => 8.13, 'late' => 0, 'early' => 0 ],
            '2025-06-17' => [ 'in' => '08:55:00', 'out' => '17:30:00', 'hours' => 8.58, 'late' => 0, 'early' => 0 ],
            '2025-06-18' => [ 'in' => '09:20:00', 'out' => '17:00:00', 'hours' => 7.67, 'late' => 1, 'early' => 0 ],
            '2025-06-19' => [ 'in' => '09:00:00', 'out' => '17:00:00', 'hours' => 8.00, 'late' => 0, 'early' => 0 ],
            '2025-06-20' => [ 'in' => '09:05:00', 'out' => '15:00:00', 'hours' => 5.92, 'late' => 0, 'early' => 1 ],
        ];

        foreach ( $active_employees as $name => $emp_id ) {
            foreach ( $work_dates as $date ) {
                $h = $hours_map[ $date ];
                $this->db->insert( $this->db->prefix . 'hr_attendance', [
                    'employee_id'         => $emp_id,
                    'date'                => $date,
                    'status'              => 'present',
                    'check_in'            => $date . ' ' . $h['in'],
                    'check_out'           => $date . ' ' . $h['out'],
                    'total_working_hours' => $h['hours'],
                    'late_entry'          => $h['late'],
                    'early_exit'          => $h['early'],
                    'overtime_hours'      => max( 0, $h['hours'] - 8 ),
                    'source'              => 'web_checkin',
                ] );
            }
        }
    }

    // ── Compensatory requests ──────────────────────────────────────────────

    private function seed_compensatory_requests( array $employee_ids, array $leave_type_ids ): void {
        if ( ! isset( $leave_type_ids['CL'] ) ) {
            return;
        }

        $rows = [
            [
                'employee_id'  => $employee_ids['David Chen'],
                'leave_type_id'=> $leave_type_ids['CL'],
                'work_date'    => '2025-01-04',  // worked on weekend
                'days'         => 1.0,
                'reason'       => 'Emergency production fix over the weekend',
                'status'       => 'approved',
                'approved_by'  => $employee_ids['Emma Davis'],
                'approved_at'  => '2025-01-06 10:00:00',
                'expires_at'   => '2025-07-04',
                'posted_by'    => $employee_ids['David Chen'],
            ],
            [
                'employee_id'  => $employee_ids['Alice Johnson'],
                'leave_type_id'=> $leave_type_ids['CL'],
                'work_date'    => '2025-03-01',
                'days'         => 0.5,
                'reason'       => 'Worked Saturday for product launch',
                'status'       => 'pending',
                'posted_by'    => $employee_ids['Alice Johnson'],
            ],
            [
                'employee_id'  => $employee_ids['Bob Smith'],
                'leave_type_id'=> $leave_type_ids['CL'],
                'work_date'    => '2025-04-19',
                'days'         => 1.0,
                'reason'       => 'On-call support during public holiday',
                'status'       => 'approved',
                'approved_by'  => $employee_ids['David Chen'],
                'approved_at'  => '2025-04-21 09:00:00',
                'expires_at'   => '2025-10-19',
                'posted_by'    => $employee_ids['Bob Smith'],
            ],
        ];

        foreach ( $rows as $row ) {
            $this->db->insert( $this->db->prefix . 'hr_compensatory_requests', $row );
        }
    }
}

// ── Admin page registration ───────────────────────────────────────────────────

add_action( 'admin_menu', function (): void {
    add_management_page(
        'ODAD HRMS Seeder',
        'ODAD HRMS Seeder',
        'manage_options',
        'odad-hrms-seeder',
        'odad_hrms_seeder_page',
    );
} );

function odad_hrms_seeder_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $result  = null;
    $error   = null;
    $nonce   = 'odad_hrms_seed';

    if (
        isset( $_POST['odad_seed'] ) &&
        check_admin_referer( $nonce )
    ) {
        try {
            $seeder = new ODAD_HRMS_Seeder();
            $result = $seeder->run();
        } catch ( Throwable $e ) {
            $error = $e->getMessage();
        }
    }

    ?>
    <div class="wrap">
        <h1>ODAD HRMS — Test Data Seeder</h1>
        <p>Truncates all HR tables and inserts fresh sample data for relationship testing. <strong>Do not run on production.</strong></p>

        <?php if ( $result !== null ) : ?>
            <div class="notice notice-success">
                <p><strong>Seeded successfully:</strong>
                    <?php echo esc_html( implode( ', ', array_map(
                        fn( $k, $v ) => "{$v} {$k}",
                        array_keys( $result ),
                        $result
                    ) ) ); ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if ( $error !== null ) : ?>
            <div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field( $nonce ); ?>
            <input type="hidden" name="odad_seed" value="1">
            <?php submit_button( 'Run Seeder', 'primary large' ); ?>
        </form>

        <hr>
        <h2>Sample OData queries to verify relationships</h2>
        <table class="widefat striped" style="max-width:900px">
            <thead><tr><th>Query</th><th>Tests</th></tr></thead>
            <tbody>
            <?php
            $base = home_url( '/wp-json/odata/v4' );
            $queries = [
                // Core relationships
                [ "$base/Employees?\$expand=Department,Position,Manager",    'Employee → Dept, Position, Manager (many-to-one)' ],
                [ "$base/Departments?\$expand=Employees,Positions,Manager",  'Dept → Employees, Positions (one-to-many) + Manager' ],
                [ "$base/Employees?\$expand=LeaveRequests,Timesheets",       'Employee → Leaves & Timesheets (one-to-many)' ],
                [ "$base/LeaveRequests?\$expand=Employee,Approver",          'Leave → Employee + Approver (both FK to Employees)' ],
                [ "$base/Timesheets?\$expand=Employee",                      'Timesheet → Employee (many-to-one)' ],
                [ "$base/Employees?\$filter=IsActive eq true&\$expand=Department", 'Active employees only + dept' ],
                // Many-to-many Skills pivot
                [ "$base/Employees?\$expand=EmployeeSkills(\$expand=Skill)", 'M:M — Employees with their Skills (via pivot)' ],
                [ "$base/Skills?\$expand=EmployeeSkills(\$expand=Employee)", 'M:M — Skills with who has them (via pivot)' ],
                // Organisation structure
                [ "$base/Companies?\$expand=Branches",                       'Company → Branches (one-to-many)' ],
                [ "$base/Branches?\$expand=Company",                         'Branch → Company (many-to-one)' ],
                // Employee details
                [ "$base/Employees?\$expand=EmployeeEducation",              'Employee → Education history' ],
                [ "$base/Employees?\$expand=EmployeeWorkHistory",            'Employee → Work history' ],
                [ "$base/Employees?\$expand=EmployeeBankAccounts",           'Employee → Bank accounts' ],
                [ "$base/EmployeeMovements?\$expand=Employee,FromDepartment,ToDepartment,FromPosition,ToPosition", 'Movements with full context' ],
                [ "$base/EmployeeMovements?\$filter=Status eq 'approved'&\$expand=Employee,Approver", 'Approved movements + who approved' ],
                // Holiday management
                [ "$base/HolidayLists?\$expand=Company,Holidays",           'Holiday list → Company + holidays (nested)' ],
                [ "$base/Holidays?\$filter=IsRestricted eq false",           'Non-restricted holidays' ],
                // Leave management (M:N via policy details)
                [ "$base/LeavePolicies?\$expand=Details(\$expand=LeaveType)", 'M:M — Policy → Leave types (via pivot)' ],
                [ "$base/LeaveTypes?\$expand=LeavePolicyDetails(\$expand=LeavePolicy)", 'M:M — Leave type → Policies (via pivot)' ],
                [ "$base/LeavePolicies?\$expand=Assignments",                'Policy assignments' ],
                [ "$base/LeaveBalances?\$expand=Employee,LeaveType",         'Balances → Employee + type' ],
                [ "$base/LeaveBalances?\$filter=Year eq 2025&\$expand=Employee(\$select=FullName)", '2025 balances with employee name' ],
                // Attendance
                [ "$base/Shifts?\$expand=Company,Assignments(\$expand=Employee)", 'Shift → Company + assigned employees' ],
                [ "$base/Attendance?\$filter=LateEntry eq true&\$expand=Employee", 'All late arrivals' ],
                [ "$base/AttendanceRequests?\$filter=Status eq 'pending'&\$expand=Employee", 'Pending attendance corrections' ],
                [ "$base/CompensatoryRequests?\$filter=Status eq 'approved'&\$expand=Employee,LeaveType", 'Approved comp requests' ],
            ];
            foreach ( $queries as [ $url, $label ] ) :
            ?>
                <tr>
                    <td><a href="<?php echo esc_url( $url ); ?>" target="_blank" style="font-family:monospace;font-size:12px;word-break:break-all"><?php echo esc_html( $url ); ?></a></td>
                    <td><?php echo esc_html( $label ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
