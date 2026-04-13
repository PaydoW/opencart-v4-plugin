<?php
namespace Opencart\Catalog\Controller\Extension\Paydo\Payment;

class Paydo extends \Opencart\System\Engine\Controller {
	private $curl;

	/**
	 * Display Paydo payment option in the checkout
	 */
	public function index() {
		$this->load->language('extension/paydo/payment/paydo');
		$widget_lang = $this->language->get('code') == 'ru' ? 'ru-RU' : 'en-US';

		return $this->load->view('extension/paydo/payment/paydo', [
			'button_pay' => $this->language->get('button_pay'),
			'paydo_url' => $this->url->link('extension/paydo/payment/paydo.pay')
		]);
	}

	/**
	 * Handle the payment request to Paydo
	 */
	public function pay(): void {
		$this->response->addHeader('Content-Type: application/json');

		try {
			$this->load->model('checkout/order');
			$this->load->model('extension/paydo/payment/paydo');

			if (empty($this->session->data['order_id'])) {
				$this->response->setOutput(json_encode(['error' => 'Missing order_id']));
				return;
			}

			$order_id = (int)$this->session->data['order_id'];
			$order_info = $this->model_checkout_order->getOrder($order_id);
			if (!$order_info) {
				$this->response->setOutput(json_encode(['error' => 'Order not found']));
				return;
			}

			$order_products = $this->model_checkout_order->getProducts($order_id);

			$paydo_order_items = array_map(function ($product) {
				return [
					'id'	=> (string)$product['order_product_id'],
					'name'  => trim($product['name'] . ' ' . $product['model']),
					'price' => (float)$product['price'],
				];
			}, $order_products);

			$request = $this->preparePaymentRequest($order_info, $paydo_order_items);
			$request['signature'] = $this->generate_signature($request['order']);

			$invoiceId = $this->makeRequest($request);

			if ($invoiceId) {
				$this->model_extension_paydo_payment_paydo->saveInvoice($order_id, $invoiceId);
				$this->setPendingOrderStatus($order_info, $invoiceId);
				$redirectUrl = 'https://checkout.paydo.com/' . rawurlencode($this->language->get('code')) . '/payment/invoice-preprocessing/' . rawurlencode($invoiceId);
				$this->response->setOutput(json_encode(['redirect' => $redirectUrl]));
			} else {
				$this->log->write('Paydo: invoice creation failed or empty identifier');
				$this->response->setOutput(json_encode(['error' => 'Invoice creation failed']));
			}
		} catch (\Throwable $e) {
			$this->log->write('Paydo pay() exception: ' . $e->getMessage());
			$this->response->setOutput(json_encode(['error' => 'Internal error']));
		}
	}

	/**
	 * Handle callback from Paydo after payment processing
	 */
	public function callback() {
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			$this->log->write('Invalid request method for Paydo callback.');
			return;
		}

		$callback = json_decode(file_get_contents('php://input'), true);

