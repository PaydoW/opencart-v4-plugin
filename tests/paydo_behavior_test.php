<?php
declare(strict_types=1);

namespace Opencart\System\Engine {
	#[\AllowDynamicProperties]
	class Controller {}

	#[\AllowDynamicProperties]
	class Model {}
}

namespace {
	final class ConfigStub {
		public array $values;

		public function __construct(array $values) {
			$this->values = $values;
		}

		public function get(string $key) {
			return $this->values[$key] ?? null;
		}
	}

	final class LanguageStub {
		public function get(string $key): string {
			$values = [
				'heading_title' => 'PayDo',
				'text_hosted_page' => 'PayDo Hosted Page',
				'order_description' => 'Payment for Order #%s'
			];

			return $values[$key] ?? $key;
		}
	}

	final class UrlStub {
		public function link(string $route): string {
			return 'https://shop.example/index.php?route=' . $route;
		}
	}

	final class LoadStub {
		public function language(string $route): void {}
		public function model(string $route): void {}
	}

	final class CartStub {
		public function hasSubscription(): bool {
			return false;
		}
	}

	final class PaymentModelStub {
		public function isHostedPageEnabled(): bool {
			return true;
		}

		public function getEnabledPaymentMethods(): array {
			return ['204' => ['identifier' => '204', 'title' => 'Internet acquiring', 'logo' => '']];
		}
	}

	function assertSameValue($expected, $actual, string $message): void {
		if ($expected !== $actual) {
			throw new \RuntimeException($message . '; expected=' . var_export($expected, true) . '; actual=' . var_export($actual, true));
		}
	}

	require dirname(__DIR__) . '/catalog/controller/payment/paydo.php';
	require dirname(__DIR__) . '/catalog/model/payment/paydo.php';
	require dirname(__DIR__) . '/admin/controller/payment/paydo.php';

	final class CallbackController extends \Opencart\Catalog\Controller\Extension\Paydo\Payment\Paydo {
		public array $remote = [];
		protected function getInvoiceWithRetry(string $invoice_id): array {
			return $this->remote;
		}
	}

	final class CheckoutController extends \Opencart\Catalog\Controller\Extension\Paydo\Payment\Paydo {
		public array $payloads = [];
		protected function makeRequest(array $request): string {
			$this->payloads[] = $request;
			return '11111111-1111-1111-1111-111111111111';
		}
	}

	final class ResponseStub {
		public array $output = [];
		public array $headers = [];
		public function addHeader(string $header): void { $this->headers[] = $header; }
		public function setOutput(string $output): void { $this->output = json_decode($output, true); }
	}

	final class LogStub {
		public function write(string $message): void {}
	}

	final class InvoiceModelStub {
		public array $invoice = [];
		public int $failures = 0;
		public int $releases = 0;
		public function getInvoiceByInvoiceId(string $id): array { return $this->invoice; }
		public function getInvoiceByOrderId(int $id): array { return $this->invoice; }
		public function acquireOrderLock(int $id): bool { return true; }
		public function releaseOrderLock(int $id): void { $this->releases++; }
		public function recordVerificationFailure(int $id, string $invoice): int { return ++$this->failures; }
		public function isHostedPageEnabled(): bool { return true; }
		public function getEnabledPaymentMethods(): array { return [204 => ['title' => 'Cards']]; }
		public function saveInvoice(int $id, string $invoice, string $mode, string $amount, string $currency): void {
			$this->invoice = ['order_id' => $id, 'invoice_id' => $invoice, 'payment_mode' => $mode, 'amount' => $amount, 'currency' => $currency];
		}
		public function updateInvoiceVerification(int $id, string $invoice, string $txid, ?int $status, ?int $state): void {
			$this->invoice = array_replace($this->invoice, ['txid' => $txid, 'invoice_status' => $status, 'transaction_state' => $state]);
		}
	}

	final class OrderModelStub {
		public array $order;
		public array $history = [];
		public function getOrder(int $id): array { return $this->order; }
		public function getProducts(int $id): array { return []; }
		public function addHistory(int $id, int $status, string $comment = ''): void {
			$this->history[] = $status;
			$this->order['order_status_id'] = $status;
		}
	}

