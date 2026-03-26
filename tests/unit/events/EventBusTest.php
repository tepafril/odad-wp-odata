<?php
/**
 * Unit tests for WPOS_Event_Bus.
 */

use PHPUnit\Framework\TestCase;

// ── Concrete event classes used only in this test ─────────────────────────────

class Test_Simple_Event implements WPOS_Event {}

class Test_Other_Event implements WPOS_Event {}

class Test_Stoppable_Event implements WPOS_Stoppable_Event {
    private bool $stopped = false;

    public function is_stopped(): bool {
        return $this->stopped;
    }

    public function stop_propagation(): void {
        $this->stopped = true;
    }
}

// ── Concrete listener helper ──────────────────────────────────────────────────

class Test_Capturing_Listener implements WPOS_Event_Listener {
    public int   $call_count  = 0;
    public array $received    = [];
    private bool $should_stop = false;

    public function __construct(
        private readonly string $event_class,
        bool $stop = false
    ) {
        $this->should_stop = $stop;
    }

    public function get_event(): string {
        return $this->event_class;
    }

    public function handle( WPOS_Event $event ): void {
        $this->call_count++;
        $this->received[] = $event;

        if ( $this->should_stop && $event instanceof WPOS_Stoppable_Event ) {
            $event->stop_propagation();
        }
    }
}

// ── Test class ───────────────────────────────────────────────────────────────

class EventBusTest extends TestCase {

    private WPOS_Event_Bus $bus;

    protected function setUp(): void {
        $this->bus = new WPOS_Event_Bus();
    }

    /**
     * A subscribed listener is called when a matching event is dispatched.
     */
    public function test_listener_is_called_for_matching_event(): void {
        $listener = new Test_Capturing_Listener( Test_Simple_Event::class );
        $this->bus->subscribe( $listener );

        $event = new Test_Simple_Event();
        $this->bus->dispatch( $event );

        $this->assertSame( 1, $listener->call_count );
        $this->assertSame( $event, $listener->received[0] );
    }

    /**
     * A listener is NOT called when a different event type is dispatched.
     */
    public function test_listener_not_called_for_different_event(): void {
        $listener = new Test_Capturing_Listener( Test_Simple_Event::class );
        $this->bus->subscribe( $listener );

        $this->bus->dispatch( new Test_Other_Event() );

        $this->assertSame( 0, $listener->call_count );
    }

    /**
     * When a stoppable event has stop_propagation() called, subsequent
     * listeners registered for that event are not invoked.
     */
    public function test_stoppable_event_stops_dispatch(): void {
        $stopping_listener = new Test_Capturing_Listener( Test_Stoppable_Event::class, stop: true );
        $second_listener   = new Test_Capturing_Listener( Test_Stoppable_Event::class );

        $this->bus->subscribe( $stopping_listener );
        $this->bus->subscribe( $second_listener );

        $this->bus->dispatch( new Test_Stoppable_Event() );

        $this->assertSame( 1, $stopping_listener->call_count, 'First listener must be called once' );
        $this->assertSame( 0, $second_listener->call_count,   'Second listener must NOT be called after stop' );
    }

    /**
     * Multiple listeners for the same event are called in registration order.
     */
    public function test_multiple_listeners_called_in_registration_order(): void {
        $order  = [];
        $first  = new class ( Test_Simple_Event::class, $order, 'first' ) implements WPOS_Event_Listener {
            public function __construct(
                private string $event_class,
                private array &$order,
                private string $label
            ) {}
            public function get_event(): string { return $this->event_class; }
            public function handle( WPOS_Event $event ): void { $this->order[] = $this->label; }
        };
        $second = new class ( Test_Simple_Event::class, $order, 'second' ) implements WPOS_Event_Listener {
            public function __construct(
                private string $event_class,
                private array &$order,
                private string $label
            ) {}
            public function get_event(): string { return $this->event_class; }
            public function handle( WPOS_Event $event ): void { $this->order[] = $this->label; }
        };

        $this->bus->subscribe( $first );
        $this->bus->subscribe( $second );

        $this->bus->dispatch( new Test_Simple_Event() );

        $this->assertSame( [ 'first', 'second' ], $order );
    }

    /**
     * dispatch() returns the event object it received.
     */
    public function test_dispatch_returns_the_event(): void {
        $event    = new Test_Simple_Event();
        $returned = $this->bus->dispatch( $event );

        $this->assertSame( $event, $returned );
    }
}
