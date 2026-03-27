<?php
defined( 'ABSPATH' ) || exit;

/**
 * Manages revocable refresh tokens in {prefix}odad_refresh_tokens.
 */
class ODAD_Token_Store {

    // ── Public API ────────────────────────────────────────────────────────

    /**
     * Persist a new refresh token.
     */
    public function store( int $user_id, string $raw_token, int $ttl, string $device = '' ): void {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'odad_refresh_tokens',
            [
                'user_id'     => $user_id,
                'token_hash'  => $this->hash( $raw_token ),
                'device_name' => $device,
                'expires_at'  => gmdate( 'Y-m-d H:i:s', time() + $ttl ),
            ],
            [ '%d', '%s', '%s', '%s' ]
        );
    }

    /**
     * Verify a raw refresh token, delete it (rotate), and return the user ID.
     *
     * @throws ODAD_Token_Exception if not found or expired.
     */
    public function consume( string $raw_token ): int {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, user_id, expires_at FROM {$wpdb->prefix}odad_refresh_tokens
                 WHERE token_hash = %s LIMIT 1",
                $this->hash( $raw_token )
            )
        );

        if ( ! $row ) {
            throw new ODAD_Token_Exception( 'Refresh token not found.', 'token_not_found' );
        }

        if ( strtotime( $row->expires_at ) <= time() ) {
            $this->delete_by_id( (int) $row->id );
            throw new ODAD_Token_Exception( 'Refresh token has expired.', 'token_expired' );
        }

        // Rotate: delete the used token so it cannot be replayed.
        $this->delete_by_id( (int) $row->id );

        return (int) $row->user_id;
    }

    /**
     * Revoke a single refresh token.
     */
    public function revoke( string $raw_token ): void {
        global $wpdb;

        $wpdb->delete(
            $wpdb->prefix . 'odad_refresh_tokens',
            [ 'token_hash' => $this->hash( $raw_token ) ],
            [ '%s' ]
        );
    }

    /**
     * Revoke all refresh tokens for a user (logout everywhere).
     */
    public function revoke_all( int $user_id ): void {
        global $wpdb;

        $wpdb->delete(
            $wpdb->prefix . 'odad_refresh_tokens',
            [ 'user_id' => $user_id ],
            [ '%d' ]
        );
    }

    /**
     * Delete all expired tokens (called by daily WP-Cron job).
     */
    public function purge_expired(): void {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- no user input, table name sanitised.
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}odad_refresh_tokens WHERE expires_at < NOW()"
        );
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private function hash( string $raw_token ): string {
        return hash( 'sha256', $raw_token );
    }

    private function delete_by_id( int $id ): void {
        global $wpdb;

        $wpdb->delete(
            $wpdb->prefix . 'odad_refresh_tokens',
            [ 'id' => $id ],
            [ '%d' ]
        );
    }
}
