<?php
/* Module Name: SynergyWholesale
 * Module URI: http://www.hostingbilling.net
 * Version: 1.0
 * Category: Domain Registrars
 * Description: Synergy Wholesale Registrar Integration.
 * Author: Hosting Billing
 * Author URI: www.hostingbilling.net
 */

class SynergyWholesale extends Hosting_Billing
{     
	private $api_key;
	private $reseller_id;

	public function __construct()
        {
			parent::__construct();  
		 
			$this->config = get_settings('synergywholesale');
			if(!empty($this->config))
			{
				$this->api_key = $this->config['apikey'];
				$this->reseller_id = $this->config['resellerid'];
			}			
		}

 

		public function synergywholesale_config ($values = null)
		{
			$config = array(								
				array(
					'label' => lang('api_key'), 
					'id' => 'apikey',
					'value' => isset($values) ? $values['apikey'] : '',
					'type' => 'password'
				),
				array(
					'label' => lang('reseller_id'), 
					'id' => 'resellerid',
					'value' => isset($values) ? $values['resellerid'] : ''
				) 
			); 
			
			return $config;        
		}
		 
		 
		

		function register_domain($id, $nameservers)
		{ 
			$order = Order::get_order($id);
			$client = Client::view_by_id($order->client_id); 
			$name = explode(' ', User::displayName($client->primary_contact));
			$first_name = $name[0];
			$last_name = (isset($name[1])) ? $name[1] : $name[0]; 

			$registrant = array(
				'registrant_firstname' => $first_name,
				'registrant_lastname' => $last_name,
				'registrant_organisation' => $client->company_name,
				'registrant_email' => $client->company_email,
				'registrant_address' => array('address1' => $client->company_address),
				'registrant_suburb' => $client->city,
				'registrant_state' => $client->state, 
				'registrant_postcode' => $client->zip, 
				'registrant_country' => App::country_code($client->country), 
				'registrant_phone' => str_replace(' ', '', $client->company_phone)
				); 

			$params = [
				'nameServers' => $nameservers,
				'years' => $order->years,
				'idProtect' => true,
				'specialConditionsAgree' => true,
				'domainName' => $order->domain
			];

			$request = array_merge($registrant, $this->admin_contacts(), $params);
			$eligibility = $this->domain_fields($id);
		
			if (!empty($eligibility)) {
				$request['eligibility'] = json_encode($eligibility);
			}
 		 
			
			try {
				$result = $this->apiRequest('domainRegister', $request);
				return (is_array($result) && isset($result['errorMessage'])) ? $result['errorMessage'] : 'OK';

			} catch (\Exception $e) {
				return $e->getMessage();
			 }
		}



				
		function webRequest($url, $method = 'GET', array $params = [])
		{
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_TIMEOUT, 5);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		
			if ('POST' === $method) {
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
			}
		
			$response = curl_exec($ch);
		
			if (0 !== curl_errno($ch)) {
				$info = curl_getinfo($ch);
				return 'Curl error: ' . curl_error($ch) ." ". $info[CURLINFO_RESPONSE_CODE];
			}
		
