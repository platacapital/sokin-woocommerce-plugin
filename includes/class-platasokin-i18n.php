<?php
/**
 * Internationalization placeholder (translations load via WordPress.org for the plugin slug).
 *
 * @package Platasokin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * i18n scaffolding.
 */
class Platasokin_I18n {

	/**
	 * Load the plugin text domain for translation.
	 */
	public function load_plugin_textdomain() {
		// No-op: WordPress.org auto-loads translations for plugin slugs since 4.6.
	}
}
