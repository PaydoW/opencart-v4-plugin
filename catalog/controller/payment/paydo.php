<?php
namespace Opencart\Catalog\Controller\Extension\Paydo\Payment;

class Paydo extends \Opencart\System\Engine\Controller {
	private const API_BASE_URL = 'https://api.paydo.com/v3';
	private const CHECKOUT_BASE_URL = 'https://checkout.paydo.com';
	private const PAID_INVOICE_STATUS = 1;
	private const TERMINAL_ERROR_STATES = [3, 5];

	public function checkoutLogos(string &$route, array &$data, string &$output): void {
		if ($this->config->get('payment_paydo_status') && $this->config->get('payment_paydo_methods_mode')) {
			$output .= $this->load->view('extension/paydo/payment/checkout_logos');
		}
	}

	public function index(): string {
		$this->load->language('extension/paydo/payment/paydo');

		return $this->load->view('extension/paydo/payment/paydo', [
			'button_pay' => $this->language->get('button_pay'),
			'error_checkout' => $this->language->get('error_checkout'),
			'paydo_url' => $this->url->link('extension/paydo/payment/paydo.pay')
		]);
	}

	public function pay(): void {
		$this->load->language('extension/paydo/payment/paydo');
		$this->response->addHeader('Content-Type: application/json');

		$lock_acquired = false;
		$order_id = 0;

		try {
			$this->load->model('checkout/order');
			$this->load->model('extension/paydo/payment/paydo');

			if (empty($this->session->data['order_id'])) {
				$this->respondWithError($this->language->get('error_order_missing'));
				return;
			}

			$order_id = (int)$this->session->data['order_id'];
			$order_info = $this->model_checkout_order->getOrder($order_id);

			if (!$order_info || !$this->isPaydoOrder($order_info)) {
				$this->respondWithError($this->language->get('error_invalid_order_payment'));
				return;
			}

			$payment_mode = $this->getSelectedPaymentMode($order_info);
			if ($payment_mode === false) {
				$this->log->write('Paydo: checkout payment option failed server-side allowlist validation');
				$this->respondWithError($this->language->get('error_payment_method'));
				return;
			}

			$lock_acquired = $this->model_extension_paydo_payment_paydo->acquireOrderLock($order_id);
			if (!$lock_acquired) {
				$this->respondWithError($this->language->get('error_payment_busy'));
				return;
			}

			$stored_invoice = $this->model_extension_paydo_payment_paydo->getInvoiceByOrderId($order_id);
			if (!empty($stored_invoice['invoice_id'])) {
				$invoice_id = $this->validateInvoiceId((string)$stored_invoice['invoice_id']);
				if ($invoice_id !== '' && $this->isStoredInvoiceReusable($stored_invoice, $order_info, $payment_mode)) {
					$this->respondWithRedirect($invoice_id);
					return;
				}

				$this->respondWithError($this->language->get('error_invoice_exists'));
				return;
			}

			$order_products = $this->model_checkout_order->getProducts($order_id);
			$items = array_map(static function (array $product): array {
				return [
					'id' => (string)$product['order_product_id'],
					'name' => trim((string)$product['name'] . ' ' . (string)$product['model']),
					'price' => (float)$product['price']
				];
			}, $order_products);

			$request = $this->preparePaymentRequest($order_info, $items, $payment_mode);
			$request['signature'] = $this->generateSignature($request['order']);
			$invoice_id = $this->makeRequest($request);

			if ($invoice_id === '') {
				$this->log->write('Paydo: invoice creation failed');
				$this->respondWithError($this->language->get('error_invoice_create'));
				return;
			}

			$this->model_extension_paydo_payment_paydo->saveInvoice(
				$order_id,
				$invoice_id,
				$payment_mode,
				(string)$request['order']['amount'],
				(string)$request['order']['currency']
			);
			$this->setPendingOrderStatus($order_info, $invoice_id);
			$this->respondWithRedirect($invoice_id);
		} catch (\Throwable $e) {
			$this->log->write('Paydo pay exception: ' . get_class($e));
			$this->respondWithError($this->language->get('error_internal'));
		} finally {
			if ($lock_acquired && $order_id > 0) {
				$this->model_extension_paydo_payment_paydo->releaseOrderLock($order_id);
			}
		}
	}

