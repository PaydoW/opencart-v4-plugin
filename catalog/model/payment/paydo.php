<?php
namespace Opencart\Catalog\Model\Extension\Paydo\Payment;

class Paydo extends \Opencart\System\Engine\Model {

	/**
	 * Get Methods
	 *
	 * @param array<string, mixed> $address array of data
	 *
	 * @return array<string, mixed>
	 */
	public function getMethods(array $address = []): array {
		$this->load->language('extension/paydo/payment/paydo');

		if ($this->cart->hasSubscription()) {
			$status = false;
		} elseif (!$this->config->get('config_checkout_payment_address')) {
			$status = true;
		} elseif (!$this->config->get('payment_paydo_geo_zone_id')) {
			$status = true;
		} else {
			// Geo Zone
			$this->load->model('localisation/geo_zone');

			$results = $this->model_localisation_geo_zone->getGeoZone((int)$this->config->get('payment_paydo_geo_zone_id'), (int)$address['country_id'], (int)$address['zone_id']);

			if ($results) {
				$status = true;
			} else {
				$status = false;
			}
		}

		$method_data = [];

		if ($status) {
			$option_data['paydo'] = [
				'code' => 'paydo.paydo',
				'name' => $this->language->get('heading_title')
			];

			$method_data = [
				'code'       => 'paydo',
				'name'       => $this->language->get('heading_title'),
				'option'     => $option_data,
				'sort_order' => $this->config->get('payment_paydo_sort_order')
			];
		}

		return $method_data;
	}

	public function saveInvoice(int $order_id, string $invoice_id): void {
		$this->ensureInvoiceTable();

		$this->db->query(
			"REPLACE INTO `" . DB_PREFIX . "paydo_invoice` SET `order_id` = '" . (int)$order_id . "', `invoice_id` = '" . $this->db->escape($invoice_id) . "', `date_added` = NOW()"
		);
	}

	public function getInvoiceByOrderId(int $order_id): array {
		$this->ensureInvoiceTable();

		$query = $this->db->query(
			"SELECT * FROM `" . DB_PREFIX . "paydo_invoice` WHERE `order_id` = '" . (int)$order_id . "' LIMIT 1"
		);

		return $query->row;
	}

	private function ensureInvoiceTable(): void {
		$this->db->query(
			"CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "paydo_invoice` (
				`order_id` INT(11) NOT NULL,
				`invoice_id` VARCHAR(64) NOT NULL,
				`date_added` DATETIME NOT NULL,
				PRIMARY KEY (`order_id`),
				UNIQUE KEY `invoice_id` (`invoice_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
		);
	}
}
