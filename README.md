# PHPStan WP

A PHPStan extension providing custom rules for WordPress projects, specifically designed for WP Media projects

## Overview

This extension adds static analysis rules to ensure proper implementation of WordPress hooks through subscriber classes. It validates that your event subscribers follow best practices for WordPress actions and filters.

## Custom Rules

### SubscriberCallbackRule

Validates `get_subscribed_events()` implementations in classes that implement subscriber interfaces. This rule ensures:

1. **Required Annotations**: All hook registrations must have `@action` or `@filter` PHPDoc annotations
2. **Filter Return Types**: Filter callbacks must return non-void values
3. **Action Return Types**: Action callbacks must return void
4. **Parameter Count Validation**: When `accepted_args` is specified, the callback method must have the correct number of parameters

#### Supported Subscriber Interfaces

- `WP_Rocket\Event_Management\Subscriber_Interface`
- `Imagify\EventManagement\SubscriberInterface`
- `WPMedia\EventManager\SubscriberInterface`
- `WPMedia\BackWPup\EventManagement\SubscriberInterface`

## Installation

Install via Composer:

```bash
composer require --dev wp-media/phpstan-wp
```

If you have [phpstan/extension-installer](https://github.com/phpstan/extension-installer) installed, the extension will be automatically registered.

### Manual Configuration

If you don't use the extension installer, add this to your `phpstan.neon` or `phpstan.neon.dist`:

```neon
includes:
    - vendor/wp-media/phpstan-wp/extension.neon
```

## Usage

### Valid Subscriber Example

```php
<?php

use WPMedia\EventManager\SubscriberInterface;

class MySubscriber implements SubscriberInterface
{
    public function get_subscribed_events(): array
    {
        return [
            // @filter - Filters must return a value
            'the_content' => 'filterContent',
            
            // @action - Actions must return void
            'init' => 'onInit',
            
            // @filter - With priority
            'the_title' => ['filterTitle', 10],
            
            // @action - With priority and accepted_args
            'save_post' => ['onSavePost', 10, 2],
        ];
    }

    /**
     * Filter callback - returns modified content
     */
    public function filterContent(string $content): string
    {
        return $content . ' - filtered';
    }

    /**
     * Action callback - returns void
     */
    public function onInit(): void
    {
        // Perform initialization
    }

    /**
     * Filter callback with priority
     */
    public function filterTitle(string $title): string
    {
        return strtoupper($title);
    }

    /**
     * Action callback with 2 parameters (matching accepted_args)
     */
    public function onSavePost(int $postId, \WP_Post $post): void
    {
        // Handle post save
    }
}
```

### Common Errors and How to Fix Them

#### Missing Annotation

❌ **Invalid** - Missing `@filter` or `@action` annotation:

```php
public function get_subscribed_events(): array
{
    return [
        'the_content' => 'filterContent', // ERROR: Missing annotation
    ];
}
```

✅ **Valid** - Add the annotation before the array element:

```php
public function get_subscribed_events(): array
{
    return [
        // @filter
        'the_content' => 'filterContent',
    ];
}
```

#### Filter with Void Return Type

❌ **Invalid** - Filter callbacks must return a value:

```php
public function get_subscribed_events(): array
{
    return [
        // @filter
        'the_content' => 'filterContent',
    ];
}

public function filterContent(string $content): void // ERROR: Returns void
{
    echo $content;
}
```

✅ **Valid** - Filter callbacks must return the filtered value:

```php
public function get_subscribed_events(): array
{
    return [
        // @filter
        'the_content' => 'filterContent',
    ];
}

public function filterContent(string $content): string
{
    return $content . ' - filtered';
}
```

#### Action with Non-Void Return Type

❌ **Invalid** - Action callbacks must return void:

```php
public function get_subscribed_events(): array
{
    return [
        // @action
        'init' => 'onInit',
    ];
}

public function onInit(): int // ERROR: Returns non-void
{
    return 42;
}
```

✅ **Valid** - Action callbacks must return void:

```php
public function get_subscribed_events(): array
{
    return [
        // @action
        'init' => 'onInit',
    ];
}

public function onInit(): void
{
    // Perform action
}
```

#### Parameter Count Mismatch

❌ **Invalid** - Parameter count doesn't match `accepted_args`:

```php
public function get_subscribed_events(): array
{
    return [
        // @action - accepted_args=3 but method has only 1 parameter
        'save_post' => ['onSavePost', 10, 3], // ERROR
    ];
}

public function onSavePost(int $postId): void
{
    // Missing 2 parameters
}
```

✅ **Valid** - Parameter count matches accepted_args:

```php
public function get_subscribed_events(): array
{
    return [
        // @action
        'save_post' => ['onSavePost', 10, 3],
    ];
}

public function onSavePost(int $postId, \WP_Post $post, bool $update): void
{
    // Now has 3 parameters matching accepted_args
}
```

## Development

### Running Tests

```bash
composer test-unit
```

### Code Style

Check code style:

```bash
composer phpcs
```

Fix code style issues:

```bash
composer phpcbf
```

## Requirements

- PHP 7.4 or higher
- PHPStan 2.0 or higher

## License

GPL-3.0-or-later

## Contributing

Contributions are welcome! Please ensure your code follows the WordPress Coding Standards and includes appropriate tests.