	$controller = new \Opencart\Catalog\Controller\Extension\Paydo\Payment\Paydo();
	$controller->config = new ConfigStub([
		'payment_paydo_secret_key' => 'secret',
		'payment_paydo_public_id' => 'public',
		'payment_paydo_methods_mode' => 1,
		'config_language' => 'en-gb'
	]);
	$controller->language = new LanguageStub();
	$controller->url = new UrlStub();
	$controller->load = new LoadStub();
	$controller->model_extension_paydo_payment_paydo = new PaymentModelStub();

	$order = [
		'order_id' => 123,
		'total' => 1,
		'currency_code' => 'EUR',
		'email' => 'payer@example.test',
		'telephone' => '',
		'firstname' => 'Test',
		'lastname' => 'Payer'
	];

	$prepare = new \ReflectionMethod($controller, 'preparePaymentRequest');
	$hosted_payload = $prepare->invoke($controller, $order, [], 'hosted');
	assertSameValue(false, array_key_exists('paymentMethod', $hosted_payload), 'Hosted Page must omit paymentMethod');
	assertSameValue('1.00', $hosted_payload['order']['amount'], 'Amount must use two decimals');

	$direct_payload = $prepare->invoke($controller, $order, [], '204');
	assertSameValue(204, $direct_payload['paymentMethod'] ?? null, 'Direct method must be an integer');

	$signature = new \ReflectionMethod($controller, 'generateSignature');
	assertSameValue(
		hash('sha256', '1.00:EUR:123:secret'),
		$signature->invoke($controller, $direct_payload['order']),
		'Signature must use the exact payload amount'
	);

	$get_mode = new \ReflectionMethod($controller, 'getSelectedPaymentMode');
	assertSameValue('hosted', $get_mode->invoke($controller, ['payment_method' => ['code' => 'paydo.hosted']]), 'Hosted option validation failed');
	assertSameValue('204', $get_mode->invoke($controller, ['payment_method' => ['code' => 'paydo.method_204']]), 'Allowed direct option validation failed');
	assertSameValue(false, $get_mode->invoke($controller, ['payment_method' => ['code' => 'paydo.method_999']]), 'Spoofed method must be rejected');

	$validate_remote = new \ReflectionMethod($controller, 'validateRemoteInvoice');
	$remote_invoice = [
		'identifier' => '11111111-1111-1111-1111-111111111111',
		'orderIdentifier' => '123',
		'amount' => '1.00',
		'currency' => 'EUR',
		'status' => 1
	];
	assertSameValue('', $validate_remote->invoke($controller, $remote_invoice, $remote_invoice['identifier'], $order), 'Matching remote invoice must validate');
	$remote_invoice['amount'] = '2.00';
	assertSameValue('amount mismatch', $validate_remote->invoke($controller, $remote_invoice, $remote_invoice['identifier'], $order), 'Amount spoofing must be rejected');

	$match_txid = new \ReflectionMethod($controller, 'transactionIdentifiersMatch');
	assertSameValue(true, $match_txid->invoke($controller, '', 'tx-1', 'tx-1'), 'Matching transaction identifiers must pass');
	assertSameValue(false, $match_txid->invoke($controller, '', 'tx-1', 'tx-2'), 'Mismatched transaction identifiers must fail');

	$reusable = new \ReflectionMethod($controller, 'isStoredInvoiceReusable');
	$stored_invoice = ['payment_mode' => '204', 'amount' => '1.0000', 'currency' => 'EUR', 'invoice_status' => null, 'transaction_state' => null];
	assertSameValue(true, $reusable->invoke($controller, $stored_invoice, $order, '204'), 'Matching pending invoice must be reusable');
	$stored_invoice['amount'] = '2.0000';
	assertSameValue(false, $reusable->invoke($controller, $stored_invoice, $order, '204'), 'Invoice with changed amount must not be reused');

	$is_paydo_order = new \ReflectionMethod($controller, 'isPaydoOrder');
	assertSameValue(true, $is_paydo_order->invoke($controller, ['payment_method' => ['code' => 'paydo.method_204']]), 'PayDo order ownership validation failed');
	assertSameValue(false, $is_paydo_order->invoke($controller, ['payment_method' => ['code' => 'notpaydo.method']]), 'Another payment module must not pass ownership validation');

