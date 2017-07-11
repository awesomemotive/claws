<?php
namespace Sandhills {

	/**
	 * Implements a library to provide a variety of database sanitization helpers when
	 * interacting with WordPress' wp-db class for custom queries.
	 *
	 * @since 1.0.0
	 *
	 * @method \Sandhills\Claws or( null|string $clause )
	 * @method \Sandhills\Claws and( null|string $clause )
	 */
	class Claws {

		/**
		 * Claws version.
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    string
		 */
		private $version = '1.0.0';

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
		 * Used for carrying the operator between methods when doing complex operations.
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    string
		 */
		private $current_operator;

		/**
		 * Stores clauses in progress for retrieval.
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    array
		 */
		private $clauses_in_progress = array();

		/**
		 * Whether the current operation is amending the previous phrase.
		 *
		 * Used when chaining multiple comparisons of different fields together
		 * in the same phrase.
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    bool
		 */
		private $amending_previous = false;

		/**
		 * Holds the value of the previously-stored phrase when set.
		 *
		 * Used in complex phrase-building.
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    string
		 */
		private $previous_phrase;

		/**
		 * Whitelist of clauses Claws is built to handle.
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    array
		 */
		private $allowed_clauses = array( 'where' );

		/**
		 * Retrieves the current Claws version.
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
		 * Handles calling pseudo-methods.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param string $name Method name.
		 * @param array  $args Method arguments.
		 */
		public function __call( $name, $args ) {

			/*
			 * Prior to PHP 7, reserved keywords could not be used in method names,
			 * so having or()/and() methods wouldn't be allowed. Using __call() allows
			 * us to circumvent that problem.
			 */
			switch( $name ) {

				case 'or':
					$clause = isset( $args[0] ) ? $args[0] : null;

					// Shared logic.
					$this->__set_current_operator( 'OR', $clause );

					return $this;
					break;

				case 'and':
					$clause = isset( $args[0] ) ? $args[0] : null;

					// Shared logic.
					$this->__set_current_operator( 'AND', $clause );

					return $this;
					break;
			}

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
		 * @param string          $compare_type     MySQL operator used for comparing the $value. Accepts '=', '!=',
		 *                                          '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN',
		 *                                          'NOT BETWEEN', 'EXISTS' or 'NOT EXISTS'.
		 *                                          Default is 'IN' when `$value` is an array, '=' otherwise.
		 * @return \Sandhills\Claws Current Claws instance.
		 */
		public function where( $field, $compare_type = null, $values = null, $callback_or_type = 'esc_sql' ) {
			$this->set_current_clause( 'where' );
			$this->set_current_field( $field );

			// Handle shorthand comparison phrases.
			if ( isset( $compare_type ) && isset( $values ) ) {

				$callback = $this->get_callback( $callback_or_type );

				$this->compare( $compare_type, $values, $callback );
			}

			return $this;
		}

		/**
		 * Handles delegating short-hand value comparison phrases.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param string           $type     Type of comparison. Accepts '=', '!=', '<', '>', '>=', or '<='.
		 * @param string|int|array $values   Single value(s) of varying type, or an array of values.
		 * @param callable         $callback Callback to pass to the comparison method.
		 * @return \Sandhills\Claws Current Claws instance.
		 */
		public function compare( $type, $values, $callback ) {
			switch( $type ) {
				case '!=':
					$this->doesnt_equal( $values, $callback );
					break;

				case '<':
					$this->lt( $values, $callback );
					break;

				case '>':
					$this->gt( $values, $callback );
					break;

				case '<=':
					$this->lte( $values, $callback );
					break;

				case '>=':
					$this->gte( $values, $callback );
					break;

				case '=':
				default:
					$this->equals( $values, $callback );
					break;
			}

			return $this;
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
		 *
		 * @return \Sandhills\Claws Current Claws instance.
		 */
		public function equals( $values, $callback_or_type = 'esc_sql', $operator = 'OR' ) {
			$sql = $this->get_comparison_sql( $values, $callback_or_type, '=', $operator );

			$this->add_clause_sql( $sql );

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
		 *
		 * @return \Sandhills\Claws Current Claws instance.
		 */
		public function doesnt_equal( $values, $callback_or_type = 'esc_sql', $operator = 'OR' ) {
			$sql = $this->get_comparison_sql( $values, $callback_or_type, '!=', $operator );

			$this->add_clause_sql( $sql );

			return $this;
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
		 *
		 * @return \Sandhills\Claws Current Claws instance.
		 */
		public function gt( $values, $callback_or_type = 'esc_sql', $operator = 'OR' ) {
			$sql = $this->get_comparison_sql( $values, $callback_or_type, '>', $operator );

			$this->add_clause_sql( $sql );

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
		 *
		 * @return \Sandhills\Claws Current Claws instance.
		 */
		public function lt( $values, $callback_or_type = 'esc_sql', $operator = 'OR' ) {
			$sql = $this->get_comparison_sql( $values, $callback_or_type, '<', $operator );

			$this->add_clause_sql( $sql );

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
		 *
		 * @return \Sandhills\Claws Current Claws instance.
		 */
		public function gte( $values, $callback_or_type = 'esc_sql', $operator = 'OR' ) {
			$sql = $this->get_comparison_sql( $values, $callback_or_type, '>=', $operator );

			$this->add_clause_sql( $sql );

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
		 *
		 * @return \Sandhills\Claws Current Claws instance.
		 */
		public function lte( $values, $callback_or_type = 'esc_sql', $operator = 'OR' ) {
			$sql = $this->get_comparison_sql( $values, $callback_or_type, '<=', $operator );

			$this->add_clause_sql( $sql );

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
		 *                                          types to use preset callbacks. Default `Claws->esc_like()`.
		 * @param string          $operator         Optional. If `$value` is an array, whether to use 'OR' or 'AND' when
		 *                                          building the expression. Default 'OR'.
		 *
		 * @return \Sandhills\Claws Current Claws instance.
		 */
		public function like( $values, $callback_or_type = 'esc_like', $operator = 'OR' ) {
			$sql = $this->get_like_sql( $values, $callback_or_type, 'LIKE', $operator );

			$this->add_clause_sql( $sql );

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
		 *                                          types to use preset callbacks. Default is `Claws->esc_like()`.
		 * @param string          $operator         Optional. If `$value` is an array, whether to use 'OR' or 'AND' when
		 *                                          building the expression. Default 'OR'.
		 *
		 * @return \Sandhills\Claws Current Claws instance.
		 */
		public function not_like( $values, $callback_or_type = 'esc_like', $operator = 'OR' ) {
			$sql = $this->get_like_sql( $values, $callback_or_type, 'NOT LIKE', $operator );

			$this->add_clause_sql( $sql );

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
		 *
		 * @return \Sandhills\Claws Current Claws instance.
		 */
		public function in( $values, $callback_or_type = 'esc_sql', $operator = 'OR' ) {
			if ( ! is_array( $values ) ) {

				$this->equals( $values, $callback_or_type, $operator );

			} else {

				$sql = $this->get_in_sql( $values, $callback_or_type, 'IN' );

				$this->add_clause_sql( $sql );
			}

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
		 *
		 * @return \Sandhills\Claws Current Claws instance.
		 */
		public function not_in( $values, $callback_or_type = 'esc_sql', $operator = 'OR' ) {
			if ( ! is_array( $values ) ) {

				$this->doesnt_equal( $values, $callback_or_type, $operator );

			} else {

				$sql = $this->get_in_sql( $values, $callback_or_type, 'NOT IN' );

				$this->add_clause_sql( $sql );
			}

			return $this;
		}

		/**
		 * Handles 'BETWEEN' value comparison.
		 *
		 * Note: If doing a between comparison for dates, care should be taken to ensure
		 * the beginning and ending dates represent the beginning and/or end of the day
		 * including hours, minutes, and seconds, depending on the expected range.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param array           $values           Array of values.
		 * @param string|callable $callback_or_type Optional. Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks. Default 'esc_sql'.
		 *
		 * @return \Sandhills\Claws Current Claws instance.
		 */
		public function between( $values, $callback_or_type = 'esc_sql' ) {
			$sql = $this->get_between_sql( $values, $callback_or_type, 'BETWEEN' );

			$this->add_clause_sql( $sql );

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
		 *
		 * @return \Sandhills\Claws Current Claws instance.
		 */
		public function not_between( $values, $callback_or_type = 'esc_sql' ) {
			$sql = $this->get_between_sql( $values, $callback_or_type, 'NOT BETWEEN' );

			$this->add_clause_sql( $sql );

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
		 *
		 * @return \Sandhills\Claws Current Claws instance.
		 */
		public function exists( $values, $callback_or_type = 'esc_sql', $operator = 'OR' ) {
			return $this->equals( $values, $callback_or_type, $operator );
		}

		/**
		 * Handles 'NOT EXISTS' value comparison.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param string|callable $callback_or_type Optional. Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks. Default 'esc_sql'.
		 * @param string          $operator         Optional. If `$value` is an array, whether to use 'OR' or 'AND' when
		 *                                          building the expression. Default 'OR'.
		 *
		 * @return \Sandhills\Claws Current Claws instance.
		 */
		public function not_exists( $callback_or_type = 'esc_sql', $operator = 'OR' ) {
			$sql = $this->build_comparison_sql( array( '' ), 'IS NULL', $operator );

			$this->add_clause_sql( $sql );

			return $this;
		}

		/**
		 * Helper used by direct comparison methods to build SQL.
		 *
		 * @access protected
		 * @since  1.0.0
		 *
		 * @param array            $values           Array of values to compare.
		 * @param string|callable  $callback_or_type Sanitization callback to pass values through, or shorthand
		 *                                           types to use preset callbacks.
		 * @param string           $compare_type     Comparison type to make. Accepts '=', '!=', '<', '>', '<=', or '>='.
		 *                                           Default '='.
		 * @param string           $operator         Optional. Operator to use between multiple sets of value comparisons.
		 *                                           Accepts 'OR' or 'AND'. Default 'OR'.
		 * @return string Raw, sanitized SQL.
		 */
		protected function get_comparison_sql( $values, $callback_or_type, $compare_type, $operator = 'OR' ) {
			if ( ! in_array( $compare_type, array( '=', '!=', '<', '>', '<=', '>=' ) ) ) {
				$compare_type = '=';
			}

			$callback = $this->get_callback( $callback_or_type );
			$operator = $this->get_operator( $operator );
			$values   = $this->prepare_values( $values );

			// Sanitize the values and built the SQL.
			$values = array_map( $callback, $values );

			return $this->build_comparison_sql( $values, $compare_type, $operator );
		}

		/**
		 * Builds and retrieves the actual comparison SQL.
		 *
		 * @acccess protected
		 * @since   1.0.0
		 *
		 * @param array  $values       Array of values.
		 * @param string $compare_type Comparison type to make. Accepts '=', '!=', '<', '>', '<=', or '>='.
		 *                             Default '='.
		 * @param string $operator     Operator to use between value comparisons.
		 * @return string Comparison SQL.
		 */
		protected function build_comparison_sql( $values, $compare_type, $operator ) {
			global $wpdb;

			$sql = '';

			$count   = count( $values );
			$current = 0;
			$field   = $this->get_current_field();

			// Loop through the values and bring in $operator if needed.
			foreach ( $values as $value ) {
				$type = $this->get_cast_for_type( gettype( $value ) );

				$value = $wpdb->prepare( '%s', $value );

				if ( 'CHAR' !== $type ) {
					$value = "CAST( {$value} AS {$type} )";
				}

				$sql .= "`{$field}` {$compare_type} {$value}";

				if ( ++$current !== $count ) {
					$sql .= " {$operator} ";
				}
			}

			// Finish the phrase.
			if ( $count > 1 ) {
				$sql = '( ' . $sql . ' )';
			}

			return $sql;
		}

		/**
		 * Helper used by 'in' comparison methods to build SQL.
		 *
		 * @access protected
		 * @since  1.0.0
		 *
		 * @param array           $values           Array of values to compare.
		 * @param string|callable $callback_or_type Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks.
		 * @param string          $compare_type     Comparison to make. Accepts 'IN' or 'NOT IN'.
		 * @return string Raw, sanitized SQL.
		 */
		protected function get_in_sql( $values, $callback_or_type, $compare_type ) {
			$field    = $this->get_current_field();
			$callback = $this->get_callback( $callback_or_type );
			$compare_type  = strtoupper( $compare_type );

			if ( ! in_array( $compare_type, array( 'IN', 'NOT IN' ) ) ) {
				$compare_type = 'IN';
			}

			// Escape values.
			$values = array_map( function( $value ) use ( $callback ) {
				$value = call_user_func( $callback, $value );

				if ( 'string' === gettype( $value ) ) {
					$value = "'{$value}'";
				}

				return $value;
			}, $values );

			$values = implode( ', ', $values );

			$sql = "{$field} {$compare_type}( {$values} )";

			return $sql;
		}

		/**
		 * Helper used by 'LIKE' comparison methods to build SQL.
		 *
		 * @access protected
		 * @since  1.0.0
		 *
		 * @param array           $values           Array of values to compare.
		 * @param string|callable $callback_or_type Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks.
		 * @param string          $compare_type     Comparison to make. Accepts 'LIKE' or 'NOT LIKE'.
		 * @return string Raw, sanitized SQL.
		 */
		protected function get_like_sql( $values, $callback_or_type, $compare_type, $operator ) {
			$sql = '';

			$callback     = $this->get_callback( $callback_or_type );
			$field        = $this->get_current_field();
			$values       = $this->prepare_values( $values );
			$compare_type = strtoupper( $compare_type );

			if ( ! in_array( $compare_type, array( 'LIKE', 'NOT LIKE' ) ) ) {
				$compare_type = 'LIKE';
			}

			$values = array_map( $callback, $values );
			$value_count = count( $values );

			$current = 0;

			// Escape values and build the SQL.
			foreach ( $values as $value ) {
				$value = $wpdb->prepare( '%s', $value );

				$sql .= "`{$field}` {$compare_type} '%%{$value}%%'";

				if ( $value_count > 1 && ++$current !== $value_count ) {
					$sql .= " {$operator} ";
				}
			}

			return $sql;
		}

		/**
		 * Helper used by 'BETWEEN' comparison methods to build SQL.
		 *
		 * @access protected
		 * @since  1.0.0
		 *
		 * @param array           $values           Array of values to compare.
		 * @param string|callable $callback_or_type Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks.
		 * @param string          $compare_type     Comparison to make. Accepts 'BETWEEN' or 'NOT BETWEEN'.
		 * @return string Raw, sanitized SQL.
		 */
		protected function get_between_sql( $values, $callback_or_type, $compare_type ) {
			global $wpdb;

			$sql = '';

			// Bail if `$values` isn't an array or there aren't at least two values.
			if ( ! is_array( $values ) || count( $values ) < 2 ) {
				return $sql;
			}

			$compare_type = strtoupper( $compare_type );

			if ( ! in_array( $compare_type, array( 'BETWEEN', 'NOT BETWEEN' ) ) ) {
				$compare_type = 'BETWEEN';
			}

			$field    = $this->get_current_field();
			$callback = $this->get_callback( $callback_or_type );

			// Grab the first two values in the array.
			$values = array_slice( $values, 0, 2 );

			// Sanitize the values according to the callback and cast dates.
			$values = array_map( function( $value ) use ( $callback, $wpdb ) {
				$value = call_user_func( $callback, $value );

				if ( false !== strpos( $value, ':' ) ) {
					$value = $wpdb->prepare( '%s', $value );
					$value = "CAST( {$value} AS DATE)";
				}

				return $value;
			}, $values );

			$sql .= "( `{$field}` {$compare_type} %s AND %s )";

			return $wpdb->prepare( $sql, $values );
		}

		/**
		 * Retrieves the callback to use for the given type.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param string|callable $callback_or_type Standard type to retrieve a callback for, or an callback.
		 * @return callable Callback.
		 */
		public function get_callback( $callback_or_type ) {

			$callback = is_callable( $callback_or_type ) ? $callback_or_type : $this->get_callback_for_type( $callback_or_type );

			/**
			 * Filters the callback to use for a given type.
			 *
			 * @since 1.0.0
			 *
			 * @param callable         $callback Callback.
			 * @param string           $type     Type to retrieve a callback for.
			 * @param \Sandhills\Claws $this     Current Sidebar instance.
			 */
			return apply_filters( 'claws_callback_for_type', $callback, $callback_or_type, $this );
		}

		/**
		 * Determines the right callback for a given type of value.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param string $type Type of value to retrieve a callback for.
		 * @return string|callable Callback string.
		 */
		public function get_callback_for_type( $type ) {
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

				case 'esc_like':
					$callback = array( $this, 'esc_like' );
					break;

				default:
					$callback = 'esc_sql';
					break;
			}

			return $callback;
		}

		/**
		 * Retrieves the CAST value for a given value type.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @see WP_Meta_Query::get_cast_for_type()
		 *
		 * @param string $type Value type (as derived from gettype()).
		 * @return string MySQL-ready CAST type.
		 */
		public function get_cast_for_type( $type ) {
			$type = strtoupper( $type );

			if ( ! preg_match( '/^(?:BINARY|CHAR|DATE|DATETIME|SIGNED|UNSIGNED|TIME|DOUBLE|INTEGER|NUMERIC(?:\(\d+(?:,\s?\d+)?\))?|DECIMAL(?:\(\d+(?:,\s?\d+)?\))?)$/', $type ) ) {
				return 'CHAR';
			}

			if ( 'INTEGER' === $type || 'NUMERIC' === $type ) {
				$type = 'SIGNED';
			}

			if ( 'DOUBLE' === $type ) {
				$type = 'DECIMAL';
			}

			return $type;
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
		 * Escapes a value used in a 'LIKE' comparison.
		 *
		 * @access protected
		 * @since  1.0.0
		 *
		 * @param mixed $like LIKE comparison value.
		 * @return string Escaped value.
		 */
		protected function esc_like( $like ) {
			return addcslashes( $like, '_%\\' );
		}

		/**
		 * Ensures values are in array form.
		 *
		 * Seems silly, but anywhere blatant duplication can be reduced is a win.
		 *
		 * @access protected
		 * @since  1.0.0
		 * @param $values
		 *
		 * @param mixed|array Single values of varying type or an array of values.
		 * @return array Array of values.
		 */
		protected function prepare_values( $values ) {
			return is_array( $values ) ? $values : (array) $values;
		}

		/**
		 * Replaces the previous phrase with the given prepared SQL.
		 *
		 * @access protected
		 * @since  1.0.0
		 *
		 * @param string      $sql    Prepared SQL to replace the phrase with.
		 * @param null|string $clause Optional. Clause to replace the last phrase for. Default is the current clause.
		 */
		protected function replace_previous_phrase( $sql, $clause = null ) {
			$clause = $this->get_clause( $clause );

			// Pop off the last phrase.
			array_pop( $this->clauses_in_progress[ $clause ] );

			// Replace it with the new one.
			$this->clauses_in_progress[ $clause ][] = $sql;
		}

		/**
		 * Adds prepared SQL to the current clause.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param string      $sql    Prepared SQL to add to the clause.
		 * @param null|string $clause Optional. Clause to add the SQL to. Default is the current clause.
		 */
		public function add_clause_sql( $sql, $clause = null ) {
			$clause = $this->get_clause( $clause );

			if ( true === $this->amending_previous ) {
				$operator = $this->get_current_operator();

				$sql = $this->get_previous_phrase() . " {$operator} {$sql}";

				$this->replace_previous_phrase( $sql, $clause );

				// Reset the amendment flag.
				$this->amending_previous = false;

				$this->previous_phrase = $sql;
			} else {
				$this->previous_phrase = $sql;
				$this->clauses_in_progress[ $clause ][] = $this->previous_phrase;
			}

		}

		/**
		 * Retrieves raw, sanitized SQL for the current clause.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param null|string $clause     Optional. Clause to build SQL for. Default is the current clause.
		 * @param bool        $reset_vars Optional. Whether to reset the clause, field, and operator vars
		 *                                after retrieving the clause's SQL. Default true.
		 * @return string Raw, sanitized SQL.
		 */
		public function get_sql( $clause = null, $reset_vars = true ) {
			$sql = '';

			$clause = $this->get_clause( $clause );

			if ( isset( $this->clauses_in_progress[ $clause ] ) ) {
				$sql .= strtoupper( $clause );

				$current = 0;

				foreach ( $this->clauses_in_progress[ $clause ] as $chunk ) {
					if ( ++$current === 1 ) {
						$sql .= " {$chunk}";
					} elseif( $current >= 2 ) {
						$sql .= " AND {$chunk}";
					}
				}

				if ( true === $reset_vars ) {
					$this->reset_vars();
				}
			}

			return $sql;
		}

		/**
		 * Sets the current clause.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param string $clause Clause to set as current.
		 * @return \Sandhills\Claws Current claws instance.
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
		 * @param null|string $clause Optional. Clause to retrieve. Default is the current clause.
		 * @return string Current clause name.
		 */
		public function get_clause( $clause = null ) {
			if ( ! isset( $clause ) || ! in_array( $clause, $this->allowed_clauses, true ) ) {
				$clause = $this->current_clause;
			}

			return $clause;
		}

		/**
		 * Sets the current field.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param string $field Field to set as current.
		 * @return \Sandhills\Claws Current claws instance.
		 */
		public function set_current_field( $field ) {
			if ( $field !== $this->get_current_field() ) {
				$this->current_field = sanitize_key( $field );
			}

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
		 * Sets the current operator for use in complex phrase building.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param string $operator Operator to persist between method calls. Accepts 'OR' or 'AND'.
		 * @return \Sandhills\Claws Current claws instance.
		 */
		public function set_current_operator( $operator ) {
			$operator = $this->get_operator( $operator );

			$this->current_operator = $operator;

			return $this;
		}

		/**
		 * Flags the previously-stored phrase to be amended and appended with the given operator.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param null|string $clause Optional. Clause to amend the previous chunk for.
		 *                            Default is the current clause.
		 * @return \Sandhills\Claws Current Claws instance.
		 */
		private function __set_current_operator( $operator, $clause ) {
			$operator = strtoupper( $operator );

			if ( ! in_array( $operator, array( 'OR', 'AND' ) ) ) {
				$operator = 'OR';
			}

			$this->set_current_operator( $operator );
			$this->amending_previous = true;

			$clause = $this->get_clause( $clause );
			$chunks = $this->clauses_in_progress[ $clause ];

			if ( ! empty( $chunks ) ) {
				$this->previous_phrase = end( $chunks );
			}

			return $this;
		}

		/**
		 * Retrieves the current operator (for use in complex phrase building).
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @return string Current operator.
		 */
		public function get_current_operator() {
			return $this->current_operator;
		}

		/**
		 * Retrieves the previous phrase for the given clause.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @return string Previous phrase SQL.
		 */
		public function get_previous_phrase() {
			return $this->previous_phrase;
		}

		/**
		 * Resets the current clause, field, and operator.
		 *
		 * @access public
		 * @since  1.0.0
		 */
		public function reset_vars() {
			$this->current_clause = null;
			$this->current_field = null;
			$this->current_operator = null;
		}
	}
}

namespace {

	/**
	 * Shorthand helper for retrieving a Claws instance.
	 *
	 * @since 1.0.0
	 *
	 * @return \Sandhills\Claws Claws instance.
	 */
	function claws() {
		return new \Sandhills\Claws;
	}

}
