<?php
defined( 'ABSPATH' ) || exit;

/**
 * Bridge entity — many-to-many between LeavePolicies and LeaveTypes.
 *
 * Query patterns:
 *   GET /LeavePolicies?$expand=Details($expand=LeaveType)
 *   GET /LeaveTypes?$expand=LeavePolicyDetails($expand=LeavePolicy)
 */
class ODAD_HR_Leave_Policy_Details extends ODAD_Adapter_Custom_Table {

    public function __construct() {
        parent::__construct(
            table_name:      'hr_leave_policy_details',
            entity_set_name: 'LeavePolicyDetails',
            key_column:      'id',
            schema: [
                'key'        => 'ID',
                'properties' => [
                    'ID'               => [ 'column' => 'id',               'type' => 'Edm.Int64',  'read_only' => true ],
                    'LeavePolicyID'    => [ 'column' => 'leave_policy_id',  'type' => 'Edm.Int64' ],
                    'LeaveTypeID'      => [ 'column' => 'leave_type_id',    'type' => 'Edm.Int64' ],
                    'AnnualAllocation' => [ 'column' => 'annual_allocation', 'type' => 'Edm.Decimal' ],
                ],
            ],
            nav_properties: [
                'LeavePolicy' => [ 'type' => 'LeavePolicies', 'collection' => false, 'fk' => 'LeavePolicyID' ],
                'LeaveType'   => [ 'type' => 'LeaveTypes',    'collection' => false, 'fk' => 'LeaveTypeID' ],
            ],
        );
    }
}
