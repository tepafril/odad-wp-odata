<?php
defined( 'ABSPATH' ) || exit;

class ODAD_HR_Audit_Log extends ODAD_Adapter_Custom_Table {

    public function __construct() {
        parent::__construct(
            table_name:      'hr_audit_log',
            entity_set_name: 'AuditLog',
            key_column:      'id',
            schema: [
                'key'        => 'ID',
                'properties' => [
                    'ID'         => [ 'column' => 'id',          'type' => 'Edm.Int64',          'read_only' => true ],
                    'UserID'     => [ 'column' => 'user_id',     'type' => 'Edm.Int64',          'read_only' => true ],
                    'Action'     => [ 'column' => 'action',      'type' => 'Edm.String',         'read_only' => true ],
                    'ObjectType' => [ 'column' => 'object_type', 'type' => 'Edm.String',         'read_only' => true ],
                    'ObjectID'   => [ 'column' => 'object_id',   'type' => 'Edm.Int64',          'read_only' => true ],
                    'OldValues'  => [ 'column' => 'old_values',  'type' => 'Edm.String',         'read_only' => true ],
                    'NewValues'  => [ 'column' => 'new_values',  'type' => 'Edm.String',         'read_only' => true ],
                    'IPAddress'  => [ 'column' => 'ip_address',  'type' => 'Edm.String',         'read_only' => true ],
                    'CreatedAt'  => [ 'column' => 'created_at',  'type' => 'Edm.DateTimeOffset', 'read_only' => true ],
                ],
            ],
            nav_properties: [],
        );
    }
}
