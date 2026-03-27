<?php
defined( 'ABSPATH' ) || exit;

class ODAD_Container {

    private array $factories  = [];
    private array $singletons = [];

    public function singleton( string $id, callable $factory ): void {
        $this->factories[ $id ] = $factory;
    }

    public function get( string $id ): mixed {
        if ( ! isset( $this->singletons[ $id ] ) ) {
            if ( ! isset( $this->factories[ $id ] ) ) {
                throw new \RuntimeException( "No binding for: {$id}" );
            }
            $this->singletons[ $id ] = ( $this->factories[ $id ] )( $this );
        }
        return $this->singletons[ $id ];
    }

    public function has( string $id ): bool {
        return isset( $this->factories[ $id ] ) || isset( $this->singletons[ $id ] );
    }
}
