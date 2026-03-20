<?php

declare(strict_types=1);

/**
 * Example: Symfony Contracts — EventDispatcherInterface.
 *
 * Demonstrates implementing a minimal event dispatcher that satisfies
 * the EventDispatcherInterface contract and dispatching events.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

// --- Domain events ---

final class User_Registered extends Event
{
    public function __construct(
        public readonly string $email,
        public readonly string $username,
    ) {}
}

final class Order_Placed extends Event
{
    public function __construct(
        public readonly int $order_id,
        public readonly float $total,
    ) {}
}

// --- Minimal event dispatcher implementation ---

final class Simple_Event_Dispatcher implements EventDispatcherInterface
{
    /** @var array<string, list<callable>> */
    private array $listeners = [];

    public function dispatch(object $event, string $event_name = null): object
    {
        $name = $event_name ?? $event::class;

        foreach ($this->listeners[$name] ?? [] as $listener) {
            if ($event instanceof Event && $event->isPropagationStopped()) {
                break;
            }
            $listener($event, $name, $this);
        }

        return $event;
    }

    public function add_listener(string $event_name, callable $listener): void
    {
        $this->listeners[$event_name][] = $listener;
    }
}

// --- Usage ---

$dispatcher = new Simple_Event_Dispatcher();

// Register listeners.
$dispatcher->add_listener(User_Registered::class, static function (User_Registered $event): void {
    echo "Sending welcome email to {$event->email}\n";
});

$dispatcher->add_listener(User_Registered::class, static function (User_Registered $event): void {
    echo "Creating profile for {$event->username}\n";
});

$dispatcher->add_listener(Order_Placed::class, static function (Order_Placed $event): void {
    echo "Processing order #{$event->order_id} (total: \${$event->total})\n";
});

// Dispatch events.
$dispatcher->dispatch(new User_Registered('alice@example.com', 'alice'));
// Output:
//   Sending welcome email to alice@example.com
//   Creating profile for alice

$dispatcher->dispatch(new Order_Placed(42, 99.95));
// Output:
//   Processing order #42 (total: $99.95)

// Demonstrate stopPropagation.
$dispatcher->add_listener(User_Registered::class, static function (User_Registered $event): void {
    echo "Guard: stopping propagation for {$event->email}\n";
    $event->stopPropagation();
});

$dispatcher->add_listener(User_Registered::class, static function (User_Registered $event): void {
    echo "This listener will NOT run.\n";
});

$dispatcher->dispatch(new User_Registered('blocked@example.com', 'blocked'));
// Output:
//   Sending welcome email to blocked@example.com
//   Creating profile for blocked
//   Guard: stopping propagation for blocked@example.com
//   (fourth listener is skipped)
