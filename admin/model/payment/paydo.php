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
	}

	public function uninstall() {
		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('payment_paydo');
	}
}
