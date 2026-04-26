<?php

/**
 * Link
 */
declare(strict_types=1);

namespace DoctorCura\OrderManagement;

use WC_Order;
use WC_Order_Query;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class OrderCancellation {

	private const CANCEL_WINDOW = HOUR_IN_SECONDS; // 1 uur
	private const META_KEY      = '_dc_customer_cancelled_at';
	private const ENDPOINT      = 'cancelled-orders';
	private const SUCCESS_QUERY = 'dc_cancelled';

	public function __construct() {
		add_action( 'init', [ $this, 'register_endpoint' ] );
		add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
		add_filter( 'woocommerce_account_menu_items', [ $this, 'add_account_menu_item' ], 20 );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', [ $this, 'render_cancelled_orders_endpoint' ] );

		add_action( 'woocommerce_email_after_order_table', [ $this, 'render_cancel_button_in_email' ], 999, 4 );
		add_action( 'template_redirect', [ $this, 'process_cancel_request' ] );

		add_action( 'woocommerce_admin_order_data_after_order_details', [ $this, 'render_admin_notice_styles' ] );
		add_action( 'woocommerce_admin_order_data_after_billing_address', [ $this, 'render_admin_notice' ] );
	}

	public function register_endpoint(): void {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
	}

	public function add_query_vars( array $vars ): array {
		$vars[] = self::ENDPOINT;
		return $vars;
	}

	public function add_account_menu_item( array $items ): array {
		$new_items = [];

		foreach ( $items as $key => $label ) {
			$new_items[ $key ] = $label;

			if ( 'orders' === $key ) {
				$new_items[ self::ENDPOINT ] = esc_html__( 'Stornierte Bestellungen', 'doctorcura-core' );
			}
		}

		if ( ! isset( $new_items[ self::ENDPOINT ] ) ) {
			$new_items[ self::ENDPOINT ] = esc_html__( 'Stornierte Bestellungen', 'doctorcura-core' );
		}

		return $new_items;
	}

	public function render_cancel_button_in_email( $order, bool $sent_to_admin, bool $plain_text, $email ): void {
		if ( $sent_to_admin || $plain_text ) {
			return;
		}

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( empty( $email->id ) ) {
			return;
		}

		$allowed_emails = [
			'customer_processing_order',
			'customer_on_hold_order',
			'customer_invoice',
		];

		if ( ! in_array( $email->id, $allowed_emails, true ) ) {
			return;
		}

		if ( ! $this->is_cancellable( $order ) ) {
			return;
		}

		$cancel_url = $this->get_cancel_url( $order );
		?>
		<div style="text-align:center;margin-top:40px;margin-bottom:30px;padding:25px 20px;background-color:#f8f9fa;border:1px solid #e0e0e0;border-radius:8px;">
			<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#333;margin:0 0 18px 0;line-height:1.8;text-align:left;display:inline-block;">
				<?php echo wp_kses_post( $this->render_language_block( 'English', 'Changed your mind?', 'You can cancel this order within 1 hour after placing it.' ) ); ?>
				<?php echo wp_kses_post( $this->render_language_block( 'Deutsch', 'Meinung geändert?', 'Sie können diese Bestellung innerhalb von 1 Stunde nach der Bestellung stornieren.' ) ); ?>
				<?php echo wp_kses_post( $this->render_language_block( 'Français', 'Vous avez changé d’avis ?', 'Vous pouvez annuler cette commande dans un délai de 1 heure après l’avoir passée.' ) ); ?>
			</div>

			<a href="<?php echo esc_url( $cancel_url ); ?>"
			   style="background:#0560FF;color:#ffffff;padding:14px 35px;border-radius:8px;text-decoration:none;font-weight:600;font-size:15px;font-family:Arial,Helvetica,sans-serif;display:inline-block;border:none;line-height:1.7;white-space:pre-line;text-align:center;">
				<?php echo esc_html( "Cancel order\nBestellung stornieren\nAnnuler la commande" ); ?>
			</a>
		</div>
		<?php
	}

	public function process_cancel_request(): void {
		if (
			empty( $_GET['dc_cancel_order'] ) ||
			empty( $_GET['key'] ) ||
			empty( $_GET['nonce'] )
		) {
			return;
		}

		$order_id  = absint( $_GET['dc_cancel_order'] );
		$order_key = wc_clean( wp_unslash( $_GET['key'] ) );
		$nonce     = wc_clean( wp_unslash( $_GET['nonce'] ) );

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			$this->add_notice_and_redirect(
				esc_html__( 'Bestellung nicht gefunden.', 'doctorcura-core' ),
				'error',
				wc_get_page_permalink( 'myaccount' )
			);
		}

		if ( ! wp_verify_nonce( $nonce, 'dc_cancel_order_' . $order_id ) ) {
			$this->add_notice_and_redirect(
				esc_html__( 'Ungültiger Sicherheits-Token.', 'doctorcura-core' ),
				'error',
				wc_get_page_permalink( 'myaccount' )
			);
		}

		if ( $order->get_order_key() !== $order_key ) {
			$this->add_notice_and_redirect(
				esc_html__( 'Ungültiger Bestellungsschlüssel.', 'doctorcura-core' ),
				'error',
				wc_get_page_permalink( 'myaccount' )
			);
		}

		if ( ! $this->is_cancellable( $order ) ) {
			$this->add_notice_and_redirect(
				esc_html__( 'Diese Bestellung kann nicht mehr storniert werden. Die 1-stündige Stornierungsfrist ist abgelaufen.', 'doctorcura-core' ),
				'error',
				$order->get_view_order_url()
			);
		}

		$order->update_status(
			'cancelled',
			esc_html__( 'Vom Kunden innerhalb der 1-stündigen Stornierungsfrist storniert.', 'doctorcura-core' )
		);

		$order->update_meta_data( self::META_KEY, current_time( 'mysql' ) );
		$order->save();

		$this->send_customer_cancellation_email( $order );

		$redirect_url = add_query_arg(
			[
				self::SUCCESS_QUERY => 1,
				'order_id'          => $order->get_id(),
			],
			wc_get_account_endpoint_url( self::ENDPOINT )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	public function render_cancelled_orders_endpoint(): void {
		if ( ! is_user_logged_in() ) {
			echo '<div class="woocommerce-info">' . esc_html__( 'Bitte melden Sie sich an, um Ihre stornierten Bestellungen zu sehen.', 'doctorcura-core' ) . '</div>';
			return;
		}

		$this->render_account_success_notice();

		$orders = $this->get_customer_cancelled_orders();

		echo '<h2>' . esc_html__( 'Stornierte Bestellungen', 'doctorcura-core' ) . '</h2>';
		echo '<p>' . esc_html__( 'Hier finden Sie alle stornierten Bestellungen in Ihrem Kundenkonto.', 'doctorcura-core' ) . '</p>';

		if ( empty( $orders ) ) {
			echo '<div class="woocommerce-info">';
			echo esc_html__( 'Noch keine stornierten Bestellungen vorhanden.', 'doctorcura-core' );
			echo '</div>';
			return;
		}

		echo '<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">';
		echo '<thead>';
		echo '<tr>';
		echo '<th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-number"><span class="nobr">' . esc_html__( 'Bestellung', 'doctorcura-core' ) . '</span></th>';
		echo '<th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-date"><span class="nobr">' . esc_html__( 'Datum', 'doctorcura-core' ) . '</span></th>';
		echo '<th class="woocommerce-orders-table__header"><span class="nobr">' . esc_html__( 'Produkte', 'doctorcura-core' ) . '</span></th>';
		echo '<th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-status"><span class="nobr">' . esc_html__( 'Status', 'woocommerce' ) . '</span></th>';
		echo '<th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-total"><span class="nobr">' . esc_html__( 'Gesamt', 'doctorcura-core' ) . '</span></th>';
		echo '<th class="woocommerce-orders-table__header"><span class="nobr">' . esc_html__( 'Storniert am', 'doctorcura-core' ) . '</span></th>';
		echo '</tr>';
		echo '</thead>';
		echo '<tbody>';

		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}

			$cancelled_at = (string) $order->get_meta( self::META_KEY );
			$cancelled_on = $cancelled_at
				? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $cancelled_at ) )
				: '—';

			$products = [];
			foreach ( $order->get_items() as $item ) {
				$name = $item->get_name();
				$qty  = (int) $item->get_quantity();

				if ( $name ) {
					$products[] = $qty > 1
						? sprintf( '%s × %d', $name, $qty )
						: $name;
				}
			}

			$product_list = ! empty( $products )
				? implode( '<br>', array_map( 'esc_html', $products ) )
				: '—';

			echo '<tr class="woocommerce-orders-table__row woocommerce-orders-table__row--status-cancelled order">';

			echo '<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-number" data-title="' . esc_attr__( 'Bestellung', 'doctorcura-core' ) . '">';
			echo '<a href="' . esc_url( $order->get_view_order_url() ) . '">#' . esc_html( $order->get_order_number() ) . '</a>';
			echo '</td>';

			echo '<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-date" data-title="' . esc_attr__( 'Datum', 'doctorcura-core' ) . '">';
			echo '<time datetime="' . esc_attr( $order->get_date_created() ? $order->get_date_created()->date( 'c' ) : '' ) . '">';
			echo esc_html( wc_format_datetime( $order->get_date_created() ) );
			echo '</time>';
			echo '</td>';

			echo '<td class="woocommerce-orders-table__cell" data-title="' . esc_attr__( 'Produkte', 'doctorcura-core' ) . '">';
			echo wp_kses_post( $product_list );
			echo '</td>';

			echo '<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-status" data-title="' . esc_attr__( 'Status', 'woocommerce' ) . '">';
			echo esc_html( wc_get_order_status_name( $order->get_status() ) );
			echo '</td>';

			echo '<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-total" data-title="' . esc_attr__( 'Gesamt', 'doctorcura-core' ) . '">';
			echo wp_kses_post( $order->get_formatted_order_total() );
			echo '</td>';

			echo '<td class="woocommerce-orders-table__cell" data-title="' . esc_attr__( 'Storniert am', 'doctorcura-core' ) . '">';
			echo esc_html( $cancelled_on );
			echo '</td>';


			echo '</tr>';
		}

		echo '</tbody>';
		echo '</table>';
	}

	private function get_customer_cancelled_orders(): array {
		$user_id = get_current_user_id();
		$user    = wp_get_current_user();
		$email   = $user instanceof WP_User ? (string) $user->user_email : '';

		$orders_by_user = [];
		$orders_by_mail = [];

		if ( $user_id > 0 ) {
			$query_user = new WC_Order_Query(
				[
					'customer_id' => $user_id,
					'status'      => [ 'wc-cancelled' ],
					'limit'       => 100,
					'orderby'     => 'date',
					'order'       => 'DESC',
					'return'      => 'objects',
				]
			);

			$orders_by_user = $query_user->get_orders();
		}

		if ( '' !== $email ) {
			$query_mail = new WC_Order_Query(
				[
					'billing_email' => $email,
					'status'        => [ 'wc-cancelled' ],
					'limit'         => 100,
					'orderby'       => 'date',
					'order'         => 'DESC',
					'return'        => 'objects',
				]
			);

			$orders_by_mail = $query_mail->get_orders();
		}

		$merged = [];

		foreach ( array_merge( $orders_by_user, $orders_by_mail ) as $order ) {
			if ( $order instanceof WC_Order ) {
				$merged[ $order->get_id() ] = $order;
			}
		}

		return array_values( $merged );
	}

	private function render_account_success_notice(): void {
		if ( ! is_account_page() || empty( $_GET[ self::SUCCESS_QUERY ] ) ) {
			return;
		}

		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		$order    = $order_id > 0 ? wc_get_order( $order_id ) : false;
		$order_nr = $order instanceof WC_Order ? $order->get_order_number() : '';

		$message = $order_nr
			? sprintf(
				esc_html__( 'Ihre Bestellung #%s wurde erfolgreich storniert.', 'doctorcura-core' ),
				esc_html( $order_nr )
			)
			: esc_html__( 'Ihre Bestellung wurde erfolgreich storniert.', 'doctorcura-core' );

		echo '<div class="woocommerce-notices-wrapper">';
		echo '<div class="woocommerce-message" role="alert">';
		echo esc_html( $message );
		echo '</div>';
		echo '</div>';
	}

	private function send_customer_cancellation_email( WC_Order $order ): void {
		$to = $order->get_billing_email();

		if ( ! is_email( $to ) ) {
			return;
		}

		$site_name    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$order_number = $order->get_order_number();
		$account_url  = wc_get_account_endpoint_url( self::ENDPOINT );
		$subject      = sprintf(
			esc_html__( 'Bestellung #%s storniert', 'doctorcura-core' ),
			$order_number
		);

		$message  = '<!DOCTYPE html>';
		$message .= '<html lang="de">';
		$message .= '<head>';
		$message .= '<meta charset="UTF-8">';
		$message .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
		$message .= '<title>' . esc_html( $subject ) . '</title>';
		$message .= '</head>';
		$message .= '<body style="margin:0;padding:0;background-color:#f4f6f8;font-family:Arial,Helvetica,sans-serif;color:#333333;">';

		$message .= '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f4f6f8;margin:0;padding:0;width:100%;">';
		$message .= '<tr><td align="center" style="padding:30px 15px;">';

		$message .= '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:700px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">';

		$message .= '<tr>';
		$message .= '<td style="background:#0560FF;padding:32px 24px;">';

		$message .= '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="width:100%;border-collapse:collapse;">';
		$message .= '<tr>';

		$message .= '<td valign="top" width="33.33%" style="width:33.33%;padding:0 10px;text-align:left;color:#ffffff;">';
		$message .= '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#ffffff;font-weight:700;margin:0 0 8px 0;">' . esc_html__( 'Status', 'doctorcura-core' ) . '</div>';
		$message .= '<div style="font-family:Arial,Helvetica,sans-serif;font-size:20px;line-height:1.35;color:#ffffff;font-weight:700;margin:0;">' . esc_html__( 'Bestellung storniert', 'doctorcura-core' ) . '</div>';
		$message .= '</td>';

		$message .= '<td valign="top" width="33.33%" style="width:33.33%;padding:0 10px;text-align:left;color:#ffffff;">';
		$message .= '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#ffffff;font-weight:700;margin:0 0 8px 0;">' . esc_html__( 'Bestellung', 'doctorcura-core' ) . '</div>';
		$message .= '<div style="font-family:Arial,Helvetica,sans-serif;font-size:20px;line-height:1.35;color:#ffffff;font-weight:700;margin:0;">#' . esc_html( $order_number ) . '</div>';
		$message .= '</td>';

		$message .= '<td valign="top" width="33.33%" style="width:33.33%;padding:0 10px;text-align:left;color:#ffffff;">';
		$message .= '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#ffffff;font-weight:700;margin:0 0 8px 0;">' . esc_html__( 'Konto', 'doctorcura-core' ) . '</div>';
		$message .= '<div style="font-family:Arial,Helvetica,sans-serif;font-size:20px;line-height:1.35;color:#ffffff;font-weight:700;margin:0;">' . esc_html__( 'Bestellübersicht', 'doctorcura-core' ) . '</div>';
		$message .= '</td>';

		$message .= '</tr>';
		$message .= '</table>';

		$message .= '</td>';
		$message .= '</tr>';

		$message .= '<tr><td style="padding:32px 30px 20px 30px;">';

		$message .= $this->render_email_language_block(
			esc_html__( 'Hallo,', 'doctorcura-core' ),
			esc_html__( 'Ihre Bestellung wurde erfolgreich storniert. Für diese Bestellung werden keine weiteren Schritte durchgeführt.', 'doctorcura-core' )
		);

		$message .= '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:20px 0;background:#f8f9fa;border:1px solid #e5e7eb;border-radius:8px;">';
		$message .= '<tr><td style="padding:18px 20px;">';
		$message .= $this->render_email_language_block(
			esc_html__( 'Bestellnummer', 'doctorcura-core' ),
			'#' . $order_number,
			false
		);
		$message .= '</td></tr>';
		$message .= '</table>';

		$message .= $this->render_email_language_block(
			esc_html__( 'Sie können Ihre stornierten Bestellungen jederzeit in Ihrem Kundenkonto einsehen.', 'doctorcura-core' ),
			'',
			false
		);

		$message .= '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 28px 0;">';
		$message .= '<tr><td align="center" style="border-radius:8px;background:#0560FF;">';
		$message .= '<a href="' . esc_url( $account_url ) . '" style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:600;line-height:1.8;color:#ffffff;text-decoration:none;border-radius:8px;">';
		$message .= esc_html__( 'Stornierte Bestellungen ansehen', 'doctorcura-core' );
		$message .= '</a>';
		$message .= '</td></tr>';
		$message .= '</table>';

		$message .= '<div style="padding:16px 18px;background:#fff8e1;border-left:4px solid #f0b429;border-radius:6px;margin:0 0 24px 0;">';
		$message .= $this->render_email_language_block(
			esc_html__( 'Falls Sie Fragen haben, kontaktieren Sie bitte unseren Kundenservice.', 'doctorcura-core' ),
			'',
			false
		);
		$message .= '</div>';

		$message .= '<div style="font-size:15px;line-height:1.8;color:#333333;">';
		$message .= $this->render_email_language_block(
			esc_html__( 'Mit freundlichen Grüßen,', 'doctorcura-core' ),
			$site_name
		);
		$message .= '</div>';

		$message .= '</td></tr>';

		$message .= '<tr>';
		$message .= '<td style="padding:18px 30px;background:#f8f9fa;border-top:1px solid #e5e7eb;text-align:left;">';
		$message .= '<div style="font-size:12px;line-height:1.8;color:#6b7280;">' . esc_html( $site_name . ' — ' . esc_html__( 'Diese E-Mail wurde automatisch versendet.', 'doctorcura-core' ) ) . '</div>';
		$message .= '</td>';
		$message .= '</tr>';

		$message .= '</table>';
		$message .= '</td></tr>';
		$message .= '</table>';
		$message .= '</body></html>';

		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		wp_mail( $to, $subject, $message, $headers );
	}

	public function render_admin_notice_styles( WC_Order $order ): void {
		$cancelled_at = $order->get_meta( self::META_KEY );

		if ( ! $cancelled_at ) {
			return;
		}
		?>
		<style>
			.dc-admin-cancel-notice {
				clear: both;
				width: 100%;
				box-sizing: border-box;
				margin: 16px 0 0;
				padding: 16px 18px;
				background: #fff8e5;
				border-left: 4px solid #dba617;
				color: #6b4e00;
			}

			.dc-admin-cancel-notice__title {
				font-weight: 700;
				margin: 0 0 6px;
			}

			.dc-admin-cancel-notice__text {
				margin: 0;
				line-height: 1.7;
			}

			.dc-admin-cancel-notice__meta {
				margin-top: 10px;
				font-size: 12px;
				line-height: 1.7;
				opacity: 0.95;
			}
		</style>
		<?php
	}

	public function render_admin_notice( WC_Order $order ): void {
		if ( ! $order->has_status( 'cancelled' ) ) {
			return;
		}

		$cancelled_at = $order->get_meta( self::META_KEY );

		if ( ! $cancelled_at ) {
			return;
		}

		$date_string = wp_date(
			get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			strtotime( (string) $cancelled_at )
		);

		echo '<div class="dc-admin-cancel-notice">';
		echo '<p class="dc-admin-cancel-notice__title">' . esc_html__( 'Hinweis', 'doctorcura-core' ) . '</p>';
		echo '<p class="dc-admin-cancel-notice__text">' . esc_html__( 'Diese Bestellung wurde vom Kunden innerhalb des 1-stündigen Zeitfensters storniert.', 'doctorcura-core' ) . '</p>';
		echo '<div class="dc-admin-cancel-notice__meta"><strong>' . esc_html__( 'Storniert am', 'doctorcura-core' ) . ':</strong> ' . esc_html( $date_string ) . '</div>';
		echo '</div>';
	}

	private function get_cancel_url( WC_Order $order ): string {
		return add_query_arg(
			[
				'dc_cancel_order' => $order->get_id(),
				'key'             => $order->get_order_key(),
				'nonce'           => wp_create_nonce( 'dc_cancel_order_' . $order->get_id() ),
			],
			home_url( '/' )
		);
	}

	private function add_notice_and_redirect( string $message, string $type, string $url ): void {
		wc_add_notice( $message, $type );
		wp_safe_redirect( $url );
		exit;
	}

	private function is_cancellable( WC_Order $order ): bool {
		if ( ! $order->has_status( [ 'pending', 'on-hold', 'processing' ] ) ) {
			return false;
		}

		$created = $order->get_date_created();

		if ( ! $created ) {
			return false;
		}

		$order_age_seconds = time() - $created->getTimestamp();

		return $order_age_seconds <= self::CANCEL_WINDOW;
	}

	private function render_language_block( string $label, string $title, string $text ): string {
		$html  = '<div style="margin:0 0 14px 0;">';
		$html .= '<div style="font-weight:700;margin-bottom:2px;">' . esc_html( $label ) . '</div>';

		if ( '' !== $title ) {
			$html .= '<div style="font-weight:600;">' . esc_html( $title ) . '</div>';
		}

		if ( '' !== $text ) {
			$html .= '<div>' . esc_html( $text ) . '</div>';
		}

		$html .= '</div>';

		return $html;
	}

	private function render_email_language_block( string $title, string $text = '', bool $with_margin = true ): string {
		$margin = $with_margin ? 'margin:0 0 16px 0;' : 'margin:0 0 10px 0;';

		$html  = '<div style="' . esc_attr( $margin . 'font-size:15px;line-height:1.8;color:#333333;' ) . '">';

		if ( '' !== $title ) {
			$html .= '<div style="font-weight:600;">' . esc_html( $title ) . '</div>';
		}

		if ( '' !== $text ) {
			$html .= '<div>' . esc_html( $text ) . '</div>';
		}

		$html .= '</div>';

		return $html;
	}
}

new OrderCancellation();

