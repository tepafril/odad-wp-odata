<?php
defined( 'ABSPATH' ) || exit;

class ODAD_HR_Act_Approve_Leave {

    public function __invoke( array $params, ?WP_User $user ): bool {
        global $wpdb;

        $leave_id = (int) $params['LeaveRequestID'];

        $leave = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hr_leave_requests WHERE id = %d",
            $leave_id
        ) );

        if ( ! $leave ) {
            throw new RuntimeException( 'Leave request not found.' );
        }

        if ( $leave->status !== 'pending' ) {
            throw new RuntimeException( 'Only pending requests can be approved.' );
        }

        // Only the employee's direct manager may approve.
        $employee = $wpdb->get_row( $wpdb->prepare(
            "SELECT manager_id FROM {$wpdb->prefix}hr_employees WHERE id = %d",
            $leave->employee_id
        ) );

        if ( ! $employee || (int) $employee->manager_id !== $user->ID ) {
            throw new RuntimeException( 'Only the direct manager can approve this request.' );
        }

        $wpdb->update(
            "{$wpdb->prefix}hr_leave_requests",
            [ 'status' => 'approved', 'approved_by' => $user->ID ],
            [ 'id'     => $leave_id ],
        );

        // Notify the employee.
        $emp_user = $wpdb->get_var( $wpdb->prepare(
            "SELECT wp_user_id FROM {$wpdb->prefix}hr_employees WHERE id = %d",
            $leave->employee_id
        ) );
        if ( $emp_user ) {
            $wp_user = get_user_by( 'id', (int) $emp_user );
            if ( $wp_user ) {
                wp_mail(
                    $wp_user->user_email,
                    'Your leave request has been approved',
                    "Your leave from {$leave->start_date} to {$leave->end_date} has been approved."
                );
            }
        }

        return true;
    }
}
