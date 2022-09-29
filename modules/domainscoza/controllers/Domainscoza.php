<?php
/* Module Name: Domainscoza
 * Module URI: http://www.hostingbilling.net
 * Version: 1.0
 * Category: Domain Registrars
 * Description: Domains Registrar Integration
 * Author: Hosting Billing
 * Author URI: www.hostingbilling.net
 */

class Domainscoza extends Hosting_Billing
{  
		private $host;
        private $username;
        private $key; 	
		private $config;	
     
        public function __construct()
        {
			parent::__construct();  
			$this->host = "https://api-v3.domains.co.za/api/domain/domain/"; 
			$this->config = get_settings('domainscoza');
			if(!empty($this->config))
			{
				$this->key = $this->config['apikey'];
			}			
		}

 

		public function domainscoza_config ($values = null)
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
					'value' => isset($values) ? $values['apikey'] : '',
					'type' => 'password'
				) 
			); 
			
			return $config;        
		}
	 

		public function check_domain($sld, $tld)
		{
			$data = array(); 
			$data["key"] = $this->key;
			$data["sld"] = $sld;
			$data["tld"] = $tld; 

			$result = $this->curl_download($this->host . "check", $data);
			$result = json_decode($result, true); 
			
			if(isset($result['isAvailable']) && $result['isAvailable'] === "true") {
				return 1;
			}

			else {
				return 0;
			}
		}
		

		public function register_domain($id, $nameservers) 
		{  
 			$order = Order::get_order($id);
			$client = Client::view_by_id($order->client_id);		  
			$tld = explode('.', $order->domain, 2);		
			$data = array(); 
			$data["key"] = $this->key;
			$data["sld"] = $tld[0];
			$data["tld"] = $tld[1]; 
			$data["registrantName"] = User::displayName($client->primary_contact);
			$data["registrantAddress1"] = $client->company_address;
			$data["registrantProvince"] = $client->state;
			$data["registrantPostalCode"] = $client->zip;
			$data["registrantCountry"] = App::country_code($client->country);
			$data["registrantContactNumber"] = "+".App::dialing_code($client->country).".".$client->company_phone;
			$data["registrantEmail"] = $client->company_email;
			$data["registrantCity"] = $client->city;
			$data["externalRef"] = "Order ID: ".$order->order_id; 		
			$data["period"] = "Order ID: ".$order->years;

			if(isset($nameservers[0])) {
				$data["ns1"] = $nameservers[0];
			}
			if(isset($nameservers[1])) {
				$data["ns2"] = $nameservers[1];
			}
			if(isset($nameservers[2])) {
				$data["ns3"] = $nameservers[2];
			}
			if(isset($nameservers[3])) {
				$data["ns4"] = $nameservers[3];
			}
			if(isset($nameservers[4])) {
				$data["ns5"] = $nameservers[4];
			}
			

 
			$result = $this->curl_download($this->host . "create", $data);
			$result = json_decode($result, true);
			return "DomainsCoZa: ". $result['strMessage'];		 
		}




		public function transfer_domain($id, $nameservers) 
		{
			$order = Order::get_order($id);
			$client = Client::view_by_id($order->client_id);		  
			$tld = explode('.', $order->domain, 2); 

			$data = array(); 
			$data["key"] = $this->key;
			$data["sld"] = $tld[0];
			$data["tld"] = $tld[1]; 
			$data["eppKey"] = $order->authkey;			
			$data["registrantName"] = User::displayName($client->primary_contact);
			$data["registrantAddress1"] = $client->company_address;
			$data["registrantProvince"] = $client->state;
			$data["registrantPostalCode"] = $client->zip;
			$data["registrantCountry"] = App::country_code($client->country);
			$data["registrantContactNumber"] = "+".App::dialing_code($client->country).".".$client->company_phone;
			$data["registrantEmail"] = $client->company_email;
			$data["registrantCity"] = $client->city;
			$data["dns"] = "custom";
			$data["externalRef"] = "Order ID: ".$order->order_id;

			if(isset($nameservers[0])) {
				$data["ns1"] = $nameservers[0];
			}
			if(isset($nameservers[1])) {
				$data["ns2"] = isset($nameservers[1]);
			}
			if(isset($nameservers[2])) {
				$data["ns3"] = isset($nameservers[2]);
			}
			if(isset($nameservers[3])) {
				$data["ns4"] = isset($nameservers[3]);
			}
			if(isset($nameservers[4])) {
				$data["ns5"] = isset($nameservers[4]);
			}
 
			$result = $this->curl_download($this->host . "transfer", $data);
			$result = json_decode($result, true);
			return "DomainsCoZa: ". $result['strMessage'];		 
		}




		public function renew_domain($id, $nameservers) 
		{
			$order = Order::get_order($id);
			$client = Client::view_by_id($order->client_id);		  
			$tld = explode('.', $order->domain, 2); 
		
			$data = array(); 
			$data["key"] = $this->key;
			$data["sld"] = $tld[0];
			$data["tld"] = $tld[1]; 
			$data["period"] = "Order ID: ".$order->years;

			$result = $this->curl_download($this->host . "renew", $data);
			$result = json_decode($result, true);
			return "DomainsCoZa: ". $result['strMessage'];		 
		}




		public function suspend_domain() 
		{

			$id = $this->input->post('id');
			$order = Order::get_order($id);
			$client = Client::view_by_id($order->client_id);		  
			$tld = explode('.', $order->domain, 2); 
		
			$data = array(); 
			$data["key"] = $this->key;
			$data["sld"] = $tld[0];
			$data["tld"] = $tld[1]; 

			$result = $this->curl_download($this->host . "suspend", $data);
			$result = json_decode($result, true); 

			if($result['intReturnCode'] == 1) {

				$this->db->set('status_id', 9); 
				$this->db->where('id', $id);  
				$this->db->update('orders');

				$this->session->set_flashdata('response_status', 'info');
				$this->session->set_flashdata('message', "DomainsCoZa: ". $result['strMessage']);			
				redirect($_SERVER['HTTP_REFERER']);		 
			} 

			else {
				$this->session->set_flashdata('response_status', 'warning');
				$this->session->set_flashdata('message', "DomainsCoZa: ". $result['strMessage']);			
				redirect($_SERVER['HTTP_REFERER']);	
			}


		}




		public function unsuspend_domain($id) 
		{

			$order = Order::get_order($id);
			$client = Client::view_by_id($order->client_id);		  
			$tld = explode('.', $order->domain, 2); 
		
			$data = array(); 
			$data["key"] = $this->key;
			$data["sld"] = $tld[0];
			$data["tld"] = $tld[1]; 

			$result = $this->curl_download($this->host . "unsuspend", $data);
			$result = json_decode($result, true);
			if($result['intReturnCode'] == 1) {

				$this->db->set('status_id', 6); 
				$this->db->where('id', $id);  
				$this->db->update('orders');

				$this->session->set_flashdata('response_status', 'info');
				$this->session->set_flashdata('message', "DomainsCoZa: ". $result['strMessage']);			
				redirect($_SERVER['HTTP_REFERER']);		 
			} 

			else {
				$this->session->set_flashdata('response_status', 'warning');
				$this->session->set_flashdata('message', "DomainsCoZa: ". $result['strMessage']);			
				redirect($_SERVER['HTTP_REFERER']);	
			}	 
		}



	public function update_nameservers($id)
	{
		$order = Order::get_order($id);
		$client = Client::view_by_id($order->client_id);		  
		$tld = explode('.', $order->domain, 2);

		$data = array(); 
		$data["key"] = $this->key;
		$data["sld"] = $tld[0];
		$data["tld"] = $tld[1]; 

		if($this->input->post('nameserver_1') != '') {
			$data["ns1"] = $this->input->post('nameserver_1');
		}
		if($this->input->post('nameserver_2') != '') {
			$data["ns2"] = $this->input->post('nameserver_2');
		}
		if($this->input->post('nameserver_3') != '') {
			$data["ns3"] = $this->input->post('nameserver_3');
		}
		if($this->input->post('nameserver_4') != '') {
			$data["ns4"] = $this->input->post('nameserver_4');
		}	 	 
		
		$result = $this->curl_download($this->host . "nsUpdate", $data);
		$result = json_decode($result, true); 

		if($result['intReturnCode'] == 1) {
			$nameservers = $this->input->post('nameserver_1', true).",".$this->input->post('nameserver_2', true);
			if($this->input->post('nameserver_3', true) != '') : $nameservers .= ",".$this->input->post('nameserver_3', true); endif;
			if($this->input->post('nameserver_4', true) != '') : $nameservers .= ",".$this->input->post('nameserver_4', true); endif;
			$this->db->set('nameservers', $nameservers); 
			$this->db->where('id', $id);  
			$this->db->update('orders');

			$this->session->set_flashdata('response_status', 'success');
			$this->session->set_flashdata('message', "DomainsCoZa: ". $result['strMessage']);			
			redirect($_SERVER['HTTP_REFERER']);	
		}
		
		else {
			$this->session->set_flashdata('response_status', 'warning');
			$this->session->set_flashdata('message', "DomainsCoZa: ". $result['strMessage']);			
			redirect($_SERVER['HTTP_REFERER']);	
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
        $time = strtotime(date('Y-m-d', $domain->intCrDate));
		$created = date('Y-m-d', $domain->intCrDate); 
		$expires = date('Y-m-d', $domain->intExDate);
		
		$nameservers = "";

		foreach($domain->arrNameservers as $ns) {
			if($ns != '') {
				$nameservers .= $ns .",";
			}
		}

        $order = array(
            'client_id' 	=> $co_id,
			'invoice_id'    => 0,
			'date'              => date('Y-m-d H:i:s'),
            'nameservers'	=> rtrim($nameservers, ','),
            'item'		    => $item_id,
            'domain'        => $domain->strDomainName,
            'item_parent'   => $item->item_id,
            'type'		    => 'domain_only',
            'process_id'    => $time,
            'order_id'      => $time,
            'fee'           => 0,
            'processed'     => $created, 
			'renewal_date'  => $expires,
			'registrar' 	=> 'domainscoza',
			'status_id'     => 6, 
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
				$accounts = $this->get_accounts();
				$domains = array();
		
					foreach($accounts as $account) {
						foreach($list as $key => $li) { 
							if($account->Domain == str_replace("_",".",$key)) {

								$tld = explode('.', $account->Domain, 2);
								$data = array(); 
								$data["key"] = $this->key;
								$data["sld"] = $tld[0];
								$data["tld"] = $tld[1]; 
								$result = $this->curl_download($this->host . "info", $data);
								$result = json_decode($result, true);
						
								if($result['intReturnCode'] == 1) { 
									$domains[] = (object) $result; 
								}								
							}					
						}			
					}


				foreach($domains as $domain) {
					$tld = explode('.', $domain->strDomainName, 2);
					$ext = $tld[1]; 
					$item = $this->db->where('item_name', $ext)->join('item_pricing', 'item_pricing.item_id = items_saved.item_id')->get('items_saved')->row();
					$client = $this->db->where('company_email', $domain->arrRegistrant['strContactEmail'])->get('companies')->row();
					
					if(is_object($item)) {
						if(is_object($client)) {
	
							if($this->db->where('domain', $domain->strDomainName)->where('(type = "domain" OR type = "domain_only")')->get('orders')->num_rows() == 0) { 							
								if($this->create_order($item, $client->co_id, $domain)) {
									$count++;
								}
							}					   
						}
						else
						{
							$username = explode('@', $domain->arrRegistrant['strContactEmail'])[0];
                            $email = $domain->arrRegistrant['strContactEmail']; 
                            $password = $domain->arrRegistrant['strContactEmail']; 
                            
                            $hasher = new PasswordHash(
                                $this->config->item('phpass_hash_strength', 'tank_auth'),
								$this->config->item('phpass_hash_portable', 'tank_auth')
							);
							
							$hashed_password = $hasher->HashPassword($password);
                                        
                            if (!is_username_available($username)) {    
                                $username = explode('.', $domain->strDomainName, 2)[0]; 
                            }                             
                                                           
                                $data = array(
                                    'username'	=> $username, 
                                    'password'  => $hashed_password,
                                    'email'		=> $email,
                                    'role_id'	=> 2 
                                );
            
                                $user_id = App::save_data('users', $data); 
                                
								$client = array(   
									'company_name'          => $domain->arrRegistrant['strContactName'],
									'company_email'         => $domain->arrRegistrant['strContactEmail'],                       
									'company_ref'			=> $this->applib->generate_string(), 
									'language' 				=> config_item('default_language'),
									'currency' 				=> config_item('default_currency'),
									'primary_contact'       => $user_id,
									'individual' 			=> 0, 
									'company_address' 		=> trim($domain->arrRegistrant['strContactAddress'][0] . " " . $domain->arrRegistrant['strContactAddress'][1] . " " . $domain->arrRegistrant['strContactAddress'][2]),                         
									'company_phone'		  	=> explode('.', $domain->arrRegistrant['strContactNumber'], 2)[1],
									'city'				  	=> $domain->arrRegistrant['strContactCity'],
									'state'			      	=> $domain->arrRegistrant['strContactProvince'],
									'zip'				  	=> $domain->arrRegistrant['strContactPostalCode'],
									'country'			  	=> $domain->arrRegistrant['strContactCountry']
									); 

                                if($co_id = App::save_data('companies', $client)) {
                                    
                                    $profile = array(
                                        'user_id'           => $user_id,
                                        'company'	        => $co_id,
                                        'fullname'	        => $domain->arrRegistrant['strContactName'],
                                        'phone'		        => explode('.', $domain->arrRegistrant['strContactNumber'], 2)[1],
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
			$data['page'] = 'DomainsCoZa';	
			$data['datatables'] = TRUE; 
			$data['domains'] = $this->get_accounts();
			$this->template
			->set_layout('users')
			->build('import',isset($data) ? $data : NULL); 
		}
	}


	
	private function get_accounts()
	{
		$data = array(); 		
		$data["key"] = $this->key; 
		$result = $this->curl_download($this->host . "domainList", $data);
		$result = json_decode($result, true);

		if($result['intReturnCode'] == 1) { 
			$_domain = array();
			$domain_list = array();
		
			foreach($result['arrDomains'] as $domain) {
				$_domain['Domain'] = $domain['strDomainName'];	
				$_domain['Created'] = date('Y-m-d', $domain['createdDate']);
				$_domain['Expires'] = date('Y-m-d', $domain['expiryDate']);
				$_domain['EmailAddress'] = $domain['contactName'];	

				if($this->db->where('domain', $domain['strDomainName'])->where('(type = "domain" OR type = "domain_only")')->get('orders')->num_rows() == 0) {
					$domain_list[] = (object) $_domain;
				}
			}
			
			return $domain_list;		 
		} 

		else {
			$this->session->set_flashdata('response_status', 'warning');
			$this->session->set_flashdata('message', "DomainsCoZa: ". $result['strMessage']);			
			redirect($_SERVER['HTTP_REFERER']);	
		} 
	}

  

	public function check_balance () 
		{		
			$data = array(); 
			$data["key"] = $this->key;
			$result = $this->curl_download($this->host . "domainTotals", $data);
			$result = json_decode($result, true);
			
			$data = array();
			if($result['intReturnCode'] == 1) {
				$data['response'] = $result['objReseller']['balance']." ZAR";
			}
			else {
				$data['response'] = $result['strMessage'];
			}

			return $data;
		}



   
	
	private function curl_download($Url, $fields = null) {   
			 $fields_string = null;    // is cURL installed yet?    
			 if (!function_exists('curl_init')) {
				 die('Sorry cURL is not installed!');    
			}// create a new cURL resource handle    
			$ch = curl_init();    if (!empty($fields)) {        
				//url-ify the data for the POST
				foreach($fields as $key=>$value) {
					if (is_array($value)) {                
					foreach($value as $value2) {                    
						if (!is_null($value2)) {
							$fields_string .= $key.'='.urlencode($value2).'&';}                
					}    } 
					else {                
						if (!is_null($value)) {$fields_string .= $key.'='.urlencode($value).'&';}            
					}        
				}        rtrim($fields_string,'&'); //set the number of POST vars, POST data        
				curl_setopt($ch,CURLOPT_POST,1);        
				curl_setopt($ch,CURLOPT_POSTFIELDS,$fields_string);    
			}    // Set URL to download    
				curl_setopt($ch, CURLOPT_URL, $Url);    // stop the verification of certificate    
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);     
				// User agent    
				curl_setopt($ch, CURLOPT_USERAGENT, "MozillaXYZ/2.0");    // Include header in result? (0 = yes, 1 = no)   
				 curl_setopt($ch, CURLOPT_HEADER, 0);    // Should cURL return or print out the data? (true = return, false = print)   
				 curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

				 // Timeout in seconds    
				 curl_setopt($ch, CURLOPT_TIMEOUT, 30);    // Download the given URL, and return output    
				 $output = curl_exec($ch);    // Close the cURL resource, and free system resources    
				 curl_close($ch);    
				 return $output;
		}


		public function admin_options()
		{
			return '<a class="btn btn-primary btn-sm" href="'.base_url().'registrars/config/domainscoza" data-toggle="ajaxModal" title="'.lang('settings').'"><i class="fa fa-pencil"></i> '.lang('settings').'</a>                               
	 				<a class="btn btn-warning btn-sm" href="'.base_url().'domainscoza/import_domains/"  ><i class="fa fa-download"></i> '.lang('import_accounts').'</a>
					<a class="btn btn-success btn-sm" href="'.base_url().'registrars/check_balance/domainscoza" data-toggle="ajaxModal" title="'.lang('check_balance').'" ><i class="fa fa-info-circle"></i> '.lang('check_balance').'</a>';
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

////end 