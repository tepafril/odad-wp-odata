<?php
defined( 'ABSPATH' ) || exit;

class ODAD_HR_Employment_Types extends ODAD_Adapter_Custom_Table {

    public function __construct() {
        parent::__construct(
            table_name:      'hr_employment_types',
            entity_set_name: 'EmploymentTypes',
            key_column:      'id',
            schema: [
                'key'        => 'ID',
                'properties' => [
                    'ID'          => [ 'column' => 'id',          'type' => 'Edm.Int64',          'read_only' => true ],
                    'Name'        => [ 'column' => 'name',        'type' => 'Edm.String' ],
                    'Slug'        => [ 'column' => 'slug',        'type' => 'Edm.String' ],
                    'Description' => [ 'column' => 'description', 'type' => 'Edm.String' ],
                    'Status'      => [ 'column' => 'status',      'type' => 'Edm.String' ],
                    'CreatedAt'   => [ 'column' => 'created_at',  'type' => 'Edm.DateTimeOffset', 'read_only' => true ],
                    'UpdatedAt'   => [ 'column' => 'updated_at',  'type' => 'Edm.DateTimeOffset', 'read_only' => true ],
                ],
            ],
            nav_properties: [],
        );
    }
}
