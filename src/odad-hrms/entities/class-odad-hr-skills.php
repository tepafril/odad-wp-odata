<?php
defined( 'ABSPATH' ) || exit;

class ODAD_HR_Skills extends ODAD_Adapter_Custom_Table {

    public function __construct() {
        parent::__construct(
            table_name:      'hr_skills',
            entity_set_name: 'Skills',
            key_column:      'id',
            schema: [
                'key'        => 'ID',
                'properties' => [
                    'ID'       => [ 'column' => 'id',       'type' => 'Edm.Int64',  'read_only' => true ],
                    'Name'     => [ 'column' => 'name',     'type' => 'Edm.String' ],
                    'Category' => [ 'column' => 'category', 'type' => 'Edm.String' ],
                ],
            ],
            nav_properties: [
                // Navigate from a Skill to all its pivot rows (then expand Employee from there)
                'EmployeeSkills' => [ 'type' => 'EmployeeSkills', 'collection' => true, 'fk' => 'ID', 'remote_fk' => 'SkillID' ],
            ],
        );
    }
}
