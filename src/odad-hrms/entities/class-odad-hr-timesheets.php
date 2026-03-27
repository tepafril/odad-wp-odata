<?php
defined( 'ABSPATH' ) || exit;

class ODAD_HR_Timesheets extends ODAD_Adapter_Custom_Table {

    public function __construct() {
        parent::__construct(
            table_name:      'hr_timesheets',
            entity_set_name: 'Timesheets',
            key_column:      'id',
            schema: [
                'key'        => 'ID',
                'properties' => [
                    'ID'          => [ 'column' => 'id',           'type' => 'Edm.Int64',          'read_only' => true ],
                    'EmployeeID'  => [ 'column' => 'employee_id',  'type' => 'Edm.Int64' ],
                    'WorkDate'    => [ 'column' => 'work_date',    'type' => 'Edm.Date' ],
                    'Hours'       => [ 'column' => 'hours',        'type' => 'Edm.Decimal' ],
                    'Note'        => [ 'column' => 'note',         'type' => 'Edm.String' ],
                    'Status'      => [ 'column' => 'status',       'type' => 'Edm.String' ],
                    'SubmittedAt' => [ 'column' => 'submitted_at', 'type' => 'Edm.DateTimeOffset', 'read_only' => true ],
                ],
            ],
            nav_properties: [
                'Employee' => [ 'type' => 'Employees', 'collection' => false, 'fk' => 'EmployeeID' ],
            ],
        );
    }
}
