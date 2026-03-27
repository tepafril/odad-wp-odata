<?php
defined( 'ABSPATH' ) || exit;

class ODAD_HR_Holidays extends ODAD_Adapter_Custom_Table {

    public function __construct() {
        parent::__construct(
            table_name:      'hr_holidays',
            entity_set_name: 'Holidays',
            key_column:      'id',
            schema: [
                'key'        => 'ID',
                'properties' => [
                    'ID'             => [ 'column' => 'id',              'type' => 'Edm.Int64', 'read_only' => true ],
                    'HolidayListID'  => [ 'column' => 'holiday_list_id', 'type' => 'Edm.Int64' ],
                    'Name'           => [ 'column' => 'name',            'type' => 'Edm.String' ],
                    'Date'           => [ 'column' => 'date',            'type' => 'Edm.Date' ],
                    'IsRestricted'   => [ 'column' => 'is_restricted',   'type' => 'Edm.Boolean' ],
                    'Description'    => [ 'column' => 'description',     'type' => 'Edm.String' ],
                ],
            ],
            nav_properties: [
                'HolidayList' => [ 'type' => 'HolidayLists', 'collection' => false, 'fk' => 'HolidayListID' ],
            ],
        );
    }
}