	public function callback(): void {
		$this->response->addHeader('Content-Type: application/json');

		if (($this->request->server['REQUEST_METHOD'] ?? '') !== 'POST') {
			$this->response->setOutput(json_encode(['status' => 'ignored']));
			return;
		}

		$callback = json_decode(file_get_contents('php://input'), true);
		if (!is_array($callback)) {
			$this->log->write('Paydo callback rejected: invalid JSON');
			$this->response->setOutput(json_encode(['status' => 'rejected']));
			return;
		}

		$this->processCallback($callback);
	}

	private function processCallback(array $callback): void {

		$invoice_id = $this->validateInvoiceId((string)($callback['invoice']['id'] ?? ''));
		$callback_order_id = (int)($callback['transaction']['order']['id'] ?? 0);
		$state = isset($callback['transaction']['state']) && is_numeric($callback['transaction']['state'])
			? (int)$callback['transaction']['state']
			: null;

		if ($invoice_id === '' || $callback_order_id <= 0 || $state === null || $state < 1 || $state > 5) {
			$this->log->write('Paydo callback rejected: missing or invalid identifiers');
			$this->response->setOutput(json_encode(['status' => 'rejected']));
			return;
		}

		$this->load->model('checkout/order');
		$this->load->model('extension/paydo/payment/paydo');

		$stored_invoice = $this->model_extension_paydo_payment_paydo->getInvoiceByInvoiceId($invoice_id);
		$order_id = (int)($stored_invoice['order_id'] ?? 0);

		if (
			$order_id <= 0 ||
			$order_id !== $callback_order_id ||
			!hash_equals((string)($stored_invoice['invoice_id'] ?? ''), $invoice_id)
		) {
			$this->log->write('Paydo callback rejected: stored invoice relation mismatch');
			$this->response->setOutput(json_encode(['status' => 'rejected']));
			return;
		}

		if (!$this->model_extension_paydo_payment_paydo->acquireOrderLock($order_id)) {
			$this->response->addHeader('HTTP/1.1 503 Service Unavailable');
			$this->response->addHeader('Retry-After: 60');
			$this->response->setOutput(json_encode(['status' => 'pending']));
			return;
		}

		try {
			$stored_invoice = $this->model_extension_paydo_payment_paydo->getInvoiceByOrderId($order_id);
			if (!hash_equals((string)($stored_invoice['invoice_id'] ?? ''), $invoice_id)) {
				$this->log->write('Paydo callback rejected: stored invoice changed during verification');
				$this->response->setOutput(json_encode(['status' => 'rejected']));
				return;
			}

			$order_info = $this->model_checkout_order->getOrder($order_id);
			if (!$order_info || !$this->isPaydoOrder($order_info)) {
				$this->log->write('Paydo callback rejected: order payment ownership mismatch');
				$this->response->setOutput(json_encode(['status' => 'rejected']));
				return;
			}

			// A delayed or duplicate notification must never undo a verified payment.
			if (isset($stored_invoice['invoice_status']) && (int)$stored_invoice['invoice_status'] === self::PAID_INVOICE_STATUS) {
				$this->response->setOutput(json_encode(['status' => 'paid']));
				return;
			}

			$remote_invoice = $this->getInvoiceWithRetry($invoice_id);
			if (!$remote_invoice) {
				$attempts = $this->model_extension_paydo_payment_paydo->recordVerificationFailure($order_id, $invoice_id);
				if ($attempts === 3) {
					$this->addManualReviewHistory($order_info, $invoice_id);
				}
				$this->log->write('Paydo callback verification deferred after temporary API failure');
				$this->response->addHeader('HTTP/1.1 503 Service Unavailable');
				$this->response->addHeader('Retry-After: 60');
				$this->response->setOutput(json_encode(['status' => 'pending']));
				return;
			}

			$validation_error = $this->validateRemoteInvoice($remote_invoice, $invoice_id, $order_info);
			if ($validation_error !== '') {
				$this->log->write('Paydo callback rejected: ' . $validation_error);
				$this->response->setOutput(json_encode(['status' => 'rejected']));
				return;
			}

			$callback_txid = $this->extractCallbackTransactionId($callback);
			$remote_txid = $this->extractRemoteTransactionId($remote_invoice);
			$stored_txid = (string)($stored_invoice['txid'] ?? '');

			if (!$this->transactionIdentifiersMatch($stored_txid, $callback_txid, $remote_txid)) {
				$this->log->write('Paydo callback rejected: transaction identifier mismatch');
				$this->response->setOutput(json_encode(['status' => 'rejected']));
				return;
			}

			// Do not persist a transaction identifier supplied only by an unverified IPN.
			$txid = $remote_txid !== '' ? $remote_txid : $stored_txid;
			$invoice_status = isset($remote_invoice['status']) && is_numeric($remote_invoice['status'])
				? (int)$remote_invoice['status']
				: null;
			$already_paid = isset($stored_invoice['invoice_status']) &&
				(int)$stored_invoice['invoice_status'] === self::PAID_INVOICE_STATUS;
			$already_terminal = isset($stored_invoice['invoice_status']) &&
				in_array((int)$stored_invoice['invoice_status'], self::TERMINAL_ERROR_STATES, true);

			$this->model_extension_paydo_payment_paydo->updateInvoiceVerification(
				$order_id,
				$invoice_id,
				$txid,
				$invoice_status,
				$state
			);

			if ($invoice_status === self::PAID_INVOICE_STATUS) {
				if (!$already_paid) {
					$this->updateOrderStatus($order_id, (int)$this->config->get('payment_paydo_order_status_success'));
				}
				$this->response->setOutput(json_encode(['status' => 'paid']));
				return;
			}

			// Callback state is only a notification; use the independently read invoice.
			if (in_array($invoice_status, self::TERMINAL_ERROR_STATES, true)) {
				if (!$already_terminal) {
					$this->updateOrderStatus($order_id, (int)$this->config->get('payment_paydo_order_status_error'));
				}
				$this->response->setOutput(json_encode(['status' => 'failed']));
				return;
			}

			// Ask PayDo to redeliver while its invoice status is still catching up.
			$this->response->addHeader('HTTP/1.1 503 Service Unavailable');
			$this->response->addHeader('Retry-After: 60');
			$this->response->setOutput(json_encode(['status' => 'pending']));
		} finally {
			$this->model_extension_paydo_payment_paydo->releaseOrderLock($order_id);
		}
	}

