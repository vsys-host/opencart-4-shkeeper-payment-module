<?php
namespace Opencart\Catalog\Controller\Extension\Shkeeper\Payment;

class Shkeeper extends \Opencart\System\Engine\Controller 
{
    public function index(): string
    {
        $this->load->language('extension/shkeeper/payment/shkeeper');

		$data['shkeeper'] = nl2br($this->config->get('payment_shkeeper_instructions_' . $this->config->get('config_language_id')));

		$data['language'] = $this->config->get('config_language');
		$data['oc_separator'] = $this->separator();

		return $this->load->view('extension/shkeeper/payment/shkeeper', $data);
    }

    public function getCurrencies()
    {
        $json = [];

        $this->load->language('extension/shkeeper/payment/shkeeper');
        $this->load->model('extension/shkeeper/payment/shkeeper');

		$currencies = $this->model_extension_shkeeper_payment_shkeeper->getAvailableCurrencies();
        $json = $currencies;
        
        if (! isset($currencies['crypto_list'])) {
            $json['error'] = $this->language->get('error_payment_error');
        }

        if (! is_null($currencies)) {
            $json = $currencies['crypto_list'];
        }

        $this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
    }

    public function confirm(): void {
		$this->load->language('extension/shkeeper/payment/shkeeper');

		$json = [];

		if (!isset($this->session->data['order_id'])) {
			$json['error'] = $this->language->get('error_order');
		}

		if (!isset($this->session->data['payment_method']) || $this->session->data['payment_method']['code'] != 'shkeeper.shkeeper') {
			$json['error'] = $this->language->get('error_payment_method');
		}

		if (!$json) {

			$comment = '';

			if (!empty($this->session->data['shkeeper'])) {
                $comment .= 'Wallet: ' . $this->session->data['shkeeper']['wallet'] . PHP_EOL;
                $comment .= 'Amount: ' . $this->session->data['shkeeper']['amount'] . PHP_EOL;
                $comment .= 'Currency: ' . $this->session->data['shkeeper']['currency'] . PHP_EOL . PHP_EOL;
			}

			$comment  .= $this->language->get('text_instruction') . "\n\n";
			$comment .= $this->config->get('payment_shkeeper_instructions_' . $this->config->get('config_language_id')) . "\n\n";

			$this->load->model('checkout/order');

			$orderInfo = $this->model_checkout_order->getOrder($this->session->data['order_id']);


			$this->model_checkout_order->addHistory($this->session->data['order_id'], !$orderInfo['order_status_id'] ? $this->config->get('config_order_status_id') : $orderInfo['order_status_id'], $comment, true);

			$json['redirect'] = $this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function getInvoice()
	{
		$this->load->model('checkout/order');
		$this->load->model('extension/shkeeper/payment/shkeeper');
        $this->load->language('extension/shkeeper/payment/shkeeper');

		$cryptoCurrency = $this->request->post['currency'];
		$order_id = $this->session->data['order_id'];

		$order_info = $this->model_checkout_order->getOrder($order_id);

		$data = [
			"external_id"   => $order_id,
			"fiat"          => $this->session->data['currency'],
			"amount"        => $order_info['total'],
			"callback_url"  => HTTP_SERVER . "index.php?route=extension/shkeeper/payment/shkeeper" . $this->separator() . "callback",
		];

		$json = $this->model_extension_shkeeper_payment_shkeeper->getInvoiceInfo($cryptoCurrency, $data);

        if (! isset($currencies['crypto_list'])) {
            $json['error'] = $this->language->get('error_payment_error');
        }

		if ('success' == $json['status']) {
            // save payment info
            $this->session->data['shkeeper'] = [
                "wallet" => $json['wallet'],
                "amount" => $json['amount'],
                "currency" => $cryptoCurrency,
            ];
        }

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function callback()
    {
        $data = file_get_contents('php://input');
        $data_collected = json_decode($data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Handle the error
            $error_message = json_last_error_msg();
            // Return a JSON response with the error message
            $this->log->write($error_message);
            exit;
        }

        $headers = getallheaders();

        // stop request in case NOT AUTH.
        if (! isset($headers['X-Shkeeper-Api-Key']) || $headers['X-Shkeeper-Api-Key'] != $this->config->get('payment_shkeeper_apiKey')) {

			$this->log->write('[SHKeeper] ApiKey mismatch. Recieved headers: ' . print_r($headers, 1));
            $this->response->addHeader('Content-Type: application/json');
            $this->response->addHeader('HTTP/1.1 401 Unauthorized');
            $this->response->setOutput(json_encode(['Unauthorized Request...']));
            return;
        }

        // collect data form request
        $order_id = $data_collected['external_id'];
        $transaction_info = "";

        // collect new transactions and save data on order update
        foreach ($data_collected['transactions'] as $transaction) {
            if ($transaction['trigger']) {

                $transaction_id = $transaction['txid'];
                $amount = $transaction['amount_crypto'] . ' ' . $transaction['crypto'];
                $date = $transaction['date'];

                $transaction_info .= "Transaction: # $transaction_id - Amount: $amount - Date: $date" . PHP_EOL;
            }
        }

        // handle duplicated callback requests
        if (empty($transaction_info)) {
			$this->log->write("[SHKeeper] Can't get transaction_info. Raw request: " . print_r($data, 1));
            $this->response->addHeader('Content-Type: application/json');
            $this->response->addHeader('HTTP/1.1 402 Payment Required');
            $this->response->setOutput(json_encode([]));
            return;
        }

        // update order status in case of full paid
		$order_status = $data_collected['paid'] ? $this->config->get('payment_shkeeper_order_status_id') : $this->config->get('config_order_status_id');
        $this->load->model('checkout/order');		
		$this->model_checkout_order->addHistory($order_id, $order_status, $transaction_info, true);
        

        // shows confirmation
        $json = [
            "success" => true,
            "message" => "order status confirmed.",
        ];

		$this->response->addHeader('Content-Type: application/json');
		$this->response->addHeader('HTTP/1.1 202 Accepted');
		$this->response->setOutput(json_encode($json));
    }

	private function separator():string
    {
        if (VERSION >= '4.0.2.0') {
            return '.';
        }

        return '|';
    }
}