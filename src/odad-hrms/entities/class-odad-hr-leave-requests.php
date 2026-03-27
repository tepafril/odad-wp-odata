<?php
defined( 'ABSPATH' ) || exit;

class ODAD_HR_Leave_Requests extends ODAD_Adapter_Custom_Table {

    public function __construct() {
        parent::__construct(
            table_name:      'hr_leave_requests',
            entity_set_name: 'LeaveRequests',
            key_column:      'id',
            schema: [
                'key'        => 'ID',
                'properties' => [
                    'ID'         => [ 'column' => 'id',          'type' => 'Edm.Int64',          'read_only' => true ],
                    'EmployeeID' => [ 'column' => 'employee_id', 'type' => 'Edm.Int64' ],
                    'Type'       => [ 'column' => 'type',        'type' => 'Edm.String' ],
                    'StartDate'  => [ 'column' => 'start_date',  'type' => 'Edm.Date' ],
                    'EndDate'    => [ 'column' => 'end_date',    'type' => 'Edm.Date' ],
                    'Days'       => [ 'column' => 'days',        'type' => 'Edm.Decimal' ],
                    'Reason'     => [ 'column' => 'reason',      'type' => 'Edm.String' ],
                    'Status'     => [ 'column' => 'status',      'type' => 'Edm.String' ],
                    'ApprovedBy' => [ 'column' => 'approved_by', 'type' => 'Edm.Int64',          'read_only' => true ],
                    'CreatedAt'  => [ 'column' => 'created_at',  'type' => 'Edm.DateTimeOffset', 'read_only' => true ],
                ],
            ],
            nav_properties: [
                'Employee' => [ 'type' => 'Employees', 'collection' => false, 'fk' => 'EmployeeID' ],
                'Approver' => [ 'type' => 'Employees', 'collection' => false, 'fk' => 'ApprovedBy' ],
            ],
        );
    }
}
