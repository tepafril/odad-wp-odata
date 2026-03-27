<?php
defined( 'ABSPATH' ) || exit;

class ODAD_HR_Employees extends ODAD_Adapter_Custom_Table {

    public function __construct() {
        parent::__construct(
            table_name:      'hr_employees',
            entity_set_name: 'Employees',
            key_column:      'id',
            schema: [
                'key'        => 'ID',
                'properties' => [
                    'ID'           => [ 'column' => 'id',            'type' => 'Edm.Int64',          'read_only' => true ],
                    'WPUserID'     => [ 'column' => 'wp_user_id',    'type' => 'Edm.Int64' ],
                    'FullName'     => [ 'column' => 'full_name',     'type' => 'Edm.String' ],
                    'Email'        => [ 'column' => 'email',         'type' => 'Edm.String' ],
                    'DepartmentID' => [ 'column' => 'department_id', 'type' => 'Edm.Int64' ],
                    'PositionID'   => [ 'column' => 'position_id',   'type' => 'Edm.Int64' ],
                    'ManagerID'    => [ 'column' => 'manager_id',    'type' => 'Edm.Int64' ],
                    'HiredAt'      => [ 'column' => 'hired_at',      'type' => 'Edm.Date' ],
                    'IsActive'     => [ 'column' => 'is_active',     'type' => 'Edm.Boolean' ],
                ],
            ],
        );
    }
}
