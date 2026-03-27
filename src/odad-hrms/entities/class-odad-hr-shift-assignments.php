<?php
defined( 'ABSPATH' ) || exit;

class ODAD_HR_Shift_Assignments extends ODAD_Adapter_Custom_Table {

    public function __construct() {
        parent::__construct(
            table_name:      'hr_shift_assignments',
            entity_set_name: 'ShiftAssignments',
            key_column:      'id',
            schema: [
                'key'        => 'ID',
                'properties' => [
                    'ID'            => [ 'column' => 'id',             'type' => 'Edm.Int64',          'read_only' => true ],
                    'EmployeeID'    => [ 'column' => 'employee_id',    'type' => 'Edm.Int64' ],
                    'ShiftID'       => [ 'column' => 'shift_id',       'type' => 'Edm.Int64' ],
                    'EffectiveFrom' => [ 'column' => 'effective_from', 'type' => 'Edm.Date' ],
                    'EffectiveTo'   => [ 'column' => 'effective_to',   'type' => 'Edm.Date' ],
                    'CreatedAt'     => [ 'column' => 'created_at',     'type' => 'Edm.DateTimeOffset', 'read_only' => true ],
                ],
            ],
            nav_properties: [
                'Employee' => [ 'type' => 'Employees', 'collection' => false, 'fk' => 'EmployeeID' ],
                'Shift'    => [ 'type' => 'Shifts',    'collection' => false, 'fk' => 'ShiftID' ],
            ],
        );
    }
}
