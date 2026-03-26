<?php
defined( 'ABSPATH' ) || exit;

/**
 * Compiles an OData $orderby clause into a SQL ORDER BY expression.
 *
 * No WordPress calls are made in this class.
 */
class WPOS_Orderby_Compiler {

    /**
     * Compile an OData $orderby string into a SQL ORDER BY clause.
     *
     * @param string               $orderby    Raw $orderby value, e.g. "PublishedDate desc,Title".
     * @param array<string,string> $column_map Map of OData property name → SQL column expression.
     * @return string SQL ORDER BY clause (without the "ORDER BY" keyword),
     *                e.g. "p.post_date_gmt DESC, p.post_title ASC".
     *                Returns an empty string when $orderby is blank.
     * @throws WPOS_Orderby_Exception If any property name is not found in $column_map.
     */
    public function compile( string $orderby, array $column_map ): string {
        $orderby = trim( $orderby );

        if ( '' === $orderby ) {
            return '';
        }

        $parts  = [];
        $tokens = explode( ',', $orderby );

        foreach ( $tokens as $token ) {
            $token = trim( $token );

            if ( '' === $token ) {
                continue;
            }

            // Split into at most two segments: <property> [<direction>].
            $segments  = preg_split( '/\s+/', $token, 2 );
            $property  = $segments[0];
            $raw_dir   = isset( $segments[1] ) ? strtolower( trim( $segments[1] ) ) : 'asc';

            // Validate direction.  Never interpolate raw input into SQL.
            $direction = strtoupper( $raw_dir );
            if ( ! in_array( $direction, [ 'ASC', 'DESC' ], true ) ) {
                $direction = 'ASC';
            }

            // Validate property against the column map.
            if ( ! array_key_exists( $property, $column_map ) ) {
                throw new WPOS_Orderby_Exception(
                    sprintf( 'Unknown $orderby property: "%s"', $property )
                );
            }

            $parts[] = $column_map[ $property ] . ' ' . $direction;
        }

        return implode( ', ', $parts );
    }
}
