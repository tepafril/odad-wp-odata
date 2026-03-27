<?php
/**
 * Subscriber: REST Init — registers OData REST routes on rest_api_init.
 *
 * Listens for ODAD_Event_REST_Init (dispatched by ODAD_Hook_Bridge::on_rest_api_init)
 * and calls ODAD_Router::register_routes() so all OData endpoints are available.
 *
 * @package ODAD
 */

defined( 'ABSPATH' ) || exit;

class ODAD_Subscriber_Rest_Init implements ODAD_Event_Listener {

    /**
     * @param ODAD_Router $router The router singleton whose routes to register.
     */
    public function __construct( private readonly ODAD_Router $router ) {}

    public function get_event(): string {
        return ODAD_Event_REST_Init::class;
    }

    public function handle( ODAD_Event $event ): void {
        $this->router->register_routes();
    }
}
