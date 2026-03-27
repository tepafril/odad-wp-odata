<?php
/**
 * ODAD_Field_ACL — strips or validates entity properties based on user permissions.
 *
 * Responsibilities:
 *   - Strip unreadable properties from query result rows (apply).
 *   - Compute the set of properties a user may access for a given operation (get_allowed_properties).
 *   - Reject write payloads that contain read-only or capability-gated fields (validate_write).
 *
 * No WordPress hooks are fired inside this class; hook wrappers live in the subscriber layer.
 *
 * @package WPOS
 */

defined( 'ABSPATH' ) || exit;

class ODAD_Field_ACL {

    /**
     * Properties that can never be written via the API, regardless of user capability.
     *
     * This list matches the task spec:
     *   - ID            : always the entity key; cannot be changed after creation.
     *   - CommentCount  : computed by WordPress on Post entities.
     *   - Count         : managed internally by WordPress on Term entities.
     *   - RegisteredDate: set at User creation; not updatable via the API.
     */
    private const ALWAYS_READ_ONLY = [ 'ID', 'CommentCount', 'Count', 'RegisteredDate' ];

    /**
     * @param ODAD_Permission_Engine|null $permission_engine  Row-level permission engine (injected
     *                                                        once Task 4.1 is complete; nullable
     *                                                        until then).
     * @param ODAD_Schema_Registry|null   $schema_registry    Registry that holds property
     *                                                        definitions including required_capability
     *                                                        and read_only flags.
     */
    public function __construct(
        private readonly ?ODAD_Permission_Engine $permission_engine = null,
        private readonly ?ODAD_Schema_Registry $schema_registry = null,
    ) {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Strip properties from result rows that the current user cannot read.
     *
     * @param array[]  $rows        Array of entity-row arrays.
     * @param string   $entity_set  Entity-set name, e.g. 'Posts'.
     * @param \WP_User $user        Current user.
     * @param string   $operation   Intended operation, typically 'read'.
     * @return array[] Filtered rows — each row contains only allowed properties.
     */
    public function apply( array $rows, string $entity_set, \WP_User $user, string $operation ): array {
        $allowed = $this->get_allowed_properties( $entity_set, $user, $operation );

        // Build a fast lookup set.
        $allowed_map = array_flip( $allowed );

        return array_map(
            static function ( array $row ) use ( $allowed_map ): array {
                return array_intersect_key( $row, $allowed_map );
            },
            $rows
        );
    }

    /**
     * Return the list of property names the user is allowed to access.
     *
     * Algorithm:
     *   1. Start with every property defined for the entity set.
     *   2. Remove properties whose required_capability the user lacks.
     *   3. Always keep the key property (it must never be stripped from read results).
     *
     * Note: The ODAD_allowed_properties filter is intentionally NOT applied here.
     * That filter is applied by the subscriber layer so that hook calls remain
     * outside this class (per acceptance criterion "No WordPress hook calls in this file").
     *
     * @param string   $entity_set
     * @param \WP_User $user
     * @param string   $operation   'read' | 'insert' | 'update'
     * @return string[]
     */
    public function get_allowed_properties( string $entity_set, \WP_User $user, string $operation ): array {
        $definition   = $this->get_definition( $entity_set );
        $properties   = $definition['properties'] ?? [];
        $key_property = $definition['key_property'] ?? 'ID';

        $allowed = [];

        foreach ( $properties as $name => $meta ) {
            $required_cap = $meta['required_capability'] ?? null;

            if ( null !== $required_cap && ! $user->has_cap( $required_cap ) ) {
                // Skip — user lacks the required capability for this field.
                continue;
            }

            $allowed[] = $name;
        }

        // The key property must always be readable; add it if it somehow ended up
        // excluded (e.g. the definition omitted it, or a capability stripped it).
        if ( ! in_array( $key_property, $allowed, true ) ) {
            $allowed[] = $key_property;
        }

        return $allowed;
    }

    /**
     * Validate that a write payload only contains fields the user is permitted to write.
     *
     * Throws ODAD_Field_ACL_Exception when:
     *   - The payload includes a read-only field (ID, CommentCount, Count, RegisteredDate),
     *     OR any property marked 'read_only' => true in the entity definition.
     *   - The payload includes a field whose required_capability the user lacks.
     *
     * @param array    $payload     Entity data being written (key → value).
     * @param string   $entity_set
     * @param \WP_User $user
     * @param string   $operation   'insert' | 'update'
     * @throws ODAD_Field_ACL_Exception
     */
    public function validate_write( array $payload, string $entity_set, \WP_User $user, string $operation ): void {
        $definition  = $this->get_definition( $entity_set );
        $properties  = $definition['properties'] ?? [];
        $forbidden   = [];

        foreach ( array_keys( $payload ) as $field ) {
            // 1. Hard-coded always-read-only list.
            if ( in_array( $field, self::ALWAYS_READ_ONLY, true ) ) {
                $forbidden[] = $field;
                continue;
            }

            // 2. Definition-level read_only flag.
            $meta = $properties[ $field ] ?? [];
            if ( ! empty( $meta['read_only'] ) ) {
                $forbidden[] = $field;
                continue;
            }

            // 3. Capability gate.
            $required_cap = $meta['required_capability'] ?? null;
            if ( null !== $required_cap && ! $user->has_cap( $required_cap ) ) {
                $forbidden[] = $field;
            }
        }

        if ( ! empty( $forbidden ) ) {
            throw new ODAD_Field_ACL_Exception(
                sprintf(
                    'Write to %s rejected: forbidden field(s): %s.',
                    $entity_set,
                    implode( ', ', $forbidden )
                ),
                $entity_set,
                $forbidden
            );
        }
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Retrieve the entity-set definition from the schema registry, or return an
     * empty array if no registry is available or the set is not registered.
     *
     * @param string $entity_set
     * @return array
     */
    private function get_definition( string $entity_set ): array {
        if ( null === $this->schema_registry ) {
            return [];
        }

        return $this->schema_registry->get( $entity_set );
    }
}
