<?php

namespace WPMedia\PHPStanWP\Tests\Data;

use WPMedia\EventManager\SubscriberInterface;

/**
 * Subscriber with filter callbacks that incorrectly return void
 */
class InvalidFilterReturnSubscriber implements SubscriberInterface
{
    public function get_subscribed_events(): array
    {
        return [
            // @filter - but callback returns void (ERROR)
            'the_content' => 'filterContent',
            // @filter - correct, returns string
            'the_title' => 'filterTitle',
        ];
    }

    /**
     * This filter incorrectly returns void
     */
    public function filterContent(string $content): void
    {
        // Filter should return something!
    }

    public function filterTitle(string $title): string
    {
        return $title;
    }
}
