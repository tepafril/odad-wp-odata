<?php
defined( 'ABSPATH' ) || exit;

class ODAD_HR_Departments extends ODAD_Adapter_Custom_Table {

    public function __construct() {
        parent::__construct(
            table_name:      'hr_departments',
            entity_set_name: 'Departments',
            key_column:      'id',
            schema: [
                'key'        => 'ID',
                'properties' => [
                    'ID'        => [ 'column' => 'id',         'type' => 'Edm.Int64', 'read_only' => true ],
                    'Name'      => [ 'column' => 'name',       'type' => 'Edm.String' ],
                    'ManagerID' => [ 'column' => 'manager_id', 'type' => 'Edm.Int64' ],
                ],
            ],
            nav_properties: [
                'Manager'   => [ 'type' => 'Employees', 'collection' => false, 'fk' => 'ManagerID' ],
                'Employees' => [ 'type' => 'Employees', 'collection' => true,  'fk' => 'ID', 'remote_fk' => 'DepartmentID' ],
                'Positions' => [ 'type' => 'Positions', 'collection' => true,  'fk' => 'ID', 'remote_fk' => 'DepartmentID' ],
            ],
        );
    }
}
