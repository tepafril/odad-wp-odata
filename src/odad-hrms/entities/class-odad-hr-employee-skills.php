<?php
defined( 'ABSPATH' ) || exit;

/**
 * Pivot entity — bridges the many-to-many between Employees and Skills.
 *
 * Query pattern:
 *   GET /Employees?$expand=EmployeeSkills($expand=Skill)
 *   GET /Skills?$expand=EmployeeSkills($expand=Employee)
 */
class ODAD_HR_Employee_Skills extends ODAD_Adapter_Custom_Table {

    public function __construct() {
        parent::__construct(
            table_name:      'hr_employee_skills',
            entity_set_name: 'EmployeeSkills',
            key_column:      'id',
            schema: [
                'key'        => 'ID',
                'properties' => [
                    'ID'               => [ 'column' => 'id',                'type' => 'Edm.Int64',  'read_only' => true ],
                    'EmployeeID'       => [ 'column' => 'employee_id',       'type' => 'Edm.Int64' ],
                    'SkillID'          => [ 'column' => 'skill_id',          'type' => 'Edm.Int64' ],
                    'ProficiencyLevel' => [ 'column' => 'proficiency_level', 'type' => 'Edm.String' ],
                ],
            ],
            nav_properties: [
                'Employee' => [ 'type' => 'Employees', 'collection' => false, 'fk' => 'EmployeeID' ],
                'Skill'    => [ 'type' => 'Skills',    'collection' => false, 'fk' => 'SkillID' ],
            ],
        );
    }
}
