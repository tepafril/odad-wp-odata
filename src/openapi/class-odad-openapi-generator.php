<?php
defined( 'ABSPATH' ) || exit;

/**
 * Generates a complete OpenAPI 3.0 specification from the live schema registry.
 * Reads entity-set definitions (key_property, properties, nav_properties) and
 * emits paths + component schemas for every registered entity set, plus the
 * auth endpoints and OData system endpoints.
 */
class ODAD_OpenAPI_Generator {

    public function __construct(
        private ODAD_Schema_Registry $registry,
    ) {}

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Build and return the full OpenAPI 3.0 spec as a PHP array.
     * Caller is responsible for JSON-encoding.
     */
    public function generate(): array {
        $paths      = [];
        $schemas    = $this->build_base_schemas();

        foreach ( $this->registry->all() as $entity_set => $definition ) {
            $paths  += $this->build_entity_paths( $entity_set, $definition );
            $schemas[ $entity_set ]            = $this->build_entity_schema( $entity_set, $definition );
            $schemas[ $entity_set . 'Write' ]  = $this->build_write_schema( $entity_set, $definition );
            $schemas[ $entity_set . 'Collection' ] = $this->build_collection_response_schema( $entity_set );
        }

        $paths += $this->build_system_paths();
        $paths += $this->build_auth_paths();

        return [
            'openapi' => '3.0.3',
            'info'    => $this->build_info(),
            'servers' => [
                [ 'url' => untrailingslashit( rest_url( '/' ) ), 'description' => 'WordPress REST root' ],
            ],
            'security' => [
                [ 'BearerAuth' => [] ],
                [ 'WpNonce'    => [] ],
            ],
            'paths'      => $paths,
            'components' => $this->build_components( $schemas ),
        ];
    }

    // ── Private builders ──────────────────────────────────────────────────────

    private function build_info(): array {
        return [
            'title'       => 'WP-OData Suite API',
            'description' => 'OData v4.01 REST API for WordPress data. Authenticate with a JWT Bearer token (from `/odad/v1/auth/login`) or an `X-WP-Nonce` header (for WordPress admin sessions).',
            'version'     => ODAD_VERSION,
        ];
    }

    private function build_components( array $schemas ): array {
        return [
            'securitySchemes' => [
                'BearerAuth' => [
                    'type'         => 'http',
                    'scheme'       => 'bearer',
                    'bearerFormat' => 'JWT',
                    'description'  => 'Access token from POST /odad/v1/auth/login. Expires in 15 min.',
                ],
                'WpNonce' => [
                    'type'        => 'apiKey',
                    'in'          => 'header',
                    'name'        => 'X-WP-Nonce',
                    'description' => 'WordPress REST nonce (wp_create_nonce("wp_rest")). For admin UI / same-origin use.',
                ],
            ],
            'parameters' => $this->build_odata_parameters(),
            'responses'  => $this->build_common_responses(),
            'schemas'    => $schemas,
        ];
    }

