<?php
defined( 'ABSPATH' ) || exit;

class ODAD_HR_Shifts extends ODAD_Adapter_Custom_Table {

    public function __construct() {
        parent::__construct(
            table_name:      'hr_shifts',
            entity_set_name: 'Shifts',
            key_column:      'id',
            schema: [
                'key'        => 'ID',
                'properties' => [
                    'ID'                           => [ 'column' => 'id',                            'type' => 'Edm.Int64',  'read_only' => true ],
                    'CompanyID'                    => [ 'column' => 'company_id',                    'type' => 'Edm.Int64' ],
                    'Name'                         => [ 'column' => 'name',                          'type' => 'Edm.String' ],
                    'StartTime'                    => [ 'column' => 'start_time',                    'type' => 'Edm.TimeOfDay' ],
                    'EndTime'                      => [ 'column' => 'end_time',                      'type' => 'Edm.TimeOfDay' ],
                    'GracePeriodMinutes'           => [ 'column' => 'grace_period_minutes',          'type' => 'Edm.Int32' ],
                    'EarlyExitThresholdMinutes'    => [ 'column' => 'early_exit_threshold_minutes',  'type' => 'Edm.Int32' ],
                    'WorkingHours'                 => [ 'column' => 'working_hours',                 'type' => 'Edm.Decimal' ],
                    'IsOvernight'                  => [ 'column' => 'is_overnight',                  'type' => 'Edm.Boolean' ],
                    'Status'                       => [ 'column' => 'status',                        'type' => 'Edm.String' ],
                ],
            ],
            nav_properties: [
                'Company'     => [ 'type' => 'Companies',         'collection' => false, 'fk' => 'CompanyID' ],
                'Assignments' => [ 'type' => 'ShiftAssignments',  'collection' => true,  'fk' => 'ID', 'remote_fk' => 'ShiftID' ],
            ],
        );
    }
}
