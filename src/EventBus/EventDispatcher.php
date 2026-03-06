<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\EventBus;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\StoppableEventInterface;

use function get_class;

class EventDispatcher implements EventDispatcherInterface, ListenerProviderInterface
{
    private array $listeners = [];

    public function subscribe(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    public function getListenersForEvent(object $event): iterable
    {
        return $this->listeners[get_class($event)] ?? [];
    }

    public function dispatch(object $event): object
    {
        foreach ($this->getListenersForEvent($event) as $listener) {
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                break;
            }
            $listener($event);
        }

        return $event;
    }
}
