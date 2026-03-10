<?php

namespace WPMedia\EventManager;

/**
 * Stub interface for testing - matches one of the interfaces checked by SubscriberCallbackRule
 */
interface SubscriberInterface {

	/**
	 * @return array<string, mixed>
	 */
	public function get_subscribed_events(): array;
}
