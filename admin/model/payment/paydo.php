<?php
namespace Opencart\Admin\Model\Extension\Paydo\Payment;

class Paydo extends \Opencart\System\Engine\Model {
	public function install() {
		$defaults = [
			'payment_paydo_sort_order' => 0,
			'payment_paydo_order_status_wait' => $this->config->get('config_order_status_id'),
			'payment_paydo_order_status_success' => $this->config->get('config_order_status_id'),
			'payment_paydo_order_status_error' => $this->config->get('config_order_status_id')
		];

		$this->load->model('setting/setting');
		$this->model_setting_setting->editSetting('payment_paydo', $defaults);

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

	public function uninstall() {
		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('payment_paydo');
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "paydo_invoice`");
	}
}
