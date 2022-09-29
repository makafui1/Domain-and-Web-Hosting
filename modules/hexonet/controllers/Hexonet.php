<?php
/* Module Name: Hexonet
 * Module URI: http://www.hostingbilling.net
 * Version: 1.0
 * Category: Domain Registrars
 * Description: Hexonet Registrar Integration.
 * Author: Hosting Billing
 * Author URI: www.hostingbilling.net
 */

class Hexonet extends Hosting_Billing
{          
               
			
		private $url;
		private $username;
		private $password;
		private $entity;
		

		public function __construct()
        {
			parent::__construct();  
			$this->url = "https://coreapi.1api.net/api/call.cgi";  
			$this->config = get_settings('hexonet');
			if(!empty($this->config))
			{
				$this->username = $this->config['user'];
				$this->password = $this->config['pass'];
				$this->entity = $this->config['entity'];
			}			
		}

		
		public function check_domain($sld, $tld)
		{			 
			$transfer_domain_command = array( "COMMAND" => "checkDomain", "DOMAIN" => $sld . "." . $tld);
				$response = $this->call($transfer_domain_command);
  
			if(isset($response['DESCRIPTION']) && $response['DESCRIPTION'] == "Available") {
			 	return 1; 
			}

			else {
				return 0;
			}
		}



		public function hexonet_config ($values = null)
		{
			$config = array( 
				array(
					'label' => lang('username'), 
					'id' => 'user',
					'value' => isset($values) ? $values['user'] : ''
				),

				array(
					'label' => lang('password'), 
					'id' => 'pass',
					'value' => isset($values) ? $values['pass'] : '',
					'type' => 'password'
				),
								
				array(
					'label' => lang('entity'), 
					'id' => 'entity',
					'value' => isset($values) ? $values['entity'] : ''
				)
				
			); 
			
			return $config;        
		}


 

		public function register_domain ($id, $nameservers)
		{ 
			$order = Order::get_order($id);
			$client = Client::view_by_id($order->client_id);		  
			$tld = explode('.', $order->domain, 2);	
			$name = explode(' ', User::displayName($client->primary_contact));
			$first_name = $name[0];
			$last_name = (isset($name[1])) ? $name[1] : $name[0];

			$create_domain_command = array( 
				"COMMAND" => "AddDomain",  
				"DOMAIN" => $tld[0] . "." . $tld[1], 
				"PERIOD" => $order->years, 
				"OWNERCONTACT0" => array( 
					"FIRSTNAME" => $first_name, 
					"LASTNAME" => $last_name, 
					"ORGANIZATION" => $client->company_name, 
					"STREET" => $client->company_address, 
					"CITY" => $client->city, 
					"STATE" => $client->state, 
					"ZIP" => $client->zip, 
					"COUNTRY" => App::country_code($client->country), 
					"PHONE" => $client->company_phone, 
					"FAX" => "", 
					"EMAIL" => $client->company_email),  
					'ADMINCONTACT0' => $this->admin_contact(), 
					'TECHCONTACT0' => $this->admin_contact(), 
					'BILLINGCONTACT0' => $this->admin_contact(),
					"NAMESERVER" => $nameservers);
			$response = $this->call($create_domain_command);

			if(isset($response["DESCRIPTION"])) 
			{
				return $response["DESCRIPTION"];
			}

			else
			{
				return $response;
			}

		}
 

		public function transfer_domain($id, $nameservers)
			{
				$order = Order::get_order($id);
				$tld = explode('.', $order->domain, 2);	
				$transfer_domain_command = array( "COMMAND" => "TransferDomain", "DOMAIN" => $tld[0] . "." . $tld[1], "AUTH" => $order->authcode, "ACTION" => "request" );
				$response = $this->call($transfer_domain_command);

				if( $response["CODE"] == 200 ) 
				{
					$this->update_contacts($id);
					return $response["DESCRIPTION"];
				}
	
				else
				{
					if(isset($response["DESCRIPTION"]))
					{
						return $response["DESCRIPTION"];
					}

					return $response;
				}
			}


   
 
