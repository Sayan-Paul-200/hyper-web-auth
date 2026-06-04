<?php
/**
 * WP_List_Table for Auth Logs.
 *
 * @package Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/admin
 */

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class HWA_Auth_Logs_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( array(
			'singular' => 'auth_log',
			'plural'   => 'auth_logs',
			'ajax'     => false,
		) );
	}

	/**
	 * Get columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'created_at' => __( 'Date', 'hyper-web-auth' ),
			'user_id'    => __( 'User', 'hyper-web-auth' ),
			'provider'   => __( 'Provider', 'hyper-web-auth' ),
			'event_type' => __( 'Event', 'hyper-web-auth' ),
			'status'     => __( 'Status', 'hyper-web-auth' ),
			'ip_hash'    => __( 'IP Hash', 'hyper-web-auth' ),
			'message'    => __( 'Message', 'hyper-web-auth' ),
		);
	}

	/**
	 * Get sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'created_at' => array( 'created_at', true ), // true means already sorted
			'user_id'    => array( 'user_id', false ),
			'provider'   => array( 'provider', false ),
			'event_type' => array( 'event_type', false ),
			'status'     => array( 'status', false ),
		);
	}

	/**
	 * Default column renderer.
	 *
	 * @param array  $item
	 * @param string $column_name
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'created_at':
				return get_date_from_gmt( $item['created_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
			case 'provider':
			case 'event_type':
			case 'ip_hash':
				return esc_html( $item[ $column_name ] );
			case 'status':
				$color = 'success' === $item['status'] ? 'green' : 'red';
				return sprintf( '<span style="color:%s; font-weight:bold;">%s</span>', $color, esc_html( ucfirst( $item['status'] ) ) );
			default:
				return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
		}
	}

	/**
	 * User column renderer.
	 *
	 * @param array $item
	 * @return string
	 */
	public function column_user_id( $item ) {
		if ( empty( $item['user_id'] ) ) {
			return '<em>' . __( 'Guest / Unknown', 'hyper-web-auth' ) . '</em>';
		}

		$user = get_userdata( $item['user_id'] );
		if ( ! $user ) {
			return sprintf( __( 'User ID: %d (Deleted)', 'hyper-web-auth' ), $item['user_id'] );
		}

		$edit_link = get_edit_user_link( $item['user_id'] );
		return sprintf( '<a href="%s">%s</a> (ID: %d)', esc_url( $edit_link ), esc_html( $user->user_login ), $item['user_id'] );
	}

	/**
	 * Message column renderer with PII masking.
	 *
	 * @param array $item
	 * @return string
	 */
	public function column_message( $item ) {
		if ( empty( $item['message'] ) ) {
			return '';
		}

		$message = $item['message'];

		// Mask emails
		$message = preg_replace_callback( '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', function( $matches ) {
			return HWA_Security::mask_email( $matches[0] );
		}, $message );

		// Mask E.164 phone numbers
		$message = preg_replace_callback( '/\+[1-9]\d{1,14}/', function( $matches ) {
			return HWA_Security::mask_phone( $matches[0] );
		}, $message );

		return esc_html( $message );
	}

	/**
	 * Add filter dropdowns above the table.
	 *
	 * @param string $which
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		$current_provider = isset( $_REQUEST['filter_provider'] ) ? sanitize_key( $_REQUEST['filter_provider'] ) : '';
		$current_status   = isset( $_REQUEST['filter_status'] ) ? sanitize_key( $_REQUEST['filter_status'] ) : '';
		$current_event    = isset( $_REQUEST['filter_event'] ) ? sanitize_key( $_REQUEST['filter_event'] ) : '';

		?>
		<div class="alignleft actions">
			<select name="filter_provider">
				<option value=""><?php esc_html_e( 'All Providers', 'hyper-web-auth' ); ?></option>
				<option value="google" <?php selected( $current_provider, 'google' ); ?>>Google</option>
				<option value="firebase_phone" <?php selected( $current_provider, 'firebase_phone' ); ?>>Firebase Phone</option>
			</select>

			<select name="filter_status">
				<option value=""><?php esc_html_e( 'All Statuses', 'hyper-web-auth' ); ?></option>
				<option value="success" <?php selected( $current_status, 'success' ); ?>>Success</option>
				<option value="failed" <?php selected( $current_status, 'failed' ); ?>>Failed</option>
			</select>

			<select name="filter_event">
				<option value=""><?php esc_html_e( 'All Events', 'hyper-web-auth' ); ?></option>
				<option value="login_success" <?php selected( $current_event, 'login_success' ); ?>>Login Success</option>
				<option value="login_failed" <?php selected( $current_event, 'login_failed' ); ?>>Login Failed</option>
				<option value="register_success" <?php selected( $current_event, 'register_success' ); ?>>Register Success</option>
				<option value="register_failed" <?php selected( $current_event, 'register_failed' ); ?>>Register Failed</option>
				<option value="link_success" <?php selected( $current_event, 'link_success' ); ?>>Link Success</option>
				<option value="link_failed" <?php selected( $current_event, 'link_failed' ); ?>>Link Failed</option>
				<option value="unlink_success" <?php selected( $current_event, 'unlink_success' ); ?>>Unlink Success</option>
				<option value="unlink_failed" <?php selected( $current_event, 'unlink_failed' ); ?>>Unlink Failed</option>
			</select>

			<?php submit_button( __( 'Filter', 'hyper-web-auth' ), 'button', 'filter_action', false, array( 'id' => 'post-query-submit' ) ); ?>
		</div>
		<?php
	}

	/**
	 * Prepare items for the table.
	 */
	public function prepare_items() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'hwa_auth_logs';

		$per_page = 20;
		$current_page = $this->get_pagenum();

		// Define columns
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		// Base query
		$query = "SELECT * FROM {$table_name} WHERE 1=1";
		$query_args = array();

		// Filters
		if ( ! empty( $_REQUEST['filter_provider'] ) ) {
			$query .= " AND provider = %s";
			$query_args[] = sanitize_key( $_REQUEST['filter_provider'] );
		}
		if ( ! empty( $_REQUEST['filter_status'] ) ) {
			$query .= " AND status = %s";
			$query_args[] = sanitize_key( $_REQUEST['filter_status'] );
		}
		if ( ! empty( $_REQUEST['filter_event'] ) ) {
			$query .= " AND event_type = %s";
			$query_args[] = sanitize_key( $_REQUEST['filter_event'] );
		}

		// Sorting
		$orderby = ! empty( $_REQUEST['orderby'] ) ? sanitize_sql_orderby( $_REQUEST['orderby'] ) : 'created_at';
		$order   = ! empty( $_REQUEST['order'] ) && 'asc' === strtolower( $_REQUEST['order'] ) ? 'ASC' : 'DESC';
		
		// Whitelist orderby
		$valid_columns = array_keys( $this->get_sortable_columns() );
		if ( ! in_array( $orderby, $valid_columns, true ) ) {
			$orderby = 'created_at';
		}

		$query .= " ORDER BY {$orderby} {$order}";

		// Count total items
		$total_query = str_replace( 'SELECT *', 'SELECT COUNT(*)', $query );
		if ( ! empty( $query_args ) ) {
			$total_items = $wpdb->get_var( $wpdb->prepare( $total_query, $query_args ) );
		} else {
			$total_items = $wpdb->get_var( $total_query );
		}

		// Pagination
		$query .= " LIMIT %d OFFSET %d";
		$query_args[] = $per_page;
		$query_args[] = ( $current_page - 1 ) * $per_page;

		// Fetch items
		if ( ! empty( $query_args ) ) {
			$this->items = $wpdb->get_results( $wpdb->prepare( $query, $query_args ), ARRAY_A );
		} else {
			$this->items = $wpdb->get_results( $query, ARRAY_A );
		}

		// Set pagination args
		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page ),
		) );
	}
}
