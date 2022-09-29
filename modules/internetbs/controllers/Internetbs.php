<?php
/* Module Name: Internetbs
 * Module URI: http://www.hostingbilling.net
 * Version: 1.0
 * Category: Domain Registrars
 * Description: Internt.bs Registrar Integration
 * Author: Hosting Billing
 * Author URI: www.hostingbilling.net
 */

class Internetbs extends Hosting_Billing
{ 	
	private static $api = null; 
	private $apiKey;
	private $password;

	const errorType_api      = 1; 
	const errorType_network  = 2;  
	const errorType_internal = 3;  

	private $lastErrorType       = null; 
	private $lastErrorCode       = null; 
	private $lastErrorMessage    = null; 


	public function __construct( $agentName = null) {	
		
		$this->config = get_settings('internetbs');
		if(!empty($this->config))
		{
			$this->apiKey = $this->config['apikey'];
			$this->password  = $this->config['pass'];
			$this->host = $this->_isSame($this->config['mode'], 'test') ? '77.247.183.107' : 'api.internet.bs';
		}
				
		$this->agentName =  'Internetbs v.1.2';
	}


	public function internetbs_config ($values = null)
		{
			$config = array(
				array(
					'label' => lang('mode'),
					'id' => 'mode',
					'type' => 'dropdown',
					'options' => array(
							'live' => lang('live'),
							'test' => lang('test')
					),
					'value' => isset($values['mode']) ? $values['mode'] : 'live'
				),
				
				array(
					'label' => lang('api_key'), 
					'id' => 'apikey',
					'value' => isset($values) ? $values['apikey'] : ''
				),
				
				array(
					'label' => lang('password'), 
					'id' => 'pass',
					'value' => isset($values) ? $values['pass'] : ''
				) 
			); 
			
			return $config;        
		}
	 

	
	
	public function check_domain($domainName)   {
		$result = $this->domainCheckInDetails($domainName); 
		if(isset($result['status']) && $result['status'] == 'AVAILABLE')
		{
			return 1;
		}

		if(isset($result['status']) && $result['status'] == 'UNAVAILABLE')
		{
			return 0;
		} 
	}


	
	
	public function register_domain($id, $nameservers)    
	{
		$order = Order::get_order($id);
		$client = Client::view_by_id($order->client_id); 
		$name = explode(' ', User::displayName($client->primary_contact));
		$first_name = $name[0];
		$last_name = (isset($name[1])) ? $name[1] : $name[0];	
		$pre = (strpos($client->company_phone, '+') === false) ? '+' : ''; 
		$registrant = array(
			'registrant_firstname' => $first_name,
			'registrant_lastname' => $last_name,
			'registrant_organization' => $client->company_name,
			'registrant_email' => $client->company_email,
			'registrant_street' => $client->company_address,
			'registrant_city' => $client->city, 
			'registrant_postalcode' => $client->zip,
			'registrant_country' => $client->country,
			'registrant_countrycode' => App::country_code($client->country), 
			'registrant_phonenumber' => $pre . App::dialing_code($client->country) .'.'.  str_replace(' ', '', $client->company_phone)
			); 

		$params = array(
			'Domain' => $order->domain,
			'Period' => intval($order->years).'Y',
			'privatewhois' => 'partial',
		);
		
		$params = array_merge($registrant, $this->admin_contacts(), $params);		
		$result = $this->execute('Domain/Create', $params);

		if(isset($result['product'][0]))	
		{
			return (isset($result['product'][0]['expiration'])) ? strtotime($result['product'][0]['expiration']) : $result['product'][0]['message'];
		}
				
		else {
			return $this->lastErrorMessage;
		}
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
			$contacts[$key . '_organization'] = config_item('domain_admin_company');
			$contacts[$key . '_city'] = config_item('domain_admin_city');
			$contacts[$key . '_street'] = config_item('domain_admin_address_1')." ".config_item('domain_admin_address_2');
			$contacts[$key . '_postalcode'] =  config_item('domain_admin_zip');
			$contacts[$key . '_countrycode'] = App::country_code(config_item('domain_admin_country'));
			$contacts[$key . '_country'] = config_item('domain_admin_country');
			$contacts[$key . '_email'] = config_item('domain_admin_email');
			$contacts[$key . '_phonenumber'] = App::dialing_code(config_item('domain_admin_country')) .'.'. str_replace(' ', '', config_item('domain_admin_phone'));				 
		}  

