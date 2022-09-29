<?php
/* Module Name: Resellerclub
 * Module URI: http://www.hostingbilling.net
 * Version: 1.0
 * Category: Domain Registrars
 * Description: ResellerClub Registrar Integration
 * Author: Hosting Billing
 * Author URI: www.hostingbilling.net
 */

class Resellerclub extends Hosting_Billing
{        

	private $url;
	private $reseller_id;
	private $api_key;
	
	public function __construct()
	{
		parent::__construct();  
		
		$this->config = get_settings('resellerclub');
		if(!empty($this->config))
		{
			$this->url = ($this->config['mode'] == 'test') ? 'test.httpapi.com' : 'httpapi.com';
			$this->api_key = $this->config['apikey'];
			$this->reseller_id = $this->config['resellerid'];
		}			
	} 

	
	public function resellerclub_config ($values = null)
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
					'label' => lang('reseller_id'), 
					'id' => 'resellerid',
					'value' => isset($values) ? $values['resellerid'] : ''
				) 
			); 
			
			return $config;        
		}
               
  
	function check_domain($sld, $tld)
	{ 
	$avail = array(
		'domain-name' => $sld,
		'tlds' => array($tld),
		'suggest-alternative' => false,
	  );
 
	$result = $this->callApi('get', 'domains', 'available', $avail);
 
	if( isset($result->status) && strtoupper($result->status) == "ERROR" ) 
	{
		if( !$result->message ) 
		{
			$result->message = $result->error;
		}
		return $result->message;
	} 
   
    $result = json_encode($result);	
	$result = json_decode($result, true);

	if(isset($result[$sld.'.'.$tld]) && $result[$sld.'.'.$tld]['status'] === "available") {
 		return 1;
	}

	else {
		return 0;
	}	 
}



    
	function create_contact($client, $customer_id, $domain)
	{ 
		$rc_data = Order::get_resellerclub_ids($domain);
		if(isset($rc_data->contact_id) && $rc_data->contact_id != 0) {
			return $rc_data->contact_id;
		}

		$contactDetails = array(
		'name' => User::displayName($client->primary_contact),
		'company' => ($client->individual == 0) ? 'N/A' : $client->company_name,
		'email' => $client->company_email,
		'address-line-1' => $client->company_address,
		'city' => $client->city,
		'state' => $client->state,
		'country' => App::country_code($client->country),
		'zipcode' => $client->zip,
		'phone-cc' => App::dialing_code($client->country),
		'phone' => ($client->company_phone != '') ? str_replace(' ', '', $client->company_phone) : str_replace(' ', '', $client->company_mobile),
		'customer-id' => $customer_id,
		'type' => $this->contact_type($domain),
		);
 
		//$contactDetails = array_merge($contactDetails, $this->contact_fields($id, $client));

		$this->validate('array', 'contact', $contactDetails); 
		$result = $this->callApi('post', 'contacts', 'add', $contactDetails);
		if(is_numeric($result)) {
			if($this->db->where('domain', $domain)->get('resellerclub_ids')->num_rows() == '0'){
				$data = array('contact_id' => $result, 'domain' => $domain);
				App::save_data('resellerclub_ids', $data);
			}
		}

		else {
			if($result->status && strtoupper($result->status) == "ERROR" ) 
			{
				if( !$result->message ) 
				{
					$result->message = $result->error;
				}
		
				return 'Customer: ' . $result->message;
			} 
		}	

		return $result;
	}    



	function create_admin_contact($customer_id, $domain)
	{  
		$rc_data = Order::get_resellerclub_ids($domain);
		if(isset($rc_data->admin_id) && $rc_data->admin_id != 0) {
			return $rc_data->admin_id;
		}

		$contactDetails = array(
		'name' => config_item('domain_admin_firstname')." ".config_item('domain_admin_lastname'),
		'company' => config_item('domain_admin_company'),
		'email' => config_item('domain_admin_email'),
		'address-line-1' => config_item('domain_admin_address_1')." ".config_item('domain_admin_address_2'),
		'city' => config_item('domain_admin_city'),
		'state' => config_item('domain_admin_state'),
		'country' => App::country_code(config_item('domain_admin_country')),
		'zipcode' => config_item('domain_admin_zip'),
		'phone-cc' => App::dialing_code(config_item('domain_admin_country')),
		'phone' => str_replace(' ', '', config_item('domain_admin_phone')),
		'customer-id' => $customer_id,
		'type' => 'Contact',
		); 


		$this->validate('array', 'contact', $contactDetails);
		$result = $this->callApi('post', 'contacts', 'add', $contactDetails);
		if(is_numeric($result)) {
			if($this->db->where('domain', $domain)->get('resellerclub_ids')->num_rows() == '0'){
				$data = array('admin_id' => $result, 'domain' => $domain);
				App::save_data('resellerclub_ids', $data);
			}
		}

		else {
			if($result->status && strtoupper($result->status) == "ERROR" ) 
			{
				if( !$result->message ) 
				{
					$result->message = $result->error;
				}
		
				return 'Admin Contact: '. $result->message;
			} 
		}	

		return $result;
	}



	function get_customer ($email) 
	{
		 $customerDetails = array('username' => $email); 
		  return $this->callApi('get', 'customers', 'details', $customerDetails); 
	}

 

	function get_details ($order_id, $options) 
	{
		$apiOptions = array(
			'order-id' => $order_id,
		  );
		  if (is_string($options)) {
			$apiOptions['options'] = $options;
		  } 
		  return $this->callApi('get', 'domains', 'details', $apiOptions);	 
	}



	function suspend_domain () 
	{
		$id = $this->input->post('id');
		$reason = $this->input->post('reason');
		$order = Order::get_order($id);
		$domain = Order::get_resellerclub_ids($order->domain);
	 
		$options = array(
			'order-id' => $domain->order_id,
			'reason' => $reason,
		  ); 
		 
		$result = $this->callApi('post', 'orders', 'suspend', $options);
		if( strtoupper($result->status) == "ERROR" ) 
			{
				if( !$result->message ) 
				{
					$result->message = $result->error;
				}		 
				$this->session->set_flashdata('message', $result->message);			
				redirect($_SERVER['HTTP_REFERER']);	
			} 
		
	 
		if($result->actionstatusdesc) {

			$this->db->set('status_id', 9); 
			$this->db->where('id', $id);  
			$this->db->update('orders');

			$this->session->set_flashdata('response_status', 'success');
			$this->session->set_flashdata('message', $result->actionstatusdesc);			
			redirect($_SERVER['HTTP_REFERER']);		 
		} 
	}
	
	


	function unsuspend_domain ($id) { 

		$order = Order::get_order($id);
		$domain = Order::get_resellerclub_ids($order->domain); 
		$options = array(
			'order-id' => $domain->order_id,
		  ); 

		  $result = $this->callApi('post', 'orders', 'unsuspend', $options);
		
		  if( strtoupper($result->status) == "ERROR" ) 
		  {
			  if( !$result->message ) 
			  {
				  $result->message = $result->error;
			  }		 
			  $this->session->set_flashdata('message', $result->message);			
			  redirect($_SERVER['HTTP_REFERER']);	
		  } 

		if($result->actionstatusdesc) {

			$this->db->set('status_id', 6); 
			$this->db->where('id', $id);  
			$this->db->update('orders');

			$this->session->set_flashdata('response_status', 'success');
			$this->session->set_flashdata('message', $result->actionstatusdesc);			
			redirect($_SERVER['HTTP_REFERER']);	
		} 
	}
 


	function renew_domain ($id, $nameservers)
	{ 
		$order = Order::get_order($id);
		$client = Client::view_by_id($order->client_id);
		$domain = Order::get_resellerclub_ids($order->domain);	
		$options = 'OrderDetails';
		$details = $this->get_details($domain->order_id, $options);

		if($details->endtime) { 			
			$domainDetails = array(
			'years' => $order->years,
			'exp-date' => $details->endtime, 
			'auto-renew' => false,
			'invoice-option' => 'NoInvoice',
			'order-id' => $domain->order_id
			);  
 
			$result = $this->callApi('post', 'domains', 'renew', $domainDetails);

				if( strtoupper($result->status) == "ERROR" ) 
				{
					if( !$result->message ) 
					{
						$result->message = $result->error;
					}
			
					return $result->message;
				} 				 

				$this->db->set('status_id', 6); 
				$this->db->where('id', $id);  
				$this->db->update('orders');

				return $result->actionstatus; 
		}
	}



	function create_customer ($client, $domain) 
	{ 	
		$customerDetails = array(
		'username' => $client->company_email,
		'passwd' => $this->generatePassword(),
		'name' => User::displayName($client->primary_contact),
		'company' => ($client->individual == 0) ? 'N/A' : $client->company_name,
		'address-line-1' => $client->company_address,
		'city' => $client->city,
		'state' => $client->state,
		'country' => App::country_code($client->country),
		'zipcode' => $client->zip,
		'phone-cc' => App::dialing_code($client->country),
		'phone' =>  ($client->company_phone != '') ? str_replace(' ', '', $client->company_phone) : str_replace(' ', '', $client->company_mobile),
		'lang-pref' => 'en',
		);

		$this->validate('array', 'customer', $customerDetails); 
		$result = $this->callApi('post', 'customers', 'signup', $customerDetails);
		if(is_numeric($result)) {
			if($this->db->where('domain', $domain)->get('resellerclub_ids')->num_rows() == '0'){
				$data = array('customer_id' => $result, 'domain' => $domain);
				App::save_data('resellerclub_ids', $data);
			}
		}

		else {
			if($result->status && strtoupper($result->status) == "ERROR" ) 
			{
				if( !$result->message ) 
				{
					$result->message = $result->error;
				}
		
				return 'Customer: ' . $result->message;
			} 
		}	

		return $result;
	}



  
	function update_nameservers ($id)
	{
		$order = Order::get_order($id);
		$domain = Order::get_resellerclub_ids($order->domain);
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

			$options = array(
				'order-id' => $domain->order_id,
				'ns' => $nameservers,
			  );  
			
		$result = $this->callApi('post', 'domains', 'modify-ns', $options);		
		
		if( strtoupper($result->status) == "ERROR" ) 
		  {
			  if( !$result->message ) 
			  {
				  $result->message = $result->error;
			  }		 
			  $this->session->set_flashdata('message', $result->message);			
			  redirect($_SERVER['HTTP_REFERER']);	
		  } 
		  

		if($result->actionstatusdesc) {

			$nameservers = $this->input->post('nameserver_1', true).",".$this->input->post('nameserver_2', true);
			if($this->input->post('nameserver_3', true) != '') : $nameservers .= ",".$this->input->post('nameserver_3', true); endif;
			if($this->input->post('nameserver_4', true) != '') : $nameservers .= ",".$this->input->post('nameserver_4', true); endif;
			$this->db->set('nameservers', $nameservers); 
			$this->db->where('id', $id);  
			$this->db->update('orders');

			$this->session->set_flashdata('response_status', 'success');
			$this->session->set_flashdata('message', $result->actionstatusdesc);			
			redirect($_SERVER['HTTP_REFERER']);	
		}  
	}
	


	function save_contact($order, $id, $column)
	{
		if($this->db->where('domain', $order->domain)->get('resellerclub_ids')->num_rows() == '0'){
			$data = array($column => $id, 'domain' => $order->domain); 
			App::save_data('resellerclub_ids', $data);
		}
		else {
			$this->db->set($column, $id); 
			$this->db->where('domain', $order->domain);  
			$this->db->update('resellerclub_ids');
		}
	}



	function register_domain ($id, $nameservers)
	{ 
		$order = Order::get_order($id);
		$client = Client::view_by_id($order->client_id);
		$result = $this->get_customer($client->company_email);	 
	 
		if(isset($result->status) && strtoupper($result->status == "ERROR" ))
		{
			$customerId = $this->create_customer($client, $order->domain);
			$this->save_contact($order, $customerId, 'customer_id');
		}

		elseif($result->customerid) { 
			$customerId = $result->customerid;
			$this->save_contact($order, $result->customerid, 'customer_id');			
		}

	 

		if(is_numeric($customerId) ) 
		{ 
			$contactId = $this->create_contact($client, $customerId, $order->domain);
			$adminId = $this->create_admin_contact($customerId, $order->domain); 

			if(!is_numeric($contactId)) {
				return $contactId; 
			}
			else{
				$this->save_contact($order, $contactId, 'contact_id');
			}
			
			if(!is_numeric($adminId)) {
				return $adminId; 
			}
			else{
				$this->save_contact($order, $adminId, 'admin_id');
			}
 			 
		}
 

		$tlds = array('eu', 'fr', 'nz', 'ru', 'uk'); 
		$ext = $this->domain_end($order->domain);

		if(in_array($ext, $tlds)){
			$adminId = '-1';
		}

		$domainDetails = array(
		'years' => $order->years,
		'ns' => $nameservers,
		'customer-id' => $customerId,
		'reg-contact-id' => $contactId,
		'admin-contact-id' => $adminId,
		'tech-contact-id' => $adminId,
		'billing-contact-id' => $adminId,
		'domain-name' => $order->domain,
		'invoice-option' => 'NoInvoice',
		);
 

		$this->defaultValidate($domainDetails);
		$result = $this->callApi('post', 'domains', 'register', $domainDetails);
 
		if( strtoupper($result->status) == "ERROR" ) 
				{
					if( !$result->message ) 
					{
						$result->message = $result->error;
					}
			
					return $result->message;
				} 

		if($result->entityid) {
			$this->db->set('order_id', $result->entityid); 
			$this->db->where('domain', $order->domain);  
			$this->db->update('resellerclub_ids');
		}  

		return $result->actiontypedesc . " - " . $result->actionstatus;
		
	}



	function transfer_domain ($id, $nameservers)
	{ 
		$order = Order::get_order($id);
		$client = Client::view_by_id($order->client_id);
		$result = $this->get_customer($client->company_email); 

		if(isset($result->status) && strtoupper($result->status == "ERROR" ))
		{
			$customerId = $this->create_customer($client, $order->domain);
			$this->save_contact($order, $customerId, 'customer_id');
		}

		elseif($result->customerid) { 
			$customerId = $result->customerid;
			$this->save_contact($order, $result->customerid, 'customer_id');			
		}

	 

		if(is_numeric($customerId) ) 
		{ 
			$contactId = $this->create_contact($client, $customerId, $order->domain);
			$adminId = $this->create_admin_contact($customerId, $order->domain); 

			if(!is_numeric($contactId)) {
				return $contactId; 
			}
			else{
				$this->save_contact($order, $contactId, 'contact_id');
			}
			
			if(!is_numeric($adminId)) {
				return $adminId; 
			}
			else{
				$this->save_contact($order, $adminId, 'admin_id');
			}
 			 
		}


 	
			$tlds = array('eu', 'fr', 'nz', 'ru', 'uk'); 
			$ext = $this->domain_end($order->domain);

			if(in_array($ext, $tlds)){
				$adminId = '-1';
			}
				
			

			$domainDetails = array(
			'years' => $order->years,
			'ns' => $nameservers,
			'customer-id' => $customerId,
			'reg-contact-id' => $contactId,
			'admin-contact-id' => $adminId,
			'tech-contact-id' => $adminId,
			'billing-contact-id' => $adminId,
			'auth-code' => $order->authcode,
			'domain-name' => $order->domain,
			'auto-renew' => false,
			'invoice-option' => 'NoInvoice',
			);
  		 
			$result = $this->callApi('post', 'domains', 'transfer', $domainDetails);

				if( strtoupper($result->status) == "ERROR" ) 
				{
					if( !$result->message ) 
					{
						$result->message = $result->error;
					}
			
					return $result->message;
				} 
 				 
				return $result->actiontypedesc . " - " . $result->actionstatusdesc;		 
	}



	function generatePassword()
	{
		$password_string = 'abcdefghijklmnpqrstuwxyzABCDEFGHJKLMNPQRSTUWXYZ123456789';		 
		return substr(str_shuffle($password_string), 0, 10);
	}



	function contact_details ($id) {
		$contactDetails = array('contact-id' => $contactId);
		$this->defaultValidate($contactDetails);
		return $this->callApi('get', 'contacts', 'details', $contactDetails); 
	}



	function check_balance () {
		  $options = array(
			'reseller-id' => config_item('resellerclub_resellerid'),
		  );
	 
		$result = $this->callApi('get', 'billing', 'reseller-balance', $options);
		$data = array();
		if(isset($result->status) && strtoupper($result->status) == "ERROR" ) 
			{
				if( !$result->message ) 
				{
					return $result->error;
				}
		
				return $result->message;
			} 

		else {
			return $result->sellingcurrencybalance. " " .$result->sellingcurrencysymbol;
		}	
		 
	}




	function create_order($item, $co_id, $domain)
    { 
        $items = array(
            'invoice_id' 	=> 0,
            'item_name'		=> 'Domain Imported',
            'item_desc'		=> '-',
            'unit_cost'		=> $item->renewal,
            'item_order'	=> 1,
            'item_tax_rate'	=> 0,
            'item_tax_total'=> 0,
            'quantity'		=> 1,
            'total_cost'	=> $item->renewal
            );
            
        $item_id = App::save_data('items', $items);
        $time = strtotime($domain['details']->Created);
		$created = $domain['details']->Created; 
		$expires = $domain['details']->Expires;
		
	 
        $order = array(
            'client_id' 	=> $co_id,
			'invoice_id'    => 0,
			'date'          => date('Y-m-d H:i:s'),
            'nameservers'	=> (null != $domain['details']->Nameservers) ? $domain['details']->Nameservers : '',
            'item'		    => $item_id,
            'domain'        => $domain['details']->Domain,
            'item_parent'   => $item->item_id,
            'type'		    => 'domain_only',
            'process_id'    => $time,
			'order_id'      => $time,
			'registrar' 	=> 'resellerclub',
            'fee'           => 0,
            'processed'     => $created, 
            'renewal_date'  => $expires,
			'status_id'     => ($domain['details']->Status == "Active") ? 6 : 5, 
            'renewal'       => 'annually'
		);    
		
		if($order_id = App::save_data('orders', $order)) 
		{ 
			return true;
		}
 
	}
	



	public function import_domains()
	{
		if($this->input->post()) 
		{
			$count = 0;			
		 
			$list = $this->input->post();

			if(count($list) > 0) {
				$accounts = $this->session->userdata('import');
				$domains = array();
		
					foreach($accounts as $account) {
						foreach($list as $key => $li) { 
							if($account->Domain == str_replace("_",".",$key)) { 

								$result = $this->contact_details($account->Contact_id); 
 
								if(isset($result->status) && strtoupper($result->status == "ERROR" ))
								{
									$this->session->set_flashdata('response_status', 'warning');
									$this->session->set_flashdata('message', $result->message);			
									redirect($_SERVER['HTTP_REFERER']);	
								}

								elseif($result) {
									$details = array('contact' => (object) $result, 'details' => $account);
									$domains[] = $details; 
								}								  								
							}					
						}			
					}
		  
			 

				foreach($domains as $domain) {
	
					$tld = explode('.', $domain['details']->Domain, 2);
					$ext = $tld[1]; 
					$item = $this->db->where('item_name', $ext)->join('item_pricing', 'item_pricing.item_id = items_saved.item_id')->get('items_saved')->row();
					$client = $this->db->where('company_email', $domain['contact']->emailaddr)->get('companies')->row();
		
					if(is_object($item)) {
						if(is_object($client)) {
	
							if($this->db->where('domain', $domain['details']->Domain)->where('(type = "domain" OR type = "domain_only")')->get('orders')->num_rows() == 0) { 							
								if($this->create_order($item, $client->co_id, $domain)) {
									$count++;
								}
							}					   
						}
						else
						{
							$username = explode('@', $domain['contact']->emailaddr)[0];
                            $email = $domain['contact']->emailaddr; 
                            $password = $domain['contact']->emailaddr; 
                            
                            $hasher = new PasswordHash(
                                $this->config->item('phpass_hash_strength', 'tank_auth'),
								$this->config->item('phpass_hash_portable', 'tank_auth')
							);
							
							$hashed_password = $hasher->HashPassword($password);
                                        
                            if (!is_username_available($username)) {    
                                $username = explode('.', $domain['details']->Domain, 2)[0]; 
                            }                             
                                                           
                                $data = array(
                                    'username'	=> $username, 
                                    'password'  => $hashed_password,
                                    'email'		=> $email,
                                    'role_id'	=> 2 
                                );
            
                                $user_id = App::save_data('users', $data); 
                                
								$client = array(   
									'company_name'          => $domain['contact']->name,
									'company_email'         => $domain['contact']->emailaddr,                       
									'company_ref'			=> $this->applib->generate_string(), 
									'language' 				=> config_item('default_language'),
									'currency' 				=> config_item('default_currency'),
									'primary_contact'       => $user_id,
									'individual' 			=> 0, 
									'company_address' 		=> $domain['contact']->address1,                         
									'company_phone'		  	=> $domain['contact']->telno,
									'city'				  	=> $domain['contact']->city,
									'state'			      	=> $domain['contact']->state,
									'zip'				  	=> $domain['contact']->zip,
									'country'			  	=> $domain['contact']->country
									); 

                                if($co_id = App::save_data('companies', $client)) {
                                    
                                    $profile = array(
                                        'user_id'           => $user_id,
                                        'company'	        => $co_id,
                                        'fullname'	        => $domain['contact']->name,
                                        'phone'		        => $domain['contact']->telno,
                                        'avatar'	        => 'default_avatar.jpg',
                                        'language'	        => config_item('default_language'),
                                        'locale'	        => config_item('locale') ? config_item('locale') : 'en_US'
                                    );
                
									App::save_data('account_details', $profile); 
						 
								if($this->create_order($item, $co_id, $domain)) {
									$count++; 
								} 
							}	
						}	
					}
				}
				 
				$this->session->unset_userdata('import');
			}			

			$this->session->set_flashdata('response_status', 'info');
			$this->session->set_flashdata('message', "Created ".$count." accounts");			
			redirect($_SERVER['HTTP_REFERER']);
		}
		else 
		{
			$this->load->module('layouts');      
        	$this->load->library('template');
 			$this->template->title(lang('import'));
			$data['page'] = 'Namecheap';	
			$data['datatables'] = TRUE; 
			$data['domains'] = $this->get_accounts();
			$this->template
			->set_layout('users')
			->build('import',isset($data) ? $data : NULL); 
		}
	}




	
	function upload()
	{		 
		if($this->input->post()) {

			$this->load->library('excel');
			ob_start();
			$file = $_FILES["import"]["tmp_name"];
			if (!empty($file)) {
				$valid = false;
				$types = array('Excel2007', 'Excel5', 'CSV');
				foreach ($types as $type) {
					$reader = PHPExcel_IOFactory::createReader($type);
					if ($reader->canRead($file)) {
						$valid = true;
					}
				}
				if (!empty($valid)) {
					try {
						$objPHPExcel = PHPExcel_IOFactory::load($file);
					} catch (Exception $e) {
						$this->session->set_flashdata('response_status', 'warning');
						$this->session->set_flashdata('message', "Error loading file:" . $e->getMessage());			
						redirect($_SERVER['HTTP_REFERER']);	
					
					}
					$sheetData = $objPHPExcel->getActiveSheet()->toArray(null, true, true, true);
					$domains = array();
					$list = array();
					for ($x = 2; $x <= count($sheetData); $x++) {					
						$domain = array();					 
						$domain['Domain'] = trim($sheetData[$x]["B"]);
						$domain['Created'] = date('Y-m-d', strtotime($sheetData[$x]["D"]));
						$domain['EmailAddress'] = trim($sheetData[$x]["E"]);
						$domain['Contact_id'] = trim($sheetData[$x]["F"]);
						$domain['Status'] = trim($sheetData[$x]["J"]);   
						$domain['Expires'] = date('Y-m-d', strtotime($sheetData[$x]["K"])); 
						$domains[] = (object) $domain;						
					}	
					
					$this->session->set_userdata('import', $domains);

				} else {
					$this->session->set_flashdata('response_status', 'warning');
					$this->session->set_flashdata('message', lang('not_csv'));			
					redirect($_SERVER['HTTP_REFERER']);	
				}
			} else {
				$this->session->set_flashdata('response_status', 'warning');
				$this->session->set_flashdata('message', lang('no_csv'));			
				redirect($_SERVER['HTTP_REFERER']);	
			}			
				$this->template->title(lang('import'));
				$data['page'] = 'ResellerClub';	 
				$data['domains'] = $domains; 
				$this->template
				->set_layout('users')
				->build('import',isset($data) ? $data : NULL); 
			}

		else {
			$this->load->module('layouts');      
        	$this->load->library('template');
			$this->template->title(lang('import'));
			$data['page'] = 'ResellerClub';	 
			$this->template
			->set_layout('users')
			->build('upload',isset($data) ? $data : NULL);
		}
	}





	function domain_end($domain) {
		$domain_parts = explode('.', $domain);
		return $domain_parts[count($domain_parts) - 1];
	}


 


	function contact_type($domain)
	{
		$ext = $this->domain_end($domain);
		
		$contacttype = "contact";
			
		if( $ext == "uk" ) 
		{
			$contacttype = "UkContact";
		}
		else
		{
			if( $ext == "eu" ) 
			{
				$contacttype = "EuContact";
			}
			else
			{
				if( $ext == "cn" ) 
				{
					$contacttype = "CnContact";
				}
				else
				{
					if( $ext == "co" ) 
					{
						$contacttype = "CoContact";
					}
					else
					{
						if( $ext == "ca" ) 
						{
							$contacttype = "CaContact";
						}
						else
						{
							if( $ext == "es" ) 
							{
								$contacttype = "EsContact";
							}
							else
							{
								if( $ext == "de" ) 
								{
									$contacttype = "DeContact";
								}
								else
								{
									if( $ext == "ru" ) 
									{
										$contacttype = "RuContact";
									}
									else
									{
										if( $ext == "nl" ) 
										{
											$contacttype = "NlContact";
										}
										else
										{
											if( $ext == "mx" ) 
											{
												$contacttype = "MxContact";
											}
											else
											{
												if( $ext == "br" ) 
												{
													$contacttype = "BrContact";
												}
												else
												{
													if( $ext == "nyc" ) 
													{
														$contacttype = "NycContact";
													}
													else
													{
														if( $ext == "tel" ) 
														{
															$contacttype = "Contact";
														}
														else
														{
															$contacttype = "Contact";
														}

													}

												}

											}

										}

									}

								}

							}

						}

					}

				}

			}

		}

		return $contacttype;
	}
 

	function contact_fields($id, $params)
	{

		$order = Order::get_order($id);
		$fields = array();
		$ext = $this->domain_end($order->domain);
		$fields = $this->db->where('domain', $order->additional_fields)->get('additional_fields')->result();

		$domain_fields = array();
		$params = (array) $params;

		foreach($fields as $key => $field) {
			$domain_fields[$field->field_name] = $field->field_value;
		}
 

		if( $ext == "us" ) 
		{
			$purpose = $domain_fields["Application Purpose"];
			$category = $domain_fields["Nexus Category"];
			if( $purpose == "Business use for profit" ) 
			{
				$purpose = "P1";
			}
			else
			{
				if( $purpose == "Non-profit business" || $purpose == "Club" || $purpose == "Association" || $purpose == "Religious Organization" ) 
				{
					$purpose = "P2";
				}
				else
				{
					if( $purpose == "Personal Use" ) 
					{
						$purpose = "P3";
					}
					else
					{
						if( $purpose == "Educational purposes" ) 
						{
							$purpose = "P4";
						}
						else
						{
							if( $purpose == "Government purposes" ) 
							{
								$purpose = "P5";
							}
	
						}
	
					}
	
				}
	
			}
	
			$fields["attr-name1"] = "purpose";
			$fields["attr-value1"] = (string) $purpose;
			$fields["attr-name2"] = "category";
			$fields["attr-value2"] = (string) $category;
			$fields["product-key"] = "domus";
		}
		else
		{
			if( $ext == "uk" ) 
			{
				if( $domain_fields["Registrant Name"] ) 
				{
					$fields["name"] = $domain_fields["Registrant Name"];
				}
	
			}
			else
			{
				if( $ext == "ca" ) 
				{
					if( $domain_fields["Legal Type"] == "Corporation" ) 
					{
						$legaltype = "CCO";
					}
					else
					{
						if( $domain_fields["Legal Type"] == "Canadian Citizen" ) 
						{
							$legaltype = "CCT";
						}
						else
						{
							if( $domain_fields["Legal Type"] == "Permanent Resident of Canada" ) 
							{
								$legaltype = "RES";
							}
							else
							{
								if( $domain_fields["Legal Type"] == "Government" ) 
								{
									$legaltype = "GOV";
								}
								else
								{
									if( $domain_fields["Legal Type"] == "Canadian Educational Institution" ) 
									{
										$legaltype = "EDU";
									}
									else
									{
										if( $domain_fields["Legal Type"] == "Canadian Unincorporated Association" ) 
										{
											$legaltype = "ASS";
										}
										else
										{
											if( $domain_fields["Legal Type"] == "Canadian Hospital" ) 
											{
												$legaltype = "HOP";
											}
											else
											{
												if( $domain_fields["Legal Type"] == "Partnership Registered in Canada" ) 
												{
													$legaltype = "PRT";
												}
												else
												{
													if( $domain_fields["Legal Type"] == "Trade-mark registered in Canada" ) 
													{
														$legaltype = "TDM";
													}
													else
													{
														$legaltype = "CCO";
													}
	
												}
	
											}
	
										}
	
									}
	
								}
	
							}
	
						}
	
					}
	
					$fields["attr-name1"] = "CPR";
					$fields["attr-value1"] = (string) $legaltype;
					$fields["attr-name2"] = "AgreementVersion";
					$fields["attr-value2"] = "2.0";
					$fields["attr-name3"] = "AgreementValue";
					$fields["attr-value3"] = "y";
					$fields["product-key"] = "dotca";
				}
				else
				{
					if( $ext == "es" ) 
					{
						 
						$legaltype =  false;

						if( !$legaltype ) 
						{
							$legaltype = "1";
						}
	
						if( $legaltype == "1" ) 
						{
							$fields["company"] = "N/A";
						} 

						$idtype = $domain_fields["ID Form Type"];
						if( $idtype == "Other Identification" ) 
						{
							$idtype = 0;
						}
						else
						{
							if( $idtype == "Tax Identification Number" || $idtype == "Tax Identification Code" ) 
							{
								$idtype = 1;
							}
							else
							{
								if( $idtype == "Foreigner Identification Number" ) 
								{
									$idtype = 3;
								}
	
							}
	
						}
	
						$idnumber = $domain_fields["ID Form Number"];
						$fields["attr-name1"] = "es_form_juridica";
						$fields["attr-value1"] = (string) $legaltype;
						$fields["attr-name2"] = "es_tipo_identificacion";
						$fields["attr-value2"] = (string) $idtype;
						$fields["attr-name3"] = "es_identificacion";
						$fields["attr-value3"] = (string) $idnumber;
						$fields["product-key"] = "dotes";
					}
					else
					{
						if( $ext == "asia" ) 
						{
							$fields["attr-name1"] = "locality";
							$fields["attr-value1"] = $params["country"];
							$fields["attr-name2"] = "legalentitytype";
							$fields["attr-value2"] = $domain_fields["Legal Type"];
							$fields["attr-name3"] = "identform";
							$fields["attr-value3"] = $domain_fields["Identity Form"];
							$fields["attr-name4"] = "identnumber";
							$fields["attr-value4"] = $domain_fields["Identity Number"];
							$fields["product-key"] = "dotasia";
						}
						else
						{
							if( $ext == "ru" ) 
							{
								$fields["attr-name1"] = "contract-type";
								if( $domain_fields["Registrant Type"] == "ORG" ) 
								{
									$fields["attr-value1"] = "ORG";
									$fields["attr-name3"] = "org-r";
									$fields["attr-value3"] = $params["company_name"];
									$fields["attr-name6"] = "kpp";
									$fields["attr-value6"] = $domain_fields["Russian Organizations Territory-Linked Taxpayer Number 2"];
									$fields["attr-name7"] = "code";
									$fields["attr-value7"] = $domain_fields["Russian Organizations Taxpayer Number 1"];
								}
								else
								{
									$fields["attr-value1"] = "PRS";
									$fields["attr-name2"] = "birth-date";
									$dateParts = explode("-", $domain_fields["Individuals Birthday"]);
									$fields["attr-value2"] = $dateParts[2] . "." . $dateParts[1] . "." . $dateParts[0];
									$fields["attr-name4"] = "person-r";
									$fields["attr-value4"] = (string) $params["company_name"];
									$fields["attr-name8"] = "passport";
									$dateParts = explode("-", $domain_fields["Individuals Passport Issue Date"]);
									$passportIssuanceDate = $dateParts[2] . "." . $dateParts[1] . "." . $dateParts[0];
									$fields["attr-value8"] = $domain_fields["Individuals Passport Number"] . ", issued by " . $domain_fields["Individuals Passport Issuer"] . ", " . $passportIssuanceDate;
								}
	
								$fields["attr-name5"] = "address-r";
								$fields["attr-value5"] = (string) $params["company_address"] . " " . $params["city"] . " " . $params["state"] . " " . $params["country"] . " " . $params["zip"];
							}
							else
							{
								if( $ext == "pro" ) 
								{
									$fields["attr-name1"] = "profession";
									$fields["attr-value1"] = $domain_fields["Profession"];
									$fields["product-key"] = "dotpro";
								}
								else
								{
									if( $ext == "nl" ) 
									{
										$fields["attr-name1"] = "legalForm";
										$fields["attr-value1"] = ($params["company_name"] ? "ANDERS" : "PERSOON");
										$fields["product-key"] = "dotnl";
									}
									else
									{
										if( $ext == "tel" ) 
										{
											$fields["attr-name1"] = "whois-type";
											if( $domain_fields["Legal Type"] == "Natural Person" ) 
											{
												$fields["attr-value1"] = "Natural";
											}
											else
											{
												if( $domain_fields["Legal Type"] == "Legal Person" ) 
												{
													$fields["attr-value1"] = "Legal";
												}
	
											}
	
										}
	
									}
	
								}
	
							}
	
						}
	
					}
	
				}
	
			}
	
		}
	
		return $fields;
	}



	public function validate($type, $subType, $parameters) 
	{
		$validationFunction = $this->getValidationFunction($type, $subType);
		if (NULL === $validationFunction) {
		  return 'Invalid validation function.';
		}
		else {
		  if (method_exists($this, $validationFunction)) {
			return $this->$validationFunction($parameters);
		  }
		}
	  }




	public function createUrlParameters($parameters) 
	{
		$parameterItems = array();
		foreach ($parameters as $key => $value) {
		  if (is_array($value)) {
			foreach ($value as $item) {
			  if ($this->isValidUrlParameter($item)) {
				$parameterItems[] = $key . '=' . urlencode($item);
			  }
			}
		  }
		  elseif ($this->isValidUrlParameter($value)) {
			$parameterItems[] = $key . '=' . urlencode($value);
		  }
		  else {
			return "Invalid URL Array";
		  }
		}
		return implode('&', $parameterItems);
	  }
	

 
	  private function isValidUrlParameter($parameter) 
	  {
		if (is_string($parameter) || is_int($parameter) || is_bool($parameter)) {
		  return TRUE;
		}
		else {
		  return FALSE;
		}
	  }
	

	 
	  public function createUrl($urlFullArray) 
	  {
		$requestPath = $this->createRequestPath($urlFullArray);
		$parameterString = $this->createParameterString($urlFullArray);
		return $requestPath . '?' . $parameterString;
	  }
	
 
	  private function createRequestPath($urlFullArray) 
	  {
		$head = $urlFullArray['head'];
		$protocol = $head['protocol'];
		$domain = $head['domain'];
		$section = $head['section'];
		$section2 = $head['section2'];
		$apiName = $head['api-name'];
		$format = $head['format'];
	
		if (NULL == $section2) {
		  $requestPath = "$protocol://$domain/api/$section/$apiName.$format";
		}
		else {
		  $requestPath = "$protocol://$domain/api/$section/$section2/$apiName.$format";
		}
		return $requestPath;
	  }
	

 
	  private function createParameterString($urlFullArray) 
	  {
		$head = $urlFullArray['head'];
		$urlArray = $urlFullArray['content'];
		if (isset($head['auth-userid']) && isset($head['api-key'])) {
		  $authParameter = array(
			'auth-userid' => $head['auth-userid'],
			'api-key' => $head['api-key'],
		  );
		  $authParameterString = $this->createUrlParameters($authParameter);
		}
		$parameterString = $this->createUrlParameters($urlArray);
		$parameters = '';
		if (!empty($parameterString)) {
		  if (!empty($authParameterString)) {
			$parameters .= $authParameterString . '&';
		  }
		  $parameters .= $parameterString;
		}
		return $parameters;
	  }
	

 
	  public function callApi($method, $section, $apiName, $urlArray, $section2 = NULL) 
	  {
		$urlFullArray = array(
		  'head' => array(
			'protocol' => 'https',
			'domain' => $this->url,
			'section' => $section,
			'section2' => $section2,
			'api-name' => $apiName,
			'format' => 'json',
			'auth-userid' => $this->reseller_id,
			'api-key' => $this->api_key
		),
		  'content' => $urlArray
		);
	 
		$curl = curl_init();
		if('get' === $method) {
		  $url = $this->createUrl($urlFullArray);
		  curl_setopt($curl, CURLOPT_URL, $url);
		  curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		} else {
	 
		  $requestPath = $this->createRequestPath($urlFullArray);	
		  curl_setopt($curl, CURLOPT_URL, $requestPath);
		  curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		  curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);	
		  curl_setopt($curl, CURLOPT_POST,TRUE);
		  curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); 
	 	  $parameterString = $this->createParameterString($urlFullArray);
		  curl_setopt($curl, CURLOPT_POSTFIELDS, $parameterString);
		}
		$json_result = curl_exec($curl);
		if (curl_errno($curl)) { 
		  return '<h3>Cannot connect to API server</h3>
		  <p>It can be because IP is not whitelisted or Connection is not available</p>';
		  
		}
		curl_close($curl);
		$result_array = json_decode($json_result);
		return $result_array;
	 
	  }


	
	  private function getValidationFunction($type, $subType) 
	  {
		$validations = array();	
 
		$validations['array']['default'] = 'validateArrayDefault';
		$validations['array']['contact'] = 'validateContact';
		$validations['array']['customer'] = 'validateCustomer';
	
 		$validations['string']['email'] = 'validateEmail';
		$validations['string']['ip'] ='validateIp';
		$validations['string']['customer-id'] ='validateCustomerId';
		$validations['string']['contact-id'] ='validateContactId';
	
		if (!empty($validations[$type][$subType])) {
		  return $validations[$type][$subType];
		}
		else {
		  return NULL;
		}
	  }




	  private function validateArray($inputArray, $mandatory, $optional = array()) 
	  {
		if (!is_array($inputArray)) {	 
		  return 'Input is not an array';
		}
		foreach ($inputArray as $key => $value) {
		  if (!(in_array($key, $mandatory) or in_array($key, $optional))
			and !empty($optional)) { 
			return 'There are invalid parameters.';
		  }

		  if (!(
			is_array($value) or
			is_string($value) or
			is_int($value) or
			is_bool($value) or
			is_float($value) or
			is_null($value)
		  )) {
			if (is_array($value)) {
			  foreach ($value as $parameter) {
				if (!(
				  is_string($parameter) or
				  is_int($parameter) or
				  is_bool($parameter) or
				  is_float($parameter) or
				  is_null($parameter)
				)) {
				  return FALSE;
				}
			  }
			}
			return 'Input is not an array.';
		  }
		  if (TRUE !== $this->validateItem($key, $value)) {
			return 'Item is invalid.';
		  }
		}
	
 
		foreach ($mandatory as $mandatoryItem) { 
		  if (!isset($inputArray[$mandatoryItem])) {
			return 'Mandatory items in array missing';
		  }
		}
		return TRUE;
	  }
	
 

	  private function validateItem($itemValidator, $item) 
	  {
 		$itemValidators = array(
		  'email' => array('string', 'email'),
		  'username' => array('string', 'email'),
		  'customer-id' => array('string', 'customer-id'),
		  'contact-id' => array('string', 'contact-id'),
		  'ip' => array('string', 'ip'),
		);
	
 		if ( !empty($itemValidators[$itemValidator])) {
		  $validator = $itemValidators[$itemValidator];
		  $validatorFunction = $this->getValidationFunction($validator[0], $validator[1]);
		} else {
		  $validatorFunction = NULL;
		}
 
		if (NULL !== $validatorFunction) {
		  return $this->$validatorFunction($item);
		}
		else { 
		  return TRUE;
		}
	  }
	

	 
	  private function validateEmail($email) 
	  {
		if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
		  return TRUE;
		}
		else {
		  return FALSE;
		}
	  }


	 
	  private function validateIp($IpAddress) 
	  {
		if(filter_var($IpAddress, FILTER_VALIDATE_IP)) {
		  return TRUE;
		}
		return FALSE;
	  }
	


	 
	  private function validateCustomerId($customerId) 
	  {
		if(is_numeric($customerId) && (strlen($customerId) === 8)) {
		  return TRUE;
		}
		return FALSE;
	  }
	 


	  private function validateContactId($contactId) 
	  {
		if(is_numeric($contactId) && (strlen($contactId) === 8)) {
		  return TRUE;
		}
		return FALSE;
	  }
	 


	  private function validateArrayDefault($validateArray) 
	  {
 		return $this->validateArray($validateArray, array());
	  }

 
 

	  public function defaultValidate($parameters) {
		return $this->validate('array', 'default', $parameters);
	  }

 


	  private function validateContact($contactDetails) 
	  {
		$mandatory = array(
		  'name',
		  'company',
		  'email',
		  'address-line-1',
		  'city',
		  'country',
		  'zipcode',
		  'phone-cc',
		  'phone',
		  'customer-id',
		  'type',
		);

		$optional = array(
		  'contact-id',
		  'address-line-2',
		  'address-line-3',
		  'state',
		  'fax-cc',
		  'fax',
		  'attr-name',
		  'attr-value',
		);
		return $this->validateArray($contactDetails, $mandatory, $optional);
	  }
	


	 
	  private function validateCustomer($customerDetails) 
	  {
		$mandatory = array(
		  'username',
		  'passwd',
		  'name',
		  'company',
		  'address-line-1',
		  'city',
		  'state',
		  'country',
		  'zipcode',
		  'phone-cc',
		  'phone',
		  'lang-pref',
		);

		$optional = array(
		  'other-state',
		  'address-line-2',
		  'address-line-3',
		  'alt-phone-cc',
		  'alt-phone',
		  'fax-cc',
		  'fax',
		  'mobile-cc',
		  'mobile',
		  'customer-id',
		);

		if ($this->validateArray($customerDetails, $mandatory, $optional)) {
		  return TRUE;
		}
		return FALSE;
	  }


	  public function admin_options()
	  {
		  return '<a class="btn btn-primary btn-sm" href="'.base_url().'registrars/config/resellerclub" data-toggle="ajaxModal" title="'.lang('settings').'"><i class="fa fa-pencil"></i> '.lang('settings').'</a>                               
				   <a class="btn btn-warning btn-sm" href="'.base_url().'resellerclub/upload/"  ><i class="fa fa-download"></i> '.lang('import_accounts').'</a>
				  <a class="btn btn-success btn-sm" href="'.base_url().'registrars/check_balance/resellerclub" data-toggle="ajaxModal" title="'.lang('check_balance').'" ><i class="fa fa-info-circle"></i> '.lang('check_balance').'</a>';
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
  
	    
	  function index()
	  {
		  redirect('domains');
	  } 
  

}


 