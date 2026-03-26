<?php
/**
 * Unit tests for WPOS_Container.
 */

use PHPUnit\Framework\TestCase;

class ContainerTest extends TestCase {

    private WPOS_Container $container;

    protected function setUp(): void {
        $this->container = new WPOS_Container();
    }

    // ── Singleton behaviour ───────────────────────────────────────────────────

    public function test_get_returns_same_instance_for_singleton(): void {
        $this->container->singleton( 'service', fn() => new stdClass() );

        $first  = $this->container->get( 'service' );
        $second = $this->container->get( 'service' );

        $this->assertSame( $first, $second );
    }

    // ── Unregistered service ──────────────────────────────────────────────────

    public function test_get_throws_for_unregistered_service(): void {
        $this->expectException( \RuntimeException::class );

        $this->container->get( 'nonexistent' );
    }

    // ── Lazy initialization ───────────────────────────────────────────────────

    public function test_factory_not_invoked_until_get_called(): void {
        $invoked = false;

        $this->container->singleton( 'lazy', function () use ( &$invoked ) {
            $invoked = true;
            return new stdClass();
        } );

        $this->assertFalse( $invoked, 'Factory must not be called on singleton() registration' );

        $this->container->get( 'lazy' );

        $this->assertTrue( $invoked, 'Factory must be called on first get()' );
    }

    // ── has() ─────────────────────────────────────────────────────────────────

    public function test_has_returns_true_for_registered_service(): void {
        $this->container->singleton( 'foo', fn() => new stdClass() );

        $this->assertTrue( $this->container->has( 'foo' ) );
    }

    public function test_has_returns_false_for_unregistered_service(): void {
        $this->assertFalse( $this->container->has( 'missing' ) );
    }

    // ── Container passed to factory ───────────────────────────────────────────

    public function test_factory_receives_container_as_argument(): void {
        $received = null;

        $this->container->singleton( 'test', function ( $c ) use ( &$received ) {
            $received = $c;
            return new stdClass();
        } );

        $this->container->get( 'test' );

        $this->assertSame( $this->container, $received );
    }
}