			public function renew_domain($id, $nameservers)
			{
				$order = Order::get_order($id); 		  
				$tld = explode('.', $order->domain, 2);	

				$renew_domain_command = array( "COMMAND" => "RenewDomain", "DOMAIN" => $tld[0] . "." . $tld[1], "EXPIRATION" => date("Y") + $order->years, "PERIOD" => $order->years);
				$response = $this->call($renew_domain_command);
				if(isset($response["DESCRIPTION"])) 
				{
					return $response["DESCRIPTION"];
				}
				else
				{
					return $response;
				}

			}




			public function update_nameservers( $id )
			{
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
				$tld = explode('.', $order->domain, 2);	
				$name = explode(' ', User::displayName($client->primary_contact));
				$first_name = $name[0];
				$last_name = (isset($name[1])) ? $name[1] : $name[0];				
				$contact_domain_command = array( "COMMAND" => "ModifyDomain", "DOMAIN" => $tld[0] . "." . $tld[1], "NAMESERVER" => $nameservers);			 
				$response = $this->call($contact_domain_command);

				if( $response["CODE"] == 200 ) 
				{
					$nameservers = $this->input->post('nameserver_1', true).",".$this->input->post('nameserver_2', true);
					if($this->input->post('nameserver_3', true) != '') : $nameservers .= ",".$this->input->post('nameserver_3', true); endif;
					if($this->input->post('nameserver_4', true) != '') : $nameservers .= ",".$this->input->post('nameserver_4', true); endif;
					$this->db->set('nameservers', $nameservers); 
					$this->db->where('id', $id);  
					$this->db->update('orders');
		
					$this->session->set_flashdata('response_status', 'success');
					$this->session->set_flashdata('message', $response["DESCRIPTION"]);			
					redirect($_SERVER['HTTP_REFERER']);	
				}
				
				elseif (isset($response["DESCRIPTION"])) 
				{
					$this->session->set_flashdata('response_status', 'error');
					$this->session->set_flashdata('message', $response["DESCRIPTION"]);			
					redirect($_SERVER['HTTP_REFERER']);	
				}

				else {
					$this->session->set_flashdata('response_status', 'success');
					$this->session->set_flashdata('message', "" .$response);			
					redirect($_SERVER['HTTP_REFERER']);	
				}
		
				 
			}

 

			public function update_contacts($id)
			{
				$order = Order::get_order($id);
				$client = Client::view_by_id($order->client_id);		  
				$tld = explode('.', $order->domain, 2);	
				$name = explode(' ', User::displayName($client->primary_contact));
				$first_name = $name[0];
				$last_name = (isset($name[1])) ? $name[1] : $name[0];				
				$contact_domain_command = array( "COMMAND" => "ModifyDomain", "DOMAIN" => $tld[0] . "." . $tld[1],
				"OWNERCONTACT0" => array( 
					"FIRSTNAME" => $first_name, 
					"LASTNAME" => $last_name, 
					"ORGANIZATION" => $client->company_name, 
					"STREET" => $client->company_address, 
					"CITY" => $client->city, 
					"STATE" => $client->state, 
					"ZIP" => $client->zip, 
					"COUNTRY" => App::country_code($client->country), 
					"PHONE" => $client->company_phone, 
					"FAX" => "", 
					"EMAIL" => $client->company_email),  
					'ADMINCONTACT0' => $this->admin_contact(), 
					'TECHCONTACT0' => $this->admin_contact(), 
					'BILLINGCONTACT0' => $this->admin_contact(),
					"NAMESERVER" => $nameservers);			 
				$response = $this->call($contact_domain_command);

				if(isset($response["DESCRIPTION"])) 
				{
					return $response["DESCRIPTION"];
				}

				else {
					return $response;
				}
			}

 

