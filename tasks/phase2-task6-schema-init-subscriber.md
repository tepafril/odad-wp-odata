# Task 2.6 — Schema Init Subscriber + Schema Changed Subscriber

## Dependencies
- All Phase 1 tasks
- Task 2.1 (adapter interface + resolver)
- Tasks 2.2–2.5 (all adapter implementations)

## Goal
Implement the `ODAD_Subscriber_Schema_Init` subscriber that wires all adapters into
the resolver and fires the `ODAD_register_entity_sets` WP action for external plugins.
Also finalize `ODAD_Subscriber_Schema_Changed` (started in Task 1.5).

---

## Schema Init Flow (from master plan Section 8)

```
WP 'init' fires
  → ODAD_Hook_Bridge::on_wp_init()
      → dispatch(ODAD_Event_WP_Init)
          → ODAD_Subscriber_Schema_Init::handle()
              → do_action('ODAD_register_entity_sets', $registry)   ← external plugins
              → dispatch(ODAD_Event_Schema_Register)
                  → registers built-in adapters into ODAD_Adapter_Resolver
                  → populates ODAD_Schema_Registry from registered adapters
```

---

## File: `src/hooks/subscribers/class-odad-subscriber-schema-init.php`

```php
class ODAD_Subscriber_Schema_Init implements ODAD_Event_Listener {

    public function __construct(
        private ODAD_Schema_Registry  $registry,
        private ODAD_Adapter_Resolver $resolver,
        private ODAD_Hook_Bridge      $bridge,
        private ODAD_Event_Bus        $event_bus,
    ) {}

    public function get_event(): string {
        return ODAD_Event_WP_Init::class;
    }

    public function handle( ODAD_Event $event ): void {
        // 1. Let external plugins register their entity sets first
        $this->bridge->action( 'ODAD_register_entity_sets', [ $this->registry ] );

        // 2. Dispatch the internal schema register event to wire built-in adapters
        $schema_event = new ODAD_Event_Schema_Register( $this->registry );
        $this->event_bus->dispatch( $schema_event );

        // 3. Register built-in adapters
        $this->register_builtin_adapters();

        // 4. Discover and register CPTs + custom taxonomies
        $this->register_discovered_adapters();

        // 5. Populate schema registry from all registered adapters
        foreach ( $this->resolver->registered_entity_sets() as $entity_set ) {
            $adapter = $this->resolver->resolve( $entity_set );
            if ( ! $this->registry->has( $entity_set ) ) {
                $this->registry->register( $entity_set, $adapter->get_entity_type_definition() );
            }
        }

        // 6. Signal that schema has been fully initialized (triggers metadata cache bust)
        $this->event_bus->dispatch( new ODAD_Event_Schema_Changed(
            reason:     'entity_registered',
            entity_set: '*',
        ) );
    }

    private function register_builtin_adapters(): void {
        // Adapters are pre-built in bootstrapper; resolve them here and register
        // into the adapter resolver with their entity set names.
        // The exact pattern is: $this->resolver->register( $adapter->get_entity_set_name(), $adapter )
        // Bootstrapper provides the concrete adapter instances via the container.
        // This method receives them through constructor injection or a builder pattern.
        // See Bootstrapper Update section below.
    }

    private function register_discovered_adapters(): void {
        // Auto-discover CPTs and custom taxonomies
        foreach ( ODAD_Adapter_CPT::discover_all() as $entity_set => $adapter ) {
            if ( ! $this->resolver->has( $entity_set ) ) {
                $this->resolver->register( $entity_set, $adapter );
            }
        }

        foreach ( ODAD_Adapter_Taxonomy::discover_all() as $entity_set => $adapter ) {
            if ( ! $this->resolver->has( $entity_set ) ) {
                $this->resolver->register( $entity_set, $adapter );
            }
        }
    }
}
```

---

## External Plugin Registration

External plugins register custom entity sets via the `ODAD_register_entity_sets` action:

```php
// Example from another plugin:
add_action( 'ODAD_register_entity_sets', function( ODAD_Schema_Registry $registry ) {
    // Register a custom table adapter
    $adapter = new ODAD_Adapter_Custom_Table( 'employees', 'Employees', 'id' );
    $registry->register( 'Employees', $adapter->get_entity_type_definition() );
    // Note: The adapter itself also needs to be registered with the resolver.
    // This is done via a companion filter or a second argument.
} );
```

**Design decision:** The `ODAD_register_entity_sets` action should receive BOTH
the schema registry AND the adapter resolver so external plugins can register adapters:

```php
$this->bridge->action( 'ODAD_register_entity_sets', [ $this->registry, $this->resolver ] );
```

Update the hook signature documentation accordingly.

---

## Bootstrapper Update

Update `ODAD_Bootstrapper::register_subscribers()` to provide all built-in adapters
to `ODAD_Subscriber_Schema_Init`. The subscriber needs the adapter instances.
Use one of these patterns:

**Option A** — Pass adapters array directly:
```php
new ODAD_Subscriber_Schema_Init(
    $c->get(ODAD_Schema_Registry::class),
    $c->get(ODAD_Adapter_Resolver::class),
    $c->get(ODAD_Hook_Bridge::class),
    $c->get(ODAD_Event_Bus::class),
    // Built-in adapters:
    [
        $c->get(ODAD_Adapter_WP_Posts::class . '.posts'),
        $c->get(ODAD_Adapter_WP_Posts::class . '.pages'),
        $c->get(ODAD_Adapter_WP_Posts::class . '.attachments'),
        $c->get(ODAD_Adapter_WP_Users::class),
        $c->get(ODAD_Adapter_WP_Terms::class . '.categories'),
        $c->get(ODAD_Adapter_WP_Terms::class . '.tags'),
    ]
)
```

**Option B** — Register directly in bootstrapper and pass only resolver:
Register adapters into the resolver inside `ODAD_Bootstrapper::build()` before
any subscribers are registered. Then `ODAD_Subscriber_Schema_Init` only needs
to call `register_discovered_adapters()` for CPTs/taxonomies.

Choose whichever pattern is cleaner; document your choice clearly.

---

## Acceptance Criteria

- `ODAD_register_entity_sets` action fires with `ODAD_Schema_Registry` (and optionally `ODAD_Adapter_Resolver`) as arguments.
- After the `init` hook fires, `Posts`, `Pages`, `Users`, `Categories`, `Tags`, `Attachments` are all registered in `ODAD_Adapter_Resolver`.
- Any public CPT registered via `register_post_type()` before `init` is auto-discovered and registered.
- Any public custom taxonomy registered before `init` is auto-discovered and registered.
- `ODAD_Schema_Registry` is populated for all registered entity sets.
- `ODAD_Event_Schema_Changed` is dispatched once after full initialization, which busts the metadata cache transients.
- External plugin can register a custom entity set via `ODAD_register_entity_sets` and have it appear in `$metadata` output.
