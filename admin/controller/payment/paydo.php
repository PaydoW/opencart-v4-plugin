<?php
namespace Opencart\Admin\Controller\Extension\Paydo\Payment;

class Paydo extends \Opencart\System\Engine\Controller {
	protected $error = [];

	public function index() {
		$this->load->language('extension/paydo/payment/paydo');
		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('extension/paydo/payment/paydo');
		$this->model_extension_paydo_payment_paydo->upgrade();

		if ($this->request->server['REQUEST_METHOD'] === 'POST' && $this->validate()) {
			$settings = $this->model_setting_setting->getSetting('payment_paydo');
			$this->model_setting_setting->editSetting('payment_paydo', array_replace($settings, $this->request->post));
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('marketplace/extension',
				'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
		}

		$data['error_warning'] = $this->error['warning'] ?? '';
		$data['error_public_id'] = $this->error['public_id'] ?? '';
		$data['error_secret_key'] = $this->error['secret_key'] ?? '';
		$data['error_enabled_methods'] = $this->error['enabled_methods'] ?? '';

		$data['breadcrumbs'] = [
			[
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
			],
			[
				'text' => $this->language->get('text_extension'),
				'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
			],
			[
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/paydo/payment/paydo', 'user_token=' . $this->session->data['user_token'], true)
			]
		];

		$data['action'] = $this->url->link('extension/paydo/payment/paydo',
			'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension',
			'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

		$fields = [
			'payment_paydo_public_id',
			'payment_paydo_secret_key',
			'payment_paydo_methods_mode',
			'payment_paydo_hosted_page_enabled',
			'payment_paydo_project_id',
			'payment_paydo_jwt_token',
			'payment_paydo_enabled_methods',
			'payment_paydo_order_status_wait',
			'payment_paydo_order_status_success',
			'payment_paydo_order_status_error',
			'payment_paydo_status',
			'payment_paydo_geo_zone_id',
			'payment_paydo_sort_order'
		];

		foreach ($fields as $f) {
			$data[$f] = $this->request->post[$f] ?? $this->config->get($f);
		}

		if ($data['payment_paydo_hosted_page_enabled'] === null) {
			$data['payment_paydo_hosted_page_enabled'] = 1;
		}

		$data['payment_paydo_enabled_methods'] = is_array($data['payment_paydo_enabled_methods'])
			? array_map('strval', $data['payment_paydo_enabled_methods'])
			: [];
		$data['payment_paydo_available_methods'] = $this->getAvailableMethods();
		$data['sync_methods_url'] = $this->url->link(
			'extension/paydo/payment/paydo.syncMethods',
			'user_token=' . $this->session->data['user_token'],
			true
		);

		$data['ipn_url'] = HTTP_CATALOG . 'index.php?route=extension/paydo/payment/paydo.callback';

		$this->load->model('localisation/order_status');
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		$this->load->model('localisation/geo_zone');
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/paydo/payment/paydo', $data));
	}

	private function validate(): bool {
		if (!$this->user->hasPermission('modify', 'extension/paydo/payment/paydo')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$required_fields = ['public_id', 'secret_key'];

		foreach ($required_fields as $f) {
			if (empty($this->request->post['payment_paydo_' . $f])) {
				$this->error[$f] = $this->language->get('error_' . $f);
			}
		}

		$this->request->post['payment_paydo_methods_mode'] = !empty($this->request->post['payment_paydo_methods_mode']) ? 1 : 0;
		$this->request->post['payment_paydo_hosted_page_enabled'] = !empty($this->request->post['payment_paydo_hosted_page_enabled']) ? 1 : 0;
		$this->request->post['payment_paydo_project_id'] = trim((string)($this->request->post['payment_paydo_project_id'] ?? ''));
		$this->request->post['payment_paydo_jwt_token'] = trim((string)($this->request->post['payment_paydo_jwt_token'] ?? ''));
		$this->request->post['payment_paydo_enabled_methods'] = $this->sanitizeEnabledMethods(
			$this->request->post['payment_paydo_enabled_methods'] ?? []
		);
		$this->request->post['payment_paydo_available_methods'] = $this->getAvailableMethods();

		if (
			$this->request->post['payment_paydo_methods_mode'] &&
			!$this->request->post['payment_paydo_hosted_page_enabled'] &&
			!$this->request->post['payment_paydo_enabled_methods']
		) {
			$this->error['enabled_methods'] = $this->language->get('error_enabled_methods');
		}

		return empty($this->error);
	}

	public function syncMethods(): void {
		$this->load->language('extension/paydo/payment/paydo');
		$this->response->addHeader('Content-Type: application/json');

		if (!$this->user->hasPermission('modify', 'extension/paydo/payment/paydo')) {
			$this->response->setOutput(json_encode(['error' => $this->language->get('error_permission')]));
			return;
		}

		if (($this->request->server['REQUEST_METHOD'] ?? '') !== 'POST') {
			$this->response->setOutput(json_encode(['error' => $this->language->get('error_methods_request')]));
			return;
		}

		$project_id = trim((string)($this->request->post['project_id'] ?? ''));
		$jwt_token = trim((string)($this->request->post['jwt_token'] ?? ''));

		if ($project_id === '' || $jwt_token === '') {
			$this->response->setOutput(json_encode(['error' => $this->language->get('error_methods_credentials')]));
			return;
		}

		$url = 'https://api.paydo.com/v3/instrument-settings/payment-methods/available-for-application/' . rawurlencode($project_id);
		$curl = curl_init($url);

		curl_setopt_array($curl, [
			CURLOPT_HTTPHEADER => [
				'Accept: application/json',
				'Authorization: Bearer ' . $jwt_token
			],
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT => 30
		]);

		$response = curl_exec($curl);
		$status_code = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);

		if ($response === false) {
			$this->log->write('Paydo methods sync transport error');
			$this->response->setOutput(json_encode(['error' => $this->language->get('error_methods_request')]));
			return;
		}

		$json = json_decode($response, true);

		if ($status_code < 200 || $status_code >= 300) {
			$this->log->write('Paydo methods sync HTTP error: ' . $status_code);
			$this->response->setOutput(json_encode(['error' => $this->getSyncHttpError($status_code)]));
			return;
		}

		if (!is_array($json)) {
			$this->log->write('Paydo methods sync invalid JSON');
			$this->response->setOutput(json_encode(['error' => $this->language->get('error_methods_response')]));
			return;
		}

		$methods = $this->parseAvailableMethods($json['data'] ?? []);

		if (!$methods) {
			$this->response->setOutput(json_encode(['error' => $this->language->get('error_methods_empty')]));
			return;
		}

		$this->load->model('setting/setting');
		$settings = $this->model_setting_setting->getSetting('payment_paydo');
		$settings['payment_paydo_available_methods'] = $methods;
		$this->model_setting_setting->editSetting('payment_paydo', $settings);

		$this->response->setOutput(json_encode([
			'success' => sprintf($this->language->get('text_methods_synced'), count($methods)),
			'methods' => $methods
		]));
	}

	private function getAvailableMethods(): array {
		$methods = $this->config->get('payment_paydo_available_methods');

		return is_array($methods) ? $this->parseAvailableMethods($methods) : [];
	}

	private function parseAvailableMethods($rows): array {
		if (!is_array($rows)) {
			return [];
		}

		$methods = [];

		foreach ($rows as $key => $row) {
			if (!is_array($row)) {
				continue;
			}

			if (isset($row['paymentMethod']) && is_array($row['paymentMethod'])) {
				$row = $row['paymentMethod'];
				if (empty($row['isEnabled'])) {
					continue;
				}
			}

			if (!is_array($row)) {
				continue;
			}

			if (array_key_exists('isEnabled', $row) && !$row['isEnabled']) {
				continue;
			}

			$identifier = trim((string)($row['identifier'] ?? $key));
			$title = trim(strip_tags((string)($row['title'] ?? '')));

			if (!preg_match('/^[1-9][0-9]*$/', $identifier) || $title === '') {
				continue;
			}

			$logo = trim((string)($row['logo'] ?? ''));
			$scheme = strtolower((string)parse_url($logo, PHP_URL_SCHEME));
			if ($logo !== '' && (filter_var($logo, FILTER_VALIDATE_URL) === false || !in_array($scheme, ['http', 'https'], true))) {
				$logo = '';
			}

			$methods[$identifier] = [
				'identifier' => $identifier,
				'title' => substr($title, 0, 255),
				'logo' => $logo
			];
		}

		uasort($methods, static function (array $left, array $right): int {
			return strnatcasecmp($left['title'], $right['title']);
		});

		return $methods;
	}

	private function sanitizeEnabledMethods($methods): array {
		if (!is_array($methods)) {
			return [];
		}

		$available = $this->getAvailableMethods();
		$enabled = [];

		foreach ($methods as $identifier) {
			$identifier = (string)$identifier;
			if (isset($available[$identifier])) {
				$enabled[] = $identifier;
			}
		}

		return array_values(array_unique($enabled));
	}

	private function getSyncHttpError(int $status_code): string {
		if (in_array($status_code, [401, 403], true)) {
			return $this->language->get('error_methods_authorization');
		}

		if ($status_code === 404) {
			return $this->language->get('error_methods_project');
		}

		if ($status_code === 422) {
			return $this->language->get('error_methods_validation');
		}

		if ($status_code >= 500) {
			return $this->language->get('error_methods_service');
		}

		return $this->language->get('error_methods_response');
	}

	public function install() {
		$this->load->model('extension/paydo/payment/paydo');
		if (method_exists($this->model_extension_paydo_payment_paydo, 'install')) {
			$this->model_extension_paydo_payment_paydo->install();
		}
	}

	public function uninstall() {
		$this->load->model('extension/paydo/payment/paydo');
		if (method_exists($this->model_extension_paydo_payment_paydo, 'uninstall')) {
			$this->model_extension_paydo_payment_paydo->uninstall();
		}
	}

}
