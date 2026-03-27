<?php
defined( 'ABSPATH' ) || exit;

class ODAD_HR_Employee_Documents extends ODAD_Adapter_Custom_Table {

    public function __construct() {
        parent::__construct(
            table_name:      'hr_employee_documents',
            entity_set_name: 'EmployeeDocuments',
            key_column:      'id',
            schema: [
                'key'        => 'ID',
                'properties' => [
                    'ID'           => [ 'column' => 'id',             'type' => 'Edm.Int64',          'read_only' => true ],
                    'EmployeeID'   => [ 'column' => 'employee_id',    'type' => 'Edm.Int64' ],
                    'DocumentType' => [ 'column' => 'document_type',  'type' => 'Edm.String' ],
                    'Title'        => [ 'column' => 'title',          'type' => 'Edm.String' ],
                    'AttachmentID' => [ 'column' => 'attachment_id',  'type' => 'Edm.Int64' ],
                    'IssueDate'    => [ 'column' => 'issue_date',     'type' => 'Edm.Date' ],
                    'ExpiryDate'   => [ 'column' => 'expiry_date',    'type' => 'Edm.Date' ],
                    'IsVerified'   => [ 'column' => 'is_verified',    'type' => 'Edm.Boolean' ],
                    'Notes'        => [ 'column' => 'notes',          'type' => 'Edm.String' ],
                    'UploadedBy'   => [ 'column' => 'uploaded_by',    'type' => 'Edm.Int64' ],
                    'CreatedAt'    => [ 'column' => 'created_at',     'type' => 'Edm.DateTimeOffset', 'read_only' => true ],
                ],
            ],
            nav_properties: [
                'Employee' => [ 'type' => 'Employees', 'collection' => false, 'fk' => 'EmployeeID' ],
            ],
        );
    }
}
