<?php
defined( 'ABSPATH' ) || exit;

class WPOS_Bootstrapper {

    public static function build(): WPOS_Container {
        $container = new WPOS_Container();

        $container->singleton( WPOS_Event_Bus::class, function ( WPOS_Container $c ): WPOS_Event_Bus {
            return new WPOS_Event_Bus();
        } );

        $container->singleton( WPOS_Hook_Bridge::class, function ( WPOS_Container $c ): WPOS_Hook_Bridge {
            return new WPOS_Hook_Bridge( $c->get( WPOS_Event_Bus::class ) );
        } );

        $container->singleton( WPOS_Schema_Registry::class, function ( WPOS_Container $c ): WPOS_Schema_Registry {
            return new WPOS_Schema_Registry();
        } );

        $container->singleton( WPOS_Metadata_Cache::class, function ( WPOS_Container $c ): WPOS_Metadata_Cache {
            return new WPOS_Metadata_Cache();
        } );

        $container->singleton( WPOS_Metadata_Builder::class, function ( WPOS_Container $c ): WPOS_Metadata_Builder {
            return new WPOS_Metadata_Builder(
                $c->get( WPOS_Schema_Registry::class ),
                $c->get( WPOS_Metadata_Cache::class ),
                $c->get( WPOS_Event_Bus::class ),
                $c->get( WPOS_Hook_Bridge::class ),
                $c->get( WPOS_Function_Registry::class ),
                $c->get( WPOS_Action_Registry::class ),
            );
        } );

        $container->singleton( WPOS_Adapter_Resolver::class, fn() => new WPOS_Adapter_Resolver() );

        $container->singleton( 'adapter.posts',       fn() => new WPOS_Adapter_WP_Posts( 'post',       'Posts' ) );
        $container->singleton( 'adapter.pages',       fn() => new WPOS_Adapter_WP_Posts( 'page',       'Pages' ) );
        $container->singleton( 'adapter.attachments', fn() => new WPOS_Adapter_WP_Posts( 'attachment', 'Attachments' ) );

        $container->singleton( WPOS_Adapter_WP_Users::class, fn() => new WPOS_Adapter_WP_Users() );

        $container->singleton( 'adapter.categories', fn() => new WPOS_Adapter_WP_Terms( 'category', 'Categories' ) );
        $container->singleton( 'adapter.tags',       fn() => new WPOS_Adapter_WP_Terms( 'post_tag',  'Tags' ) );

        // ── Permission layer (Phase 4) ────────────────────────────────────
        $container->singleton( WPOS_Capability_Map::class, fn() => new WPOS_Capability_Map() );

        $container->singleton( WPOS_Permission_Engine::class, fn( WPOS_Container $c ) => new WPOS_Permission_Engine(
            $c->get( WPOS_Capability_Map::class ),
        ) );

        $container->singleton( WPOS_Field_ACL::class, fn( WPOS_Container $c ) => new WPOS_Field_ACL(
            $c->get( WPOS_Permission_Engine::class ),
            $c->get( WPOS_Schema_Registry::class ),
        ) );

        // ── Query compilers (Phase 3) ──────────────────────────────────────
        $container->singleton( WPOS_Filter_Parser::class,    fn() => new WPOS_Filter_Parser() );
        $container->singleton( WPOS_Filter_Compiler::class,  fn() => new WPOS_Filter_Compiler() );
        $container->singleton( WPOS_Select_Compiler::class,  fn() => new WPOS_Select_Compiler() );
        $container->singleton( WPOS_Expand_Compiler::class,  fn( WPOS_Container $c ) => new WPOS_Expand_Compiler(
            $c->get( WPOS_Adapter_Resolver::class )
        ) );
        $container->singleton( WPOS_Compute_Compiler::class,  fn() => new WPOS_Compute_Compiler() );
        $container->singleton( WPOS_Orderby_Compiler::class,  fn() => new WPOS_Orderby_Compiler() );
        $container->singleton( WPOS_Search_Compiler::class,   fn() => new WPOS_Search_Compiler() );

        // ── Query engine (Phase 3) ─────────────────────────────────────────
        $container->singleton( WPOS_Query_Engine::class, fn( WPOS_Container $c ) => new WPOS_Query_Engine(
            $c->get( WPOS_Filter_Parser::class ),
            $c->get( WPOS_Filter_Compiler::class ),
            $c->get( WPOS_Select_Compiler::class ),
            $c->get( WPOS_Expand_Compiler::class ),
            $c->get( WPOS_Compute_Compiler::class ),
            $c->get( WPOS_Orderby_Compiler::class ),
            $c->get( WPOS_Search_Compiler::class ),
            $c->get( WPOS_Adapter_Resolver::class ),
            $c->get( WPOS_Event_Bus::class ),
        ) );

        // ── Function & Action registries (Phase 5.4) ─────────────────────
        $container->singleton( WPOS_Function_Registry::class, fn() => new WPOS_Function_Registry() );
        $container->singleton( WPOS_Action_Registry::class,   fn() => new WPOS_Action_Registry() );

        // ── Write layer (Phase 5) ─────────────────────────────────────────
        $container->singleton( WPOS_Deep_Insert::class, fn( WPOS_Container $c ) => new WPOS_Deep_Insert(
            $c->get( WPOS_Adapter_Resolver::class ),
            $c->get( WPOS_Schema_Registry::class ),
            $c->get( WPOS_Event_Bus::class ),
        ) );

        $container->singleton( WPOS_Deep_Update::class, fn( WPOS_Container $c ) => new WPOS_Deep_Update(
            $c->get( WPOS_Adapter_Resolver::class ),
            $c->get( WPOS_Schema_Registry::class ),
            $c->get( WPOS_Event_Bus::class ),
        ) );

        $container->singleton( WPOS_Set_Operations::class, fn( WPOS_Container $c ) => new WPOS_Set_Operations(
            $c->get( WPOS_Adapter_Resolver::class ),
            $c->get( WPOS_Filter_Parser::class ),
            $c->get( WPOS_Filter_Compiler::class ),
            $c->get( WPOS_Event_Bus::class ),
        ) );

        $container->singleton( WPOS_Write_Handler::class, fn( WPOS_Container $c ) => new WPOS_Write_Handler(
            $c->get( WPOS_Adapter_Resolver::class ),
            $c->get( WPOS_Schema_Registry::class ),
            $c->get( WPOS_Deep_Insert::class ),
            $c->get( WPOS_Deep_Update::class ),
            $c->get( WPOS_Set_Operations::class ),
            $c->get( WPOS_Event_Bus::class ),
        ) );

        // ── Admin UI (Phase 6) ────────────────────────────────────────────
        $container->singleton( WPOS_Admin::class, fn( WPOS_Container $c ) => new WPOS_Admin(
            $c->get( WPOS_Schema_Registry::class ),
            $c->get( WPOS_Event_Bus::class ),
        ) );

        $container->singleton( WPOS_Admin_Entity_Config::class, fn( WPOS_Container $c ) => new WPOS_Admin_Entity_Config(
            $c->get( WPOS_Schema_Registry::class ),
            $c->get( WPOS_Event_Bus::class ),
        ) );

        $container->singleton( WPOS_Admin_Permission_Config::class, fn( WPOS_Container $c ) => new WPOS_Admin_Permission_Config(
            $c->get( WPOS_Schema_Registry::class ),
            $c->get( WPOS_Capability_Map::class ),
            $c->get( WPOS_Event_Bus::class ),
        ) );

        // ── Async handler (Phase 5.6) ─────────────────────────────────────
        $container->singleton( WPOS_Async_Handler::class, fn( WPOS_Container $c ) => new WPOS_Async_Handler(
            $c->get( WPOS_Query_Engine::class ),
        ) );

        // ── Batch handler (Phase 5.5) ─────────────────────────────────────
        // WPOS_Batch_Handler dispatches sub-requests through WPOS_Router, but
        // WPOS_Router also holds a reference to WPOS_Batch_Handler. The circular
        // dependency is broken by passing a lazy-resolving Closure to
        // WPOS_Batch_Handler. The Closure is invoked only on the first batch
        // sub-request, at which point the WPOS_Router singleton is already
        // fully constructed.
        $container->singleton( WPOS_Batch_Handler::class, fn( WPOS_Container $c ) => new WPOS_Batch_Handler(
            fn() => $c->get( WPOS_Router::class ),
            $c->get( WPOS_Permission_Engine::class ),
        ) );

        $container->singleton( WPOS_Router::class, function ( WPOS_Container $c ): WPOS_Router {
            return new WPOS_Router(
                query_engine:       $c->get( WPOS_Query_Engine::class ),
                write_handler:      $c->get( WPOS_Write_Handler::class ),
                metadata_builder:   $c->get( WPOS_Metadata_Builder::class ),
                permission_engine:  $c->get( WPOS_Permission_Engine::class ),
                bridge:             $c->get( WPOS_Hook_Bridge::class ),
                function_registry:  $c->get( WPOS_Function_Registry::class ),
                action_registry:    $c->get( WPOS_Action_Registry::class ),
                async_handler:      $c->get( WPOS_Async_Handler::class ),
                batch_handler:      $c->get( WPOS_Batch_Handler::class ),
            );
        } );

        self::register_subscribers( $container );

        return $container;
    }