		if ($callback && isset($callback['invoice'])) {
			$callback_check = $this->callback_check($callback);

			if ($callback_check === 'valid') {
				$this->processCallback($callback);
			} else {
				$this->log->write('Error callback: ' . $callback_check);
			}
		} else {
			$this->log->write('Error. Callback is not an object or missing invoice.');
		}
	}

	/**
	 * Prepare payment request data
	 * @param $order_info
	 * @param $paydo_order_items
	 * @return array
	 */
	private function preparePaymentRequest($order_info, $paydo_order_items) {
		$amount = (float)$order_info['total'];
		$amount = number_format($amount, 2, '.', '');

		return [
			'publicKey' => $this->config->get('payment_paydo_public_id'),
			'order' => [
				'id' => $order_info['order_id'],
				'amount' => $amount,
				'currency' => $order_info['currency_code'],
				'description' => sprintf($this->language->get('order_description'), $order_info['order_id']),
				'items' => $paydo_order_items,
			],
			'payer' => [
				'email' => $order_info['email'],
				'phone' => $order_info['telephone'],
				'name' => $order_info['firstname'] . ' ' . $order_info['lastname']
			],
			'resultUrl' => $this->url->link('checkout/success'),
			'failPath' => $this->url->link('checkout/failure'),
			'language' => $this->language->get('code')
		];
	}

	/**
	 * Process callback and update order status
	 * @param $callback
	 */
	private function processCallback($callback) {
		$this->load->model('checkout/order');
		$order_id = (int)$callback['transaction']['order']['id'];
		$state = (int)$callback['transaction']['state'];

		if ($state === 2) {
			$this->model_checkout_order->addHistory($order_id, $this->config->get('payment_paydo_order_status_success'));
		} elseif (in_array($state, [3, 5], true)) {
			$this->model_checkout_order->addHistory($order_id, $this->config->get('payment_paydo_order_status_error'));
		}
	}

	/**
	 * Check the callback validity
	 * @param $callback
	 * @return string
	 */
	private function callback_check($callback) {
		$invoiceId = !empty($callback['invoice']['id']) ? $callback['invoice']['id'] : null;
		$txid = !empty($callback['invoice']['txid']) ? $callback['invoice']['txid'] : null;
		$orderId = !empty($callback['transaction']['order']['id']) ? $callback['transaction']['order']['id'] : null;
		$state = !empty($callback['transaction']['state']) ? $callback['transaction']['state'] : null;

		if (!$invoiceId) return 'Empty invoice id';
		if (!$txid) return 'Empty transaction id';
		if (!$orderId) return 'Empty order id';
		if (!(1 <= $state && $state <= 5)) return 'State is not valid';

		$this->load->model('checkout/order');
		$this->load->model('extension/paydo/payment/paydo');

		$order_info = $this->model_checkout_order->getOrder((int)$orderId);

		if (!$order_info) {
			return 'Order not found';
		}

		if (!$this->isPaydoOrder($order_info)) {
			$this->log->write(
				'Paydo callback order ownership mismatch. payment_code=' . $this->stringifyLogValue($order_info['payment_code'] ?? '') .
				'; payment_method=' . $this->stringifyLogValue($order_info['payment_method'] ?? '')
			);

			return 'Order does not belong to Paydo payment method';
		}

		$stored_invoice = $this->model_extension_paydo_payment_paydo->getInvoiceByOrderId((int)$orderId);

		if (!$stored_invoice) {
			return 'Stored Paydo invoice not found for order';
		}

		if (!hash_equals((string)$stored_invoice['invoice_id'], (string)$invoiceId)) {
			return 'Invoice identifier does not match stored value';
		}

		$remote_invoice = $this->getInvoice($invoiceId);

		if (!$remote_invoice) {
			return 'Unable to verify invoice with Paydo';
		}

		if ((string)($remote_invoice['identifier'] ?? '') !== (string)$invoiceId) {
			return 'Verified invoice identifier mismatch';
		}

		if ((string)($remote_invoice['orderIdentifier'] ?? '') !== (string)$orderId) {
			return 'Verified invoice order mismatch';
		}

		if (
			isset($remote_invoice['amount']) &&
			number_format((float)$remote_invoice['amount'], 2, '.', '') !== number_format((float)$order_info['total'], 2, '.', '')
		) {
			return 'Verified invoice amount mismatch';
		}

		if (
			isset($remote_invoice['currency']) &&
			strtoupper((string)$remote_invoice['currency']) !== strtoupper((string)$order_info['currency_code'])
		) {
			return 'Verified invoice currency mismatch';
		}

		if ((int)$state === 2 && (int)($remote_invoice['status'] ?? -1) !== 1) {
			return 'Invoice is not marked as paid by Paydo';
		}

		return 'valid';
	}

	private function setPendingOrderStatus(array $order_info, string $invoice_id): void {
		$this->load->model('checkout/order');

		$current_status_id = (int)($order_info['order_status_id'] ?? 0);
		$pending_status_id = (int)$this->config->get('payment_paydo_order_status_wait');

		if ($pending_status_id <= 0 || $current_status_id > 0) {
			return;
		}

		$comment = 'Paydo invoice created: ' . $invoice_id;

		$this->model_checkout_order->addHistory(
			(int)$order_info['order_id'],
			$pending_status_id,
			$comment
		);
	}

	private function isPaydoOrder(array $order_info): bool {
		foreach ($this->extractPaymentMethodCandidates($order_info) as $candidate) {
			if (strpos($candidate, 'paydo') !== false) {
				return true;
			}
		}

		return false;
	}

	private function extractPaymentMethodCandidates(array $order_info): array {
		$candidates = [];
		$payment_code = $order_info['payment_code'] ?? '';
		$payment_method = $order_info['payment_method'] ?? '';

		if (is_scalar($payment_code)) {
			$candidates[] = strtolower(trim((string)$payment_code));
		}

		if (is_scalar($payment_method)) {
			$candidates[] = strtolower(trim((string)$payment_method));
		} elseif (is_array($payment_method)) {
			foreach (['code', 'name', 'title'] as $key) {
				if (isset($payment_method[$key]) && is_scalar($payment_method[$key])) {
					$candidates[] = strtolower(trim((string)$payment_method[$key]));
				}
			}
		}

		return array_values(array_filter(array_unique($candidates)));
	}

	private function stringifyLogValue($value): string {
		if (is_array($value)) {
			$json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

			return $json !== false ? $json : '[array]';
		}

		if (is_bool($value)) {
			return $value ? 'true' : 'false';
		}

		if ($value === null) {
			return 'null';
		}

		return (string)$value;
	}

	/**
	 * Generate signature for API request
	 * @param $order
	 * @return string
	 */
	private function generate_signature($order) {
		$sign_str = [
			'amount'   => (string)$order['amount'],
			'currency' => (string)$order['currency'],
			'id'	   => (string)$order['id'],
		];
		ksort($sign_str, SORT_STRING);
		$sign_data = array_values($sign_str);
		$sign_data[] = (string)$this->config->get('payment_paydo_secret_key');
		return hash('sha256', implode(':', $sign_data));
	}

	/**
	 * Creates a Paydo invoice and returns its identifier
	 *
	 * @param array $request
	 * @return string
	 */
	private function makeRequest($request) {
		$payload = json_encode($request, JSON_UNESCAPED_UNICODE);

		if (!$this->curl) {
			$this->curl = curl_init();
			curl_setopt($this->curl, CURLOPT_URL, 'https://api.paydo.com/v1/invoices/create');
			curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, true);
			curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, 2);
			curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($this->curl, CURLOPT_HEADER, false);
		}

		curl_setopt($this->curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
		curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($this->curl, CURLOPT_POSTFIELDS, $payload);

		$response = curl_exec($this->curl);

		if ($response === false) {
			$this->log->write('Paydo cURL error: ' . curl_error($this->curl));
			curl_close($this->curl);
			$this->curl = null;
			return '';
		}

		$code = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
		curl_close($this->curl);
		$this->curl = null;

		if ($code < 200 || $code >= 300) {
			$this->log->write('Paydo invoice creation HTTP error: ' . $code . '; body: ' . $response);
			return '';
		}

		$json = json_decode($response, true);

		if (is_array($json) && isset($json['data']) && is_string($json['data']) && $json['data'] !== '') {
			return $this->validateInvoiceId($json['data']);
		}

		$id = $json['data']['invoice']['identifier']
			?? $json['invoice']['identifier']
			?? $json['identifier']
			?? '';

		if ($id !== '') {
			return $this->validateInvoiceId((string)$id);
		}

		return '';
	}

	private function getInvoice(string $invoice_id): array {
		$curl = curl_init();

		curl_setopt($curl, CURLOPT_URL, 'https://api.paydo.com/v1/invoices/' . rawurlencode($invoice_id));
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

		$response = curl_exec($curl);

		if ($response === false) {
			$this->log->write('Paydo get invoice cURL error: ' . curl_error($curl));
			curl_close($curl);
			return [];
		}

		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);

		if ($code < 200 || $code >= 300) {
			$this->log->write('Paydo get invoice HTTP error: ' . $code . '; body: ' . $response);
			return [];
		}

		$json = json_decode($response, true);

		if (!is_array($json) || !isset($json['data']) || !is_array($json['data'])) {
			$this->log->write('Paydo get invoice invalid response: ' . $response);
			return [];
		}

		return $json['data'];
	}

	private function validateInvoiceId(string $invoice_id): string {
		$invoice_id = trim($invoice_id);

		if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $invoice_id)) {
			$this->log->write('Paydo: invalid invoice ID format');

			return '';
		}

		return $invoice_id;
	}
}
