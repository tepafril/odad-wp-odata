<?php
defined( 'ABSPATH' ) || exit;

/**
 * Compiles an OData $search clause into a SQL LIKE expression.
 *
 * No WordPress calls are made in this class.  The returned `params` array
 * contains values ready to be passed to $wpdb->prepare() using %s placeholders.
 */
class ODAD_Search_Compiler {

    /**
     * Compile a search term into a SQL LIKE fragment.
     *
     * @param string   $search_term   Raw search term from the $search query option.
     * @param string[] $search_columns SQL column names (already fully qualified) to
     *                                 search across, e.g. ['p.post_title', 'p.post_content'].
     * @return array{sql: string, params: array<int,string>}
     *         'sql'    — A parenthesised OR expression, e.g.
     *                    "(p.post_title LIKE %s OR p.post_content LIKE %s)".
     *                    Empty string when $search_term is blank or no columns given.
     *         'params' — One "%keyword%" string per column, in the same order as
     *                    the placeholders in 'sql'.
     */
    public function compile( string $search_term, array $search_columns ): array {
        $empty = [ 'sql' => '', 'params' => [] ];

        $search_term = trim( $search_term );

        if ( '' === $search_term || empty( $search_columns ) ) {
            return $empty;
        }

        // Escape LIKE special characters so they are treated as literals.
        $escaped = $this->escape_like( $search_term );
        $pattern = '%' . $escaped . '%';

        $clauses = [];
        $params  = [];

        foreach ( $search_columns as $column ) {
            $clauses[] = $column . ' LIKE %s';
            $params[]  = $pattern;
        }

        $sql = '(' . implode( ' OR ', $clauses ) . ')';

        return [ 'sql' => $sql, 'params' => $params ];
    }

    /**
     * Escape percent and underscore characters in a LIKE pattern value.
     *
     * @param string $value Raw user input.
     * @return string Escaped string safe for inclusion in a LIKE pattern.
     */
    private function escape_like( string $value ): string {
        return str_replace(
            [ '\\', '%', '_' ],
            [ '\\\\', '\\%', '\\_' ],
            $value
        );
    }
}
