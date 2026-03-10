<?php

namespace WPMedia\PHPStanWP\Tests\Data;

use WPMedia\EventManager\SubscriberInterface;

/**
 * Subscriber with parameter count mismatches
 */
class ParameterCountMismatchSubscriber implements SubscriberInterface
{
    public function get_subscribed_events(): array
    {
        return [
            // @action - accepted_args=3 but method has 1 parameter (ERROR)
            'save_post' => ['onSavePost', 10, 3],
            // @filter - accepted_args=2, method has 2 required params (OK)
            'the_content' => ['filterContent', 10, 2],
            // @action - accepted_args=0 but method requires 2 params (ERROR)
            'update_option' => ['onUpdateOption', 10, 0],
        ];
    }

    public function onSavePost(int $postId): void
    {
        // Method has 1 parameter but accepted_args is 3
    }

    public function filterContent(string $content, int $postId): string
    {
        return $content;
    }

    public function onUpdateOption(string $option, mixed $value): void
    {
        // Method requires 2 params but accepted_args is 0
    }
}
