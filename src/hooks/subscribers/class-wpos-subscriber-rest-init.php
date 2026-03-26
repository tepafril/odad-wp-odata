<?php
/**
 * Subscriber: REST Init — registers OData REST routes on rest_api_init.
 *
 * Listens for WPOS_Event_REST_Init (dispatched by WPOS_Hook_Bridge::on_rest_api_init)
 * and calls WPOS_Router::register_routes() so all OData endpoints are available.
 *
 * @package WPOS
 */

defined( 'ABSPATH' ) || exit;

class WPOS_Subscriber_Rest_Init implements WPOS_Event_Listener {

    /**
     * @param WPOS_Router $router The router singleton whose routes to register.
     */
    public function __construct( private readonly WPOS_Router $router ) {}

    public function get_event(): string {
        return WPOS_Event_REST_Init::class;
    }

    public function handle( WPOS_Event $event ): void {
        $this->router->register_routes();
    }
}
