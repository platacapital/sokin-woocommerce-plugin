<?php
/**
 * Register all actions and filters for the plugin.
 *
 * @package Platasokin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hook loader.
 */
class Platasokin_Loader {

	/**
	 * Registered actions.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	protected $actions;

	/**
	 * Registered filters.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	protected $filters;

	public function __construct() {
		$this->actions = array();
		$this->filters = array();
	}

	/**
	 * Add a WordPress action.
	 *
	 * @param string $hook          Hook name.
	 * @param object $component     Object instance.
	 * @param string $callback      Method name.
	 * @param int    $priority      Priority.
	 * @param int    $accepted_args Accepted argument count.
	 */
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Add a WordPress filter.
	 *
	 * @param string $hook          Hook name.
	 * @param object $component     Object instance.
	 * @param string $callback      Method name.
	 * @param int    $priority      Priority.
	 * @param int    $accepted_args Accepted argument count.
	 */
	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * @param array<int, array<string, mixed>> $hooks         Existing hooks.
	 * @param string                           $hook          Hook name.
	 * @param object                           $component     Object instance.
	 * @param string                           $callback      Method name.
	 * @param int                              $priority      Priority.
	 * @param int                              $accepted_args Accepted argument count.
	 * @return array<int, array<string, mixed>>
	 */
	private function add( $hooks, $hook, $component, $callback, $priority, $accepted_args ) {
		$hooks[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);

		return $hooks;
	}

	/**
	 * Register hooks with WordPress.
	 */
	public function run() {
		foreach ( $this->filters as $hook ) {
			add_filter( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}

		foreach ( $this->actions as $hook ) {
			add_action( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}
	}
}
