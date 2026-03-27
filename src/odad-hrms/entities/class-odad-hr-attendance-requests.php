<?php
defined( 'ABSPATH' ) || exit;

class ODAD_HR_Attendance_Requests extends ODAD_Adapter_Custom_Table {

    public function __construct() {
        parent::__construct(
            table_name:      'hr_attendance_requests',
            entity_set_name: 'AttendanceRequests',
            key_column:      'id',
            schema: [
                'key'        => 'ID',
                'properties' => [
                    'ID'                 => [ 'column' => 'id',                   'type' => 'Edm.Int64',          'read_only' => true ],
                    'EmployeeID'         => [ 'column' => 'employee_id',          'type' => 'Edm.Int64' ],
                    'AttendanceID'       => [ 'column' => 'attendance_id',        'type' => 'Edm.Int64' ],
                    'Date'               => [ 'column' => 'date',                 'type' => 'Edm.Date' ],
                    'RequestedCheckIn'   => [ 'column' => 'requested_check_in',   'type' => 'Edm.DateTimeOffset' ],
                    'RequestedCheckOut'  => [ 'column' => 'requested_check_out',  'type' => 'Edm.DateTimeOffset' ],
                    'RequestedStatus'    => [ 'column' => 'requested_status',     'type' => 'Edm.String' ],
                    'Reason'             => [ 'column' => 'reason',               'type' => 'Edm.String' ],
                    'Status'             => [ 'column' => 'status',               'type' => 'Edm.String' ],
                    'ApprovedBy'         => [ 'column' => 'approved_by',          'type' => 'Edm.Int64' ],
                    'CreatedAt'          => [ 'column' => 'created_at',           'type' => 'Edm.DateTimeOffset', 'read_only' => true ],
                ],
            ],
            nav_properties: [
                'Employee'   => [ 'type' => 'Employees',  'collection' => false, 'fk' => 'EmployeeID' ],
                'Attendance' => [ 'type' => 'Attendance', 'collection' => false, 'fk' => 'AttendanceID' ],
                'Approver'   => [ 'type' => 'Employees',  'collection' => false, 'fk' => 'ApprovedBy' ],
            ],
        );
    }
}
