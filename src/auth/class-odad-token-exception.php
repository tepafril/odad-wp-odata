<?php
defined( 'ABSPATH' ) || exit;

class ODAD_Token_Exception extends \RuntimeException {

    public function __construct(
        string $message,
        public readonly string $code_slug, // token_expired|token_invalid|token_not_found|token_revoked
    ) {
        parent::__construct( $message );
    }
}
