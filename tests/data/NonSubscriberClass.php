<?php

namespace WPMedia\PHPStanWP\Tests\Data;

/**
 * Class that does NOT implement SubscriberInterface
 * The rule should not analyze this class
 */
class NonSubscriberClass
{
    public function get_subscribed_events(): array
    {
        return [
            // Missing annotation - but should be ignored since class doesn't implement interface
            'the_content' => 'filterContent',
        ];
    }

    public function filterContent(string $content): void
    {
        // Returns void - but should be ignored
    }
}
