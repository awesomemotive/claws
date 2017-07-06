<?php
namespace Sandhills {

	/**
	 * Standalone library to provide a variety of database sanitization helpers when
	 * interacting with WordPress' wp-db class for custom queries.
	 *
	 * @since 1.0.0
	 */
	class Sidecar {

		/**
		 * The single Sidecar instance.
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    \Sandhills\Sidecar
		 * @static
		 */
		private static $instance;

		/**
		 * Sidecar version.
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    string
		 */
		private $version = '1.0.0';

		/**
		 * Sets up and retrieves the Sidecar instance.
		 *
		 * @access public
		 * @since  1.0.0
		 * @static
		 *
		 * @return \Sandhills\Sidecar Sidecar instance.
		 */
		public static function instance() {

			if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Sidecar ) ) {
				self::$instance = new Sidecar;
			}

			return self::$instance;
		}

		/**
		 * Retrieves the current Sidecar version.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @return string
		 */
		public function version() {
			return $this->version;
		}


	}
}

/**
 * Shorthand helper for retrieving the Sidecar instance.
 *
 * @since 1.0.0
 *
 * @return \Sandhills\Sidecar Sidecar instance.
 */
function sidecar() {
	return \Sandhills\Sidecar::instance();
}
