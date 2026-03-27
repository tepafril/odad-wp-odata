<?php
/**
 * HR domain bootstrap.
 *
 * Registers all HR entity sets, OData functions, OData actions,
 * and capability mappings into the OData plugin.
 *
 * Each section maps to one MVC layer:
 *   ODAD_register_entity_sets  → Model  (custom tables as OData entities)
 *   ODAD_register_functions    → Controller read  (calculated/reported data)
 *   ODAD_register_actions      → Controller write (business workflows)
 *   ODAD_register_permissions  → Security (who can do what)
 */

defined( 'ABSPATH' ) || exit;

// ── Model: expose HR tables as OData entity sets ──────────────────────────────

add_action( 'ODAD_register_entity_sets', function (
    ODAD_Schema_Registry  $registry,
    ODAD_Adapter_Resolver $resolver
): void {

    $entities = [
        new ODAD_HR_Employees(),
        new ODAD_HR_Departments(),
        new ODAD_HR_Positions(),
        new ODAD_HR_Leave_Requests(),
        new ODAD_HR_Timesheets(),
    ];

    foreach ( $entities as $adapter ) {
        $name = $adapter->get_entity_set_name();
        $resolver->register( $name, $adapter );
        $registry->register( $name, $adapter->get_entity_type_definition() );
    }

}, 10, 2 );


// ── Controller (read): OData Functions ────────────────────────────────────────

add_action( 'ODAD_register_functions', function ( ODAD_Function_Registry $registry ): void {

    // GET /odata/v4/HR.GetLeaveBalance(EmployeeID=5)
    $registry->register(
        name:        'HR.GetLeaveBalance',
        handler:     new ODAD_HR_Fn_Leave_Balance(),
        binding:     [],
        parameters:  [
            [ 'name' => 'EmployeeID', 'type' => 'Edm.Int32', 'required' => true ],
        ],
        return_type: 'Edm.Int32',
    );

} );


// ── Controller (write): OData Actions ─────────────────────────────────────────

add_action( 'ODAD_register_actions', function ( ODAD_Action_Registry $registry ): void {

    // POST /odata/v4/HR.ApproveLeave   body: { "LeaveRequestID": 42 }
    $registry->register(
        name:        'HR.ApproveLeave',
        handler:     new ODAD_HR_Act_Approve_Leave(),
        binding:     [],
        parameters:  [
            [ 'name' => 'LeaveRequestID', 'type' => 'Edm.Int32', 'required' => true ],
        ],
        return_type: 'Edm.Boolean',
    );

    // POST /odata/v4/HR.SubmitTimesheet   body: { "TimesheetID": 7 }
    $registry->register(
        name:        'HR.SubmitTimesheet',
        handler:     new ODAD_HR_Act_Submit_Timesheet(),
        binding:     [],
        parameters:  [
            [ 'name' => 'TimesheetID', 'type' => 'Edm.Int32', 'required' => true ],
        ],
        return_type: 'Edm.Boolean',
    );

} );


// ── Security: capability mapping per entity set ───────────────────────────────

add_action( 'ODAD_register_permissions', function ( ODAD_Capability_Map $map ): void {

    // Any logged-in user can read; only HR managers can write.
    foreach ( [ 'Employees', 'Departments', 'Positions' ] as $entity ) {
        $map->register( $entity, [
            'read'   => 'read',
            'insert' => 'manage_hr',
            'update' => 'manage_hr',
            'delete' => 'manage_hr',
        ] );
    }

    // Employees submit their own; managers approve via Action (not direct write).
    $map->register( 'LeaveRequests', [
        'read'   => 'read',
        'insert' => 'read',       // any employee can create a request
        'update' => 'manage_hr',  // only HR/manager can change status directly
        'delete' => 'manage_hr',
    ] );

    $map->register( 'Timesheets', [
        'read'   => 'read',
        'insert' => 'read',       // employees log their own hours
        'update' => 'read',       // employees edit drafts
        'delete' => 'manage_hr',
    ] );

} );
