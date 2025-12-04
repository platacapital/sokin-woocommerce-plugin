<?php

/**
 * The file that defines the core plugin class
 */
class WooCPay {


	/**
	 * The loader is used to maintaining and registering all hooks the plugin.
	 */
	protected $loader;
	protected $plugin_name;
	protected $version;

	public function __construct() {
		if (defined('PLUGIN_NAME_VERSION')) {
			$this->version = PLUGIN_NAME_VERSION;
		} else {
			$this->version = '1.1.3';
		}
		$this->plugin_name = 'Sokin Pay';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the core plugin.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class_woo_cpay_loader.php';

		$this->loader = new Woo_CPay_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 */
	private function set_locale() {

		// $plugin_i18n = new Tsi_Lrs_I18n();

		// $this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality of the plugin.
	 */
	private function define_admin_hooks() {}

	/**
	 * Register all of the hooks related to the public functionality of the plugin.
	 */
	private function define_public_hooks() {}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 */
	public function run() {
		$this->loader->run();
	}

	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
}
