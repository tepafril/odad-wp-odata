<?php
defined( 'ABSPATH' ) || exit;

class ODAD_HR_Employee_Education extends ODAD_Adapter_Custom_Table {

    public function __construct() {
        parent::__construct(
            table_name:      'hr_employee_education',
            entity_set_name: 'EmployeeEducation',
            key_column:      'id',
            schema: [
                'key'        => 'ID',
                'properties' => [
                    'ID'           => [ 'column' => 'id',             'type' => 'Edm.Int64', 'read_only' => true ],
                    'EmployeeID'   => [ 'column' => 'employee_id',    'type' => 'Edm.Int64' ],
                    'Institution'  => [ 'column' => 'institution',    'type' => 'Edm.String' ],
                    'Degree'       => [ 'column' => 'degree',         'type' => 'Edm.String' ],
                    'FieldOfStudy' => [ 'column' => 'field_of_study', 'type' => 'Edm.String' ],
                    'StartDate'    => [ 'column' => 'start_date',     'type' => 'Edm.Date' ],
                    'EndDate'      => [ 'column' => 'end_date',       'type' => 'Edm.Date' ],
                    'GradeOrGPA'   => [ 'column' => 'grade_or_gpa',  'type' => 'Edm.String' ],
                    'Notes'        => [ 'column' => 'notes',          'type' => 'Edm.String' ],
                ],
            ],
            nav_properties: [
                'Employee' => [ 'type' => 'Employees', 'collection' => false, 'fk' => 'EmployeeID' ],
            ],
        );
    }
}