			curl_close($ch);
			return $response;
		}
		

		function getDomain(array $params)
		{
			return $params['sld'] . '.' . $params['tld'];
		}

	
	 
	 
		function apiRequest($command, array $request = [], $throw_on_error = true, $force_domain = true)
		{
			$auth = [
				'apiKey' => $this->api_key,
				'resellerID' => $this->reseller_id
			];		  
		
			$request = array_merge($request, $auth);		 
				
			$client = new \SoapClient(null, [
				'location' => 'https://api.synergywholesale.com?wsdl',
				'uri' => '',
				'trace' => true,
			]);
 		
			try {
				$response = $client->{$command}($request);
				//logModuleCall(SW_MODULE_NAME, $command, $request, $response, $response, $auth);
			} catch (SoapFault $e) {
				//logModuleCall(SW_MODULE_NAME, $command, $request, $e->getMessage(), $e->getMessage(), $auth);
		
				if ($throw_on_error) {
					// Convert SOAP Faults to Exceptions
					return $e->getMessage();
				}
				
				return $e->getMessage();
			}
		
		
			if (!preg_match('/^(OK|AVAILABLE).*?/', $response->status)) {
				if ($throw_on_error) {
					return $response->errorMessage;
				}
		 
			}
		
		   return get_object_vars($response);
		}
		
	 
		
		

		function admin_contacts()
		{
			$contact_types = array('technical', 'admin', 'billing');
			$contacts = array();
			
			foreach ($contact_types as $key)
			{
				$pre = (strpos(config_item('domain_admin_phone'), '+') === false) ? '+' : '';
				$contacts[$key . '_firstname'] = config_item('domain_admin_firstname');
				$contacts[$key . '_lastname'] = config_item('domain_admin_lastname');
				$contacts[$key . '_organisation'] = config_item('domain_admin_company');
				$contacts[$key . '_address'] = array('address1' => config_item('domain_admin_address_1'));
				$contacts[$key . '_suburb'] = config_item('domain_admin_address_2');
				$contacts[$key . '_state'] =  config_item('domain_admin_state');
				$contacts[$key . '_country'] = App::country_code(config_item('domain_admin_country'));				
				$contacts[$key . '_postcode'] =  config_item('domain_admin_zip');
				$contacts[$key . '_email'] = config_item('domain_admin_email');
				$contacts[$key . '_phone'] = $pre . App::dialing_code(config_item('domain_admin_country')) .'.'. str_replace(' ', '', config_item('domain_admin_phone'));				 
			}  

			return $contacts; 
		}

		

	 
		function ajaxResponse(array $data, $response_code = 200)
		{
			http_response_code($response_code);
			header('Content-Type: application/json');
			echo json_encode($data);
			exit;
		}

 	
  

		function update_nameservers($id)
		{
			$order = Order::get_order($id); 
			
			$nameservers = array();
		
			if($this->input->post('nameserver_1') != '') {
				$nameservers[] = $this->input->post('nameserver_1');
			}
			if($this->input->post('nameserver_2') != '') {
				$nameservers[] = $this->input->post('nameserver_2');
			}
			if($this->input->post('nameserver_3') != '') {
				$nameservers[] = $this->input->post('nameserver_3');
			}
			if($this->input->post('nameserver_4') != '') {
				$nameservers[] = $this->input->post('nameserver_4');
			}  

			$request = [
				'dnsConfigType' => 1,
				'nameServers' => $nameservers,
				'domainName' => $order->domain
			];
		  
			try {
				
				$result = $this->apiRequest('updateNameServers', $request);				 
				if(isset($result['status']) && $result['status'] == 'OK')
				{
					$nameservers = $this->input->post('nameserver_1', true).",".$this->input->post('nameserver_2', true);
					if($this->input->post('nameserver_3', true) != '') : $nameservers .= ",".$this->input->post('nameserver_3', true); endif;
					if($this->input->post('nameserver_4', true) != '') : $nameservers .= ",".$this->input->post('nameserver_4', true); endif;
					$this->db->set('nameservers', $nameservers); 
					$this->db->where('id', $id);  
					$this->db->update('orders');
				}				

				$this->session->set_flashdata('response_status', 'success');
				$this->session->set_flashdata('message', (is_array($result) && isset($result['errorMessage'])) ? $result['errorMessage'] : $result);			
				redirect($_SERVER['HTTP_REFERER']);				 

			} catch (\Exception $e) {
				$this->session->set_flashdata('response_status', 'error');
				$this->session->set_flashdata('message', $e->getMessage());			
				redirect($_SERVER['HTTP_REFERER']); 
			 }
		}
 
	



		function domain_end($domain) 
		{
			$domain_parts = explode('.', $domain);
			return $domain_parts[count($domain_parts) - 1];
		}
		
		 

		function domain_fields($id)
	   {
			$order = Order::get_order($id);
			$fields = array();
			$ext = $this->domain_end($order->domain);
			$fields = $this->db->where('domain', $order->additional_fields)->get('additional_fields')->result();
 
			$domain_fields = array(); 

			foreach($fields as $key => $field) {
				$domain_fields[$field->field_name] = $field->field_value;
			}

			$eligibility = array();

			if ($ext == 'au') {
				$eligibility['registrantName'] = $domain_fields['Registrant Name'];
				$eligibility['registrantID'] = $domain_fields['Registrant ID'];
		
				if ('Business Registration Number' === $domain_fields['Registrant ID Type']) {
					$domain_fields['Registrant ID Type'] = 'OTHER';
				}
		
				$eligibility['registrantIDType'] = $domain_fields['Registrant ID Type'];
				$eligibility['eligibilityType'] = $domain_fields['Eligibility Type'];
		
				$eligibility['registrantIDType)']= $domain_fields['Eligibility ID Type']; 
				$eligibility['eligibilityID'] = $domain_fields['Eligibility ID'];
				$eligibility['eligibilityName'] = $domain_fields['Eligibility Name'];
			}
		
			if ($ext == 'uk') {
				$eligibility['tradingName'] = $domain_fields['Registrant Name'];
				$eligibility['number'] = $domain_fields['Registrant ID'];
				$eligibility['type'] = $domain_fields['Registrant ID Type'];
				$eligibility['optout'] = $domain_fields['WHOIS Opt-out'];
			}
		
		
			if ($ext == 'us') {
				$eligibility['nexusCategory'] = $domain_fields['Nexus Category'];
				if (!empty($domain_fields['Nexus Country'])) {
					$eligibility['nexusCountry'] = $domain_fields['Nexus Country'];
				}
		
				switch ($domain_fields['Application Purpose']) {
					case 'Business use for profit':
						$eligibility['appPurpose'] = 'P1';
						break;
					case 'Non-profit business':
					case 'Club':
					case 'Association':
					case 'Religious Organization':
						$eligibility['appPurpose'] = 'P2';
						break;
					case 'Personal Use':
						$eligibility['appPurpose'] = 'P3';
						break;
					case 'Educational purposes':
						$eligibility['appPurpose'] = 'P4';
						break;
					case 'Government purposes':
						$eligibility['appPurpose'] = 'P5';
						break;
					default:
						$eligibility['appPurpose'] = '';
						break;
				}
			}
			 
			return $eligibility; 
		}





		function transfer_domain($id, $nameservers)
		{
			$order = Order::get_order($id);
			$client = Client::view_by_id($order->client_id); 
			$name = explode(' ', User::displayName($client->primary_contact));
			$first_name = $name[0];
			$last_name = (isset($name[1])) ? $name[1] : $name[0];

			$registrant = array(
				'registrant_firstname' => $first_name,
				'registrant_lastname' => $last_name,
				'registrant_organisation' => $client->company_name,
				'registrant_email' => $client->company_email,
				'registrant_address' => array('address1' => $client->company_address),
				'registrant_suburb' => $client->city,
				'registrant_state' => $client->state, 
				'registrant_postcode' => $client->zip, 
				'registrant_country' => App::country_code($client->country), 
				'registrant_phone' => str_replace(' ', '', $client->company_phone)
				); 

			$params = [
				'authInfo' => $order->authcode,
				'nameServers' => $nameservers,
				'doRenewal' => 1,
				'domainName' => $order->domain
			];
			

			$request = array_merge($registrant, $this->admin_contacts(), $params);
			$eligibility = $this->domain_fields($id);
		
			if (!empty($eligibility)) {
				$request['eligibility'] = json_encode($eligibility);
			} 
		 
			try {
				$result = $this->apiRequest('transferDomain', $request);
				return (is_array($result)) ? 'OK' : $result;

			} catch (\Exception $e) {
				return $e->getMessage();
			 }
		}
		
	 
 
		function renew_domain($id, $nameservers)
		{
			$order = Order::get_order($id);
			$request = [
				'years' => $order->years,
				'domainName' => $order->domain,
			];		
		 
			try {
				$result = $this->apiRequest('renewDomain', $request);
				return (is_array($result)) ? 'OK' : $result;

			} catch (\Exception $e) {
				return $e->getMessage();
			 }
		}



		function check_balance()
		{ 		 
			try {
				$result = $this->apiRequest('balanceQuery', array());
				return (is_array($result)) ? (isset($result['balance']) ? $result['balance'] : $result['status'])  : $result;

			} catch (\Exception $e) {
				return $e->getMessage();
			 }
		} 


		
		function check_domain($domain)
		{		 
			$request = ['domainName' => $domain];

			try {
				$result = $this->apiRequest('checkDomain', $request);
				return (is_array($result) && $result['status'] == 'AVAILABLE') ? 1 : 0;

			} catch (\Exception $e) {
				return $e->getMessage();
			 }
		}



		public function admin_options()
		{
			return '<a class="btn btn-primary btn-sm" href="'.base_url().'registrars/config/synergywholesale" data-toggle="ajaxModal" title="'.lang('settings').'"><i class="fa fa-pencil"></i> '.lang('settings').'</a>                               
	 				<a class="btn btn-success btn-sm" href="'.base_url().'registrars/check_balance/synergywholesale" data-toggle="ajaxModal" title="'.lang('check_balance').'" ><i class="fa fa-info-circle"></i> '.lang('check_balance').'</a>';
		}


		        
		public function activate($data)
		{ 
			return true;
		}



		public function install()
		{ 
			return true;
		}



		public function uninstall()
		{ 
			return true;
		} 		
		 
		
 } 