	public function failure(): void {
		$this->load->language('extension/paydo/payment/paydo');
		$this->session->data['error'] = $this->language->get('error_payment_failed');
		$this->response->redirect($this->url->link('checkout/checkout', '', true));
	}

	private function preparePaymentRequest(array $order_info, array $items, string $payment_mode): array {
		$amount = number_format((float)$order_info['total'], 2, '.', '');
		$request = [
			'publicKey' => (string)$this->config->get('payment_paydo_public_id'),
			'order' => [
				'id' => (string)$order_info['order_id'],
				'amount' => $amount,
				'currency' => (string)$order_info['currency_code'],
				'description' => sprintf($this->language->get('order_description'), $order_info['order_id']),
				'items' => $items
			],
			'payer' => [
				'email' => (string)$order_info['email'],
				'phone' => (string)$order_info['telephone'],
				'name' => trim((string)$order_info['firstname'] . ' ' . (string)$order_info['lastname'])
			],
			'language' => $this->getPaydoLocale(),
			'productUrl' => $this->url->link('common/home'),
			'resultUrl' => $this->url->link('checkout/success'),
			'failPath' => $this->url->link('extension/paydo/payment/paydo.failure')
		];

		if (preg_match('/^[1-9][0-9]*$/', $payment_mode)) {
			$request['paymentMethod'] = (int)$payment_mode;
		}

		return $request;
	}

	private function getSelectedPaymentMode(array $order_info) {
		if (!$this->config->get('payment_paydo_methods_mode')) {
			return 'hosted';
		}

		$code = $this->extractPaymentCode($order_info);
		$this->load->model('extension/paydo/payment/paydo');

		if (
			in_array($code, ['paydo.hosted', 'paydo.paydo'], true) &&
			$this->model_extension_paydo_payment_paydo->isHostedPageEnabled()
		) {
			return 'hosted';
		}

		if (!preg_match('/^paydo\.method_([1-9][0-9]*)$/', $code, $matches)) {
			return false;
		}

		$methods = $this->model_extension_paydo_payment_paydo->getEnabledPaymentMethods();

		return isset($methods[$matches[1]]) ? $matches[1] : false;
	}

	private function extractPaymentCode(array $order_info): string {
		$payment_method = $order_info['payment_method'] ?? [];

		if (is_array($payment_method) && isset($payment_method['code'])) {
			return (string)$payment_method['code'];
		}

		if (is_string($payment_method)) {
			$decoded = json_decode($payment_method, true);
			if (is_array($decoded) && isset($decoded['code'])) {
				return (string)$decoded['code'];
			}
		}

		return isset($order_info['payment_code']) ? (string)$order_info['payment_code'] : '';
	}

