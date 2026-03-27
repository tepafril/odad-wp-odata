<?php
defined( 'ABSPATH' ) || exit;

class ODAD_HR_Leave_Policy_Assignments extends ODAD_Adapter_Custom_Table {

    public function __construct() {
        parent::__construct(
            table_name:      'hr_leave_policy_assignments',
            entity_set_name: 'LeavePolicyAssignments',
            key_column:      'id',
            schema: [
                'key'        => 'ID',
                'properties' => [
                    'ID'             => [ 'column' => 'id',              'type' => 'Edm.Int64', 'read_only' => true ],
                    'LeavePolicyID'  => [ 'column' => 'leave_policy_id', 'type' => 'Edm.Int64' ],
                    'AssignmentType' => [ 'column' => 'assignment_type', 'type' => 'Edm.String' ],
                    'AssignmentID'   => [ 'column' => 'assignment_id',   'type' => 'Edm.String' ],
                    'EffectiveFrom'  => [ 'column' => 'effective_from',  'type' => 'Edm.Date' ],
                    'Priority'       => [ 'column' => 'priority',        'type' => 'Edm.Int32' ],
                ],
            ],
            nav_properties: [
                'LeavePolicy' => [ 'type' => 'LeavePolicies', 'collection' => false, 'fk' => 'LeavePolicyID' ],
            ],
        );
    }
}
