<?php
defined( 'ABSPATH' ) || exit;

class ODAD_HR_Companies extends ODAD_Adapter_Custom_Table {

    public function __construct() {
        parent::__construct(
            table_name:      'hr_companies',
            entity_set_name: 'Companies',
            key_column:      'id',
            schema: [
                'key'        => 'ID',
                'properties' => [
                    'ID'               => [ 'column' => 'id',                'type' => 'Edm.Int64',          'read_only' => true ],
                    'Name'             => [ 'column' => 'name',              'type' => 'Edm.String' ],
                    'ShortName'        => [ 'column' => 'short_name',        'type' => 'Edm.String' ],
                    'Industry'         => [ 'column' => 'industry',          'type' => 'Edm.String' ],
                    'RegistrationNo'   => [ 'column' => 'registration_no',   'type' => 'Edm.String' ],
                    'TaxID'            => [ 'column' => 'tax_id',            'type' => 'Edm.String' ],
                    'DefaultCurrency'  => [ 'column' => 'default_currency',  'type' => 'Edm.String' ],
                    'FiscalYearStart'  => [ 'column' => 'fiscal_year_start', 'type' => 'Edm.Int32' ],
                    'AddressLine1'     => [ 'column' => 'address_line_1',    'type' => 'Edm.String' ],
                    'AddressLine2'     => [ 'column' => 'address_line_2',    'type' => 'Edm.String' ],
                    'City'             => [ 'column' => 'city',              'type' => 'Edm.String' ],
                    'State'            => [ 'column' => 'state',             'type' => 'Edm.String' ],
                    'Country'          => [ 'column' => 'country',           'type' => 'Edm.String' ],
                    'PostalCode'       => [ 'column' => 'postal_code',       'type' => 'Edm.String' ],
                    'Phone'            => [ 'column' => 'phone',             'type' => 'Edm.String' ],
                    'Email'            => [ 'column' => 'email',             'type' => 'Edm.String' ],
                    'CreatedAt'        => [ 'column' => 'created_at',        'type' => 'Edm.DateTimeOffset', 'read_only' => true ],
                    'UpdatedAt'        => [ 'column' => 'updated_at',        'type' => 'Edm.DateTimeOffset', 'read_only' => true ],
                ],
            ],
            nav_properties: [
                'Branches'     => [ 'type' => 'Branches',     'collection' => true, 'fk' => 'ID', 'remote_fk' => 'CompanyID' ],
                'HolidayLists' => [ 'type' => 'HolidayLists', 'collection' => true, 'fk' => 'ID', 'remote_fk' => 'CompanyID' ],
                'LeaveTypes'   => [ 'type' => 'LeaveTypes',   'collection' => true, 'fk' => 'ID', 'remote_fk' => 'CompanyID' ],
                'LeavePolicies' => [ 'type' => 'LeavePolicies', 'collection' => true, 'fk' => 'ID', 'remote_fk' => 'CompanyID' ],
                'Shifts'       => [ 'type' => 'Shifts',       'collection' => true, 'fk' => 'ID', 'remote_fk' => 'CompanyID' ],
            ],
        );
    }
}
