<?php
defined( 'ABSPATH' ) || exit;

class ODAD_HR_Leave_Policies extends ODAD_Adapter_Custom_Table {

    public function __construct() {
        parent::__construct(
            table_name:      'hr_leave_policies',
            entity_set_name: 'LeavePolicies',
            key_column:      'id',
            schema: [
                'key'        => 'ID',
                'properties' => [
                    'ID'               => [ 'column' => 'id',                'type' => 'Edm.Int64',  'read_only' => true ],
                    'CompanyID'        => [ 'column' => 'company_id',        'type' => 'Edm.Int64' ],
                    'Name'             => [ 'column' => 'name',              'type' => 'Edm.String' ],
                    'EffectiveFrom'    => [ 'column' => 'effective_from',    'type' => 'Edm.Date' ],
                    'EffectiveTo'      => [ 'column' => 'effective_to',      'type' => 'Edm.Date' ],
                    'Status'           => [ 'column' => 'status',            'type' => 'Edm.String' ],
                    'ConflictStrategy' => [ 'column' => 'conflict_strategy', 'type' => 'Edm.String' ],
                ],
            ],
            nav_properties: [
                'Company'     => [ 'type' => 'Companies',            'collection' => false, 'fk' => 'CompanyID' ],
                'Details'     => [ 'type' => 'LeavePolicyDetails',   'collection' => true,  'fk' => 'ID', 'remote_fk' => 'LeavePolicyID' ],
                'Assignments' => [ 'type' => 'LeavePolicyAssignments', 'collection' => true, 'fk' => 'ID', 'remote_fk' => 'LeavePolicyID' ],
            ],
        );
    }
}
