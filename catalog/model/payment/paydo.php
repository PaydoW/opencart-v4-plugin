<?php
namespace Opencart\Catalog\Model\Extension\Paydo\Payment;

class Paydo extends \Opencart\System\Engine\Model {
	private $invoice_table_ready = false;

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
			$option_data = [];

			if ($this->config->get('payment_paydo_methods_mode')) {
				if ($this->isHostedPageEnabled()) {
					$option_data['hosted'] = [
						'code' => 'paydo.hosted',
						'name' => $this->language->get('text_hosted_page')
					];
				}

				foreach ($this->getEnabledPaymentMethods() as $identifier => $method) {
					$option_data['method_' . $identifier] = [
						'code' => 'paydo.method_' . $identifier,
						'name' => htmlspecialchars($method['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
						'image' => $method['logo']
					];
				}
			} else {
				$option_data['paydo'] = [
					'code' => 'paydo.paydo',
					'name' => $this->language->get('heading_title')
				];
			}

			if (!$option_data) {
				return [];
			}

			$method_data = [
				'code'       => 'paydo',
				'name'       => $this->language->get('heading_title'),
				'option'     => $option_data,
				'sort_order' => $this->config->get('payment_paydo_sort_order')
			];
		}

		return $method_data;
	}

	public function getEnabledPaymentMethods(): array {
		$available_methods = $this->config->get('payment_paydo_available_methods');
		$enabled_methods = $this->config->get('payment_paydo_enabled_methods');

		if (!is_array($available_methods) || !is_array($enabled_methods)) {
			return [];
		}

		$methods = [];

		foreach (array_unique(array_map('strval', $enabled_methods)) as $identifier) {
			if (
				!preg_match('/^[1-9][0-9]*$/', $identifier) ||
				!isset($available_methods[$identifier]) ||
				!is_array($available_methods[$identifier])
			) {
				continue;
			}

			$title = trim((string)($available_methods[$identifier]['title'] ?? ''));
			if ($title === '') {
				continue;
			}

			$logo = trim((string)($available_methods[$identifier]['logo'] ?? ''));
			$scheme = strtolower((string)parse_url($logo, PHP_URL_SCHEME));
			if ($logo !== '' && (filter_var($logo, FILTER_VALIDATE_URL) === false || !in_array($scheme, ['http', 'https'], true))) {
				$logo = '';
			}

			$methods[$identifier] = [
				'identifier' => $identifier,
				'title' => $title,
				'logo' => $logo
			];
		}

		return $methods;
	}

	public function isHostedPageEnabled(): bool {
		$value = $this->config->get('payment_paydo_hosted_page_enabled');

		return $value === null ? true : (bool)$value;
	}

	public function saveInvoice(
		int $order_id,
		string $invoice_id,
		string $payment_mode,
		string $amount,
		string $currency
	): void {
		$this->ensureInvoiceTable();

		$this->db->query(
			"INSERT INTO `" . DB_PREFIX . "paydo_invoice` SET
				`order_id` = '" . (int)$order_id . "',
				`invoice_id` = '" . $this->db->escape($invoice_id) . "',
				`payment_mode` = '" . $this->db->escape($payment_mode) . "',
				`amount` = '" . $this->db->escape($amount) . "',
				`currency` = '" . $this->db->escape($currency) . "',
				`date_added` = NOW(),
				`date_modified` = NOW()
			ON DUPLICATE KEY UPDATE
				`invoice_id` = VALUES(`invoice_id`),
				`payment_mode` = VALUES(`payment_mode`),
				`amount` = VALUES(`amount`),
				`currency` = VALUES(`currency`),
				`date_modified` = NOW()"
		);
	}

	public function getInvoiceByOrderId(int $order_id): array {
		$this->ensureInvoiceTable();

		$query = $this->db->query(
			"SELECT * FROM `" . DB_PREFIX . "paydo_invoice` WHERE `order_id` = '" . (int)$order_id . "' LIMIT 1"
		);

		return is_array($query->row) ? $query->row : [];
	}

	public function getInvoiceByInvoiceId(string $invoice_id): array {
		$this->ensureInvoiceTable();

		$query = $this->db->query(
			"SELECT * FROM `" . DB_PREFIX . "paydo_invoice` WHERE `invoice_id` = '" . $this->db->escape($invoice_id) . "' LIMIT 1"
		);

		return is_array($query->row) ? $query->row : [];
	}

