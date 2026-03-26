<?php
defined( 'ABSPATH' ) || exit;

/**
 * Compiles an OData $select clause into a SQL column list.
 *
 * No WordPress calls are made in this class.
 */
class WPOS_Select_Compiler {

    /**
     * Compile a list of OData property names into a SQL column list.
     *
     * The key property (mapped as 'ID') is always prepended if not already
     * present in the resolved column list.
     *
     * @param string[]             $properties OData property names from $select.
     *                                         Empty array means "all columns".
     * @param array<string,string> $column_map Map of OData property name → SQL column expression.
     *                                         The entry keyed 'ID' (if present) is treated as the
     *                                         primary key and always included.
     * @return string SQL column list, e.g. "p.ID, p.post_title, p.post_status".
     * @throws WPOS_Select_Exception If any property name is not found in $column_map.
     */
    public function compile( array $properties, array $column_map ): string {
        if ( empty( $column_map ) ) {
            return '*';
        }

        // Resolve the columns to emit.
        if ( empty( $properties ) ) {
            // No explicit $select → return all mapped columns.
            $columns = array_values( $column_map );
        } else {
            $columns = [];

            foreach ( $properties as $property ) {
                if ( ! array_key_exists( $property, $column_map ) ) {
                    throw new WPOS_Select_Exception(
                        sprintf( 'Unknown $select property: "%s"', $property )
                    );
                }
                $columns[] = $column_map[ $property ];
            }

            // Ensure the key column (ID) is always included at the front.
            if ( array_key_exists( 'ID', $column_map ) ) {
                $id_column = $column_map['ID'];
                if ( ! in_array( $id_column, $columns, true ) ) {
                    array_unshift( $columns, $id_column );
                }
            }
        }

        return implode( ', ', $columns );
    }
}
