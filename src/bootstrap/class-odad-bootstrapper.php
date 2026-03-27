<?php
defined( 'ABSPATH' ) || exit;

class ODAD_Bootstrapper {

    public static function build(): ODAD_Container {
        $container = new ODAD_Container();

        $container->singleton( ODAD_Event_Bus::class, function ( ODAD_Container $c ): ODAD_Event_Bus {
            return new ODAD_Event_Bus();
        } );

        $container->singleton( ODAD_Hook_Bridge::class, function ( ODAD_Container $c ): ODAD_Hook_Bridge {
            return new ODAD_Hook_Bridge( $c->get( ODAD_Event_Bus::class ) );
        } );

        $container->singleton( ODAD_Schema_Registry::class, function ( ODAD_Container $c ): ODAD_Schema_Registry {
            return new ODAD_Schema_Registry();
        } );

        $container->singleton( ODAD_Metadata_Cache::class, function ( ODAD_Container $c ): ODAD_Metadata_Cache {
            return new ODAD_Metadata_Cache();
        } );

        $container->singleton( ODAD_Metadata_Builder::class, function ( ODAD_Container $c ): ODAD_Metadata_Builder {
            return new ODAD_Metadata_Builder(
                $c->get( ODAD_Schema_Registry::class ),
                $c->get( ODAD_Metadata_Cache::class ),
                $c->get( ODAD_Event_Bus::class ),
                $c->get( ODAD_Hook_Bridge::class ),
                $c->get( ODAD_Function_Registry::class ),
                $c->get( ODAD_Action_Registry::class ),
            );
        } );

        $container->singleton( ODAD_Adapter_Resolver::class, fn() => new ODAD_Adapter_Resolver() );

        $container->singleton( 'adapter.posts',       fn() => new ODAD_Adapter_WP_Posts( 'post',       'Posts' ) );
        $container->singleton( 'adapter.pages',       fn() => new ODAD_Adapter_WP_Posts( 'page',       'Pages' ) );
        $container->singleton( 'adapter.attachments', fn() => new ODAD_Adapter_WP_Posts( 'attachment', 'Attachments' ) );

        $container->singleton( ODAD_Adapter_WP_Users::class, fn() => new ODAD_Adapter_WP_Users() );

        $container->singleton( 'adapter.categories', fn() => new ODAD_Adapter_WP_Terms( 'category', 'Categories' ) );
        $container->singleton( 'adapter.tags',       fn() => new ODAD_Adapter_WP_Terms( 'post_tag',  'Tags' ) );

        // ── Permission layer (Phase 4) ────────────────────────────────────
        $container->singleton( ODAD_Capability_Map::class, fn() => new ODAD_Capability_Map() );

        $container->singleton( ODAD_Permission_Engine::class, fn( ODAD_Container $c ) => new ODAD_Permission_Engine(
            $c->get( ODAD_Capability_Map::class ),
        ) );

        $container->singleton( ODAD_Field_ACL::class, fn( ODAD_Container $c ) => new ODAD_Field_ACL(
            $c->get( ODAD_Permission_Engine::class ),
            $c->get( ODAD_Schema_Registry::class ),
        ) );

        // ── Query compilers (Phase 3) ──────────────────────────────────────
        $container->singleton( ODAD_Filter_Parser::class,    fn() => new ODAD_Filter_Parser() );
        $container->singleton( ODAD_Filter_Compiler::class,  fn() => new ODAD_Filter_Compiler() );
        $container->singleton( ODAD_Select_Compiler::class,  fn() => new ODAD_Select_Compiler() );
        $container->singleton( ODAD_Expand_Compiler::class,  fn( ODAD_Container $c ) => new ODAD_Expand_Compiler(
            $c->get( ODAD_Adapter_Resolver::class )
        ) );
        $container->singleton( ODAD_Compute_Compiler::class,  fn() => new ODAD_Compute_Compiler() );
        $container->singleton( ODAD_Orderby_Compiler::class,  fn() => new ODAD_Orderby_Compiler() );
        $container->singleton( ODAD_Search_Compiler::class,   fn() => new ODAD_Search_Compiler() );

        // ── Query engine (Phase 3) ─────────────────────────────────────────
        $container->singleton( ODAD_Query_Engine::class, fn( ODAD_Container $c ) => new ODAD_Query_Engine(
            $c->get( ODAD_Filter_Parser::class ),
            $c->get( ODAD_Filter_Compiler::class ),
            $c->get( ODAD_Select_Compiler::class ),
            $c->get( ODAD_Expand_Compiler::class ),
            $c->get( ODAD_Compute_Compiler::class ),
            $c->get( ODAD_Orderby_Compiler::class ),
            $c->get( ODAD_Search_Compiler::class ),
            $c->get( ODAD_Adapter_Resolver::class ),
            $c->get( ODAD_Event_Bus::class ),
        ) );

        // ── Function & Action registries (Phase 5.4) ─────────────────────
        $container->singleton( ODAD_Function_Registry::class, fn() => new ODAD_Function_Registry() );
        $container->singleton( ODAD_Action_Registry::class,   fn() => new ODAD_Action_Registry() );

        // ── Write layer (Phase 5) ─────────────────────────────────────────
        $container->singleton( ODAD_Deep_Insert::class, fn( ODAD_Container $c ) => new ODAD_Deep_Insert(
            $c->get( ODAD_Adapter_Resolver::class ),
            $c->get( ODAD_Schema_Registry::class ),
            $c->get( ODAD_Event_Bus::class ),
        ) );

        $container->singleton( ODAD_Deep_Update::class, fn( ODAD_Container $c ) => new ODAD_Deep_Update(
            $c->get( ODAD_Adapter_Resolver::class ),
            $c->get( ODAD_Schema_Registry::class ),
            $c->get( ODAD_Event_Bus::class ),
        ) );

        $container->singleton( ODAD_Set_Operations::class, fn( ODAD_Container $c ) => new ODAD_Set_Operations(
            $c->get( ODAD_Adapter_Resolver::class ),
            $c->get( ODAD_Filter_Parser::class ),
            $c->get( ODAD_Filter_Compiler::class ),
            $c->get( ODAD_Event_Bus::class ),
        ) );

        $container->singleton( ODAD_Write_Handler::class, fn( ODAD_Container $c ) => new ODAD_Write_Handler(
            $c->get( ODAD_Adapter_Resolver::class ),
            $c->get( ODAD_Schema_Registry::class ),
            $c->get( ODAD_Deep_Insert::class ),
            $c->get( ODAD_Deep_Update::class ),
            $c->get( ODAD_Set_Operations::class ),
            $c->get( ODAD_Event_Bus::class ),
        ) );

        // ── Admin UI (Phase 6) ────────────────────────────────────────────
        $container->singleton( ODAD_Admin::class, fn( ODAD_Container $c ) => new ODAD_Admin(
            $c->get( ODAD_Schema_Registry::class ),
            $c->get( ODAD_Event_Bus::class ),
        ) );

        $container->singleton( ODAD_Admin_Entity_Config::class, fn( ODAD_Container $c ) => new ODAD_Admin_Entity_Config(
            $c->get( ODAD_Schema_Registry::class ),
            $c->get( ODAD_Event_Bus::class ),
        ) );

        $container->singleton( ODAD_Admin_Permission_Config::class, fn( ODAD_Container $c ) => new ODAD_Admin_Permission_Config(
            $c->get( ODAD_Schema_Registry::class ),
            $c->get( ODAD_Capability_Map::class ),
            $c->get( ODAD_Event_Bus::class ),
        ) );

        // ── Async handler (Phase 5.6) ─────────────────────────────────────
        $container->singleton( ODAD_Async_Handler::class, fn( ODAD_Container $c ) => new ODAD_Async_Handler(
            $c->get( ODAD_Query_Engine::class ),
        ) );

        // ── Batch handler (Phase 5.5) ─────────────────────────────────────
        // ODAD_Batch_Handler dispatches sub-requests through ODAD_Router, but
        // ODAD_Router also holds a reference to ODAD_Batch_Handler. The circular
        // dependency is broken by passing a lazy-resolving Closure to
        // ODAD_Batch_Handler. The Closure is invoked only on the first batch
        // sub-request, at which point the ODAD_Router singleton is already
        // fully constructed.
        $container->singleton( ODAD_Batch_Handler::class, fn( ODAD_Container $c ) => new ODAD_Batch_Handler(
            fn() => $c->get( ODAD_Router::class ),
            $c->get( ODAD_Permission_Engine::class ),
        ) );

        $container->singleton( ODAD_Router::class, function ( ODAD_Container $c ): ODAD_Router {
            return new ODAD_Router(
                query_engine:       $c->get( ODAD_Query_Engine::class ),
                write_handler:      $c->get( ODAD_Write_Handler::class ),
                metadata_builder:   $c->get( ODAD_Metadata_Builder::class ),
                permission_engine:  $c->get( ODAD_Permission_Engine::class ),
                bridge:             $c->get( ODAD_Hook_Bridge::class ),
                function_registry:  $c->get( ODAD_Function_Registry::class ),
                action_registry:    $c->get( ODAD_Action_Registry::class ),
                async_handler:      $c->get( ODAD_Async_Handler::class ),
                batch_handler:      $c->get( ODAD_Batch_Handler::class ),
            );
        } );

        self::register_subscribers( $container );

        return $container;
    }

