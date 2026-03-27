<?php
defined( 'ABSPATH' ) || exit;

class ODAD_HR_Employee_Movement extends ODAD_Adapter_Custom_Table {

    public function __construct() {
        parent::__construct(
            table_name:      'hr_employee_movement',
            entity_set_name: 'EmployeeMovements',
            key_column:      'id',
            schema: [
                'key'        => 'ID',
                'properties' => [
                    'ID'               => [ 'column' => 'id',                 'type' => 'Edm.Int64',          'read_only' => true ],
                    'EmployeeID'       => [ 'column' => 'employee_id',        'type' => 'Edm.Int64' ],
                    'MovementType'     => [ 'column' => 'movement_type',      'type' => 'Edm.String' ],
                    'EffectiveDate'    => [ 'column' => 'effective_date',     'type' => 'Edm.Date' ],
                    'FromDepartmentID' => [ 'column' => 'from_department_id', 'type' => 'Edm.Int64' ],
                    'ToDepartmentID'   => [ 'column' => 'to_department_id',   'type' => 'Edm.Int64' ],
                    'FromPositionID'   => [ 'column' => 'from_position_id',   'type' => 'Edm.Int64' ],
                    'ToPositionID'     => [ 'column' => 'to_position_id',     'type' => 'Edm.Int64' ],
                    'FromBranchID'     => [ 'column' => 'from_branch_id',     'type' => 'Edm.Int64' ],
                    'ToBranchID'       => [ 'column' => 'to_branch_id',       'type' => 'Edm.Int64' ],
                    'Reason'           => [ 'column' => 'reason',             'type' => 'Edm.String' ],
                    'ApprovedBy'       => [ 'column' => 'approved_by',        'type' => 'Edm.Int64' ],
                    'Status'           => [ 'column' => 'status',             'type' => 'Edm.String' ],
                    'CreatedAt'        => [ 'column' => 'created_at',         'type' => 'Edm.DateTimeOffset', 'read_only' => true ],
                ],
            ],
            nav_properties: [
                'Employee'       => [ 'type' => 'Employees',   'collection' => false, 'fk' => 'EmployeeID' ],
                'FromDepartment' => [ 'type' => 'Departments', 'collection' => false, 'fk' => 'FromDepartmentID' ],
                'ToDepartment'   => [ 'type' => 'Departments', 'collection' => false, 'fk' => 'ToDepartmentID' ],
                'FromPosition'   => [ 'type' => 'Positions',   'collection' => false, 'fk' => 'FromPositionID' ],
                'ToPosition'     => [ 'type' => 'Positions',   'collection' => false, 'fk' => 'ToPositionID' ],
                'FromBranch'     => [ 'type' => 'Branches',    'collection' => false, 'fk' => 'FromBranchID' ],
                'ToBranch'       => [ 'type' => 'Branches',    'collection' => false, 'fk' => 'ToBranchID' ],
                'Approver'       => [ 'type' => 'Employees',   'collection' => false, 'fk' => 'ApprovedBy' ],
            ],
        );
    }
}
