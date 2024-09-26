<?php
namespace Opencart\Catalog\Model\Extension\Shkeeper\Payment;

class Shkeeper extends \Opencart\System\Engine\Model
{
    public function getMethods(array $options = []): array
    {
        $this->load->language('extension/shkeeper/payment/shkeeper');

        $status = true;

		if ($this->cart->hasSubscription()) {
			$status = false;
		}

		$method_data = [];

		if ($status) {
			$option_data['shkeeper'] = [
				'code' => 'shkeeper.shkeeper',
				'name' => $this->language->get('heading_title')
			];

			$method_data = [
				'code'       => 'shkeeper',
				'name'       => $this->language->get('heading_title'),
				'option'     => $option_data,
				'sort_order' => $this->config->get('payment_shkeeper_transfer_sort_order')
			];
		}

		return $method_data;
    }

	public function getAvailableCurrencies()
	{
		$headers = [
            "X-Shkeeper-Api-Key: " . $this->config->get('payment_shkeeper_apiKey'),
        ];

        $base_url = $this->addTrailingSlash($this->config->get('payment_shkeeper_apiUrl'));

        $options = [
            CURLOPT_URL => "{$base_url}crypto",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
        ];

        $curl = curl_init();
        curl_setopt_array($curl, $options);
        $response = curl_exec($curl);
        curl_close($curl);

        return json_decode($response, true);
	}

	public function getInvoiceInfo(string $currency, array $data = [])
	{
		$headers = [
            "X-Shkeeper-Api-Key: " . $this->config->get('payment_shkeeper_apiKey'),
        ];

        $base_url = $this->addTrailingSlash($this->config->get('payment_shkeeper_apiUrl'));
        $url = $base_url  . $currency . "/payment_request";

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_POST => true,
        ];

        $curl = curl_init();
        curl_setopt_array($curl, $options);
        $response = curl_exec($curl);
        curl_close($curl);

        return json_decode($response, true);
	}

    private function addTrailingSlash($base_url) {
        if ( substr($base_url, -1) != DIRECTORY_SEPARATOR) {
            $base_url .= DIRECTORY_SEPARATOR;
        }
        return $base_url;
    }
}