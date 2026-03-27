<?php
defined( 'ABSPATH' ) || exit;

class ODAD_HR_Positions extends ODAD_Adapter_Custom_Table {

    public function __construct() {
        parent::__construct(
            table_name:      'hr_positions',
            entity_set_name: 'Positions',
            key_column:      'id',
            schema: [
                'key'        => 'ID',
                'properties' => [
                    'ID'           => [ 'column' => 'id',            'type' => 'Edm.Int64', 'read_only' => true ],
                    'Title'        => [ 'column' => 'title',         'type' => 'Edm.String' ],
                    'DepartmentID' => [ 'column' => 'department_id', 'type' => 'Edm.Int64' ],
                ],
            ],
        );
    }
}