    private static function register_subscribers( ODAD_Container $container ): void {
        $bus = $container->get( ODAD_Event_Bus::class );

        $bus->subscribe( new ODAD_Subscriber_Schema_Init(
            $container->get( ODAD_Schema_Registry::class ),
            $container->get( ODAD_Adapter_Resolver::class ),
            $container->get( ODAD_Hook_Bridge::class ),
            $container->get( ODAD_Event_Bus::class ),
            // Built-in adapters registered in declaration order:
            // Posts, Pages, Attachments, Users, Categories, Tags.
            [
                $container->get( 'adapter.posts' ),
                $container->get( 'adapter.pages' ),
                $container->get( 'adapter.attachments' ),
                $container->get( ODAD_Adapter_WP_Users::class ),
                $container->get( 'adapter.categories' ),
                $container->get( 'adapter.tags' ),
            ],
            $container->get( ODAD_Function_Registry::class ),
            $container->get( ODAD_Action_Registry::class ),
            $container->get( ODAD_Capability_Map::class ),
        ) );
        $bus->subscribe( new ODAD_Subscriber_Schema_Changed( $container->get( ODAD_Metadata_Cache::class ) ) );
        $bus->subscribe( new ODAD_Subscriber_Rest_Init( $container->get( ODAD_Router::class ) ) );
        $bus->subscribe( new ODAD_Subscriber_Permission_Check(
            $container->get( ODAD_Permission_Engine::class ),
            $container->get( ODAD_Hook_Bridge::class ),
        ) );
        $bus->subscribe( new ODAD_Subscriber_Query_Before(
            $container->get( ODAD_Permission_Engine::class ),
            $container->get( ODAD_Hook_Bridge::class ),
        ) );
        $bus->subscribe( new ODAD_Subscriber_Query_After(
            $container->get( ODAD_Field_ACL::class ),
            $container->get( ODAD_Hook_Bridge::class ),
        ) );
        $bus->subscribe( new ODAD_Subscriber_Write_Before(
            $container->get( ODAD_Permission_Engine::class ),
            $container->get( ODAD_Field_ACL::class ),
            $container->get( ODAD_Hook_Bridge::class ),
        ) );
        $bus->subscribe( new ODAD_Subscriber_Write_After(
            $container->get( ODAD_Hook_Bridge::class ),
        ) );
        $deep_insert_sub = new ODAD_Subscriber_Deep_Insert(
            $container->get( ODAD_Permission_Engine::class ),
            $container->get( ODAD_Hook_Bridge::class ),
        );
        $bus->subscribe( $deep_insert_sub );
        // Also register for nested and after events.
        foreach ( [
            ODAD_Event_Deep_Insert_Nested_Before::class,
            ODAD_Event_Deep_Insert_After::class,
        ] as $extra_event ) {
            $bus->subscribe( new class( $deep_insert_sub, $extra_event ) implements ODAD_Event_Listener {
                public function __construct(
                    private ODAD_Subscriber_Deep_Insert $inner,
                    private string $event_class
                ) {}
                public function get_event(): string { return $this->event_class; }
                public function handle( ODAD_Event $event ): void { $this->inner->handle( $event ); }
            } );
        }

        $deep_update_sub = new ODAD_Subscriber_Deep_Update(
            $container->get( ODAD_Permission_Engine::class ),
            $container->get( ODAD_Hook_Bridge::class ),
        );
        $bus->subscribe( $deep_update_sub );
        foreach ( [
            ODAD_Event_Deep_Update_Nested_Before::class,
            ODAD_Event_Deep_Update_After::class,
        ] as $extra_event ) {
            $bus->subscribe( new class( $deep_update_sub, $extra_event ) implements ODAD_Event_Listener {
                public function __construct(
                    private ODAD_Subscriber_Deep_Update $inner,
                    private string $event_class
                ) {}
                public function get_event(): string { return $this->event_class; }
                public function handle( ODAD_Event $event ): void { $this->inner->handle( $event ); }
            } );
        }

        $set_op_sub = new ODAD_Subscriber_Set_Operation(
            $container->get( ODAD_Permission_Engine::class ),
            $container->get( ODAD_Hook_Bridge::class ),
        );
        $bus->subscribe( $set_op_sub );
        $bus->subscribe( new class( $set_op_sub ) implements ODAD_Event_Listener {
            public function __construct( private ODAD_Subscriber_Set_Operation $inner ) {}
            public function get_event(): string { return ODAD_Event_Set_Operation_After::class; }
            public function handle( ODAD_Event $event ): void { $this->inner->handle( $event ); }
        } );
        $bus->subscribe( new ODAD_Subscriber_Metadata_Build() );
        $bus->subscribe( new ODAD_Subscriber_Admin_Config_Saved(
            $container->get( ODAD_Hook_Bridge::class ),
            $container->get( ODAD_Event_Bus::class ),
        ) );
        $bus->subscribe( new ODAD_Subscriber_Admin_Permission_Saved(
            $container->get( ODAD_Hook_Bridge::class ),
            $container->get( ODAD_Event_Bus::class ),
        ) );
    }
}
