<?php
/**
 * ODAD_Function_Registry — registry for OData bound and unbound functions.
 *
 * External plugins register callables during the 'ODAD_register_functions'
 * WP action.  The router dispatches GET requests to the registered handlers.
 *
 * Registration entry shape:
 *   [
 *     'name'        => 'NS.GetPublishedCount',
 *     'handler'     => callable,
 *     'binding'     => []  // unbound
 *                   | ['entity_set' => 'Posts']                               // bound to entity set
 *                   | ['entity_set' => 'Posts', 'bound_to' => 'entity']       // bound to single entity
 *     'parameters'  => [ ['name' => 'status', 'type' => 'Edm.String', 'required' => true], ... ],
 *     'return_type' => 'Edm.Int32',
 *   ]
 *
 * @package WPOS
 */

defined( 'ABSPATH' ) || exit;

class ODAD_Function_Registry {

    /** @var array<string, array> Registered functions keyed by qualified name. */
    private array $functions = [];

    // -------------------------------------------------------------------------
    // Registration
    // -------------------------------------------------------------------------

    /**
     * Register an OData function.
     *
     * @param string   $name        Qualified name, e.g. 'NS.GetPublishedCount'.
     * @param callable $handler     fn(array $params, ?WP_User $user): mixed
     * @param array    $binding     Empty = unbound.
     *                              ['entity_set' => 'Posts'] = bound to entity set.
     *                              ['entity_set' => 'Posts', 'bound_to' => 'entity'] = bound to single entity.
     * @param array    $parameters  Parameter definitions:
     *                              [ ['name' => 'status', 'type' => 'Edm.String', 'required' => true], ... ]
     * @param string   $return_type OData return type, e.g. 'Edm.Int32', 'Collection(WPOData.PostEntityType)'.
     */
    public function register(
        string   $name,
        callable $handler,
        array    $binding     = [],
        array    $parameters  = [],
        string   $return_type = 'Edm.String'
    ): void {
        $this->functions[ $name ] = [
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
     * Check whether a function with the given qualified name is registered.
     *
     * @param string $name Qualified function name.
     * @return bool
     */
    public function has( string $name ): bool {
        return isset( $this->functions[ $name ] );
    }

    /**
     * Retrieve the registration entry for a named function.
     *
     * Returns an empty array when the function is not registered; callers
     * should call has() first or guard on the result.
     *
     * @param string $name Qualified function name.
     * @return array Registration entry or empty array.
     */
    public function get( string $name ): array {
        return $this->functions[ $name ] ?? [];
    }

    /**
     * Return all registered function entries.
     *
     * @return array<string, array>
     */
    public function all(): array {
        return $this->functions;
    }
}