	private function generateSignature(array $order): string {
		return hash('sha256', implode(':', [
			(string)$order['amount'],
			(string)$order['currency'],
			(string)$order['id'],
			(string)$this->config->get('payment_paydo_secret_key')
		]));
	}

	protected function makeRequest(array $request): string {
		$payload = json_encode($request, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($payload === false) {
			return '';
		}

		$json = $this->sendApiRequest(self::API_BASE_URL . '/invoices/create', 'POST', $payload);
		if (!$json) {
			return '';
		}

		$id = is_string($json['data'] ?? null)
			? $json['data']
			: (string)($json['data']['invoice']['identifier'] ?? $json['invoice']['identifier'] ?? $json['identifier'] ?? '');

		return $this->validateInvoiceId($id);
	}

	protected function getInvoiceWithRetry(string $invoice_id): array {
		$delays = [0, 250000, 750000];

		foreach ($delays as $delay) {
			if ($delay > 0) {
				usleep($delay);
			}

			$invoice = $this->getInvoice($invoice_id);
			if ($invoice) {
				return $invoice;
			}
		}

		return [];
	}

	private function getInvoice(string $invoice_id): array {
		if ($this->validateInvoiceId($invoice_id) === '') {
			return [];
		}

		$json = $this->sendApiRequest(self::API_BASE_URL . '/invoices/' . rawurlencode($invoice_id), 'GET');

		return isset($json['data']) && is_array($json['data']) ? $json['data'] : [];
	}

	private function sendApiRequest(string $url, string $method, string $payload = ''): array {
		$curl = curl_init($url);
		curl_setopt_array($curl, [
			CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => false,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT => 30
		]);

		if ($method === 'POST') {
			curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
		}

		$response = curl_exec($curl);
		$status_code = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$transport_failed = $response === false;
		curl_close($curl);

		if ($transport_failed) {
			$this->log->write('Paydo API transport error');
			return [];
		}

		if ($status_code < 200 || $status_code >= 300) {
			$this->log->write('Paydo API HTTP error: ' . $status_code);
			return [];
		}

		$json = json_decode((string)$response, true);
		if (!is_array($json)) {
			$this->log->write('Paydo API returned invalid JSON');
			return [];
		}

		return $json;
	}

	private function validateRemoteInvoice(array $invoice, string $invoice_id, array $order_info): string {
		if (!hash_equals((string)($invoice['identifier'] ?? ''), $invoice_id)) {
			return 'invoice identifier mismatch';
		}

		if (!hash_equals((string)($invoice['orderIdentifier'] ?? ''), (string)$order_info['order_id'])) {
			return 'order identifier mismatch';
		}

		if (!isset($invoice['amount']) || !$this->sameAmount($invoice['amount'], $order_info['total'])) {
			return 'amount mismatch';
		}

		if (!isset($invoice['currency']) || strtoupper((string)$invoice['currency']) !== strtoupper((string)$order_info['currency_code'])) {
			return 'currency mismatch';
		}

		return '';
	}

	private function extractCallbackTransactionId(array $callback): string {
		foreach ([
			$callback['invoice']['txid'] ?? '',
			$callback['transaction']['txid'] ?? '',
			$callback['transaction']['id'] ?? '',
			$callback['transaction']['transactionIdentifier'] ?? ''
		] as $value) {
			if (is_scalar($value) && trim((string)$value) !== '') {
				return trim((string)$value);
			}
		}

		return '';
	}

	private function extractRemoteTransactionId(array $invoice): string {
		$candidates = [
			$invoice['txid'] ?? '',
			$invoice['transactionIdentifier'] ?? '',
			$invoice['transaction']['txid'] ?? '',
			$invoice['transaction']['transactionIdentifier'] ?? ''
		];

		if (!empty($invoice['transactions']) && is_array($invoice['transactions'])) {
			$transaction = end($invoice['transactions']);
			if (is_array($transaction)) {
				$candidates[] = $transaction['txid'] ?? '';
				$candidates[] = $transaction['transactionIdentifier'] ?? '';
				$candidates[] = $transaction['identifier'] ?? '';
			}
		}

		foreach ($candidates as $value) {
			if (is_scalar($value) && trim((string)$value) !== '') {
				return trim((string)$value);
			}
		}

		return '';
	}

	private function transactionIdentifiersMatch(string ...$identifiers): bool {
		$known = array_values(array_unique(array_filter($identifiers, static fn(string $value): bool => $value !== '')));

		return count($known) <= 1;
	}

	private function sameAmount($left, $right): bool {
		return number_format((float)$left, 2, '.', '') === number_format((float)$right, 2, '.', '');
	}

	private function isStoredInvoiceReusable(array $invoice, array $order_info, string $payment_mode): bool {
		if ((string)($invoice['payment_mode'] ?? '') !== $payment_mode) {
			return false;
		}

		if (!$this->sameAmount($invoice['amount'] ?? null, $order_info['total'])) {
			return false;
		}

		if (strtoupper((string)($invoice['currency'] ?? '')) !== strtoupper((string)$order_info['currency_code'])) {
			return false;
		}

		if (isset($invoice['invoice_status']) && (int)$invoice['invoice_status'] === self::PAID_INVOICE_STATUS) {
			return false;
		}

		return !isset($invoice['transaction_state']) ||
			!in_array((int)$invoice['transaction_state'], self::TERMINAL_ERROR_STATES, true);
	}

	private function updateOrderStatus(int $order_id, int $status_id, string $comment = ''): void {
		if ($status_id <= 0) {
			return;
		}

		$order_info = $this->model_checkout_order->getOrder($order_id);
		if (!$order_info || (int)($order_info['order_status_id'] ?? 0) === $status_id) {
			return;
		}

		$this->model_checkout_order->addHistory($order_id, $status_id, $comment);
	}

	private function setPendingOrderStatus(array $order_info, string $invoice_id): void {
		if ((int)($order_info['order_status_id'] ?? 0) > 0) {
			return;
		}

		$this->updateOrderStatus(
			(int)$order_info['order_id'],
			(int)$this->config->get('payment_paydo_order_status_wait'),
			'Paydo invoice created: ' . $invoice_id
		);
	}

	private function addManualReviewHistory(array $order_info, string $invoice_id): void {
		$status_id = (int)($order_info['order_status_id'] ?? 0);
		if ($status_id <= 0) {
			$status_id = (int)$this->config->get('payment_paydo_order_status_wait');
		}

		if ($status_id > 0) {
			$this->model_checkout_order->addHistory(
				(int)$order_info['order_id'],
				$status_id,
				'Paydo invoice verification requires manual review: ' . $invoice_id
			);
		}
	}

	private function isPaydoOrder(array $order_info): bool {
		$code = strtolower(trim($this->extractPaymentCode($order_info)));
		if ($code !== '') {
			return (bool)preg_match('/^paydo(?:[._-]|$)/', $code);
		}

		foreach ($this->extractPaymentMethodCandidates($order_info) as $candidate) {
			if ($candidate === 'paydo' || strpos($candidate, 'paydo ') === 0) {
				return true;
			}
		}

		return false;
	}

	private function extractPaymentMethodCandidates(array $order_info): array {
		$candidates = [];
		foreach (['payment_code', 'payment_method'] as $key) {
			$value = $order_info[$key] ?? '';
			if (is_scalar($value)) {
				$candidates[] = strtolower(trim((string)$value));
			} elseif (is_array($value)) {
				foreach (['code', 'name', 'title'] as $child_key) {
					if (isset($value[$child_key]) && is_scalar($value[$child_key])) {
						$candidates[] = strtolower(trim((string)$value[$child_key]));
					}
				}
			}
		}

		return array_values(array_filter(array_unique($candidates)));
	}

	private function getPaydoLocale(): string {
		$code = strtolower((string)$this->config->get('config_language'));

		return strpos($code, 'ru') === 0 ? 'ru' : 'en';
	}

	private function validateInvoiceId(string $invoice_id): string {
		$invoice_id = trim($invoice_id);

		return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $invoice_id)
			? $invoice_id
			: '';
	}

	private function respondWithRedirect(string $invoice_id): void {
		$url = self::CHECKOUT_BASE_URL . '/' . rawurlencode($this->getPaydoLocale()) .
			'/payment/invoice-preprocessing/' . rawurlencode($invoice_id);
		$this->response->setOutput(json_encode(['redirect' => $url]));
	}

	private function respondWithError(string $message): void {
		$this->response->setOutput(json_encode(['error' => $message]));
	}
}
