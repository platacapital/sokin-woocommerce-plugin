<?php
/**
 * Core plugin class.
 *
 * @package Platasokin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin orchestration.
 */
class Platasokin_Plugin {

	/**
	 * Hook loader.
	 *
	 * @var Platasokin_Loader
	 */
	protected $loader;

	/**
	 * Human-readable plugin name.
	 *
	 * @var string
	 */
	protected $plugin_name;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	protected $version;

	public function __construct() {
		$this->version = PLATASOKIN_VERSION;
		$this->plugin_name = 'Sokin Pay';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load dependencies.
	 */
	private function load_dependencies() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-platasokin-loader.php';

		$this->loader = new Platasokin_Loader();
	}

	/**
	 * Register locale / text domain hooks (reserved for future use).
	 */
	private function set_locale() {
	}

	/**
	 * Admin hooks (reserved for future use).
	 */
	private function define_admin_hooks() {
	}

	/**
	 * Public hooks (reserved for future use).
	 */
	private function define_public_hooks() {
	}

	/**
	 * Run registered hooks.
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * @return string
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * @return Platasokin_Loader
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * @return string
	 */
	public function get_version() {
		return $this->version;
	}
}
