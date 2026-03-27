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
        // Core
        new ODAD_HR_Employees(),
        new ODAD_HR_Departments(),
        new ODAD_HR_Positions(),
        new ODAD_HR_Leave_Requests(),
        new ODAD_HR_Timesheets(),
        new ODAD_HR_Skills(),
        new ODAD_HR_Employee_Skills(),
        // Organisation structure
        new ODAD_HR_Companies(),
        new ODAD_HR_Branches(),
        new ODAD_HR_Employment_Types(),
        // Employee details
        new ODAD_HR_Employee_Education(),
        new ODAD_HR_Employee_Work_History(),
        new ODAD_HR_Employee_Bank(),
        new ODAD_HR_Employee_Movement(),
        new ODAD_HR_Employee_Documents(),
        // System
        new ODAD_HR_Audit_Log(),
        new ODAD_HR_Notifications(),
        // Holiday management
        new ODAD_HR_Holiday_Lists(),
        new ODAD_HR_Holidays(),
        // Leave management
        new ODAD_HR_Leave_Types(),
        new ODAD_HR_Leave_Policies(),
        new ODAD_HR_Leave_Policy_Details(),
        new ODAD_HR_Leave_Policy_Assignments(),
        new ODAD_HR_Leave_Balances(),
        // Attendance
        new ODAD_HR_Shifts(),
        new ODAD_HR_Shift_Assignments(),
        new ODAD_HR_Attendance(),
        new ODAD_HR_Attendance_Requests(),
        new ODAD_HR_Compensatory_Requests(),
    ];

    foreach ( $entities as $adapter ) {
        $name = $adapter->get_entity_set_name();
        $resolver->register( $name, $adapter );
        $registry->register( $name, $adapter->get_entity_type_definition() );
    }

}, 10, 2 );


// ── Controller (read): OData Functions ────────────────────────────────────────

add_action( 'ODAD_register_functions', function ( ODAD_Function_Registry $registry ): void {

    // // GET /odata/v4/HR.GetLeaveBalance(EmployeeID=5)
    // $registry->register(
    //     name:        'HR.GetLeaveBalance',
    //     handler:     new ODAD_HR_Fn_Leave_Balance(),
    //     binding:     [],
    //     parameters:  [
    //         [ 'name' => 'EmployeeID', 'type' => 'Edm.Int32', 'required' => true ],
    //     ],
    //     return_type: 'Edm.Int32',
    // );

} );


// ── Controller (write): OData Actions ─────────────────────────────────────────

add_action( 'ODAD_register_actions', function ( ODAD_Action_Registry $registry ): void {

    // // POST /odata/v4/HR.ApproveLeave   body: { "LeaveRequestID": 42 }
    // $registry->register(
    //     name:        'HR.ApproveLeave',
    //     handler:     new ODAD_HR_Act_Approve_Leave(),
    //     binding:     [],
    //     parameters:  [
    //         [ 'name' => 'LeaveRequestID', 'type' => 'Edm.Int32', 'required' => true ],
    //     ],
    //     return_type: 'Edm.Boolean',
    // );

    // // POST /odata/v4/HR.SubmitTimesheet   body: { "TimesheetID": 7 }
    // $registry->register(
    //     name:        'HR.SubmitTimesheet',
    //     handler:     new ODAD_HR_Act_Submit_Timesheet(),
    //     binding:     [],
    //     parameters:  [
    //         [ 'name' => 'TimesheetID', 'type' => 'Edm.Int32', 'required' => true ],
    //     ],
    //     return_type: 'Edm.Boolean',
    // );

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

    $map->register( 'Skills', [
        'read'   => 'read',
        'insert' => 'manage_hr',
        'update' => 'manage_hr',
        'delete' => 'manage_hr',
    ] );

    $map->register( 'EmployeeSkills', [
        'read'   => 'read',
        'insert' => 'manage_hr',
        'update' => 'manage_hr',
        'delete' => 'manage_hr',
    ] );

    // Organisation structure — HR-managed reference data.
    foreach ( [ 'Companies', 'Branches', 'EmploymentTypes' ] as $entity ) {
        $map->register( $entity, [
            'read'   => 'read',
            'insert' => 'manage_hr',
            'update' => 'manage_hr',
            'delete' => 'manage_hr',
        ] );
    }

    // Employee detail tables — employees can read their own; HR manages.
    foreach ( [ 'EmployeeEducation', 'EmployeeWorkHistory', 'EmployeeBankAccounts', 'EmployeeDocuments' ] as $entity ) {
        $map->register( $entity, [
            'read'   => 'read',
            'insert' => 'manage_hr',
            'update' => 'manage_hr',
            'delete' => 'manage_hr',
        ] );
    }

    // Movements require HR approval workflow.
    $map->register( 'EmployeeMovements', [
        'read'   => 'read',
        'insert' => 'manage_hr',
        'update' => 'manage_hr',
        'delete' => 'manage_hr',
    ] );

    // Audit log and notifications are read-only via OData.
    $map->register( 'AuditLog', [
        'read'   => 'manage_hr',
        'insert' => 'manage_options',
        'update' => 'manage_options',
        'delete' => 'manage_options',
    ] );

    $map->register( 'Notifications', [
        'read'   => 'read',
        'insert' => 'manage_hr',
        'update' => 'read',       // users mark their own as read
        'delete' => 'manage_hr',
    ] );

    // Holiday management.
    foreach ( [ 'HolidayLists', 'Holidays' ] as $entity ) {
        $map->register( $entity, [
            'read'   => 'read',
            'insert' => 'manage_hr',
            'update' => 'manage_hr',
            'delete' => 'manage_hr',
        ] );
    }

    // Leave configuration — HR only.
    foreach ( [ 'LeaveTypes', 'LeavePolicies', 'LeavePolicyDetails', 'LeavePolicyAssignments' ] as $entity ) {
        $map->register( $entity, [
            'read'   => 'read',
            'insert' => 'manage_hr',
            'update' => 'manage_hr',
            'delete' => 'manage_hr',
        ] );
    }

    // Leave balances — read for all; write for HR only.
    $map->register( 'LeaveBalances', [
        'read'   => 'read',
        'insert' => 'manage_hr',
        'update' => 'manage_hr',
        'delete' => 'manage_hr',
    ] );

    // Shifts and assignments — HR manages.
    foreach ( [ 'Shifts', 'ShiftAssignments' ] as $entity ) {
        $map->register( $entity, [
            'read'   => 'read',
            'insert' => 'manage_hr',
            'update' => 'manage_hr',
            'delete' => 'manage_hr',
        ] );
    }

    // Attendance — employees submit; HR manages.
    $map->register( 'Attendance', [
        'read'   => 'read',
        'insert' => 'read',
        'update' => 'manage_hr',
        'delete' => 'manage_hr',
    ] );

    $map->register( 'AttendanceRequests', [
        'read'   => 'read',
        'insert' => 'read',      // employees create correction requests
        'update' => 'manage_hr',
        'delete' => 'manage_hr',
    ] );

    $map->register( 'CompensatoryRequests', [
        'read'   => 'read',
        'insert' => 'read',      // employees submit
        'update' => 'manage_hr',
        'delete' => 'manage_hr',
    ] );

} );
