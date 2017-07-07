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
		 * Stores clauses in progress for retrieval.
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    array
		 */
		private $clauses_in_progress = array();

		/**
		 * Whitelist of clauses Sidecar is built to handle.
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    array
		 */
		private $allowed_clauses = array( 'select', 'where', 'join', 'orderby', 'order', 'count' );

		/**
		 * Whitelist of allowed comparison operators.
		 *
		 * @access public
		 * @since  1.0.0
		 * @var    array
		 */
		private $allowed_compares = array(
			'=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN',
			'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'EXISTS', 'NOT EXISTS'
		);

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
		 * Sets the current clause.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param string $clause Clause to set as current.
		 * @return \Sandhills\Sidecar Current sidecar instance.
		 */
		public function set_current_clause( $clause ) {
			$clause = strtolower( $clause );

			if ( in_array( $clause, $this->allowed_clauses, true ) ) {
				$this->current_clause = $clause;
			}

			return $this;
		}

		/**
		 * Retrieves the current clause.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @return string Current clause name.
		 */
		public function get_current_clause() {
			return $this->current_clause;
		}

		/**
		 * Sets the current field.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param string $field Field to set as current.
		 * @return \Sandhills\Sidecar Current sidecar instance.
		 */
		public function set_current_field( $field ) {
			$this->current_field = sanitize_key( $field );

			return $this;
		}

		/**
		 * Retrieves the current field name.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @return string Current field name.
		 */
		public function get_current_field() {
			return $this->current_field;
		}

		/**
		 * Resets the current clause.
		 *
		 * @access public
		 * @since  1.0.0
		 */
		public function reset_vars() {
			$this->current_clause = null;
			$this->current_field = null;
		}

		/**
		 * Validates that the given comparison operator is allowed.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param string $operator Comparison operator.
		 * @return bool True if the operator is valid, otherwise false.
		 */
		public function validate_compare( $operator ) {
			$allowed = in_array( $operator, $this->allowed_compares, true );

			/**
			 * Filters whether the given comparison operator is "allowed".
			 *
			 * @since 1.0.0
			 *
			 * @param bool               $allowed  Whether the operator is allowed.
			 * @param string             $operator Comparison operator being checked.
			 * @param \Sandhills\Sidecar $this     Current Sidecar instance.
			 */
			return apply_filters( 'sidecar_valid_compare', $allowed, $operator, $this );
		}

		/**
		 * Builds a section of the WHERE clause.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param mixed           $values           Single value of varying types, or array of values.
		 * @param string|callable $callback_or_type Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks. Default 'intval'.
		 * @param string          $compare          MySQL operator used for comparing the $value. Accepts '=', '!=',
		 *                                          '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN',
		 *                                          'NOT BETWEEN', 'EXISTS' or 'NOT EXISTS'.
		 *                                          Default is 'IN' when `$value` is an array, '=' otherwise.
		 * @return \Sandhills\Sidecar
		 */
		public function where( $field ) {
			if ( $field !== $this->get_current_field() ) {
				$this->set_current_field( $field );
			}

			$this->set_current_clause( 'where' );

			if ( ! is_callable( $callback_or_type ) ) {
				/*
				 * TODO: Decide whether to throw an exception if get_callback() stiill doesn't yield a callable.
				 *
				 * Could make implementing code a bit too long-winded having to try/catch all over the place.
				 * Mayyyybe it can be done via an abstraction layer, such as moving this business logic a
				 * level deeper.
				 */
				$callback = $this->get_callback( $callback_or_type );
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
