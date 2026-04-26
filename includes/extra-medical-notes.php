<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MedicalOrderNotes
 * Beheert medische beoordelingen door artsen en apothekers voor WooCommerce orders.
 */
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

final class MedicalOrderNotes {

	private const META_PREFIX = '_dc_medical_';

	public function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'register_medical_metabox' ] );
		add_action( 'woocommerce_process_shop_order_meta', [ $this, 'handle_order_save' ] );

		add_action( 'woocommerce_email_after_order_table', [ $this, 'render_email_content' ], 15, 4 );

		add_filter( 'manage_woocommerce_page_wc-orders_columns', [ $this, 'inject_admin_columns' ] );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', [ $this, 'render_column_data' ], 10, 2 );
		add_filter( 'manage_edit-shop_order_columns', [ $this, 'inject_admin_columns' ] );
		add_action( 'manage_shop_order_posts_custom_column', [ $this, 'render_column_data' ], 10, 2 );
	}

	/**
	 * Definieert de beschikbare statussen en hun configuratie.
	 */
	private function get_status_config(): array {
		return [
			'cancel' => [
				'label' => __( 'Annuleren', 'doctorcura-core' ),
				'slug'  => 'cancelled',
				'color' => '#d63638',
			],
			'processing' => [
				'label' => __( 'In behandeling', 'doctorcura-core' ),
				'slug'  => 'processing',
				'color' => '#dba617',
			],
			'completed' => [
				'label' => __( 'Afgerond', 'doctorcura-core' ),
				'slug'  => 'completed',
				'color' => '#00a32a',
			],
		];
	}

	/**
	 * Vindt een status key op basis van label.
	 */
	private function get_status_key_by_label( string $label ): string {
		$config = $this->get_status_config();

		foreach ( $config as $key => $status ) {
			if ( isset( $status['label'] ) && $status['label'] === $label ) {
				return $key;
			}
		}

		return '';
	}

	public function register_medical_metabox(): void {
		$screen = class_exists( CustomOrdersTableController::class )
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';

		add_meta_box(
			'dc_medical_notes_box',
			__( 'Medische beoordeling', 'doctorcura-core' ),
			[ $this, 'render_metabox_html' ],
			$screen,
			'normal',
			'high'
		);
	}

	public function render_metabox_html( mixed $post_or_order ): void {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );

		if ( ! $order ) {
			return;
		}

		wp_nonce_field( 'dc_medical_action', 'dc_medical_nonce' );

		$roles = [
			'dokter'    => __( 'Arts', 'doctorcura-core' ),
			'apotheker' => __( 'Apotheker', 'doctorcura-core' ),
		];

		$statuses = $this->get_status_config();
		?>
		<style>
			.dc-medical-grid {
				display: grid;
				grid-template-columns: 1fr 1fr;
				gap: 20px;
				margin-top: 10px;
			}

			.dc-role-card {
				padding: 15px;
				background: #fff;
				border: 1px solid #c3c4c7;
				border-radius: 4px;
				box-shadow: 0 1px 1px rgba(0,0,0,.04);
			}

			.dc-role-card label {
				display: block;
				font-weight: 700;
				margin-bottom: 10px;
				font-size: 14px;
				color: #2c3338;
			}

			.dc-role-card select,
			.dc-role-card textarea {
				width: 100%;
				margin-bottom: 10px;
			}

			.dc-role-card textarea {
				min-height: 100px;
				font-size: 13px;
			}
		</style>

		<div class="dc-medical-grid">
			<?php foreach ( $roles as $key => $label ) : ?>
				<div class="dc-role-card">
					<label><?php echo esc_html( $label ); ?></label>

					<select name="dc_med[<?php echo esc_attr( $key ); ?>][status]">
						<option value=""><?php esc_html_e( '-- Status selecteren --', 'doctorcura-core' ); ?></option>
						<?php foreach ( $statuses as $status_key => $status ) : ?>
							<option
								value="<?php echo esc_attr( $status_key ); ?>"
								<?php selected( $order->get_meta( self::META_PREFIX . $key . '_status' ), $status_key ); ?>
							>
								<?php echo esc_html( $status['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<textarea
						name="dc_med[<?php echo esc_attr( $key ); ?>][note]"
						placeholder="<?php echo esc_attr__( 'Medische toelichting...', 'doctorcura-core' ); ?>"
					><?php echo esc_textarea( (string) $order->get_meta( self::META_PREFIX . $key . '_note' ) ); ?></textarea>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	public function handle_order_save( int $order_id ): void {
		if (
			! isset( $_POST['dc_medical_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dc_medical_nonce'] ) ), 'dc_medical_action' ) ||
			! current_user_can( 'edit_shop_orders' )
		) {
			return;
		}

		$order = wc_get_order( $order_id );
		$data  = isset( $_POST['dc_med'] ) && is_array( $_POST['dc_med'] )
			? wp_unslash( $_POST['dc_med'] )
			: [];

		if ( ! $order || empty( $data ) ) {
			return;
		}

		foreach ( [ 'dokter', 'apotheker' ] as $role ) {
			if ( ! isset( $data[ $role ] ) || ! is_array( $data[ $role ] ) ) {
				continue;
			}

			$status = sanitize_text_field( $data[ $role ]['status'] ?? '' );
			$note   = sanitize_textarea_field( $data[ $role ]['note'] ?? '' );

			$order->update_meta_data( self::META_PREFIX . $role . '_status', $status );
			$order->update_meta_data( self::META_PREFIX . $role . '_note', $note );
		}

		$this->update_order_status_logic( $order, $data );

		$order->save();
	}

	private function update_order_status_logic( WC_Order $order, array $data ): void {
		$doc_status = isset( $data['dokter']['status'] ) ? sanitize_text_field( (string) $data['dokter']['status'] ) : '';
		$apo_status = isset( $data['apotheker']['status'] ) ? sanitize_text_field( (string) $data['apotheker']['status'] ) : '';
		$config     = $this->get_status_config();

		if ( 'cancel' === $doc_status || 'cancel' === $apo_status ) {
			$order->set_status(
				$config['cancel']['slug'],
				__( 'Geannuleerd na medische beoordeling.', 'doctorcura-core' )
			);
			return;
		}

		$final_choice = $doc_status ?: $apo_status;

		if ( isset( $config[ $final_choice ] ) ) {
			$order->set_status(
				$config[ $final_choice ]['slug'],
				__( 'Status bijgewerkt via medische beoordeling.', 'doctorcura-core' )
			);
		}
	}

	public function render_email_content( WC_Order $order, bool $sent_to_admin, bool $plain_text = false ): void {
		if ( $sent_to_admin ) {
			return;
		}

		$doc_status = $order->get_meta( self::META_PREFIX . 'dokter_status' );
		$apo_status = $order->get_meta( self::META_PREFIX . 'apotheker_status' );

		if ( ! $doc_status && ! $apo_status ) {
			return;
		}

		echo '<div style="margin:20px 0;padding:15px;border:1px solid #eee;border-radius:8px;background-color:#fcfcfc;">';
		echo '<h3 style="margin-top:0;color:#222;">' . esc_html__( 'Uw medische beoordeling', 'doctorcura-core' ) . '</h3>';

		$role_labels = [
			'dokter'    => __( 'Arts', 'doctorcura-core' ),
			'apotheker' => __( 'Apotheker', 'doctorcura-core' ),
		];

		$config = $this->get_status_config();

		foreach ( $role_labels as $key => $label ) {
			$status_key = (string) $order->get_meta( self::META_PREFIX . $key . '_status' );
			$note       = $order->get_meta( self::META_PREFIX . $key . '_note' );

			if ( ! $status_key || ! isset( $config[ $status_key ] ) ) {
				continue;
			}

			$status_label = $config[ $status_key ]['label'];

			echo '<p style="margin:5px 0;"><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $status_label ) . '</p>';

			if ( $note ) {
				echo '<p style="margin:0 0 10px 0;font-style:italic;color:#666;">' . nl2br( esc_html( (string) $note ) ) . '</p>';
			}
		}

		echo '</div>';
	}

	public function inject_admin_columns( array $columns ): array {
		$updated_columns = [];

		foreach ( $columns as $key => $label ) {
			$updated_columns[ $key ] = $label;

			if ( 'order_status' === $key ) {
				$updated_columns['medical_review'] = __( 'Medisch', 'doctorcura-core' );
			}
		}

		return $updated_columns;
	}

	public function render_column_data( string $column, mixed $order_or_id ): void {
		if ( 'medical_review' !== $column ) {
			return;
		}

		$order = $order_or_id instanceof WC_Order ? $order_or_id : wc_get_order( $order_or_id );

		if ( ! $order ) {
			return;
		}

		$config = $this->get_status_config();

		foreach ( [ 'dokter' => 'D', 'apotheker' => 'A' ] as $key => $short ) {
			$status_key = (string) ( $order->get_meta( self::META_PREFIX . $key . '_status' ) ?: '' );

			if ( $status_key && isset( $config[ $status_key ] ) ) {
				$val   = $config[ $status_key ]['label'];
				$color = $config[ $status_key ]['color'];
			} else {
				$val   = '-';
				$color = '#999';
			}

			printf(
				'<div style="font-size:11px;line-height:1.2;font-weight:600;color:%s;">%s: %s</div>',
				esc_attr( $color ),
				esc_html( $short ),
				esc_html( $val )
			);
		}
	}
}

new MedicalOrderNotes();

