<?php

namespace WPMedia\PHPStanWP\Tests\Data;

use WPMedia\EventManager\SubscriberInterface;

/**
 * Subscriber missing required @filter/@action annotations
 */
class MissingAnnotationSubscriber implements SubscriberInterface
{
    public function get_subscribed_events(): array
    {
        return [
            // This one is missing annotation
            'the_content' => 'filterContent',
            // @filter - this one is correct
            'the_title' => 'filterTitle',
            // Also missing annotation
            'init' => 'onInit',
        ];
    }

    public function filterContent(string $content): string
    {
        return $content;
    }

    public function filterTitle(string $title): string
    {
        return $title;
    }

    public function onInit(): void
    {
    }
}