		return $contacts; 
	}




				
	public function _isFailed()   {
		return !$this->_isSuccess();
	}

	
	public function _isSuccess()   {
		return null === $this->_lastErrorType();
	}

	
	public function _lastErrorType(){
		return $this->lastErrorType;
	}

	
	public function _lastErrorCode(){
		return $this->lastErrorCode;
	}

	public function _lastErrorMessage(){
		return $this->lastErrorMessage;
	}
	
	public function _setExceptionClassNameForErrorType($errorType, $className)   {
		$this->exceptionClassNames[$errorType] = $className;
	}

	
	public function _getExceptionClassNameForErrorType($errorType)   {
		return isset($this->exceptionClassNames[$errorType]) ? $this->exceptionClassNames[$errorType] : 'Exception';
	}


	protected function _error($type, $message, $code = 0)   {

		$this->lastErrorType    = $type;
		$this->lastErrorCode    = $code;
		$this->lastErrorMessage = $message;
	
		if(self::errorType_internal == $type)    {

			$exceptionClassName = $this->_getExceptionClassNameForErrorType($type);
			throw new $exceptionClassName($message, $code);
		}
	}

	
	protected function _releaseLastError()   {
		$this->lastErrorType    = null;
		$this->lastErrorCode    = null;
		$this->lastErrorMessage = null;
	}

	protected function _microtime()  {
		return function_exists('microtime') ? microtime(true) : time();
	}

	protected function _isSame($value1, $value2)    {
		return trim(strtolower($value1)) == trim(strtolower($value2));
	}		

	
	public function domainCheckInDetails($domainName)   {
		return $this->execute('Domain/Check', array('Domain' => $domainName));
	}


	
	public final function _executeApiCommand($commandName, array $params)  { 

		$commandName = trim($commandName, ' /');  
		$this->_releaseLastError();
	
		$params['ApiKey']         = $this->apiKey;
		$params['Password']       = $this->password;

		$params['responseformat'] = 'JSON'; 
		$apiRequestUrl = 'https://'.$this->host.'/'.$commandName;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,            $apiRequestUrl);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_HEADER,         0);
		curl_setopt($ch, CURLOPT_USERAGENT,      $this->agentName);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST,           1);
		curl_setopt($ch, CURLOPT_POSTFIELDS,     $params);

		$startTime = $this->_microtime(); 
		$data = curl_exec($ch);

		$endTime = $this->_microtime();  
	
		if($errorMsg = curl_error($ch))    {
			$this->_error(self::errorType_network, $errorMsg, curl_errno($ch));
		} else { 
			$data = json_decode($data, true);
				if(isset($data['status']) && $this->_isSame($data['status'],'FAILURE'))    {
					if(isset($data['message']))
					{
					$this->lastErrorMessage = $data['message'];
					}
					
			}

		}

		curl_close($ch);
		unset($ch);

		// Write command execution result in log if need
		//$this->_writeLog($apiRequestUrl, $commandName, $params, $data, $startTime, $endTime);

		unset($apiRequestUrl);
		unset($commandName);
		unset($params);
		unset($startTime);
		unset($endTime);

		return $data;
	}

	


	
	public function update_nameservers($id)   {
	
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

		$order = Order::get_order($id);
		$response = $this->domainUpdate($order->domain, array(), array('Ns_list' => implode(',', $nameservers)));

	
		if(isset($response['status']) && $this->_isSame($response['status'],'SUCCESS'))    
		{
			$nameservers = $this->input->post('nameserver_1', true).",".$this->input->post('nameserver_2', true);
			if($this->input->post('nameserver_3', true) != '') : $nameservers .= ",".$this->input->post('nameserver_3', true); endif;
			if($this->input->post('nameserver_4', true) != '') : $nameservers .= ",".$this->input->post('nameserver_4', true); endif;
			$this->db->set('nameservers', $nameservers); 
			$this->db->where('id', $id);  
			$this->db->update('orders');

			$this->session->set_flashdata('response_status', 'success');
			$this->session->set_flashdata('message', $response['message']);			
			redirect($_SERVER['HTTP_REFERER']);	
		}
		
		else 
		{
			$this->session->set_flashdata('response_status', 'error');
			$this->session->set_flashdata('message', $this->lastErrorMessage);			
			redirect($_SERVER['HTTP_REFERER']);	
		}

	}



	public function domainUpdate($domainName, array $contacts, array $other = array())    {

		$params = array(
			'Domain' => $domainName,
		);


		foreach($contacts as $contactType => $values)   {
			foreach($values as $key => $value)   {
				$params[$contactType.'_'.$key] = $value;
			}
		}		
		
		foreach($other as $key => $value)   {
			$params[$key] = $value;
		}

		return $this->execute('Domain/Update', $params, 'status');
	}



	public function renew_domain($id, $nameservers) { 
		$order = Order::get_order($id);
		$currentExpirationDate = '';

		$params = array(
			'Domain' => $order->domain,
			'Period' => intval($order->years).'Y',
		);		

		if(!empty($currentExpirationDate))    {

			$currentExpirationDate = trim($currentExpirationDate);

			if(preg_match('/^[0-9]+$/is', $currentExpirationDate))    {
				$params['currentexpiration'] = date('Y-m-d', intval($currentExpirationDate));
			} else if(preg_match('/^20[0-9]{2}-[01][0-9]-[0-3][0-9]$/is', $currentExpirationDate))    {
				$params['currentexpiration'] = $currentExpirationDate;
			} else {
				$this->_error(self::errorType_internal, 'Invalid current expiration value ['.$currentExpirationDate.'], expected format YYYY-mm-dd');
			}

		}
	
		$result = $this->execute('Domain/Renew', $params); 
		if(isset($result['product']) && isset($result['product'][0]) && isset($result['product'][0]['newexpiration']))    {
			return strtotime($result['product'][0]['newexpiration']);
		} else {
			$this->lastErrorMessage = $data['message'];
		}
	}




	
	public function check_balance()   {

		$balance = $this->execute('Account/Balance/Get', array(), 'balance');
		$result = "";			 
		if(is_array($balance) && !empty($balance) && count($balance) > 1)    {
			foreach($balance as $info)   {
				$result .= trim(strtoupper($info['currency'])) ." ". floatval($info['amount']) ."<br>";
			}
			return $result;
		}

		else
		{
			return trim(strtoupper($balance[0]['currency'])) ." ". floatval($balance[0]['amount']);
		}		
		
	}


	public function transfer_domain ($id, $nameservers)   {

		$order = Order::get_order($id);
		$client = Client::view_by_id($order->client_id); 
		$name = explode(' ', User::displayName($client->primary_contact));
		$first_name = $name[0];
		$last_name = (isset($name[1])) ? $name[1] : $name[0];	
		$pre = (strpos($client->company_phone, '+') === false) ? '+' : '';
		$period = 1;	
		$registrant = array(
			'registrant_firstname' => $first_name,
			'registrant_lastname' => $last_name,
			'registrant_organization' => $client->company_name,
			'registrant_email' => $client->company_email,
			'registrant_street' => $client->company_address,
			'registrant_city' => $client->city, 
			'registrant_postalcode' => $client->zip,
			'registrant_country' => $client->country,
			'registrant_countrycode' => App::country_code($client->country), 
			'registrant_phonenumber' => $pre . App::dialing_code($client->country) .'.'.  str_replace(' ', '', $client->company_phone)
			); 

			$params = array(
				'Domain' => $order->domain,
				'transferAuthInfo' => $order->authcode,
			);
		
		$params = array_merge($registrant, $this->admin_contacts(), $params);
		$result = $this->execute('Domain/Transfer/Initiate', $params);

		if(isset($result['product'][0]))
		{
			if($this->_isSame($result['product'][0]['status'], 'SUCCESS'))
			{
				return $result['product'][0]['message'];
			}
		}

		else
		{ 
			return $this->lastErrorMessage;
		}

	
	}
	


	
	public function execute($commandName, array $params, $key = null)   {
	
		$result = $this->_executeApiCommand($commandName, $params);
	
		if($this->_isSuccess())    {
	
			if(null === $key)    {
				return $result;
			} else {
				return isset($result[$key]) ? $result[$key] : null;
			}

		} else { 
			$exceptionClassName = $this->_getExceptionClassNameForErrorType($this->_lastErrorType());
			throw new $exceptionClassName($this->_lastErrorMessage(), $this->_lastErrorCode());
		}
	}



	public function admin_options()
	{
		return '<a class="btn btn-primary btn-sm" href="'.base_url().'registrars/config/internetbs" data-toggle="ajaxModal" title="'.lang('settings').'"><i class="fa fa-pencil"></i> '.lang('settings').'</a>
				<a class="btn btn-success btn-sm" href="'.base_url().'registrars/check_balance/internetbs" data-toggle="ajaxModal" title="'.lang('check_balance').'" ><i class="fa fa-info-circle"></i> '.lang('check_balance').'</a>';
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
 