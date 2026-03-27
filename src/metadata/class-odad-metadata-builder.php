<?php
/**
 * ODAD_Metadata_Builder — builds the OData $metadata document (CSDL).
 *
 * Checks the metadata cache before building. On a cache miss it:
 *   1. Dispatches ODAD_Event_Metadata_Build so internal listeners can inspect
 *      the entity types / sets before serialisation.
 *   2. Applies the ODAD_metadata_entity_types WP filter (via the Hook Bridge)
 *      so external plugins can inject or modify entity type definitions.
 *   3. Applies the ODAD_metadata_entity_sets WP filter (via the Hook Bridge)
 *      so external plugins can inject or modify entity set declarations.
 *   4. Serialises the result to CSDL XML (and separately to CSDL JSON).
 *   5. Stores both representations in the cache.
 *
 * The public get_xml() / get_json() methods are the canonical cache-aware API.
 * build_xml() / build_json() are kept as public aliases for backward
 * compatibility with ODAD_Router (Phase 1).
 *
 * @package ODAD
 */

defined( 'ABSPATH' ) || exit;

class ODAD_Metadata_Builder {

    /** OData / CSDL XML namespaces. */
    private const EDMX_NS = 'http://docs.oasis-open.org/odata/ns/edmx';
    private const EDM_NS  = 'http://docs.oasis-open.org/odata/ns/edm';

    /** OData version declared in the EDMX wrapper. */
    private const ODATA_VERSION = '4.01';

    /** Schema namespace used in generated CSDL. */
    private const SCHEMA_NS = 'WPOData';

    /** EntityContainer name. */
    private const CONTAINER_NAME = 'WPODataService';

    public function __construct(
        private ODAD_Schema_Registry       $registry,
        private ODAD_Metadata_Cache        $cache,
        private ODAD_Event_Bus             $event_bus,
        private ODAD_Hook_Bridge           $bridge,
        private ?ODAD_Function_Registry    $function_registry = null,
        private ?ODAD_Action_Registry      $action_registry   = null,
    ) {}

    // -------------------------------------------------------------------------
    // Public cache-aware API
    // -------------------------------------------------------------------------

    /**
     * Return the CSDL XML document, using the cache when available.
     *
     * @return string Well-formed CSDL XML.
     */
    public function get_xml(): string {
        $cached = $this->cache->get_xml();
        if ( null !== $cached ) {
            return $cached;
        }

        $xml = $this->do_build_xml();
        $this->cache->set_xml( $xml );
        return $xml;
    }

    /**
     * Return the CSDL JSON document, using the cache when available.
     *
     * @return string JSON-encoded CSDL.
     */
    public function get_json(): string {
        $cached = $this->cache->get_json();
        if ( null !== $cached ) {
            return $cached;
        }

        $json = $this->do_build_json();
        $this->cache->set_json( $json );
        return $json;
    }

    // -------------------------------------------------------------------------
    // Backward-compatible aliases used by ODAD_Router (Phase 1)
    // -------------------------------------------------------------------------

    /**
     * Return the CSDL XML document.
     *
     * Delegates to get_xml() so the cache is always consulted first.
     *
     * @return string Well-formed CSDL XML.
     */
    public function build_xml(): string {
        return $this->get_xml();
    }

    /**
     * Return the CSDL JSON document.
     *
     * Delegates to get_json() so the cache is always consulted first.
     *
     * @return string JSON-encoded CSDL.
     */
    public function build_json(): string {
        return $this->get_json();
    }

    /**
     * Return the names of all registered entity sets.
     *
     * Used by ODAD_Router to build the service document.
     *
     * @return string[]
     */
    public function get_entity_set_names(): array {
        return $this->registry->get_entity_set_names();
    }

    // -------------------------------------------------------------------------
    // Private build logic
    // -------------------------------------------------------------------------

