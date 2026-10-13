<?php
namespace Opencart\Admin\Model\Extension\Paydo\Payment;

class Paydo extends \Opencart\System\Engine\Model {
	private $invoice_table_ready = false;

	public function install(): void {
		$this->upgrade();
	}

	public function upgrade(): void {
		$defaults = [
			'payment_paydo_sort_order' => 0,
			'payment_paydo_methods_mode' => 0,
			'payment_paydo_hosted_page_enabled' => 1,
			'payment_paydo_project_id' => '',
			'payment_paydo_jwt_token' => '',
			'payment_paydo_available_methods' => [],
			'payment_paydo_enabled_methods' => [],
			'payment_paydo_order_status_wait' => $this->config->get('config_order_status_id'),
			'payment_paydo_order_status_success' => $this->config->get('config_order_status_id'),
			'payment_paydo_order_status_error' => $this->config->get('config_order_status_id')
		];

		$this->load->model('setting/setting');
		$existing = $this->model_setting_setting->getSetting('payment_paydo');
		$settings = $existing;
		foreach ($defaults as $key => $value) {
			if (!array_key_exists($key, $settings)) {
				$settings[$key] = $value;
			}
		}
		if ($settings !== $existing) {
			$this->model_setting_setting->editSetting('payment_paydo', $settings);
		}
		$this->ensureInvoiceTable();
		$this->load->model('setting/event');
		if (!$this->model_setting_event->getEventByCode('paydo_checkout_logos')) {
			$this->model_setting_event->addEvent([
				'code' => 'paydo_checkout_logos',
				'description' => 'Show PayDo payment method logos in checkout',
				'trigger' => 'catalog/view/checkout/payment_method/after',
				'action' => 'extension/paydo/payment/paydo.checkoutLogos',
				'status' => 1,
				'sort_order' => 0
			]);
		}
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

	public function uninstall(): void {
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('paydo_checkout_logos');
		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('payment_paydo');
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "paydo_invoice`");
	}
}
