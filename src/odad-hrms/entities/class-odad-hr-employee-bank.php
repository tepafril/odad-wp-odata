<?php
defined( 'ABSPATH' ) || exit;

class ODAD_HR_Employee_Bank extends ODAD_Adapter_Custom_Table {

    public function __construct() {
        parent::__construct(
            table_name:      'hr_employee_bank',
            entity_set_name: 'EmployeeBankAccounts',
            key_column:      'id',
            schema: [
                'key'        => 'ID',
                'properties' => [
                    'ID'            => [ 'column' => 'id',             'type' => 'Edm.Int64',          'read_only' => true ],
                    'EmployeeID'    => [ 'column' => 'employee_id',    'type' => 'Edm.Int64' ],
                    'BankName'      => [ 'column' => 'bank_name',      'type' => 'Edm.String' ],
                    'BranchName'    => [ 'column' => 'branch_name',    'type' => 'Edm.String' ],
                    'AccountName'   => [ 'column' => 'account_name',   'type' => 'Edm.String' ],
                    'AccountNumber' => [ 'column' => 'account_number', 'type' => 'Edm.String' ],
                    'RoutingNumber' => [ 'column' => 'routing_number', 'type' => 'Edm.String' ],
                    'IsPrimary'     => [ 'column' => 'is_primary',     'type' => 'Edm.Boolean' ],
                    'CreatedAt'     => [ 'column' => 'created_at',     'type' => 'Edm.DateTimeOffset', 'read_only' => true ],
                ],
            ],
            nav_properties: [
                'Employee' => [ 'type' => 'Employees', 'collection' => false, 'fk' => 'EmployeeID' ],
            ],
        );
    }
}
