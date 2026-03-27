<?php
defined( 'ABSPATH' ) || exit;

class ODAD_HR_Act_Submit_Timesheet {

    public function __invoke( array $params, ?WP_User $user ): bool {
        global $wpdb;

        $timesheet_id = (int) $params['TimesheetID'];

        $timesheet = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hr_timesheets WHERE id = %d",
            $timesheet_id
        ) );

        if ( ! $timesheet ) {
            throw new RuntimeException( 'Timesheet not found.' );
        }

        if ( $timesheet->status !== 'draft' ) {
            throw new RuntimeException( 'Only draft timesheets can be submitted.' );
        }

        // Only the owning employee can submit their own timesheet.
        $owner = $wpdb->get_var( $wpdb->prepare(
            "SELECT wp_user_id FROM {$wpdb->prefix}hr_employees WHERE id = %d",
            $timesheet->employee_id
        ) );

        if ( (int) $owner !== $user->ID && ! user_can( $user, 'manage_options' ) ) {
            throw new RuntimeException( 'You can only submit your own timesheet.' );
        }

        $wpdb->update(
            "{$wpdb->prefix}hr_timesheets",
            [ 'status' => 'submitted', 'submitted_at' => current_time( 'mysql' ) ],
            [ 'id'     => $timesheet_id ],
        );

        return true;
    }
}
