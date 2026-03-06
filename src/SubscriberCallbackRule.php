<?php

namespace WPMedia\PHPStanWP;

use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\MixedType;
use PHPStan\Type\NeverType;
use PHPStan\Type\Type;
use PHPStan\Type\VerbosityLevel;

/**
 * PHPStan rule to validate get_subscribed_events() callbacks for SubscriberInterface implementations.
 *
 * Ensures that:
 * - All array elements have @filter or @action PHPDoc annotations
 * - Filter callbacks return non-void values
 * - Action callbacks return void
 * - Parameter counts match accepted_args when specified
 *
 * @implements Rule<ClassMethod>
 */
class SubscriberCallbackRule implements Rule {

	/**
	 * Get the node type that this rule applies to.
	 *
	 * @return string
	 */
	public function getNodeType(): string {
		return ClassMethod::class;
	}

	/**
	 * Process the node and return any rule violations.
	 *
	 * @param Node  $node  The node being analyzed.
	 * @param Scope $scope The scope in which the node is found.
	 *
	 * @return array<\PHPStan\Rules\RuleError>
	 */
	public function processNode( Node $node, Scope $scope ): array {
		/** @var ClassMethod $node */
		// Only process get_subscribed_events() methods.
		if ( $node->name->toString() !== 'get_subscribed_events' ) {
			return array();
		}

		// Check if class implements SubscriberInterface.
		if ( ! $this->implementsSubscriberInterface( $scope ) ) {
			return array();
		}

		// Find the array to analyze.
		/** @var ClassMethod $node */
		$array_node = $this->findArrayNode( $node, $scope );

		if ( null === $array_node ) {
			// Skip validation for delegated implementations or complex patterns.
			return array();
		}

		// Collect all errors for this method.
		$errors = array();

		// Process each array element.
		foreach ( $array_node->items as $item ) {
			// Parse PHPDoc annotation for hook type.
			$hook_type = $this->getHookType( $item );

			if ( null === $hook_type ) {
				// Missing annotation error.
				$errors[] = RuleErrorBuilder::message( 'Hook key missing required @filter or @action annotation before this array element' )
					->line( $item->getStartLine() )
					->identifier( 'wpmedia.subscriber.missingAnnotation' )
					->build();
				continue;
			}

			// Extract callback information.
			$callbacks = $this->extractCallbacks( $item->value );

			// Validate each callback.
			foreach ( $callbacks as $callback_info ) {
				$method_name   = $callback_info['method'];
				$accepted_args = $callback_info['accepted_args'] ?? null;

				// Validate callback return type.
				$return_type_errors = $this->validateCallbackReturnType(
					$scope,
					$method_name,
					$hook_type,
					$item->getStartLine()
				);
				$errors             = array_merge( $errors, $return_type_errors );

				// Validate parameter count if accepted_args is specified.
				if ( null !== $accepted_args ) {
					$param_errors = $this->validateParameterCount(
						$scope,
						$method_name,
						$accepted_args,
						$item->getStartLine()
					);
					$errors       = array_merge( $errors, $param_errors );
				}
			}
		}

		return $errors;
	}

