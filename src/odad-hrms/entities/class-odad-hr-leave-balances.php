<?php
defined( 'ABSPATH' ) || exit;

class ODAD_HR_Leave_Balances extends ODAD_Adapter_Custom_Table {

    public function __construct() {
        parent::__construct(
            table_name:      'hr_leave_balances',
            entity_set_name: 'LeaveBalances',
            key_column:      'id',
            schema: [
                'key'        => 'ID',
                'properties' => [
                    'ID'               => [ 'column' => 'id',               'type' => 'Edm.Int64',          'read_only' => true ],
                    'EmployeeID'       => [ 'column' => 'employee_id',      'type' => 'Edm.Int64' ],
                    'LeaveTypeID'      => [ 'column' => 'leave_type_id',    'type' => 'Edm.Int64' ],
                    'Year'             => [ 'column' => 'year',             'type' => 'Edm.Int32' ],
                    'TotalAllocated'   => [ 'column' => 'total_allocated',  'type' => 'Edm.Decimal' ],
                    'TotalTaken'       => [ 'column' => 'total_taken',      'type' => 'Edm.Decimal' ],
                    'TotalPending'     => [ 'column' => 'total_pending',    'type' => 'Edm.Decimal' ],
                    'CarryForwarded'   => [ 'column' => 'carry_forwarded',  'type' => 'Edm.Decimal' ],
                    'ManualAdjustment' => [ 'column' => 'manual_adjustment','type' => 'Edm.Decimal' ],
                    'LastAccrualDate'  => [ 'column' => 'last_accrual_date','type' => 'Edm.Date' ],
                    'UpdatedAt'        => [ 'column' => 'updated_at',       'type' => 'Edm.DateTimeOffset', 'read_only' => true ],
                ],
            ],
            nav_properties: [
                'Employee'  => [ 'type' => 'Employees',  'collection' => false, 'fk' => 'EmployeeID' ],
                'LeaveType' => [ 'type' => 'LeaveTypes', 'collection' => false, 'fk' => 'LeaveTypeID' ],
            ],
        );
    }
}