    /**
     * Gather entity types and sets, apply filters, dispatch the build event,
     * then serialise to CSDL XML.
     *
     * @return string
     */
    private function do_build_xml(): string {
        [ $entity_types, $entity_sets ] = $this->prepare_schema();

        $schema_ns     = self::SCHEMA_NS;
        $edmx_ns       = self::EDMX_NS;
        $edm_ns        = self::EDM_NS;
        $odata_ver     = self::ODATA_VERSION;
        $container     = self::CONTAINER_NAME;

        $entity_type_xml = '';
        $entity_set_xml  = '';

        foreach ( $entity_types as $name => $def ) {
            $type_name   = htmlspecialchars( $def['entity_type'] ?? $name, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
            $key_prop    = htmlspecialchars( $def['key_property'] ?? 'Id', ENT_XML1 | ENT_QUOTES, 'UTF-8' );

            $entity_type_xml .= "\n      <EntityType Name=\"{$type_name}\">";
            $entity_type_xml .= "\n        <Key><PropertyRef Name=\"{$key_prop}\"/></Key>";

            foreach ( $def['properties'] ?? [] as $prop_name => $prop_def ) {
                $p_name     = htmlspecialchars( (string) $prop_name, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
                $p_type     = htmlspecialchars( $prop_def['type'] ?? 'Edm.String', ENT_XML1 | ENT_QUOTES, 'UTF-8' );
                $nullable   = isset( $prop_def['nullable'] ) && false === $prop_def['nullable']
                    ? ' Nullable="false"'
                    : '';
                $entity_type_xml .= "\n        <Property Name=\"{$p_name}\" Type=\"{$p_type}\"{$nullable}/>";
            }

            foreach ( $def['nav_properties'] ?? [] as $nav_name => $nav_def ) {
                $n_name       = htmlspecialchars( (string) $nav_name, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
                $nav_type_raw = $nav_def['type'] ?? $nav_name;
                $nav_type_ns  = htmlspecialchars( "{$schema_ns}.{$nav_type_raw}", ENT_XML1 | ENT_QUOTES, 'UTF-8' );
                $nav_type_val = ! empty( $nav_def['collection'] )
                    ? "Collection({$nav_type_ns})"
                    : $nav_type_ns;
                $entity_type_xml .= "\n        <NavigationProperty Name=\"{$n_name}\" Type=\"{$nav_type_val}\"/>";
            }

            $entity_type_xml .= "\n      </EntityType>";
        }

        foreach ( $entity_sets as $set_name => $def ) {
            $s_name    = htmlspecialchars( (string) $set_name, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
            $type_name = htmlspecialchars( $def['entity_type'] ?? $set_name, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
            $entity_set_xml .= "\n        <EntitySet Name=\"{$s_name}\" EntityType=\"{$schema_ns}.{$type_name}\"/>";
        }

        // Functions and actions from the registries.
        $function_xml = '';
        if ( null !== $this->function_registry ) {
            foreach ( $this->function_registry->all() as $fn ) {
                $fn_name     = htmlspecialchars( $fn['name'], ENT_XML1 | ENT_QUOTES, 'UTF-8' );
                $return_type = htmlspecialchars( $fn['return_type'] ?? 'Edm.String', ENT_XML1 | ENT_QUOTES, 'UTF-8' );
                $function_xml .= "\n      <Function Name=\"{$fn_name}\" IsBound=\"" . ( empty( $fn['binding'] ) ? 'false' : 'true' ) . "\">";
                foreach ( $fn['parameters'] ?? [] as $param ) {
                    $p_name = htmlspecialchars( $param['name'] ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8' );
                    $p_type = htmlspecialchars( $param['type'] ?? 'Edm.String', ENT_XML1 | ENT_QUOTES, 'UTF-8' );
                    $function_xml .= "\n        <Parameter Name=\"{$p_name}\" Type=\"{$p_type}\"/>";
                }
                $function_xml .= "\n        <ReturnType Type=\"{$return_type}\"/>";
                $function_xml .= "\n      </Function>";
            }
        }

        $action_xml = '';
        if ( null !== $this->action_registry ) {
            foreach ( $this->action_registry->all() as $action ) {
                $act_name    = htmlspecialchars( $action['name'], ENT_XML1 | ENT_QUOTES, 'UTF-8' );
                $return_type = $action['return_type'] ?? '';
                $action_xml .= "\n      <Action Name=\"{$act_name}\" IsBound=\"" . ( empty( $action['binding'] ) ? 'false' : 'true' ) . "\">";
                foreach ( $action['parameters'] ?? [] as $param ) {
                    $p_name = htmlspecialchars( $param['name'] ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8' );
                    $p_type = htmlspecialchars( $param['type'] ?? 'Edm.String', ENT_XML1 | ENT_QUOTES, 'UTF-8' );
                    $action_xml .= "\n        <Parameter Name=\"{$p_name}\" Type=\"{$p_type}\"/>";
                }
                if ( '' !== $return_type && 'void' !== strtolower( $return_type ) ) {
                    $rt_esc      = htmlspecialchars( $return_type, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
                    $action_xml .= "\n        <ReturnType Type=\"{$rt_esc}\"/>";
                }
                $action_xml .= "\n      </Action>";
            }
        }

        // When the registry is empty the EntityContainer contains only a comment.
        $container_body = '' !== $entity_set_xml
            ? $entity_set_xml . "\n      "
            : "\n        <!-- EntitySets populated by adapters in Phase 2 -->\n      ";

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<edmx:Edmx Version="{$odata_ver}" xmlns:edmx="{$edmx_ns}">
  <edmx:DataServices>
    <Schema Namespace="{$schema_ns}" xmlns="{$edm_ns}">{$entity_type_xml}{$function_xml}{$action_xml}
      <EntityContainer Name="{$container}">{$container_body}</EntityContainer>
    </Schema>
  </edmx:DataServices>
</edmx:Edmx>
XML;
    }

    /**
     * Gather entity types, sets, functions and actions, apply filters, dispatch
     * the build event, then serialise to OData v4.01 JSON CSDL format.
     *
     * JSON CSDL spec: https://docs.oasis-open.org/odata/odata-csdl-json/v4.01/
     *
     * @return string
     */
    private function do_build_json(): string {
        [ $entity_types, $entity_sets ] = $this->prepare_schema();

        $schema_ns = self::SCHEMA_NS;

        // ── Entity types ──────────────────────────────────────────────────────
        $type_defs = new \stdClass();

        foreach ( $entity_types as $name => $def ) {
            $type_name = $def['entity_type'] ?? $name;
            $key_prop  = $def['key_property'] ?? 'Id';

            $type            = new \stdClass();
            $type->{'$Kind'} = 'EntityType';
            $type->{'$Key'}  = [ $key_prop ];

            foreach ( $def['properties'] ?? [] as $prop_name => $prop_def ) {
                $p            = new \stdClass();
                $p->{'$Type'} = $prop_def['type'] ?? 'Edm.String';
                if ( isset( $prop_def['nullable'] ) && false === $prop_def['nullable'] ) {
                    $p->{'$Nullable'} = false;
                }
                $type->{ $prop_name } = $p;
            }

            foreach ( $def['nav_properties'] ?? [] as $nav_name => $nav_def ) {
                $n              = new \stdClass();
                $n->{'$Kind'}   = 'NavigationProperty';
                $nav_type_val   = "{$schema_ns}.{$nav_def['type']}";
                if ( ! empty( $nav_def['collection'] ) ) {
                    $n->{'$Collection'} = true;
                }
                $n->{'$Type'}   = $nav_type_val;
                $type->{ $nav_name } = $n;
            }

            $type_defs->{ $type_name } = $type;
        }

        // ── Entity container ──────────────────────────────────────────────────
        $container          = new \stdClass();
        $container->{'$Kind'} = 'EntityContainer';

        foreach ( $entity_sets as $set_name => $def ) {
            $type_name = $def['entity_type'] ?? $set_name;
            $es                  = new \stdClass();
            $es->{'$Collection'} = true;
            $es->{'$Type'}       = "{$schema_ns}.{$type_name}";
            $container->{ $set_name } = $es;
        }

        // ── Functions ─────────────────────────────────────────────────────────
        $fn_defs = new \stdClass();
        if ( null !== $this->function_registry ) {
            foreach ( $this->function_registry->all() as $fn ) {
                $fn_obj            = new \stdClass();
                $fn_obj->{'$Kind'} = 'Function';
                $fn_obj->{'$IsBound'} = ! empty( $fn['binding'] );

                $fn_params = [];
                foreach ( $fn['parameters'] ?? [] as $param ) {
                    $p_obj            = new \stdClass();
                    $p_obj->{'$Name'} = $param['name'] ?? '';
                    $p_obj->{'$Type'} = $param['type'] ?? 'Edm.String';
                    $fn_params[]      = $p_obj;
                }
                if ( ! empty( $fn_params ) ) {
                    $fn_obj->{'$Parameter'} = $fn_params;
                }

                $fn_obj->{'$ReturnType'} = new \stdClass();
                $fn_obj->{'$ReturnType'}->{'$Type'} = $fn['return_type'] ?? 'Edm.String';

                $fn_defs->{ $fn['name'] } = $fn_obj;
            }
        }

        // ── Actions ───────────────────────────────────────────────────────────
        $action_defs = new \stdClass();
        if ( null !== $this->action_registry ) {
            foreach ( $this->action_registry->all() as $action ) {
                $act_obj            = new \stdClass();
                $act_obj->{'$Kind'} = 'Action';
                $act_obj->{'$IsBound'} = ! empty( $action['binding'] );

                $act_params = [];
                foreach ( $action['parameters'] ?? [] as $param ) {
                    $p_obj            = new \stdClass();
                    $p_obj->{'$Name'} = $param['name'] ?? '';
                    $p_obj->{'$Type'} = $param['type'] ?? 'Edm.String';
                    $act_params[]     = $p_obj;
                }
                if ( ! empty( $act_params ) ) {
                    $act_obj->{'$Parameter'} = $act_params;
                }

                $return_type = $action['return_type'] ?? '';
                if ( '' !== $return_type && 'void' !== strtolower( $return_type ) ) {
                    $rt                  = new \stdClass();
                    $rt->{'$Type'}       = $return_type;
                    $act_obj->{'$ReturnType'} = $rt;
                }

                $action_defs->{ $action['name'] } = $act_obj;
            }
        }

        // ── Assemble schema object ────────────────────────────────────────────
        $schema_obj = new \stdClass();

        // Entity types first, then functions, actions, then the container.
        foreach ( (array) $type_defs as $k => $v ) {
            $schema_obj->{ $k } = $v;
        }
        foreach ( (array) $fn_defs as $k => $v ) {
            $schema_obj->{ $k } = $v;
        }
        foreach ( (array) $action_defs as $k => $v ) {
            $schema_obj->{ $k } = $v;
        }
        $schema_obj->{ self::CONTAINER_NAME } = $container;

        // ── Root document ─────────────────────────────────────────────────────
        $root = new \stdClass();
        $root->{'$Version'}         = self::ODATA_VERSION;
        $root->{'$EntityContainer'} = "{$schema_ns}." . self::CONTAINER_NAME;
        $root->{ $schema_ns }       = $schema_obj;

        return (string) wp_json_encode( $root, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
    }

    /**
     * Build the final entity-type and entity-set arrays by:
     *   1. Starting from the schema registry.
     *   2. Dispatching ODAD_Event_Metadata_Build (internal listeners may mutate).
     *   3. Applying ODAD_metadata_entity_types WP filter via the Hook Bridge.
     *   4. Applying ODAD_metadata_entity_sets  WP filter via the Hook Bridge.
     *
     * @return array{ 0: array<string,array>, 1: array<string,array> }
     *              [ entity_types, entity_sets ]
     */
    private function prepare_schema(): array {
        $all_defs = $this->registry->all();

        // Dispatch internal event so subscribers (e.g. ODAD_Subscriber_Metadata_Build)
        // can react before filters are applied.
        $event = new ODAD_Event_Metadata_Build(
            entity_types: $all_defs,
            entity_sets:  $all_defs,
        );
        /** @var ODAD_Event_Metadata_Build $event */
        $event = $this->event_bus->dispatch( $event );

        // Allow external plugins to modify entity type definitions.
        $entity_types = $this->bridge->filter(
            'ODAD_metadata_entity_types',
            $event->entity_types,
        );

        // Allow external plugins to modify entity set declarations.
        $entity_sets = $this->bridge->filter(
            'ODAD_metadata_entity_sets',
            $event->entity_sets,
        );

        // Ensure we always have arrays.
        if ( ! is_array( $entity_types ) ) {
            $entity_types = [];
        }
        if ( ! is_array( $entity_sets ) ) {
            $entity_sets = [];
        }

        return [ $entity_types, $entity_sets ];
    }
}
