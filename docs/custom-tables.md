# Adding a Custom Table

This guide explains how to expose a custom database table as an OData entity set.

---

## Quick Start

Add the following to your theme's `functions.php` or a site-specific plugin:

```php
add_action( 'wpos_register_entity_sets', function (
    WPOS_Schema_Registry  $registry,
    WPOS_Adapter_Resolver $resolver
) {
    $adapter = new WPOS_Adapter_Custom_Table(
        table_name:      'employees',   // table name WITHOUT $wpdb->prefix
        entity_set_name: 'Employees',   // OData entity set name (PascalCase)
        key_column:      'id',          // primary key column
    );

    $resolver->register( 'Employees', $adapter );
    $registry->register( 'Employees', $adapter->get_entity_type_definition() );

}, 10, 2 );
```

The adapter runs `DESCRIBE {table}` on first use to detect columns automatically.
Your table is then available at:

```
GET /wp-json/odata/v4/Employees
GET /wp-json/odata/v4/Employees(42)
GET /wp-json/odata/v4/Employees?$filter=Department eq 'Engineering'&$orderby=HiredAt desc&$top=25
POST   /wp-json/odata/v4/Employees
PATCH  /wp-json/odata/v4/Employees(42)
DELETE /wp-json/odata/v4/Employees(42)
```

---

## Custom Property Names

By default, OData property names match the column names exactly. Pass a `schema`
array to remap them to PascalCase or any other naming convention:

```php
$adapter = new WPOS_Adapter_Custom_Table(
    table_name:      'employees',
    entity_set_name: 'Employees',
    key_column:      'id',
    schema: [
        'key'        => 'ID',
        'properties' => [
            'ID'         => [ 'column' => 'id',          'type' => 'Edm.Int64' ],
            'FullName'   => [ 'column' => 'full_name',   'type' => 'Edm.String' ],
            'Email'      => [ 'column' => 'email',       'type' => 'Edm.String' ],
            'Department' => [ 'column' => 'dept_id',     'type' => 'Edm.Int32' ],
            'Salary'     => [ 'column' => 'salary',      'type' => 'Edm.Decimal' ],
            'HiredAt'    => [ 'column' => 'hired_at',    'type' => 'Edm.DateTimeOffset' ],
            'IsActive'   => [ 'column' => 'is_active',   'type' => 'Edm.Boolean' ],
        ],
    ]
);
```

### Supported OData types

| `type` | MySQL column type |
|---|---|
| `Edm.Int32` | INT, MEDIUMINT |
| `Edm.Int64` | BIGINT |
| `Edm.String` | VARCHAR, TEXT, CHAR |
| `Edm.Boolean` | TINYINT(1) |
| `Edm.Decimal` | DECIMAL, NUMERIC |
| `Edm.Double` | FLOAT, DOUBLE |
| `Edm.DateTimeOffset` | DATETIME, TIMESTAMP |
| `Edm.Date` | DATE |

---

## Permissions

### Option A — WP Admin UI

Go to **WP-OData Suite → Permissions** in the WordPress admin and configure which
roles can read, insert, update, and delete `Employees`.

### Option B — Programmatically

Register capability rules alongside the adapter:

```php
add_action( 'wpos_register_permissions', function ( WPOS_Capability_Map $map ) {
    $map->register( 'Employees', [
        'read'   => 'read',             // any logged-in user
        'insert' => 'edit_employees',   // custom WP capability
        'update' => 'edit_employees',
        'delete' => 'delete_employees',
    ]);
});
```

If no permissions are registered, the plugin falls back to the convention:
`wpos_{entityset_lowercase}_{operation}` — e.g. `wpos_employees_read`.

---

## Read-Only Fields

Mark a property as read-only so it can never be set via POST/PATCH:

```php
'properties' => [
    'ID'        => [ 'column' => 'id',         'type' => 'Edm.Int64',  'read_only' => true ],
    'CreatedAt' => [ 'column' => 'created_at', 'type' => 'Edm.DateTimeOffset', 'read_only' => true ],
    'FullName'  => [ 'column' => 'full_name',  'type' => 'Edm.String' ],
],
```

### Capability-gated fields

Restrict a sensitive field (e.g. salary) to users with a specific capability:

```php
'Salary' => [
    'column'              => 'salary',
    'type'                => 'Edm.Decimal',
    'required_capability' => 'view_salaries',
],
```

Users without `view_salaries` will not see this field in any response.

---

## Disable Write Operations

To make the entity set read-only entirely, go to **WP-OData Suite → Entity Settings**,
find `Employees`, and uncheck **Allow Insert**, **Allow Update**, and **Allow Delete**.

Or do it in code via the entity config option:

```php
update_option( 'wpos_entity_config_Employees', [
    'enabled'      => true,
    'allow_insert' => false,
    'allow_update' => false,
    'allow_delete' => false,
    'max_top'      => 500,
    'require_auth' => true,
] );
```

---

## Creating the Table

The adapter does not create the table for you. Register it in a plugin activation hook:

```php
register_activation_hook( __FILE__, function () {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}employees (
        id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        full_name  VARCHAR(200)    NOT NULL DEFAULT '',
        email      VARCHAR(200)    NOT NULL DEFAULT '',
        dept_id    INT             NOT NULL DEFAULT 0,
        salary     DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
        hired_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        is_active  TINYINT(1)      NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        KEY idx_dept (dept_id),
        KEY idx_active (is_active)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
} );
```

---

## Full Example

```php
<?php
/**
 * Plugin Name: My Employees OData Entity
 */

// 1. Create the table on activation.
register_activation_hook( __FILE__, function () {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}employees (
        id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        full_name VARCHAR(200)    NOT NULL DEFAULT '',
        email     VARCHAR(200)    NOT NULL DEFAULT '',
        dept_id   INT             NOT NULL DEFAULT 0,
        hired_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
} );

// 2. Register the entity set.
add_action( 'wpos_register_entity_sets', function (
    WPOS_Schema_Registry  $registry,
    WPOS_Adapter_Resolver $resolver
) {
    $adapter = new WPOS_Adapter_Custom_Table(
        table_name:      'employees',
        entity_set_name: 'Employees',
        key_column:      'id',
        schema: [
            'key'        => 'ID',
            'properties' => [
                'ID'       => [ 'column' => 'id',        'type' => 'Edm.Int64',          'read_only' => true ],
                'FullName' => [ 'column' => 'full_name', 'type' => 'Edm.String' ],
                'Email'    => [ 'column' => 'email',     'type' => 'Edm.String' ],
                'DeptID'   => [ 'column' => 'dept_id',   'type' => 'Edm.Int32' ],
                'HiredAt'  => [ 'column' => 'hired_at',  'type' => 'Edm.DateTimeOffset', 'read_only' => true ],
            ],
        ]
    );

    $resolver->register( 'Employees', $adapter );
    $registry->register( 'Employees', $adapter->get_entity_type_definition() );

}, 10, 2 );

// 3. Register permissions.
add_action( 'wpos_register_permissions', function ( WPOS_Capability_Map $map ) {
    $map->register( 'Employees', [
        'read'   => 'read',
        'insert' => 'manage_options',
        'update' => 'manage_options',
        'delete' => 'manage_options',
    ]);
} );
```
