<?php

declare(strict_types=1);

namespace WPMedia\PHPStanWP\Tests\Rules;

use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use WPMedia\PHPStanWP\SubscriberCallbackRule;

/**
 * Test case for SubscriberCallbackRule.
 *
 * @extends RuleTestCase<SubscriberCallbackRule>
 */
class SubscriberCallbackRuleTest extends RuleTestCase {

	/**
	 * Get the rule being tested.
	 *
	 * @return Rule<ClassMethod>
	 */
	protected function getRule(): Rule {
		return new SubscriberCallbackRule();
	}

	/**
	 * Get additional config files needed for testing.
	 *
	 * @return array<string>
	 */
	public static function getAdditionalConfigFiles(): array {
		// Path to your project's phpstan.neon, or extension.neon in case of custom extension packages.
		// This is only necessary if your custom rule relies on some extra configuration and other extensions.
		return array( __DIR__ . '/../extension.neon' );
	}

	/**
	 * Test that valid subscriber passes without errors.
	 *
	 * @return void
	 */
	public function testValidSubscriber(): void {
		$this->analyse( array( __DIR__ . '/data/ValidSubscriber.php' ), array() );
	}

	/**
	 * Test that missing @filter/@action annotations are detected.
	 *
	 * @return void
	 */
	public function testMissingAnnotation(): void {
		$this->analyse(
			array( __DIR__ . '/data/MissingAnnotationSubscriber.php' ),
			array(
				array(
					'Hook key missing required @filter or @action annotation before this array element',
					16,
				),
				array(
					'Hook key missing required @filter or @action annotation before this array element',
					20,
				),
			)
		);
	}

	/**
	 * Test that filter callbacks without return statements are detected.
	 *
	 * @return void
	 */
	public function testFilterMissingReturn(): void {
		$this->analyse(
			array( __DIR__ . '/data/InvalidFilterReturnSubscriber.php' ),
			array(
				array(
					'Filter callback return statement is missing.',
					16,
				),
			)
		);
	}

	/**
	 * Test that action callbacks with return statements are detected.
	 *
	 * @return void
	 */
	public function testActionUnexpectedReturn(): void {
		$this->analyse(
			array( __DIR__ . '/data/InvalidActionReturnSubscriber.php' ),
			array(
				array(
					'Action callback returns int but should not return anything.',
					16,
				),
			)
		);
	}

	/**
	 * Test that parameter count mismatches are detected.
	 *
	 * @return void
	 */
	public function testParameterCountMismatch(): void {
		$this->analyse(
			array( __DIR__ . '/data/ParameterCountMismatchSubscriber.php' ),
			array(
				array(
					'Callback expects 1 parameter, $accepted_args is set to 3.',
					16,
				),
				array(
					'Callback expects 2 parameters, $accepted_args is set to 0.',
					20,
				),
			)
		);
	}

	/**
	 * Test that non-subscriber classes are ignored.
	 *
	 * @return void
	 */
	public function testNonSubscriberClassIgnored(): void {
		$this->analyse( array( __DIR__ . '/data/NonSubscriberClass.php' ), array() );
	}
}
