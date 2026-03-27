<?php
/**
 * Subscriber: WP Init — triggers schema initialisation.
 *
 * Listens for ODAD_Event_WP_Init (dispatched by ODAD_Hook_Bridge::on_wp_init)
 * and orchestrates the full schema init flow:
 *
 *   1. Fire 'ODAD_register_entity_sets' WP action so external plugins can
 *      register custom adapters and entity types.
 *   2. Register the built-in adapters (Posts, Pages, Attachments, Users,
 *      Categories, Tags) that were injected at construction time.
 *   3. Auto-discover CPTs and custom taxonomies registered before 'init'.
 *   4. Populate ODAD_Schema_Registry from every adapter now in the resolver.
 *   5. Dispatch ODAD_Event_Schema_Changed to bust the metadata cache.
 *
 * Design choice: Option A (built-in adapters passed as constructor array).
 * This keeps the subscriber self-contained and makes its dependencies
 * explicit without requiring a second registration pass in the bootstrapper.
 *
 * @package ODAD
 */

defined( 'ABSPATH' ) || exit;

class ODAD_Subscriber_Schema_Init implements ODAD_Event_Listener {

    /**
     * @param ODAD_Schema_Registry        $registry          Schema registry to populate.
     * @param ODAD_Adapter_Resolver       $resolver          Adapter resolver to register into.
     * @param ODAD_Hook_Bridge            $bridge            Hook bridge used to fire WP actions.
     * @param ODAD_Event_Bus              $event_bus         Internal event bus.
     * @param ODAD_Adapter[]              $builtin_adapters  Ordered list of built-in adapter
     *                                                       instances (Posts, Pages, Attachments,
     *                                                       Users, Categories, Tags).
     * @param ODAD_Function_Registry|null $function_registry OData function registry (Phase 5.4).
     * @param ODAD_Action_Registry|null   $action_registry   OData action registry (Phase 5.4).
     * @param ODAD_Capability_Map|null    $capability_map    Capability map for loading saved role overrides (Phase 6).
     */
    public function __construct(
        private ODAD_Schema_Registry        $registry,
        private ODAD_Adapter_Resolver       $resolver,
        private ODAD_Hook_Bridge            $bridge,
        private ODAD_Event_Bus              $event_bus,
        private array                       $builtin_adapters  = [],
        private ?ODAD_Function_Registry     $function_registry = null,
        private ?ODAD_Action_Registry       $action_registry   = null,
        private ?ODAD_Capability_Map        $capability_map    = null,
    ) {}

    public function get_event(): string {
        return ODAD_Event_WP_Init::class;
    }

    /**
     * Orchestrate the full schema init flow.
     *
     * @param ODAD_Event $event The dispatched ODAD_Event_WP_Init instance.
     */
    public function handle( ODAD_Event $event ): void {
        // 1. Let external plugins register their entity sets / adapters first.
        //    Both $registry and $resolver are passed so external code can do
        //    either $registry->register() or $resolver->register() as needed.
        $this->bridge->action( 'ODAD_register_entity_sets', [ $this->registry, $this->resolver ] );

        // 1b. Fire hooks so external plugins can register OData functions and actions.
        if ( null !== $this->function_registry ) {
            $this->bridge->action( 'ODAD_register_functions', [ $this->function_registry ] );
        }
        if ( null !== $this->action_registry ) {
            $this->bridge->action( 'ODAD_register_actions', [ $this->action_registry ] );
        }

        // 1c. Fire hook so external plugins can register entity-set capability rules.
        if ( null !== $this->capability_map ) {
            $this->bridge->action( 'ODAD_register_permissions', [ $this->capability_map ] );
        }

        // 2. Register built-in adapters injected at construction time.
        $this->register_builtin_adapters();

        // 3. Auto-discover public CPTs and custom taxonomies.
        $this->register_discovered_adapters();

        // 4. Populate schema registry from every adapter now in the resolver.
        foreach ( $this->resolver->registered_entity_sets() as $entity_set ) {
            if ( ! $this->registry->has( $entity_set ) ) {
                $adapter = $this->resolver->resolve( $entity_set );
                $this->registry->register( $entity_set, $adapter->get_entity_type_definition() );
            }
        }

        // 5. Load admin-saved role overrides into the capability map.
        $this->load_saved_permissions();

        // 6. Signal that schema is fully initialised — busts metadata cache transients.
        $this->event_bus->dispatch( new ODAD_Event_Schema_Changed(
            reason:     'entity_registered',
            entity_set: '*',
        ) );
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Register every built-in adapter injected via the constructor array into
     * the adapter resolver.  Adapters already registered (e.g. by an external
     * plugin that hooked 'ODAD_register_entity_sets' early) are skipped.
     */
    private function register_builtin_adapters(): void {
        foreach ( $this->builtin_adapters as $adapter ) {
            $entity_set = $adapter->get_entity_set_name();
            if ( ! $this->resolver->has( $entity_set ) ) {
                $this->resolver->register( $entity_set, $adapter );
            }
        }
    }

    /**
     * Load admin-saved role overrides from WP options into the capability map.
     */
    private function load_saved_permissions(): void {
        if ( null === $this->capability_map ) {
            return;
        }
        foreach ( $this->resolver->registered_entity_sets() as $entity_set ) {
            $saved = get_option( "ODAD_permissions_{$entity_set}", [] );
            if ( is_array( $saved ) && ! empty( $saved ) ) {
                $this->capability_map->register_role_overrides( $entity_set, $saved );
            }
        }
    }

    /**
     * Auto-discover public non-builtin CPTs and custom taxonomies that were
     * registered before 'init' fired, and add them to the resolver.
     */
    private function register_discovered_adapters(): void {
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
