<?php

namespace WPMedia\PHPStanWP\Tests\Data;

use WPMedia\EventManager\SubscriberInterface;

/**
 * Subscriber with action callbacks that incorrectly return values
 */
class InvalidActionReturnSubscriber implements SubscriberInterface
{
    public function get_subscribed_events(): array
    {
        return [
            // @action - but callback returns int (ERROR)
            'init' => 'onInit',
            // @action - correct, returns void
            'wp_loaded' => 'onWpLoaded',
        ];
    }

    /**
     * This action incorrectly returns a value
     */
    public function onInit(): int
    {
        return 42;
    }

    public function onWpLoaded(): void
    {
        // Correct - returns void
    }
}
