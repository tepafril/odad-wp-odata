<?php
/**
 * ODAD_Action_Registry — registry for OData bound and unbound actions.
 *
 * External plugins register callables during the 'ODAD_register_actions'
 * WP action.  The router dispatches POST requests to the registered handlers.
 *
 * Registration entry shape:
 *   [
 *     'name'        => 'NS.SendNotification',
 *     'handler'     => callable,
 *     'binding'     => []  // unbound
 *                   | ['entity_set' => 'Posts']                               // bound to entity set
 *                   | ['entity_set' => 'Posts', 'bound_to' => 'entity']       // bound to single entity
 *     'parameters'  => [ ['name' => 'message', 'type' => 'Edm.String', 'required' => true], ... ],
 *     'return_type' => 'Edm.String',
 *   ]
 *
 * @package WPOS
 */

defined( 'ABSPATH' ) || exit;

class ODAD_Action_Registry {

    /** @var array<string, array> Registered actions keyed by qualified name. */
    private array $actions = [];

    // -------------------------------------------------------------------------
    // Registration
    // -------------------------------------------------------------------------

    /**
     * Register an OData action.
     *
     * @param string   $name        Qualified name, e.g. 'NS.SendNotification'.
     * @param callable $handler     fn(array $params, ?WP_User $user): mixed
     * @param array    $binding     Empty = unbound.
     *                              ['entity_set' => 'Posts'] = bound to entity set.
     *                              ['entity_set' => 'Posts', 'bound_to' => 'entity'] = bound to single entity.
     * @param array    $parameters  Parameter definitions:
     *                              [ ['name' => 'message', 'type' => 'Edm.String', 'required' => true], ... ]
     * @param string   $return_type OData return type, e.g. 'Edm.Boolean'.  Use '' or 'void' for no return value.
     */
    public function register(
        string   $name,
        callable $handler,
        array    $binding     = [],
        array    $parameters  = [],
        string   $return_type = 'Edm.String'
    ): void {
        $this->actions[ $name ] = [
            'name'        => $name,
            'handler'     => $handler,
            'binding'     => $binding,
            'parameters'  => $parameters,
            'return_type' => $return_type,
        ];
    }

    // -------------------------------------------------------------------------
    // Lookup
    // -------------------------------------------------------------------------

    /**
     * Check whether an action with the given qualified name is registered.
     *
     * @param string $name Qualified action name.
     * @return bool
     */
    public function has( string $name ): bool {
        return isset( $this->actions[ $name ] );
    }

    /**
     * Retrieve the registration entry for a named action.
     *
     * Returns an empty array when the action is not registered; callers
     * should call has() first or guard on the result.
     *
     * @param string $name Qualified action name.
     * @return array Registration entry or empty array.
     */
    public function get( string $name ): array {
        return $this->actions[ $name ] ?? [];
    }

    /**
     * Return all registered action entries.
     *
     * @return array<string, array>
     */
    public function all(): array {
        return $this->actions;
    }
}
