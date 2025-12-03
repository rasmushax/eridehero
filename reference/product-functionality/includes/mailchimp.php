<?php
// mailchimp-api.php

class MailchimpAPI {
    private $api_key;
    private $api_endpoint = 'https://<dc>.api.mailchimp.com/3.0';

    public function __construct($api_key) {
        $this->api_key = $api_key;
        list(, $dc) = explode('-', $this->api_key);
        $this->api_endpoint = str_replace('<dc>', $dc, $this->api_endpoint);
    }

    public function makeRequest($method, $endpoint, $data = array()) {
		$url = $this->api_endpoint . $endpoint;

		error_log("Mailchimp API Request - Method: $method, Endpoint: $endpoint");
		if ($data) {
			error_log("Request Data: " . json_encode($data));
		}

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_USERPWD, 'user:' . $this->api_key);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

		if ($data) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		}

		$result = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		
		if (curl_errno($ch)) {
			error_log('Curl error: ' . curl_error($ch));
		}
		
		curl_close($ch);

		error_log("Mailchimp API Response - HTTP Code: $http_code");
		error_log("Response Body: $result");

		return $result ? json_decode($result) : false;
	}

    public function getListMember($list_id, $subscriber_hash) {
        return $this->makeRequest('GET', "/lists/$list_id/members/$subscriber_hash");
    }

    public function addOrUpdateListMember($list_id, $subscriber_hash, $data) {
        return $this->makeRequest('PUT', "/lists/$list_id/members/$subscriber_hash", $data);
    }
}