<?php

namespace WPMedia\PHPStanWP\Tests\Data;

use WPMedia\EventManager\SubscriberInterface;

/**
 * Valid subscriber with correct annotations and return types
 */
class ValidSubscriber implements SubscriberInterface
{
    public function get_subscribed_events(): array
    {
        return [
            // @filter
            'the_content' => 'filterContent',
            // @action
            'init' => 'onInit',
            // @filter with priority
            'the_title' => ['filterTitle', 10],
            // @action with priority and accepted_args
            'save_post' => ['onSavePost', 10, 2],
        ];
    }

    public function filterContent(string $content): string
    {
        return $content . ' filtered';
    }

    public function onInit(): void
    {
        // Action does not return anything
    }

    public function filterTitle(string $title): string
    {
        return strtoupper($title);
    }

    public function onSavePost(int $postId, \WP_Post $post): void
    {
        // Action with 2 parameters
    }
}