    private static function register_subscribers( WPOS_Container $container ): void {
        $bus = $container->get( WPOS_Event_Bus::class );

        $bus->subscribe( new WPOS_Subscriber_Schema_Init(
            $container->get( WPOS_Schema_Registry::class ),
            $container->get( WPOS_Adapter_Resolver::class ),
            $container->get( WPOS_Hook_Bridge::class ),
            $container->get( WPOS_Event_Bus::class ),
            // Built-in adapters registered in declaration order:
            // Posts, Pages, Attachments, Users, Categories, Tags.
            [
                $container->get( 'adapter.posts' ),
                $container->get( 'adapter.pages' ),
                $container->get( 'adapter.attachments' ),
                $container->get( WPOS_Adapter_WP_Users::class ),
                $container->get( 'adapter.categories' ),
                $container->get( 'adapter.tags' ),
            ],
            $container->get( WPOS_Function_Registry::class ),
            $container->get( WPOS_Action_Registry::class ),
            $container->get( WPOS_Capability_Map::class ),
        ) );
        $bus->subscribe( new WPOS_Subscriber_Schema_Changed( $container->get( WPOS_Metadata_Cache::class ) ) );
        $bus->subscribe( new WPOS_Subscriber_Rest_Init( $container->get( WPOS_Router::class ) ) );
        $bus->subscribe( new WPOS_Subscriber_Permission_Check(
            $container->get( WPOS_Permission_Engine::class ),
            $container->get( WPOS_Hook_Bridge::class ),
        ) );
        $bus->subscribe( new WPOS_Subscriber_Query_Before(
            $container->get( WPOS_Permission_Engine::class ),
            $container->get( WPOS_Hook_Bridge::class ),
        ) );
        $bus->subscribe( new WPOS_Subscriber_Query_After(
            $container->get( WPOS_Field_ACL::class ),
            $container->get( WPOS_Hook_Bridge::class ),
        ) );
        $bus->subscribe( new WPOS_Subscriber_Write_Before(
            $container->get( WPOS_Permission_Engine::class ),
            $container->get( WPOS_Field_ACL::class ),
            $container->get( WPOS_Hook_Bridge::class ),
        ) );
        $bus->subscribe( new WPOS_Subscriber_Write_After(
            $container->get( WPOS_Hook_Bridge::class ),
        ) );
        $deep_insert_sub = new WPOS_Subscriber_Deep_Insert(
            $container->get( WPOS_Permission_Engine::class ),
            $container->get( WPOS_Hook_Bridge::class ),
        );
        $bus->subscribe( $deep_insert_sub );
        // Also register for nested and after events.
        foreach ( [
            WPOS_Event_Deep_Insert_Nested_Before::class,
            WPOS_Event_Deep_Insert_After::class,
        ] as $extra_event ) {
            $bus->subscribe( new class( $deep_insert_sub, $extra_event ) implements WPOS_Event_Listener {
                public function __construct(
                    private WPOS_Subscriber_Deep_Insert $inner,
                    private string $event_class
                ) {}
                public function get_event(): string { return $this->event_class; }
                public function handle( WPOS_Event $event ): void { $this->inner->handle( $event ); }
            } );
        }

        $deep_update_sub = new WPOS_Subscriber_Deep_Update(
            $container->get( WPOS_Permission_Engine::class ),
            $container->get( WPOS_Hook_Bridge::class ),
        );
        $bus->subscribe( $deep_update_sub );
        foreach ( [
            WPOS_Event_Deep_Update_Nested_Before::class,
            WPOS_Event_Deep_Update_After::class,
        ] as $extra_event ) {
            $bus->subscribe( new class( $deep_update_sub, $extra_event ) implements WPOS_Event_Listener {
                public function __construct(
                    private WPOS_Subscriber_Deep_Update $inner,
                    private string $event_class
                ) {}
                public function get_event(): string { return $this->event_class; }
                public function handle( WPOS_Event $event ): void { $this->inner->handle( $event ); }
            } );
        }

        $set_op_sub = new WPOS_Subscriber_Set_Operation(
            $container->get( WPOS_Permission_Engine::class ),
            $container->get( WPOS_Hook_Bridge::class ),
        );
        $bus->subscribe( $set_op_sub );
        $bus->subscribe( new class( $set_op_sub ) implements WPOS_Event_Listener {
            public function __construct( private WPOS_Subscriber_Set_Operation $inner ) {}
            public function get_event(): string { return WPOS_Event_Set_Operation_After::class; }
            public function handle( WPOS_Event $event ): void { $this->inner->handle( $event ); }
        } );
        $bus->subscribe( new WPOS_Subscriber_Metadata_Build() );
        $bus->subscribe( new WPOS_Subscriber_Admin_Config_Saved(
            $container->get( WPOS_Hook_Bridge::class ),
            $container->get( WPOS_Event_Bus::class ),
        ) );
        $bus->subscribe( new WPOS_Subscriber_Admin_Permission_Saved(
            $container->get( WPOS_Hook_Bridge::class ),
            $container->get( WPOS_Event_Bus::class ),
        ) );
    }
}
