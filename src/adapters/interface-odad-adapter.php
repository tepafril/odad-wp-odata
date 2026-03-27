<?php
defined( 'ABSPATH' ) || exit;

interface ODAD_Adapter {

    // ── Reads ─────────────────────────────────────────────────────────────
    /** Return an array of entity rows matching the query context. */
    public function get_collection( ODAD_Query_Context $ctx ): array;

    /** Return a single entity row by key, or null if not found. */
    public function get_entity( mixed $key, ODAD_Query_Context $ctx ): ?array;

    /** Return the total count of rows matching $ctx (ignoring $top/$skip). */
    public function get_count( ODAD_Query_Context $ctx ): int;

    // ── Writes ────────────────────────────────────────────────────────────
    /** Insert a new entity. Returns the new key value. */
    public function insert( array $data ): mixed;

    /** Update an existing entity. Returns true on success. */
    public function update( mixed $key, array $data ): bool;

    /** Delete an entity. Returns true on success. */
    public function delete( mixed $key ): bool;

    // ── Schema ────────────────────────────────────────────────────────────
    /**
     * Return the entity type definition array for the schema registry.
     * Shape:
     * [
     *     'entity_type'    => 'PostEntityType',
     *     'key_property'   => 'ID',
     *     'properties'     => [ 'ID' => ['type'=>'Edm.Int32', 'nullable'=>false], ... ],
     *     'nav_properties' => [ 'Author' => ['type'=>'Users', 'collection'=>false], ... ],
     *     'adapter_class'  => static::class,
     * ]
     */
    public function get_entity_type_definition(): array;

    /** Return the OData entity set name (e.g. 'Posts', 'Users'). */
    public function get_entity_set_name(): string;
}