	$model = new \Opencart\Catalog\Model\Extension\Paydo\Payment\Paydo();
	$model->load = new LoadStub();
	$model->language = new LanguageStub();
	$model->cart = new CartStub();
	$model->config = new ConfigStub([
		'config_checkout_payment_address' => false,
		'payment_paydo_methods_mode' => 1,
		'payment_paydo_hosted_page_enabled' => 1,
		'payment_paydo_available_methods' => [
			'204' => ['identifier' => '204', 'title' => 'Internet acquiring', 'logo' => 'https://cdn.example/card.svg'],
			'999' => ['identifier' => '999', 'title' => 'Disabled', 'logo' => '']
		],
		'payment_paydo_enabled_methods' => ['204'],
		'payment_paydo_sort_order' => 1
	]);
	$options = $model->getMethods()['option'] ?? [];
	assertSameValue(['hosted', 'method_204'], array_keys($options), 'Checkout options must contain Hosted Page and enabled methods only');
	assertSameValue('https://cdn.example/card.svg', $options['method_204']['image'] ?? '', 'Safe logo must be available to checkout themes');

	$admin = new \Opencart\Admin\Controller\Extension\Paydo\Payment\Paydo();
	$parse = new \ReflectionMethod($admin, 'parseAvailableMethods');
	$parsed = $parse->invoke($admin, [
		['paymentMethod' => ['isEnabled' => true, 'identifier' => 204, 'title' => 'Internet acquiring', 'logo' => 'http://cdn.example/card.svg']],
		['paymentMethod' => ['isEnabled' => false, 'identifier' => 999, 'title' => 'Disabled']]
	]);
	assertSameValue([204], array_keys($parsed), 'Synchronization must keep enabled positive numeric IDs only');
	$parsed = $parse->invoke($admin, [
		['identifier' => 204, 'title' => 'Cards', 'isEnabled' => true],
		['identifier' => 999, 'title' => 'Disabled', 'isEnabled' => false],
		'bad row'
	]);
	assertSameValue([204], array_keys($parsed), 'Flat API response must also exclude disabled methods');

	$ipn = new CallbackController();
	$ipn->config = new ConfigStub(['payment_paydo_order_status_success' => 5, 'payment_paydo_order_status_error' => 10]);
	$ipn->load = new LoadStub();
	$ipn->log = new LogStub();
	$ipn->response = new ResponseStub();
	$ipn->model_extension_paydo_payment_paydo = new InvoiceModelStub();
	$ipn->model_checkout_order = new OrderModelStub();
	$ipn->model_checkout_order->order = $order + ['payment_method' => ['code' => 'paydo.method_204'], 'order_status_id' => 1];
	$base_invoice = ['order_id' => 123, 'invoice_id' => '11111111-1111-1111-1111-111111111111', 'txid' => '', 'invoice_status' => null];
	$ipn->model_extension_paydo_payment_paydo->invoice = $base_invoice;
	$ipn->remote = [
		'identifier' => $base_invoice['invoice_id'], 'orderIdentifier' => '123',
		'amount' => '1.00', 'currency' => 'EUR', 'status' => 0, 'transactionIdentifier' => 'tx-card'
	];
	$process = new \ReflectionMethod($ipn, 'processCallback');
	$card_callback = ['invoice' => ['id' => $base_invoice['invoice_id']], 'transaction' => ['id' => 'tx-card', 'order' => ['id' => '123'], 'state' => 3]];
	$process->invoke($ipn, $card_callback);
	assertSameValue(['status' => 'pending'], $ipn->response->output, 'Unverified callback failure must leave invoice pending');
	assertSameValue([], $ipn->model_checkout_order->history, 'Callback alone must not fail an order');