	/**
	 * Check if the current class implements SubscriberInterface
	 *
	 * @param Scope $scope The current scope.
	 *
	 * @return bool
	 */
	private function implementsSubscriberInterface( Scope $scope ): bool {
		$class_reflection = $scope->getClassReflection();

		if ( null === $class_reflection ) {
			return false;
		}

		$interfaces = array(
			'WP_Rocket\Event_Management\Subscriber_Interface',
			'Imagify\EventManagement\SubscriberInterface',
			'WPMedia\EventManager\SubscriberInterface',
			'WPMedia\BackWPup\EventManagement\SubscriberInterface',
		);

		foreach ( $interfaces as $interface ) {
			if ( $class_reflection->implementsInterface( $interface ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Find the array node to analyze from the method body
	 * Handles inline arrays and simple variable assignments
	 *
	 * @param ClassMethod $method The method being analyzed.
	 * @param Scope       $scope  The current scope.
	 *
	 * @return Array_|null
	 */
	private function findArrayNode( ClassMethod $method, Scope $scope ): ?Array_ {
		$stmts = $method->getStmts();

		if ( null === $stmts ) {
			return null;
		}

		$variable_arrays = array();

		// Look through statements.
		foreach ( $stmts as $stmt ) {
			// Direct return of array.
			if ( $stmt instanceof Return_ && $stmt->expr instanceof Array_ ) {
				$array = $stmt->expr;
				// Skip empty arrays.
				if ( empty( $array->items ) ) {
					return null;
				}
				return $array;
			}

			// Variable assignment to array.
			if ( $stmt instanceof Node\Stmt\Expression
				&& $stmt->expr instanceof Assign
				&& $stmt->expr->var instanceof Variable
				&& $stmt->expr->expr instanceof Array_
			) {
				$var_name = $stmt->expr->var->name;
				if ( is_string( $var_name ) ) {
					$variable_arrays[ $var_name ] = $stmt->expr->expr;
				}
			}

			// Return of variable.
			if ( $stmt instanceof Return_ && $stmt->expr instanceof Variable ) {
				$var_name = $stmt->expr->name;
				if ( is_string( $var_name ) && isset( $variable_arrays[ $var_name ] ) ) {
					$array = $variable_arrays[ $var_name ];
					// Skip empty arrays.
					if ( empty( $array->items ) ) {
						return null;
					}
					return $array;
				}
			}
		}

		// Delegated or complex implementation - skip validation.
		return null;
	}

	/**
	 * Get hook type (@filter or @action) from array item comment
	 *
	 * @param ArrayItem $item The array item to check.
	 *
	 * @return string|null
	 */
	private function getHookType( ArrayItem $item ): ?string {
		$comments = $item->getAttribute( 'comments' );

		if ( empty( $comments ) ) {
			return null;
		}

		/** @var array<\PhpParser\Comment> $comments */
		// Check all comments attached to this node.
		foreach ( $comments as $comment ) {
			$text = $comment->getText();

			// Match // @filter or // @action.
			if ( preg_match( '/\/\/\s*@filter\b/i', $text ) ) {
				return 'filter';
			}

			if ( preg_match( '/\/\/\s*@action\b/i', $text ) ) {
				return 'action';
			}
		}

		return null;
	}

	/**
	 * Extract callback information from array value
	 * Handles: 'method', ['method'], ['method', priority], ['method', priority, accepted_args]
	 * and nested arrays for multiple callbacks per hook
	 *
	 * @param Node\Expr $value The node value to extract callbacks from.
	 *
	 * @return array<array{method: string, accepted_args: int|null}> Array of callback info: [['method' => string, 'accepted_args' => ?int], ...]
	 */
	private function extractCallbacks( Node\Expr $value ): array {
		$callbacks = array();

		// Simple string method name.
		if ( $value instanceof String_ ) {
			$callbacks[] = array(
				'method'        => $value->value,
				'accepted_args' => null,
			);
			return $callbacks;
		}

		// Array format.
		if ( $value instanceof Array_ ) {
			// Check if nested array (multiple callbacks for same hook).
			$is_nested = false;
			if ( ! empty( $value->items ) ) {
				$first_item = $value->items[0];
				if ( $first_item->value instanceof Array_ ) {
					$is_nested = true;
				}
			}

			if ( $is_nested ) {
				// Process each nested array.
				foreach ( $value->items as $nested_item ) {
					if ( ! ( $nested_item->value instanceof Array_ ) ) {
						continue;
					}
					$callbacks = array_merge( $callbacks, $this->extractCallbackFromArray( $nested_item->value ) );
				}
			} else {
				// Single callback array.
				$callbacks = $this->extractCallbackFromArray( $value );
			}
		}

		return $callbacks;
	}

	/**
	 * Extract callback info from array node
	 * Format: ['method'], ['method', priority], or ['method', priority, accepted_args]
	 *
	 * @param Array_ $array_param The array to extract callback info from.
	 *
	 * @return array<array{method: string, accepted_args: int|null}>
	 */
	private function extractCallbackFromArray( Array_ $array_param ): array {
		if ( empty( $array_param->items ) ) {
			return array();
		}

		$items = array_values( $array_param->items );

		// First element should be method name.
		$first_item = $items[0];

		if ( ! ( $first_item->value instanceof String_ ) ) {
			return array();
		}

		$method_name   = $first_item->value->value;
		$accepted_args = null;

		// Third element (index 2) is accepted_args.
		if ( isset( $items[2] ) && $items[2]->value instanceof Int_ ) {
			$accepted_args = $items[2]->value->value;
		}

		return array(
			array(
				'method'        => $method_name,
				'accepted_args' => $accepted_args,
			),
		);
	}

	/**
	 * Validate callback return type based on hook type
	 *
	 * @param Scope  $scope       The current scope.
	 * @param string $method_name The callback method name.
	 * @param string $hook_type   The type of hook ('filter' or 'action').
	 * @param int    $line_number The line number for error reporting.
	 *
	 * @return array<\PHPStan\Rules\RuleError>
	 */
	private function validateCallbackReturnType( Scope $scope, string $method_name, string $hook_type, int $line_number ): array {
		$errors           = array();
		$class_reflection = $scope->getClassReflection();

		if ( null === $class_reflection ) {
			return $errors;
		}

		// Check if method exists.
		if ( ! $class_reflection->hasNativeMethod( $method_name ) ) {
			// Let PHPStan's native undefined method detection handle this.
			return $errors;
		}

		$method_reflection = $class_reflection->getNativeMethod( $method_name );
		$variants          = $method_reflection->getVariants();

		if ( empty( $variants ) ) {
			return $errors;
		}

		$return_type = $variants[0]->getReturnType();

		if ( 'filter' === $hook_type ) {
			$errors = array_merge( $errors, $this->validateFilterReturnType( $return_type, $line_number ) );
		} else {
			$errors = array_merge( $errors, $this->validateActionReturnType( $return_type, $line_number ) );
		}

		return $errors;
	}

	/**
	 * Validate filter callback return type (must return non-void)
	 *
	 * @param Type $return_type The return type to validate.
	 * @param int  $line_number The line number for error reporting.
	 *
	 * @return array<\PHPStan\Rules\RuleError>
	 */
	private function validateFilterReturnType( Type $return_type, int $line_number ): array {
		// Skip non-explicit mixed types (will be handled by PHPStan).
		if ( $return_type instanceof MixedType ) {
			return array();
		}

		// Filter must NOT return void/never.
		if ( ( $return_type->isVoid()->no() ) && ! $this->isExplicitNever( $return_type ) ) {
			return array();
		}

		return array(
			RuleErrorBuilder::message( 'Filter callback return statement is missing.' )
				->line( $line_number )
				->identifier( 'wpmedia.subscriber.filter.missingReturn' )
				->build(),
		);
	}

	/**
	 * Validate action callback return type (must return void)
	 *
	 * @param Type $return_type The return type to validate.
	 * @param int  $line_number The line number for error reporting.
	 *
	 * @return array<\PHPStan\Rules\RuleError>
	 */
	private function validateActionReturnType( Type $return_type, int $line_number ): array {
		// Skip non-explicit mixed types (will be handled by PHPStan).
		if ( $return_type instanceof MixedType && ! $return_type->isExplicitMixed() ) {
			return array();
		}

		// Action must return void or explicit never.
		if ( $return_type->isVoid()->yes() || $this->isExplicitNever( $return_type ) ) {
			return array();
		}

		return array(
			RuleErrorBuilder::message(
				sprintf(
					'Action callback returns %s but should not return anything.',
					$return_type->describe( VerbosityLevel::getRecommendedLevelByType( $return_type ) )
				)
			)
				->line( $line_number )
				->identifier( 'wpmedia.subscriber.action.unexpectedReturn' )
				->build(),
		);
	}

	/**
	 * Check if type is explicit never
	 *
	 * @param Type $type The type to check.
	 *
	 * @return bool
	 */
	private function isExplicitNever( Type $type ): bool {
		return $type instanceof NeverType && $type->isExplicit();
	}

	/**
	 * Validate parameter count against accepted_args
	 *
	 * @param Scope  $scope         The current scope.
	 * @param string $method_name   The callback method name.
	 * @param int    $accepted_args The number of accepted arguments.
	 * @param int    $line_number   The line number for error reporting.
	 *
	 * @return array<\PHPStan\Rules\RuleError>
	 */
	private function validateParameterCount( Scope $scope, string $method_name, int $accepted_args, int $line_number ): array {
		$errors           = array();
		$class_reflection = $scope->getClassReflection();

		if ( null === $class_reflection ) {
			return $errors;
		}

		// Check if method exists.
		if ( ! $class_reflection->hasNativeMethod( $method_name ) ) {
			return $errors;
		}

		$method_reflection = $class_reflection->getNativeMethod( $method_name );
		$variants          = $method_reflection->getVariants();

		if ( empty( $variants ) ) {
			return $errors;
		}

		$variant             = $variants[0];
		$all_parameters      = $variant->getParameters();
		$required_parameters = array_filter(
			$all_parameters,
			static function ( $parameter ): bool {
				return ! $parameter->isOptional();
			}
		);

		$max_args = count( $all_parameters );
		$min_args = count( $required_parameters );

		// Valid: accepted_args within min-max range.
		if ( $min_args <= $accepted_args && $accepted_args <= $max_args ) {
			return $errors;
		}

		// Valid: accepted_args >= minArgs and callback is variadic.
		if ( $min_args <= $accepted_args && $variant->isVariadic() ) {
			return $errors;
		}

		// Special case: no required params but accepted_args is 1 (common pattern).
		if ( 0 === $min_args && 1 === $accepted_args ) {
			return $errors;
		}

		// Build error message.
		$expected_parameters_message = (string) $min_args;
		if ( $max_args !== $min_args ) {
			$expected_parameters_message = sprintf( '%d-%d', $min_args, $max_args );
		}

		$message = ( '1' === $expected_parameters_message )
			? 'Callback expects %s parameter, $accepted_args is set to %d.'
			: 'Callback expects %s parameters, $accepted_args is set to %d.';

		if ( $variant->isVariadic() ) {
			$message = ( 1 === $min_args )
				? 'Callback expects at least %s parameter, $accepted_args is set to %d.'
				: 'Callback expects at least %s parameters, $accepted_args is set to %d.';
		}

		$errors[] = RuleErrorBuilder::message(
			sprintf(
				$message,
				$expected_parameters_message,
				$accepted_args
			)
		)
			->line( $line_number )
			->identifier( 'wpmedia.subscriber.arguments.count' )
			->build();

		return $errors;
	}
}
