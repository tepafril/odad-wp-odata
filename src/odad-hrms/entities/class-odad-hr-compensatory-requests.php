<?php
defined( 'ABSPATH' ) || exit;

class ODAD_HR_Compensatory_Requests extends ODAD_Adapter_Custom_Table {

    public function __construct() {
        parent::__construct(
            table_name:      'hr_compensatory_requests',
            entity_set_name: 'CompensatoryRequests',
            key_column:      'id',
            schema: [
                'key'        => 'ID',
                'properties' => [
                    'ID'              => [ 'column' => 'id',               'type' => 'Edm.Int64',          'read_only' => true ],
                    'EmployeeID'      => [ 'column' => 'employee_id',      'type' => 'Edm.Int64' ],
                    'LeaveTypeID'     => [ 'column' => 'leave_type_id',    'type' => 'Edm.Int64' ],
                    'WorkDate'        => [ 'column' => 'work_date',        'type' => 'Edm.Date' ],
                    'Days'            => [ 'column' => 'days',             'type' => 'Edm.Decimal' ],
                    'Reason'          => [ 'column' => 'reason',           'type' => 'Edm.String' ],
                    'Status'          => [ 'column' => 'status',           'type' => 'Edm.String' ],
                    'ApprovedBy'      => [ 'column' => 'approved_by',      'type' => 'Edm.Int64' ],
                    'ApprovalComment' => [ 'column' => 'approval_comment', 'type' => 'Edm.String' ],
                    'ApprovedAt'      => [ 'column' => 'approved_at',      'type' => 'Edm.DateTimeOffset' ],
                    'ExpiresAt'       => [ 'column' => 'expires_at',       'type' => 'Edm.Date' ],
                    'PostedBy'        => [ 'column' => 'posted_by',        'type' => 'Edm.Int64' ],
                    'CreatedAt'       => [ 'column' => 'created_at',       'type' => 'Edm.DateTimeOffset', 'read_only' => true ],
                    'UpdatedAt'       => [ 'column' => 'updated_at',       'type' => 'Edm.DateTimeOffset', 'read_only' => true ],
                ],
            ],
            nav_properties: [
                'Employee'  => [ 'type' => 'Employees',  'collection' => false, 'fk' => 'EmployeeID' ],
                'LeaveType' => [ 'type' => 'LeaveTypes', 'collection' => false, 'fk' => 'LeaveTypeID' ],
                'Approver'  => [ 'type' => 'Employees',  'collection' => false, 'fk' => 'ApprovedBy' ],
                'PostedByEmployee' => [ 'type' => 'Employees', 'collection' => false, 'fk' => 'PostedBy' ],
            ],
        );
    }
}