	$ipn->remote['status'] = 1;
	$card_callback['transaction']['state'] = 4;
	$process->invoke($ipn, $card_callback);
	assertSameValue(['status' => 'paid'], $ipn->response->output, 'Paid invoice must confirm card payment even with pending IPN state');
	assertSameValue([5], $ipn->model_checkout_order->history, 'Verified payment must update order once');
	$ipn->remote['status'] = 3;
	$card_callback['transaction']['state'] = 3;
	$process->invoke($ipn, $card_callback);
	assertSameValue(['status' => 'paid'], $ipn->response->output, 'Late failure must not undo paid order');
	assertSameValue([5], $ipn->model_checkout_order->history, 'Duplicate callback must not append history');

	$ipn->model_extension_paydo_payment_paydo->invoice = $base_invoice;
	$ipn->model_checkout_order->history = [];
	$process->invoke($ipn, $card_callback);
	assertSameValue(['status' => 'failed'], $ipn->response->output, 'Verified invoice failure must fail an unpaid order');
	assertSameValue([10], $ipn->model_checkout_order->history, 'Verified failure must update order');

	$ipn->model_extension_paydo_payment_paydo->invoice = $base_invoice;
	$ipn->remote['transactionIdentifier'] = 'different-tx';
	$process->invoke($ipn, $card_callback);
	assertSameValue(['status' => 'rejected'], $ipn->response->output, 'Card transaction.id mismatch must be rejected');
	$ipn->remote = [];
	$process->invoke($ipn, $card_callback);
	assertSameValue(['status' => 'pending'], $ipn->response->output, 'Temporary API failure must leave payment pending');
	assertSameValue(1, $ipn->model_extension_paydo_payment_paydo->failures, 'Verification failure must be recorded');
	assertSameValue(6, $ipn->model_extension_paydo_payment_paydo->releases, 'Every callback must release order lock');
	assertSameValue(true, in_array('HTTP/1.1 503 Service Unavailable', $ipn->response->headers, true), 'Pending verification must request IPN redelivery');
	$ipn->remote = [
		'identifier' => $base_invoice['invoice_id'], 'orderIdentifier' => '123',
		'amount' => '1.00', 'currency' => 'EUR', 'status' => 1, 'transactionIdentifier' => 'tx-normal'
	];
	$ipn->model_checkout_order->order['payment_method'] = ['code' => 'paydo.paydo'];
	$process->invoke($ipn, ['invoice' => ['id' => $base_invoice['invoice_id'], 'txid' => 'tx-normal'], 'transaction' => ['order' => ['id' => '123'], 'state' => 2]]);
	assertSameValue(['status' => 'paid'], $ipn->response->output, 'Standard Hosted Page callback must still confirm payment');

	$checkout = new CheckoutController();
	$checkout->config = $controller->config;
	$checkout->language = new LanguageStub();
	$checkout->url = new UrlStub();
	$checkout->load = new LoadStub();
	$checkout->log = new LogStub();
	$checkout->response = new ResponseStub();
	$checkout->session = (object)['data' => ['order_id' => 123]];
	$checkout->model_extension_paydo_payment_paydo = new InvoiceModelStub();
	$checkout->model_checkout_order = new OrderModelStub();
	$checkout->model_checkout_order->order = $order + ['payment_method' => ['code' => 'paydo.method_204'], 'order_status_id' => 1];
	$checkout->pay();
	assertSameValue(204, $checkout->payloads[0]['paymentMethod'], 'Checkout must send the selected card method to API');
	assertSameValue(true, strpos($checkout->response->output['redirect'] ?? '', 'https://checkout.paydo.com/en/payment/invoice-preprocessing/') === 0, 'Checkout must redirect to the created invoice');
	$checkout->pay();
	assertSameValue(1, count($checkout->payloads), 'Double click must reuse invoice without creating another payment');
	$checkout->model_extension_paydo_payment_paydo->invoice = [];
	$checkout->model_checkout_order->order['payment_method']['code'] = 'paydo.hosted';
	$checkout->pay();
	assertSameValue(false, isset($checkout->payloads[1]['paymentMethod']), 'Hosted checkout must omit method selection');
	$checkout->model_extension_paydo_payment_paydo->invoice = [];
	$checkout->model_checkout_order->order['payment_method']['code'] = 'paydo.method_999';
	$checkout->pay();
	assertSameValue(2, count($checkout->payloads), 'Disabled method must never create a payment');

	echo "PayDo behavior tests passed\n";
}
