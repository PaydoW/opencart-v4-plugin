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

			// статус "ожидания"
			if ($this->config->get('payment_paydo_order_status_wait')) {
				$this->model_checkout_order->addHistory(
					$order_id,
					(int)$this->config->get('payment_paydo_order_status_wait')
				);
			}

			$invoiceId = $this->makeRequest($request);

			if ($invoiceId) {
				$redirectUrl = "https://checkout.paydo.com/{$this->language->get('code')}/payment/invoice-preprocessing/{$invoiceId}";
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
		$this->log->write('Callback: ' . print_r($callback, true));
		if ($callback && isset($callback['invoice'])) {
			if ($this->callback_check($callback) === 'valid') {
				$this->processCallback($callback);
			} else {
				$this->log->write('Error callback: ' . $this->callback_check($callback));
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
		if ($callback['transaction']['state'] === 2) {
			$this->model_checkout_order->addHistory($callback['transaction']['order']['id'], $this->config->get('payment_paydo_order_status_success'));
		} elseif (in_array($callback['transaction']['state'], [3, 5])) {
			$this->model_checkout_order->addHistory($callback['transaction']['order']['id'], $this->config->get('payment_paydo_order_status_error'));
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
		
		return 'valid';
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
			curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
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

		$json = json_decode($response, true);

		if (is_array($json) && isset($json['data']) && is_string($json['data']) && $json['data'] !== '') {
			return $json['data'];
		}

		$id = $json['data']['invoice']['identifier']
			?? $json['invoice']['identifier']
			?? $json['identifier']
			?? '';

		if ($id !== '') {
			return (string)$id;
		}

		return '';
	}
}