    private function build_odata_parameters(): array {
        return [
            'filter'  => [ 'name' => '$filter',  'in' => 'query', 'required' => false, 'schema' => [ 'type' => 'string' ],  'description' => 'OData $filter expression. Example: Status eq \'publish\'' ],
            'select'  => [ 'name' => '$select',  'in' => 'query', 'required' => false, 'schema' => [ 'type' => 'string' ],  'description' => 'Comma-separated property names to return.' ],
            'orderby' => [ 'name' => '$orderby', 'in' => 'query', 'required' => false, 'schema' => [ 'type' => 'string' ],  'description' => 'Sort expression. Example: PublishedDate desc' ],
            'top'     => [ 'name' => '$top',     'in' => 'query', 'required' => false, 'schema' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 1000, 'default' => 100 ] ],
            'skip'    => [ 'name' => '$skip',    'in' => 'query', 'required' => false, 'schema' => [ 'type' => 'integer', 'minimum' => 0, 'default' => 0 ] ],
            'count'   => [ 'name' => '$count',   'in' => 'query', 'required' => false, 'schema' => [ 'type' => 'boolean' ], 'description' => 'Include @odata.count in response.' ],
            'expand'  => [ 'name' => '$expand',  'in' => 'query', 'required' => false, 'schema' => [ 'type' => 'string' ],  'description' => 'Navigation properties to expand. Example: Author' ],
            'search'  => [ 'name' => '$search',  'in' => 'query', 'required' => false, 'schema' => [ 'type' => 'string' ] ],
        ];
    }

    private function build_common_responses(): array {
        $error_ref = [ 'application/json' => [ 'schema' => [ '$ref' => '#/components/schemas/ODataError' ] ] ];
        return [
            '400' => [ 'description' => 'Bad Request',            'content' => $error_ref ],
            '401' => [ 'description' => 'Unauthorized',           'content' => $error_ref ],
            '403' => [ 'description' => 'Forbidden',              'content' => $error_ref ],
            '404' => [ 'description' => 'Not Found',              'content' => $error_ref ],
            '500' => [ 'description' => 'Internal Server Error',  'content' => $error_ref ],
        ];
    }

    private function build_base_schemas(): array {
        return [
            'ODataError' => [
                'type'       => 'object',
                'properties' => [
                    'error' => [
                        'type'       => 'object',
                        'properties' => [
                            'code'    => [ 'type' => 'string' ],
                            'message' => [ 'type' => 'string' ],
                        ],
                    ],
                ],
            ],
            'LoginResponse' => [
                'type'       => 'object',
                'properties' => [
                    'access_token'  => [ 'type' => 'string' ],
                    'refresh_token' => [ 'type' => 'string' ],
                    'expires_in'    => [ 'type' => 'integer', 'example' => 900 ],
                    'user'          => [ '$ref' => '#/components/schemas/AuthUser' ],
                ],
            ],
            'AuthUser' => [
                'type'       => 'object',
                'properties' => [
                    'id'           => [ 'type' => 'integer' ],
                    'login'        => [ 'type' => 'string' ],
                    'email'        => [ 'type' => 'string', 'format' => 'email' ],
                    'display_name' => [ 'type' => 'string' ],
                    'roles'        => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                ],
            ],
            'RefreshResponse' => [
                'type'       => 'object',
                'properties' => [
                    'access_token' => [ 'type' => 'string' ],
                    'expires_in'   => [ 'type' => 'integer', 'example' => 900 ],
                ],
            ],
        ];
    }

    private function build_entity_schema( string $entity_set, array $definition ): array {
        $properties = [];
        foreach ( $definition['properties'] ?? [] as $name => $prop ) {
            $properties[ $name ] = $this->map_type( $prop['type'] ?? 'Edm.String' );
            if ( isset( $prop['nullable'] ) && false === $prop['nullable'] ) {
                $properties[ $name ]['nullable'] = false;
            }
        }
        return [
            'type'       => 'object',
            'properties' => $properties,
        ];
    }

    /**
     * Write schema: excludes the key property (auto-generated) and any
     * property explicitly marked read_only => true.
     */
    private function build_write_schema( string $entity_set, array $definition ): array {
        $key_property = $definition['key_property'] ?? '';
        $properties   = [];

        foreach ( $definition['properties'] ?? [] as $name => $prop ) {
            if ( $name === $key_property ) {
                continue;
            }
            if ( ! empty( $prop['read_only'] ) ) {
                continue;
            }
            $properties[ $name ] = $this->map_type( $prop['type'] ?? 'Edm.String' );
        }

        return [
            'type'       => 'object',
            'properties' => $properties,
        ];
    }

    private function build_collection_response_schema( string $entity_set ): array {
        return [
            'type'       => 'object',
            'properties' => [
                '@odata.context' => [ 'type' => 'string' ],
                '@odata.count'   => [ 'type' => 'integer', 'description' => 'Total count (only when $count=true).' ],
                '@odata.nextLink'=> [ 'type' => 'string',  'description' => 'Next page URL (only when more results exist).' ],
                'value'          => [
                    'type'  => 'array',
                    'items' => [ '$ref' => '#/components/schemas/' . $entity_set ],
                ],
            ],
            'required' => [ 'value' ],
        ];
    }

    private function build_entity_paths( string $entity_set, array $definition ): array {
        $key_prop  = $definition['key_property'] ?? 'ID';
        $key_type  = $this->map_type( $definition['properties'][ $key_prop ]['type'] ?? 'Edm.Int32' );
        $tag       = $entity_set;
        $singular  = rtrim( $entity_set, 's' ); // naive singularisation for operation IDs

        $collection_path = '/odata/v4/' . $entity_set;
        $entity_path     = '/odata/v4/' . $entity_set . '({' . $key_prop . '})';

        return [
            $collection_path => [
                'get' => [
                    'tags'        => [ $tag ],
                    'summary'     => 'List ' . $entity_set,
                    'operationId' => 'list' . $entity_set,
                    'parameters'  => [
                        [ '$ref' => '#/components/parameters/filter' ],
                        [ '$ref' => '#/components/parameters/select' ],
                        [ '$ref' => '#/components/parameters/orderby' ],
                        [ '$ref' => '#/components/parameters/top' ],
                        [ '$ref' => '#/components/parameters/skip' ],
                        [ '$ref' => '#/components/parameters/count' ],
                        [ '$ref' => '#/components/parameters/expand' ],
                        [ '$ref' => '#/components/parameters/search' ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Collection of ' . $entity_set,
                            'content'     => [ 'application/json' => [ 'schema' => [ '$ref' => '#/components/schemas/' . $entity_set . 'Collection' ] ] ],
                        ],
                        '401' => [ '$ref' => '#/components/responses/401' ],
                        '403' => [ '$ref' => '#/components/responses/403' ],
                    ],
                ],
                'post' => [
                    'tags'        => [ $tag ],
                    'summary'     => 'Create ' . $singular,
                    'operationId' => 'create' . $entity_set,
                    'requestBody' => [
                        'required' => true,
                        'content'  => [ 'application/json' => [ 'schema' => [ '$ref' => '#/components/schemas/' . $entity_set . 'Write' ] ] ],
                    ],
                    'responses' => [
                        '201' => [
                            'description' => 'Created',
                            'content'     => [ 'application/json' => [ 'schema' => [ '$ref' => '#/components/schemas/' . $entity_set ] ] ],
                        ],
                        '400' => [ '$ref' => '#/components/responses/400' ],
                        '403' => [ '$ref' => '#/components/responses/403' ],
                    ],
                ],
            ],
            $entity_path => [
                'parameters' => [
                    [ 'name' => $key_prop, 'in' => 'path', 'required' => true, 'schema' => $key_type ],
                ],
                'get' => [
                    'tags'        => [ $tag ],
                    'summary'     => 'Get ' . $singular . ' by ' . $key_prop,
                    'operationId' => 'get' . $entity_set,
                    'responses'   => [
                        '200' => [
                            'description' => $singular,
                            'content'     => [ 'application/json' => [ 'schema' => [ '$ref' => '#/components/schemas/' . $entity_set ] ] ],
                        ],
                        '401' => [ '$ref' => '#/components/responses/401' ],
                        '403' => [ '$ref' => '#/components/responses/403' ],
                        '404' => [ '$ref' => '#/components/responses/404' ],
                    ],
                ],
                'patch' => [
                    'tags'        => [ $tag ],
                    'summary'     => 'Update ' . $singular,
                    'operationId' => 'update' . $entity_set,
                    'requestBody' => [
                        'required' => true,
                        'content'  => [ 'application/json' => [ 'schema' => [ '$ref' => '#/components/schemas/' . $entity_set . 'Write' ] ] ],
                    ],
                    'responses' => [
                        '204' => [ 'description' => 'Updated' ],
                        '400' => [ '$ref' => '#/components/responses/400' ],
                        '403' => [ '$ref' => '#/components/responses/403' ],
                        '404' => [ '$ref' => '#/components/responses/404' ],
                    ],
                ],
                'put' => [
                    'tags'        => [ $tag ],
                    'summary'     => 'Replace ' . $singular,
                    'operationId' => 'replace' . $entity_set,
                    'requestBody' => [
                        'required' => true,
                        'content'  => [ 'application/json' => [ 'schema' => [ '$ref' => '#/components/schemas/' . $entity_set . 'Write' ] ] ],
                    ],
                    'responses' => [
                        '204' => [ 'description' => 'Replaced' ],
                        '400' => [ '$ref' => '#/components/responses/400' ],
                        '403' => [ '$ref' => '#/components/responses/403' ],
                        '404' => [ '$ref' => '#/components/responses/404' ],
                    ],
                ],
                'delete' => [
                    'tags'        => [ $tag ],
                    'summary'     => 'Delete ' . $singular,
                    'operationId' => 'delete' . $entity_set,
                    'responses'   => [
                        '204' => [ 'description' => 'Deleted' ],
                        '403' => [ '$ref' => '#/components/responses/403' ],
                        '404' => [ '$ref' => '#/components/responses/404' ],
                    ],
                ],
            ],
        ];
    }

    private function build_auth_paths(): array {
        return [
            '/odad/v1/auth/login' => [
                'post' => [
                    'tags'        => [ 'Authentication' ],
                    'summary'     => 'Login — obtain access + refresh tokens',
                    'operationId' => 'authLogin',
                    'security'    => [], // public endpoint
                    'requestBody' => [
                        'required' => true,
                        'content'  => [ 'application/json' => [ 'schema' => [
                            'type'       => 'object',
                            'required'   => [ 'username', 'password' ],
                            'properties' => [
                                'username' => [ 'type' => 'string' ],
                                'password' => [ 'type' => 'string', 'format' => 'password' ],
                                'device'   => [ 'type' => 'string', 'description' => 'Label for this session (optional, max 100 chars).' ],
                            ],
                        ] ] ],
                    ],
                    'responses' => [
                        '200' => [ 'description' => 'Tokens issued',      'content' => [ 'application/json' => [ 'schema' => [ '$ref' => '#/components/schemas/LoginResponse' ] ] ] ],
                        '401' => [ 'description' => 'Invalid credentials' ],
                        '429' => [ 'description' => 'Too many failed login attempts — wait 15 minutes' ],
                    ],
                ],
            ],
            '/odad/v1/auth/refresh' => [
                'post' => [
                    'tags'        => [ 'Authentication' ],
                    'summary'     => 'Refresh — exchange refresh token for a new access token',
                    'operationId' => 'authRefresh',
                    'security'    => [],
                    'requestBody' => [
                        'required' => true,
                        'content'  => [ 'application/json' => [ 'schema' => [
                            'type'       => 'object',
                            'required'   => [ 'refresh_token' ],
                            'properties' => [
                                'refresh_token' => [ 'type' => 'string', 'description' => '64-char hex string from login. Single-use — consumed on call.' ],
                            ],
                        ] ] ],
                    ],
                    'responses' => [
                        '200' => [ 'description' => 'New access token', 'content' => [ 'application/json' => [ 'schema' => [ '$ref' => '#/components/schemas/RefreshResponse' ] ] ] ],
                        '401' => [ 'description' => 'Invalid or expired refresh token' ],
                    ],
                ],
            ],
            '/odad/v1/auth/logout' => [
                'post' => [
                    'tags'        => [ 'Authentication' ],
                    'summary'     => 'Logout — revoke one or all refresh tokens',
                    'operationId' => 'authLogout',
                    'requestBody' => [
                        'required' => false,
                        'content'  => [ 'application/json' => [ 'schema' => [
                            'type'       => 'object',
                            'properties' => [
                                'refresh_token' => [ 'type' => 'string', 'description' => 'Revoke this specific device token.' ],
                                'all_devices'   => [ 'type' => 'boolean', 'default' => false, 'description' => 'Set true to revoke all sessions.' ],
                            ],
                        ] ] ],
                    ],
                    'responses' => [
                        '204' => [ 'description' => 'Logged out' ],
                        '401' => [ 'description' => 'Missing or invalid access token' ],
                    ],
                ],
            ],
        ];
    }

    private function build_system_paths(): array {
        return [
            '/odata/v4/' => [
                'get' => [
                    'tags'        => [ 'OData System' ],
                    'summary'     => 'Service document — lists all entity sets',
                    'operationId' => 'serviceDocument',
                    'security'    => [],
                    'responses'   => [
                        '200' => [ 'description' => 'OData service document', 'content' => [ 'application/json' => [ 'schema' => [ 'type' => 'object' ] ] ] ],
                    ],
                ],
            ],
            '/odata/v4/$metadata' => [
                'get' => [
                    'tags'        => [ 'OData System' ],
                    'summary'     => 'CSDL metadata (XML default, JSON with Accept: application/json)',
                    'operationId' => 'metadata',
                    'security'    => [],
                    'parameters'  => [
                        [ 'name' => '$format', 'in' => 'query', 'required' => false, 'schema' => [ 'type' => 'string', 'enum' => [ 'application/json', 'application/xml' ] ] ],
                    ],
                    'responses' => [
                        '200' => [ 'description' => 'CSDL document' ],
                    ],
                ],
            ],
            '/odata/v4/$batch' => [
                'post' => [
                    'tags'        => [ 'OData System' ],
                    'summary'     => 'Batch — send multiple requests in one HTTP call',
                    'operationId' => 'batch',
                    'responses'   => [
                        '200' => [ 'description' => 'Batch response' ],
                        '401' => [ '$ref' => '#/components/responses/401' ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Map an OData Edm type string to an OpenAPI schema fragment.
     */
    private function map_type( string $edm_type ): array {
        return match ( $edm_type ) {
            'Edm.Int16', 'Edm.Int32'        => [ 'type' => 'integer', 'format' => 'int32' ],
            'Edm.Int64'                      => [ 'type' => 'integer', 'format' => 'int64' ],
            'Edm.Boolean'                    => [ 'type' => 'boolean' ],
            'Edm.Decimal'                    => [ 'type' => 'number' ],
            'Edm.Single', 'Edm.Double'       => [ 'type' => 'number',  'format' => 'double' ],
            'Edm.DateTimeOffset'             => [ 'type' => 'string',  'format' => 'date-time' ],
            'Edm.Date'                       => [ 'type' => 'string',  'format' => 'date' ],
            'Edm.TimeOfDay'                  => [ 'type' => 'string',  'format' => 'time' ],
            'Edm.Guid'                       => [ 'type' => 'string',  'format' => 'uuid' ],
            'Edm.Binary'                     => [ 'type' => 'string',  'format' => 'byte' ],
            default                          => [ 'type' => 'string' ],  // Edm.String and unknowns
        };
    }
}