			public function admin_contact()
			{
				$details = array( 
					"FIRSTNAME" => config_item('domain_admin_firstname'), 
					"LASTNAME" => config_item('domain_admin_lastname'), 
					"ORGANIZATION" => config_item('domain_admin_company'), 
					"STREET" => config_item('domain_admin_address_1')." ".config_item('domain_admin_address_2'), 
					"CITY" => config_item('domain_admin_city'), 
					"STATE" => config_item('domain_admin_state'), 
					"ZIP" => config_item('domain_admin_zip'), 
					"COUNTRY" => App::country_code(config_item('domain_admin_country')), 
					"PHONE" => config_item('domain_admin_phone'), 
					"FAX" => "", 
					"EMAIL" => config_item('domain_admin_email')
				);
				
				return $details;				 
			}
 


			function call($command, $user = "", $config = "")
			{
				return $this->parse_response($this->call_raw($command, $user, $config));
			}


			function call_raw($command, $user = "", $config = "")
			{
				 
				$url = $this->url;
				$args = array(); 
				$args["s_login"] =  $this->username; 
				$args["s_pw"] = $this->password;
				$args["entity"] = $this->entity;				  
			 

				$args["s_command"] = $this->encode_command($command);
				$curl = curl_init();
				if( $curl === false ) 
				{
					return "[RESPONSE]\nCODE=423\nAPI access error: curl_init failed\nEOF\n";
				}

				$postfields = array(  );
				foreach( $args as $key => $value ) 
				{
					$postfields[] = urlencode($key) . "=" . urlencode($value);
				}
				$postfields = implode("&", $postfields);
				curl_setopt($curl, CURLOPT_URL, $url);
				
				curl_setopt($curl, CURLOPT_POSTFIELDS, $postfields);
				curl_setopt($curl, CURLOPT_HEADER, 0);
				curl_setopt($curl, CURLOPT_POST, 1);
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
				curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
				$response = curl_exec($curl); 
				return $response;
			}



			function call_retry($retries = 1, $command, $user = "", $config = "")
			{
				for( $i = 1; $i <= $retries; $i++ ) 
				{
					$response = parse_response(call_raw($command, $user, $config));
					if( substr($response["CODE"], 0, 1) != "4" ) 
					{
						return $response;
					}

				}
			}



			function encode_command($commandarray)
			{
				if( !is_array($commandarray) ) 
				{
					return $commandarray;
				}

				$command = "";
				foreach( $commandarray as $k => $v ) 
				{
					if( is_array($v) ) 
					{
						$v = $this->encode_command($v);
						$l = explode("\n", trim($v));
						foreach( $l as $line ) 
						{
							$command .= (string) $k . $line . "\n";
						}
					}
					else
					{
						$v = preg_replace("/\r|\n/", "", $v);
						$command .= (string) $k . "=" . $v . "\n";
					}

				}
				return $command;
			}



			function parse_response($response)
			{
				if( is_array($response) ) 
				{
					return $response;
				}

				if( !$response ) 
				{
					return array( "CODE" => "423", "DESCRIPTION" => "Empty response from API" );
				}

				$hash = array( "PROPERTY" => array(  ) );
				$rlist = explode("\n", $response);
				foreach( $rlist as $item ) 
				{
					if( preg_match("/^([^\\=]*[^\t\\= ])[\t ]*=[\t ]*(.*)\$/", $item, $m) ) 
					{
						list(, $attr, $value) = $m;
						$value = preg_replace("/[\t ]*\$/", "", $value);
						if( preg_match("/^property\\[([^\\]]*)\\]/i", $attr, $m) ) 
						{
							$prop = strtoupper($m[1]);
							$prop = preg_replace("/\\s/", "", $prop);
							if( in_array($prop, array_keys($hash["PROPERTY"])) ) 
							{
								array_push($hash["PROPERTY"][$prop], $value);
							}
							else
							{
								$hash["PROPERTY"][$prop] = array( $value );
							}

						}
						else
						{
							$hash[strtoupper($attr)] = $value;
						}

					}

				}
				return $hash;
			}

		public function admin_options()
		{
			return '<a class="btn btn-primary btn-sm" href="'.base_url().'registrars/config/hexonet" data-toggle="ajaxModal" title="'.lang('settings').'"><i class="fa fa-pencil"></i> '.lang('settings').'</a>';
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