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
		 * Holds the wpdb global instance.
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    \wpdb
		 */
		private $wpdb;

		/**
		 * The running SELECT clause for the current instance.
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    string
		 *
		 * @see Sidecar::clause()
		 */
		private $select_clause = '*';

		/**
		 * The running WHERE clause for the current instance.
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    string
		 *
		 * @see Sidecar::clause()
		 */
		private $where_clause = '';

		/**
		 * The running JOIN clause for the current instance.
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    string
		 *
		 * @see Sidecar::clause()
		 */
		private $join_clause = '';

		/**
		 * The running ORDERBY clause for the current instance.
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    string
		 *
		 * @see Sidecar::clause()
		 */
		private $orderby_clause = '';

		/**
		 * The running ORDER clause for the current instance.
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    string
		 *
		 * @see Sidecar::clause()
		 */
		private $order_clause = '';

		/**
		 * The running COUNT clause for the current instance.
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    string
		 *
		 * @see Sidecar::clause()
		 */
		private $count_clause = '';

		/**
		 * Represents the current clause being worked with.
		 *
		 * Resets at the end of escape_input().
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    string
		 */
		private $current_clause;

		/**
		 * Represents the current field(s) being worked with.
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    string
		 */
		private $current_field;

		/**
		 * Represents the current input value(s).
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    mixed
		 */
		private $current_value;

		/**
		 * Whitelist of clauses Sidecar is built to handle.
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    array
		 */
		private $allowed_clauses = array( 'select', 'where', 'join', 'orderby', 'order', 'count' );

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

				self::$instance->setup();
			}

			return self::$instance;
		}

		/**
		 * Sets up needed values.
		 *
		 * @access private
		 * @since  1.0.0
		 */
		private function setup() {
			global $wpdb;

			if ( $wpdb instanceof \wpdb ) {
				$this->wpdb = $wpdb;
			}
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

		/**
		 * Sets the current clause for manipulation and returns the current instance.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param string $type Clause type.
		 * @return Sidecar Current Sidecar instance.
		 */
		public function clause( $type ) {
			$type = strtolower( $type );

			if ( in_array( $type, $this->allowed_clauses, true ) ) {
				$this->current_clause = $type;
			}

			return $this;
		}

		/**
		 * Resets "current" props.
		 *
		 * @access protected
		 * @since  1.0.0
		 */
		protected function reset() {
			unset( $this->current_clause );
			unset( $this->current_input );
		}

	}
}

namespace {

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

}
