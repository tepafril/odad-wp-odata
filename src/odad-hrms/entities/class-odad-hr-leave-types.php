<?php
defined( 'ABSPATH' ) || exit;

class ODAD_HR_Leave_Types extends ODAD_Adapter_Custom_Table {

    public function __construct() {
        parent::__construct(
            table_name:      'hr_leave_types',
            entity_set_name: 'LeaveTypes',
            key_column:      'id',
            schema: [
                'key'        => 'ID',
                'properties' => [
                    'ID'                          => [ 'column' => 'id',                             'type' => 'Edm.Int64',  'read_only' => true ],
                    'CompanyID'                   => [ 'column' => 'company_id',                     'type' => 'Edm.Int64' ],
                    'Name'                        => [ 'column' => 'name',                           'type' => 'Edm.String' ],
                    'Code'                        => [ 'column' => 'code',                           'type' => 'Edm.String' ],
                    'MaxDaysPerYear'              => [ 'column' => 'max_days_per_year',              'type' => 'Edm.Decimal' ],
                    'IsPaid'                      => [ 'column' => 'is_paid',                        'type' => 'Edm.Boolean' ],
                    'IsCarryForward'              => [ 'column' => 'is_carry_forward',               'type' => 'Edm.Boolean' ],
                    'MaxCarryForwardDays'         => [ 'column' => 'max_carry_forward_days',         'type' => 'Edm.Decimal' ],
                    'IsEncashable'                => [ 'column' => 'is_encashable',                  'type' => 'Edm.Boolean' ],
                    'AllowNegativeBalance'        => [ 'column' => 'allow_negative_balance',         'type' => 'Edm.Boolean' ],
                    'IncludeHolidaysWithin'       => [ 'column' => 'include_holidays_within',        'type' => 'Edm.Boolean' ],
                    'AllowHalfDay'                => [ 'column' => 'allow_half_day',                 'type' => 'Edm.Boolean' ],
                    'RequiresAttachment'          => [ 'column' => 'requires_attachment',            'type' => 'Edm.Boolean' ],
                    'RequiresAttachmentAfterDays' => [ 'column' => 'requires_attachment_after_days', 'type' => 'Edm.Int32' ],
                    'AccrualEnabled'              => [ 'column' => 'accrual_enabled',                'type' => 'Edm.Boolean' ],
                    'AccrualFrequency'            => [ 'column' => 'accrual_frequency',              'type' => 'Edm.String' ],
                    'ProrateOnJoining'            => [ 'column' => 'prorate_on_joining',             'type' => 'Edm.Boolean' ],
                    'ApplicableGender'            => [ 'column' => 'applicable_gender',              'type' => 'Edm.String' ],
                    'Category'                    => [ 'column' => 'category',                       'type' => 'Edm.String' ],
                    'Color'                       => [ 'column' => 'color',                          'type' => 'Edm.String' ],
                    'SortOrder'                   => [ 'column' => 'sort_order',                     'type' => 'Edm.Int32' ],
                    'Status'                      => [ 'column' => 'status',                         'type' => 'Edm.String' ],
                ],
            ],
            nav_properties: [
                'Company'                => [ 'type' => 'Companies',       'collection' => false, 'fk' => 'CompanyID' ],
                'LeavePolicyDetails'     => [ 'type' => 'LeavePolicyDetails',    'collection' => true,  'fk' => 'ID', 'remote_fk' => 'LeaveTypeID' ],
                'LeaveBalances'          => [ 'type' => 'LeaveBalances',   'collection' => true,  'fk' => 'ID', 'remote_fk' => 'LeaveTypeID' ],
                'CompensatoryRequests'   => [ 'type' => 'CompensatoryRequests', 'collection' => true, 'fk' => 'ID', 'remote_fk' => 'LeaveTypeID' ],
            ],
        );
    }
}
