<?php
defined( 'ABSPATH' ) || exit;

class ODAD_HR_Notifications extends ODAD_Adapter_Custom_Table {

    public function __construct() {
        parent::__construct(
            table_name:      'hr_notifications',
            entity_set_name: 'Notifications',
            key_column:      'id',
            schema: [
                'key'        => 'ID',
                'properties' => [
                    'ID'        => [ 'column' => 'id',         'type' => 'Edm.Int64',          'read_only' => true ],
                    'UserID'    => [ 'column' => 'user_id',    'type' => 'Edm.Int64' ],
                    'Type'      => [ 'column' => 'type',       'type' => 'Edm.String' ],
                    'Title'     => [ 'column' => 'title',      'type' => 'Edm.String' ],
                    'Message'   => [ 'column' => 'message',    'type' => 'Edm.String' ],
                    'Link'      => [ 'column' => 'link',       'type' => 'Edm.String' ],
                    'IsRead'    => [ 'column' => 'is_read',    'type' => 'Edm.Boolean' ],
                    'CreatedAt' => [ 'column' => 'created_at', 'type' => 'Edm.DateTimeOffset', 'read_only' => true ],
                ],
            ],
            nav_properties: [],
        );
    }
}
