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
		 * @return Sidecar $this Current Sidecar instance.
		 */
		public function clause( $type ) {
			$type = strtolower( $type );

			if ( in_array( $type, $this->allowed_clauses, true ) ) {
				$this->current_clause = $type;
			}

			return $this;
		}

		/**
		 * Sets the current field for manipulation and returns the current instance.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param string $field Field to use.
		 * @return Sidecar $this Current Sidecar instance.
		 */
		public function field( $field ) {
			$this->current_field = $field;

			return $this;
		}

		/**
		 * Sanitizes values for the clause.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param string|int|array $values   Value(s) as a string, integer, or array.
		 * @param string           $callback Optional. Callback to use against the value(s). Default 'intval'.
		 * @param string           $clause   Optional. Clause to append the sanitized values against.
		 *                                   Default empty (current clause).
		 * @return string|\WP_Error SQL for the current clause, otherwise a WP_Error object.
		 */
		public function values( $values, $callback = 'intval', $clause = '' ) {
			if ( empty( $callback ) || ! is_callable( $callback ) ) {
				$callback = 'intval';
			}

			if ( is_array( $values ) ) {
				$this->current_value = array_map( $callback, $values );
			} else {
				$this->current_value = call_user_func( $callback, $values );
			}

			return $this->get_clause( $clause );
		}

		/**
		 * Retrieves the SQL for the given clause.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param string $clause Optional. Clause to retrieve built SQL for. Default empty (current clause).
		 * @return string|\WP_Error SQL for the current clause, otherwise a WP_Error object.
		 */
		public function get_clause( $clause = '' ) {
			if ( ! empty( $clause ) ) {
				$clause = strtolower( $clause );

				if ( in_array( $clause, $this->allowed_clauses, true ) && $clause !== $this->current_clause ) {
					$this->current_clause = $clause;
				}
			}

			return $this->build_clause();
		}

		/**
		 * Retrieves the raw, sanitize SQL for the current clause.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @return string|\WP_Error SQL for the current clause, otherwise a WP_Error object.
		 */
		public function build_clause() {
			if ( ! isset( $this->current_clause ) ) {
				return new \WP_Error( 'sidecar_invalid_clause', 'A clause must be specified to build against.' );
			}

			// TODO Add handling for non field clauses.
			if ( ! isset( $this->current_field ) ) {
				return new \WP_Error( 'sidecar_invalid_field', 'A field must be specified to build the clause against.' );
			}

			if ( ! isset( $this->current_value ) ) {
				return new \WP_Error( 'sidecar_invalid_values', 'A value or values must be specified to build the clause against.' );
			}

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
