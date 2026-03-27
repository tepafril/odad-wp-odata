<?php
defined( 'ABSPATH' ) || exit;

class ODAD_HR_Employee_Work_History extends ODAD_Adapter_Custom_Table {

    public function __construct() {
        parent::__construct(
            table_name:      'hr_employee_work_history',
            entity_set_name: 'EmployeeWorkHistory',
            key_column:      'id',
            schema: [
                'key'        => 'ID',
                'properties' => [
                    'ID'          => [ 'column' => 'id',           'type' => 'Edm.Int64', 'read_only' => true ],
                    'EmployeeID'  => [ 'column' => 'employee_id',  'type' => 'Edm.Int64' ],
                    'CompanyName' => [ 'column' => 'company_name', 'type' => 'Edm.String' ],
                    'JobTitle'    => [ 'column' => 'job_title',    'type' => 'Edm.String' ],
                    'StartDate'   => [ 'column' => 'start_date',   'type' => 'Edm.Date' ],
                    'EndDate'     => [ 'column' => 'end_date',     'type' => 'Edm.Date' ],
                    'Description' => [ 'column' => 'description',  'type' => 'Edm.String' ],
                ],
            ],
            nav_properties: [
                'Employee' => [ 'type' => 'Employees', 'collection' => false, 'fk' => 'EmployeeID' ],
            ],
        );
    }
}
