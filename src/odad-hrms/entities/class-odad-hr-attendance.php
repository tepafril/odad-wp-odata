<?php
defined( 'ABSPATH' ) || exit;

class ODAD_HR_Attendance extends ODAD_Adapter_Custom_Table {

    public function __construct() {
        parent::__construct(
            table_name:      'hr_attendance',
            entity_set_name: 'Attendance',
            key_column:      'id',
            schema: [
                'key'        => 'ID',
                'properties' => [
                    'ID'                 => [ 'column' => 'id',                   'type' => 'Edm.Int64',          'read_only' => true ],
                    'EmployeeID'         => [ 'column' => 'employee_id',          'type' => 'Edm.Int64' ],
                    'Date'               => [ 'column' => 'date',                 'type' => 'Edm.Date' ],
                    'Status'             => [ 'column' => 'status',               'type' => 'Edm.String' ],
                    'CheckIn'            => [ 'column' => 'check_in',             'type' => 'Edm.DateTimeOffset' ],
                    'CheckOut'           => [ 'column' => 'check_out',            'type' => 'Edm.DateTimeOffset' ],
                    'TotalWorkingHours'  => [ 'column' => 'total_working_hours',  'type' => 'Edm.Decimal' ],
                    'LateEntry'          => [ 'column' => 'late_entry',           'type' => 'Edm.Boolean' ],
                    'EarlyExit'          => [ 'column' => 'early_exit',           'type' => 'Edm.Boolean' ],
                    'OvertimeHours'      => [ 'column' => 'overtime_hours',       'type' => 'Edm.Decimal' ],
                    'LeaveRequestID'     => [ 'column' => 'leave_request_id',     'type' => 'Edm.Int64' ],
                    'Source'             => [ 'column' => 'source',               'type' => 'Edm.String' ],
                    'Remarks'            => [ 'column' => 'remarks',              'type' => 'Edm.String' ],
                    'CreatedAt'          => [ 'column' => 'created_at',           'type' => 'Edm.DateTimeOffset', 'read_only' => true ],
                ],
            ],
            nav_properties: [
                'Employee'     => [ 'type' => 'Employees',     'collection' => false, 'fk' => 'EmployeeID' ],
                'LeaveRequest' => [ 'type' => 'LeaveRequests', 'collection' => false, 'fk' => 'LeaveRequestID' ],
            ],
        );
    }
}
