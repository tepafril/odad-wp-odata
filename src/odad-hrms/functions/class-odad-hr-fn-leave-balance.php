<?php
defined( 'ABSPATH' ) || exit;

class ODAD_HR_Fn_Leave_Balance {

    /** Annual leave allowance in days. Could later be fetched from a policy table. */
    private const ANNUAL_ALLOWANCE = 20;

    public function __invoke( array $params, ?WP_User $user ): int {
        global $wpdb;

        $employee_id = (int) $params['EmployeeID'];

        $used = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(days), 0)
               FROM {$wpdb->prefix}hr_leave_requests
              WHERE employee_id = %d
                AND status      = 'approved'
                AND YEAR(start_date) = YEAR(NOW())",
            $employee_id
        ) );

        return (int) max( 0, self::ANNUAL_ALLOWANCE - $used );
    }
}