	public function updateInvoiceVerification(
		int $order_id,
		string $invoice_id,
		string $txid,
		?int $invoice_status,
		?int $transaction_state
	): void {
		$this->ensureInvoiceTable();

		$this->db->query(
			"UPDATE `" . DB_PREFIX . "paydo_invoice` SET
				`txid` = '" . $this->db->escape($txid) . "',
				`invoice_status` = " . ($invoice_status === null ? 'NULL' : (int)$invoice_status) . ",
				`transaction_state` = " . ($transaction_state === null ? 'NULL' : (int)$transaction_state) . ",
				`verification_attempts` = '0',
				`date_modified` = NOW()
			WHERE `order_id` = '" . (int)$order_id . "'
				AND `invoice_id` = '" . $this->db->escape($invoice_id) . "'"
		);
	}

	public function recordVerificationFailure(int $order_id, string $invoice_id): int {
		$this->ensureInvoiceTable();
		$this->db->query(
			"UPDATE `" . DB_PREFIX . "paydo_invoice` SET
				`verification_attempts` = `verification_attempts` + 1,
				`date_modified` = NOW()
			WHERE `order_id` = '" . (int)$order_id . "'
				AND `invoice_id` = '" . $this->db->escape($invoice_id) . "'"
		);

		$record = $this->getInvoiceByOrderId($order_id);

		return (int)($record['verification_attempts'] ?? 0);
	}

	public function acquireOrderLock(int $order_id): bool {
		$lock_name = 'paydo_invoice_' . $order_id;
		$query = $this->db->query("SELECT GET_LOCK('" . $this->db->escape($lock_name) . "', 10) AS `acquired`");

		return (int)($query->row['acquired'] ?? 0) === 1;
	}

	public function releaseOrderLock(int $order_id): void {
		$lock_name = 'paydo_invoice_' . $order_id;
		$this->db->query("SELECT RELEASE_LOCK('" . $this->db->escape($lock_name) . "')");
	}

	private function ensureInvoiceTable(): void {
		if ($this->invoice_table_ready) {
			return;
		}

		$this->db->query(
			"CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "paydo_invoice` (
				`order_id` INT(11) NOT NULL,
				`invoice_id` VARCHAR(64) NOT NULL,
				`payment_mode` VARCHAR(64) NOT NULL DEFAULT 'hosted',
				`txid` VARCHAR(128) NOT NULL DEFAULT '',
				`amount` DECIMAL(15,4) NOT NULL DEFAULT '0.0000',
				`currency` VARCHAR(8) NOT NULL DEFAULT '',
				`invoice_status` INT(11) DEFAULT NULL,
				`transaction_state` INT(11) DEFAULT NULL,
				`verification_attempts` INT(11) NOT NULL DEFAULT '0',
				`date_added` DATETIME NOT NULL,
				`date_modified` DATETIME NOT NULL,
				PRIMARY KEY (`order_id`),
				UNIQUE KEY `invoice_id` (`invoice_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
		);

		$columns = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "paydo_invoice`")->rows;
		$column_names = array_column($columns, 'Field');
		$definitions = [
			'payment_mode' => "VARCHAR(64) NOT NULL DEFAULT 'hosted' AFTER `invoice_id`",
			'txid' => "VARCHAR(128) NOT NULL DEFAULT '' AFTER `payment_mode`",
			'amount' => "DECIMAL(15,4) NOT NULL DEFAULT '0.0000' AFTER `txid`",
			'currency' => "VARCHAR(8) NOT NULL DEFAULT '' AFTER `amount`",
			'invoice_status' => "INT(11) DEFAULT NULL AFTER `currency`",
			'transaction_state' => "INT(11) DEFAULT NULL AFTER `invoice_status`",
			'verification_attempts' => "INT(11) NOT NULL DEFAULT '0' AFTER `transaction_state`",
			'date_modified' => "DATETIME NULL DEFAULT NULL AFTER `date_added`"
		];

		foreach ($definitions as $column => $definition) {
			if (!in_array($column, $column_names, true)) {
				$this->db->query("ALTER TABLE `" . DB_PREFIX . "paydo_invoice` ADD `" . $column . "` " . $definition);
			}
		}

		$this->db->query(
			"UPDATE `" . DB_PREFIX . "paydo_invoice` SET `date_modified` = `date_added` WHERE `date_modified` IS NULL"
		);
		$this->invoice_table_ready = true;
	}
}
