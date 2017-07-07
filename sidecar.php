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
		 * Sidecar version.
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    string
		 */
		private $version = '1.0.0';

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
		private $allowed_clauses = array(
			'select', 'where', 'join', 'orderby', 'order', 'count'
		);

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
			return apply_filters( 'sidecar_validate_compare', $allowed, $operator, $this );
		}

		/**
		 * Builds a section of the WHERE clause.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param mixed           $values           Single value of varying types, or array of values.
		 * @param string|callable $callback_or_type Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks. Default 'esc_sql'.
		 * @param string          $compare          MySQL operator used for comparing the $value. Accepts '=', '!=',
		 *                                          '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN',
		 *                                          'NOT BETWEEN', 'EXISTS' or 'NOT EXISTS'.
		 *                                          Default is 'IN' when `$value` is an array, '=' otherwise.
		 * @return \Sandhills\Sidecar Current Sidecar instance.
		 */
		public function where( $field, $compare = null, $values = null ) {
			if ( $field !== $this->get_current_field() ) {
				$this->set_current_field( $field );
			}

			$this->set_current_clause( 'where' );

			// Handle shorthand comparison phrases.
			if ( isset( $compare ) && isset( $values ) ) {
				switch( $compare ) {
					case '!=':
						$this->doesnt_equal( $values );
						break;

					case '<':
						$this->lt( $values );
						breal;

					case '>':
						$this->gt( $values );
						break;

					case '<=':
						$this->lte( $values );
						break;

					case '>=':
						$this->gte( $values );
						break;

					case '=':
					default:
						$this->equals( $values );
						break;
				}
			}
		}

		/**
		 * Handles '=' value comparison.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param mixed           $values           Value of varying types, or array of values.
		 * @param string|callable $callback_or_type Optional. Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks. Default 'esc_sql'.
		 * @param string          $operator         Optional. If `$value` is an array, whether to use 'OR' or 'AND' when
		 *                                          building the expression. Default 'OR'.
		 * @return \Sandhills\Sidecar Current Sidecar instance.
		 */
		public function equals( $values, $callback_or_type = 'esc_sql', $operator = 'OR' ) {
			$current_clause = $this->get_current_clause();
			$sql            = $this->get_comparison_sql( $values, $callback_or_type, '=', $operator );

			$this->clauses_in_progress[ $current_clause ][] = $sql;

			return $this;
		}

		/**
		 * Handles '!=' value comparison.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param mixed           $values           Value of varying types, or array of values.
		 * @param string|callable $callback_or_type Optional. Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks. Default 'esc_sql'.
		 * @param string          $operator         Optional. If `$value` is an array, whether to use 'OR' or 'AND' when
		 *                                          building the expression. Default 'OR'.
		 * @return \Sandhills\Sidecar Current Sidecar instance.
		 */
		public function doesnt_equal( $value, $callback_or_type = 'esc_sql', $operator = 'OR' ) {
			$current_clause = $this->get_current_clause();
			$sql            = $this->get_comparison_sql( $values, $callback_or_type, '!=', $operator );

			$this->clauses_in_progress[ $current_clause ][] = $sql;

			return $this;
		}

		/**
		 * Helper used by direct comparison methods to build SQL.
		 *
		 * @access protected
		 * @since  1.0.0
		 *
		 * @param array $values Array of values.
		 * @param string $compare Comparison to make. Accepts '=' or '
		 * @param $operator
		 */
		protected function get_comparison_sql( $values, $callback_or_type, $compare, $operator ) {
			if ( ! in_array( $compare, array( '=', '!=', '<', '>', '<=', '>=' ) ) ) {
				$compare = '=';
			}

			$sql      = '';
			$callback = $this->get_callback( $callback_or_type );
			$operator = $this->get_operator( $operator );
			$values   = is_array( $value ) ? $value : (array) $value;

			// Sanitize the values and built the SQL.
			$values = array_map( $callback, $value );

			$value_count = count( $values );

			// Start the phrase.
			if ( $value_count > 1 ) {
				$sql .= '( ';
			}

			$current_field = $this->get_current_field();

			$current = 0;

			// Loop through the values and bring in $operator if needed.
			foreach ( $values as $value ) {
				$sql .= "`{$current_field}` {$compare} {$value}";

				if ( $value_count > 1 && ++$current !== $value_count ) {
					$sql .= " {$operator} ";
				}
			}

			// Finish the phrase.
			if ( $value_count > 1 ) {
				$sql .= ' )';
			}

			return $sql;
		}

		/**
		 * Handles '>' value comparison.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param mixed           $values           Value of varying types, or array of values.
		 * @param string|callable $callback_or_type Optional. Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks. Default 'esc_sql'.
		 * @param string          $operator         Optional. If `$value` is an array, whether to use 'OR' or 'AND' when
		 *                                          building the expression. Default 'OR'.
		 * @return \Sandhills\Sidecar Current Sidecar instance.
		 */
		public function gt( $values, $callback_or_type = 'esc_sql', $operator = 'OR' ) {
			$current_clause = $this->get_current_clause();
			$sql            = $this->get_comparison_sql( $values, $callback_or_type, '>', $operator );

			$this->clauses_in_progress[ $current_clause ][] = $sql;

			return $this;
		}

		/**
		 * Handles '<' value comparison.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param mixed           $values           Value of varying types, or array of values.
		 * @param string|callable $callback_or_type Optional. Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks. Default 'esc_sql'.
		 * @param string          $operator         Optional. If `$value` is an array, whether to use 'OR' or 'AND' when
		 *                                          building the expression. Default 'OR'.
		 * @return \Sandhills\Sidecar Current Sidecar instance.
		 */
		public function lt( $values, $callback_or_type = 'esc_sql', $operator = 'OR' ) {
			$current_clause = $this->get_current_clause();
			$sql            = $this->get_comparison_sql( $values, $callback_or_type, '<', $operator );

			$this->clauses_in_progress[ $current_clause ][] = $sql;

			return $this;
		}

		/**
		 * Handles '>=' value comparison.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param mixed           $values           Value of varying types, or array of values.
		 * @param string|callable $callback_or_type Optional. Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks. Default 'esc_sql'.
		 * @param string          $operator         Optional. If `$value` is an array, whether to use 'OR' or 'AND' when
		 *                                          building the expression. Default 'OR'.
		 * @return \Sandhills\Sidecar Current Sidecar instance.
		 */
		public function gte( $values, $callback_or_type = 'esc_sql', $operator = 'OR' ) {
			$current_clause = $this->get_current_clause();
			$sql            = $this->get_comparison_sql( $values, $callback_or_type, '>=', $operator );

			$this->clauses_in_progress[ $current_clause ][] = $sql;

			return $this;
		}

		/**
		 * Handles '<=' value comparison.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param mixed           $values           Value of varying types, or array of values.
		 * @param string|callable $callback_or_type Optional. Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks. Default 'esc_sql'.
		 * @param string          $operator         Optional. If `$value` is an array, whether to use 'OR' or 'AND' when
		 *                                          building the expression. Default 'OR'.
		 * @return \Sandhills\Sidecar Current Sidecar instance.
		 */
		public function lte( $values, $callback_or_type = 'esc_sql', $operator = 'OR' ) {
			$current_clause = $this->get_current_clause();
			$sql            = $this->get_comparison_sql( $values, $callback_or_type, '<=', $operator );

			$this->clauses_in_progress[ $current_clause ][] = $sql;

			return $this;
		}

		/**
		 * Handles 'LIKE' value comparison.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param mixed           $values           Value of varying types, or array of values.
		 * @param string|callable $callback_or_type Optional. Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks. Default 'esc_sql'.
		 * @param string          $operator         Optional. If `$value` is an array, whether to use 'OR' or 'AND' when
		 *                                          building the expression. Default 'OR'.
		 * @return \Sandhills\Sidecar Current Sidecar instance.
		 */
		public function like( $values, $callback_or_type = 'esc_sql', $operator = 'OR' ) {
			return $this;
		}

		/**
		 * Handles 'NOT LIKE' value comparison.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param mixed           $values           Value of varying types, or array of values.
		 * @param string|callable $callback_or_type Optional. Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks. Default 'esc_sql'.
		 * @param string          $operator         Optional. If `$value` is an array, whether to use 'OR' or 'AND' when
		 *                                          building the expression. Default 'OR'.
		 * @return \Sandhills\Sidecar Current Sidecar instance.
		 */
		public function not_like( $values, $callback_or_type = 'esc_sql', $operator = 'OR' ) {
			return $this;
		}

		/**
		 * Handles 'IN' value comparison.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param mixed           $values           Value of varying types, or array of values.
		 * @param string|callable $callback_or_type Optional. Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks. Default 'esc_sql'.
		 * @param string          $operator         Optional. If `$value` is an array, whether to use 'OR' or 'AND' when
		 *                                          building the expression. Default 'OR'.
		 * @return \Sandhills\Sidecar Current Sidecar instance.
		 */
		public function in( $values, $callback_or_type = 'esc_sql', $operator = 'OR' ) {
			return $this;
		}

		/**
		 * Handles 'NOT IN' value comparison.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param mixed           $values           Value of varying types, or array of values.
		 * @param string|callable $callback_or_type Optional. Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks. Default 'esc_sql'.
		 * @param string          $operator         Optional. If `$value` is an array, whether to use 'OR' or 'AND' when
		 *                                          building the expression. Default 'OR'.
		 * @return \Sandhills\Sidecar Current Sidecar instance.
		 */
		public function not_in( $values, $callback_or_type = 'esc_sql', $operator = 'OR' ) {
			return $this;
		}

		/**
		 * Handles 'BETWEEN' value comparison.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param mixed           $values           Value of varying types, or array of values.
		 * @param string|callable $callback_or_type Optional. Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks. Default 'esc_sql'.
		 * @return \Sandhills\Sidecar Current Sidecar instance.
		 */
		public function between( $values, $callback_or_type = 'intval', $operator = 'OR' ) {
			return $this;
		}

		/**
		 * Handles 'NOT BETWEEN' value comparison.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param mixed           $values           Value of varying types, or array of values.
		 * @param string|callable $callback_or_type Optional. Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks. Default 'esc_sql'.
		 * @param string          $operator         Optional. If `$value` is an array, whether to use 'OR' or 'AND' when
		 *                                          building the expression. Default 'OR'.
		 * @return \Sandhills\Sidecar Current Sidecar instance.
		 */
		public function not_between( $values, $callback_or_type = 'esc_sql', $operator = 'OR' ) {
			return $this;
		}

		/**
		 * Handles 'EXISTS' value comparison.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param mixed           $values           Value of varying types, or array of values.
		 * @param string|callable $callback_or_type Optional. Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks. Default 'esc_sql'.
		 * @param string          $operator         Optional. If `$value` is an array, whether to use 'OR' or 'AND' when
		 *                                          building the expression. Default 'OR'.
		 * @return \Sandhills\Sidecar Current Sidecar instance.
		 */
		public function exists( $values, $callback_or_type = 'esc_sql', $operator = 'OR' ) {
			return $this;
		}

		/**
		 * Handles 'NOT EXISTS' value comparison.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param mixed           $values           Value of varying types, or array of values.
		 * @param string|callable $callback_or_type Optional. Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks. Default 'esc_sql'.
		 * @param string          $operator         Optional. If `$value` is an array, whether to use 'OR' or 'AND' when
		 *                                          building the expression. Default 'OR'.
		 * @return \Sandhills\Sidecar Current Sidecar instance.
		 */
		public function not_exists( $values, $callback_or_type = 'esc_sql', $operator = 'OR' ) {
			return $this;
		}

		/**
		 * Retrieves the callback to use for the given type.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param string|callable $type Standard type to retrieve a callback for, or an already-callable.
		 * @return callable Callback.
		 */
		public function get_callback( $type ) {

			if ( is_callable( $type ) ) {

				$callback = $type;

			} else {

				switch( $type ) {

					case 'int':
					case 'integer':
						$callback = 'intval';
						break;

					case 'float':
					case 'double':
						$callback = 'floatval';
						break;

					case 'string':
						$callback = 'sanitize_text_field';
						break;

					case 'key':
						$callback = 'sanitize_key';
						break;

					default:
						$callback = 'esc_sql';
						break;
				}

			}

			/**
			 * Filters the callback to use for a given type.
			 *
			 * @since 1.0.0
			 *
			 * @param callable           $callback Callback.
			 * @param string             $type     Type to retrieve a callback for.
			 * @param \Sandhills\Sidecar $this     Current Sidebar instance.
			 */
			return apply_filters( 'sidecar_callback_for_type', $callback, $type, $this );
		}

		/**
		 * Validates and retrieves the operator.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param string $operator Operator. Accepts 'OR' or 'AND'.
		 * @return string Operator. 'OR' if an invalid operator is passed to `$operator`.
		 */
		public function get_operator( $operator ) {
			$operator = strtoupper( $operator );

			if ( ! in_array( $operator, array( 'OR', 'AND' ) ) ) {
				$operator = 'OR';
			}

			return $operator;
		}

		/**
		 * Retrieves raw, sanitized SQL for the current clause.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @return string Raw, sanitized SQL.
		 */
		public function get_sql() {
			$sql            = '';
			$current_clause = $this->get_current_clause();

			if ( isset( $this->clauses_in_progress[ $current_clause ] ) ) {
				$sql = strtoupper( $current_clause ) . ' ' . $this->clauses_in_progress[ $current_clause ];

				$this->reset_vars();
			}

			return $sql;
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
		return new \Sandhills\Sidecar;
	}

}
