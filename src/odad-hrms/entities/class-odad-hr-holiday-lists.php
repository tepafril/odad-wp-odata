<?php
defined( 'ABSPATH' ) || exit;

class ODAD_HR_Holiday_Lists extends ODAD_Adapter_Custom_Table {

    public function __construct() {
        parent::__construct(
            table_name:      'hr_holiday_lists',
            entity_set_name: 'HolidayLists',
            key_column:      'id',
            schema: [
                'key'        => 'ID',
                'properties' => [
                    'ID'        => [ 'column' => 'id',         'type' => 'Edm.Int64',          'read_only' => true ],
                    'CompanyID' => [ 'column' => 'company_id', 'type' => 'Edm.Int64' ],
                    'Name'      => [ 'column' => 'name',       'type' => 'Edm.String' ],
                    'Year'      => [ 'column' => 'year',       'type' => 'Edm.Int32' ],
                    'Status'    => [ 'column' => 'status',     'type' => 'Edm.String' ],
                    'CreatedAt' => [ 'column' => 'created_at', 'type' => 'Edm.DateTimeOffset', 'read_only' => true ],
                ],
            ],
            nav_properties: [
                'Company'  => [ 'type' => 'Companies', 'collection' => false, 'fk' => 'CompanyID' ],
                'Holidays' => [ 'type' => 'Holidays',  'collection' => true,  'fk' => 'ID', 'remote_fk' => 'HolidayListID' ],
            ],
        );
    }
}
