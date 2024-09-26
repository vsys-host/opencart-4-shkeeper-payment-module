<?php
namespace Opencart\Admin\Controller\Extension\Shkeeper\Payment;

class Shkeeper extends \Opencart\System\Engine\Controller
{
    public function index(): void
    {
        $this->load->language('extension/shkeeper/payment/shkeeper');
        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard','user_token=' . $this->session->data['user_token']),
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extensions'),
            'href' => $this->url->link('marketplace/opencart/extension', 'user_token=' . $this->session->data['user_token']),
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/shkeeper/payment/shkeeper', 'user_token=' . $this->session->data['user_token']),
        ];

        $data['save'] = $this->url->link('extension/shkeeper/payment/shkeeper' . $this->separator() . 'save', 'user_token=' . $this->session->data['user_token']);
        $data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');

		$this->load->model('localisation/language');

		$data['payment_bank_transfer_bank'] = [];

		$languages = $this->model_localisation_language->getLanguages();
		
		foreach ($languages as $language) {
			$data['payment_shkeeper_instructions'][$language['language_id']] = $this->config->get('payment_shkeeper_instructions_' . $language['language_id']);
		}

		$data['languages'] = $languages;

        $data['payment_shkeeper_apiKey'] = $this->config->get('payment_shkeeper_apiKey');
        $data['payment_shkeeper_apiUrl'] = $this->config->get('payment_shkeeper_apiUrl');
        
        $this->load->model('localisation/order_status');
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        $data['payment_shkeeper_order_status_id'] = $this->config->get('payment_shkeeper_order_status_id');

        $data['payment_shkeeper_geo_zone_id'] = $this->config->get('payment_shkeeper_geo_zone_id');
		$this->load->model('localisation/geo_zone');
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		$data['payment_shkeeper_sort_order'] = $this->config->get('payment_shkeeper_sort_order');
        $data['payment_shkeeper_status'] = $this->config->get('payment_shkeeper_status');

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/shkeeper/payment/shkeeper', $data));

    }

    public function save(): void
    {
        $this->load->language('extension/shkeeper/payment/shkeeper');

        $json = [];

        // check modifing permission
        if (!$this->user->hasPermission('modify', 'extension/shkeeper/payment/shkeeper')) {
			$json['error']['warning'] = $this->language->get('error_permission');
		}

        // check languages for instructions
        $this->load->model('localisation/language');

		$languages = $this->model_localisation_language->getLanguages();

		foreach ($languages as $language) {
			if (empty($this->request->post['payment_shkeeper_instructions_' . $language['language_id']])) {
				$json['error']['shkeeper_' . $language['language_id']] = $this->language->get('error_shkeeper');
			}
		}


		if (!$json) {
            $this->load->model('setting/setting');
            
            $this->model_setting_setting->editSetting('payment_shkeeper', $this->request->post);
            $json['success'] = $this->language->get('success_save');
        }

        $this->response->addHeader('Content-Type: application/json');
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
