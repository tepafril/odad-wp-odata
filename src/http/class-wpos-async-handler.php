<?php
/**
 * ODAD_Async_Handler — WP-Cron-backed async request processing.
 *
 * Flow:
 *   1. Router detects "Prefer: respond-async" on an incoming request.
 *   2. Calls queue() → stores serialised request data in a transient and
 *      schedules a one-off WP-Cron event.  Returns an opaque job ID.
 *   3. Returns 202 Accepted with Location: /odata/v4/$status/{job_id}.
 *   4. WP-Cron fires execute_job() → runs the actual query and stores the
 *      result (or any error) in a second transient.
 *   5. Client polls GET /odata/v4/$status/{job_id} which calls get_status().
 *
 * Transients:
 *   ODAD_async_job_{id}        → serialised job payload (TTL: 1 hour)
 *   ODAD_async_result_{id}     → serialised result     (TTL: 1 hour)
 *
 * @package WPOS
 */

defined( 'ABSPATH' ) || exit;

class ODAD_Async_Handler {

    /** WP-Cron hook prefix. */
    private const CRON_HOOK_PREFIX = 'ODAD_async_job_';

    /** Transient key prefix for job payloads. */
    private const TRANSIENT_JOB_PREFIX = 'ODAD_async_job_';

    /** Transient key prefix for job results. */
    private const TRANSIENT_RESULT_PREFIX = 'ODAD_async_result_';

    /** Time-to-live for job/result transients (seconds). */
    private const TTL = HOUR_IN_SECONDS;

    /**
     * @param object $query_engine ODAD_Query_Engine instance.
     */
    public function __construct(
        private readonly object $query_engine,
    ) {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Queue a request as a background WP-Cron job.
     *
     * Serialises the request and schedules a one-off cron event.
     *
     * @param ODAD_Request $request Incoming OData request.
     * @param WP_User      $user    Authenticated user at the time of the request.
     * @return string Opaque job ID (UUID v4 format).
     */
    public function queue( ODAD_Request $request, WP_User $user ): string {
        $job_id = $this->generate_job_id();

        $payload = [
            'request'    => $request,
            'user_id'    => $user->ID,
            'queued_at'  => time(),
            'status'     => 'queued',
        ];

        set_transient(
            self::TRANSIENT_JOB_PREFIX . $job_id,
            $payload,
            self::TTL
        );

        // Schedule the cron event to fire as soon as possible (timestamp = now).
        wp_schedule_single_event(
            time(),
            self::CRON_HOOK_PREFIX . $job_id,
            [ $job_id ]
        );

        return $job_id;
    }

    /**
     * Retrieve the status and result of a queued job.
     *
     * Possible return shapes:
     *   ['status' => 'queued']                                  — not yet started
     *   ['status' => 'processing']                              — cron is running
     *   ['status' => 'complete', 'result' => array]             — finished OK
     *   ['status' => 'error',   'message' => string]            — finished with error
     *   ['status' => 'not_found']                               — unknown / expired job
     *
     * @param string $job_id The job ID returned by queue().
     * @return array Status array.
     */
    public function get_status( string $job_id ): array {
        $result = get_transient( self::TRANSIENT_RESULT_PREFIX . $job_id );

        if ( false !== $result && is_array( $result ) ) {
            return $result;
        }

        $payload = get_transient( self::TRANSIENT_JOB_PREFIX . $job_id );

        if ( false === $payload ) {
            return [ 'status' => 'not_found' ];
        }

        return [ 'status' => $payload['status'] ?? 'queued' ];
    }

    /**
     * WP-Cron callback: execute the queued request and store the result.
     *
     * This method is registered as the handler for the per-job cron hook
     * (ODAD_async_job_{job_id}).  It retrieves the serialised payload, runs
     * the query engine, and writes the result to a transient.
     *
     * @param string $job_id The job ID to execute.
     */
    public function execute_job( string $job_id ): void {
        $payload = get_transient( self::TRANSIENT_JOB_PREFIX . $job_id );

        if ( false === $payload || ! is_array( $payload ) ) {
            // Job expired or was already processed.
            return;
        }

        // Mark as processing so status polling reflects current state.
        $payload['status'] = 'processing';
        set_transient( self::TRANSIENT_JOB_PREFIX . $job_id, $payload, self::TTL );

        /** @var ODAD_Request $request */
        $request = $payload['request'];
        $user_id = (int) ( $payload['user_id'] ?? 0 );
        $user    = $user_id > 0 ? get_user_by( 'id', $user_id ) : new WP_User();
        if ( false === $user ) {
            $user = new WP_User();
        }

        try {
            $result = $this->query_engine->execute( $request, $user );

            $result_data = [
                'status'       => 'complete',
                'result'       => [
                    'rows'        => $result->rows,
                    'total_count' => $result->total_count,
                    'next_link'   => $result->next_link,
                ],
                'completed_at' => time(),
            ];
        } catch ( \Throwable $e ) {
            $result_data = [
                'status'  => 'error',
                'message' => $e->getMessage(),
            ];
        }

        set_transient(
            self::TRANSIENT_RESULT_PREFIX . $job_id,
            $result_data,
            self::TTL
        );

        // Clean up the job payload transient.
        delete_transient( self::TRANSIENT_JOB_PREFIX . $job_id );
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Generate a pseudo-UUID v4 job identifier.
     *
     * Uses wp_generate_uuid4() when available (WP 4.7+), falling back to a
     * crypto-safe random hex string.
     *
     * @return string Unique job ID.
     */
    private function generate_job_id(): string {
        if ( function_exists( 'wp_generate_uuid4' ) ) {
            return wp_generate_uuid4();
        }

        // Fallback: 32 hex characters of random data.
        try {
            return bin2hex( random_bytes( 16 ) );
        } catch ( \Exception $e ) {
            return md5( uniqid( (string) mt_rand(), true ) );
        }
    }
}
