<?php
defined( 'ABSPATH' ) || exit;

class ODAD_HR_Branches extends ODAD_Adapter_Custom_Table {

    public function __construct() {
        parent::__construct(
            table_name:      'hr_branches',
            entity_set_name: 'Branches',
            key_column:      'id',
            schema: [
                'key'        => 'ID',
                'properties' => [
                    'ID'           => [ 'column' => 'id',            'type' => 'Edm.Int64',          'read_only' => true ],
                    'CompanyID'    => [ 'column' => 'company_id',    'type' => 'Edm.Int64' ],
                    'Name'         => [ 'column' => 'name',          'type' => 'Edm.String' ],
                    'BranchCode'   => [ 'column' => 'branch_code',   'type' => 'Edm.String' ],
                    'AddressLine1' => [ 'column' => 'address_line_1','type' => 'Edm.String' ],
                    'AddressLine2' => [ 'column' => 'address_line_2','type' => 'Edm.String' ],
                    'City'         => [ 'column' => 'city',          'type' => 'Edm.String' ],
                    'State'        => [ 'column' => 'state',         'type' => 'Edm.String' ],
                    'Country'      => [ 'column' => 'country',       'type' => 'Edm.String' ],
                    'PostalCode'   => [ 'column' => 'postal_code',   'type' => 'Edm.String' ],
                    'Phone'        => [ 'column' => 'phone',         'type' => 'Edm.String' ],
                    'IsHeadOffice' => [ 'column' => 'is_head_office','type' => 'Edm.Boolean' ],
                    'Status'       => [ 'column' => 'status',        'type' => 'Edm.String' ],
                    'CreatedAt'    => [ 'column' => 'created_at',    'type' => 'Edm.DateTimeOffset', 'read_only' => true ],
                ],
            ],
            nav_properties: [
                'Company' => [ 'type' => 'Companies', 'collection' => false, 'fk' => 'CompanyID' ],
            ],
        );
    }
}
