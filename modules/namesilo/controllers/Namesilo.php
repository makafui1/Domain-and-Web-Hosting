<?php
/* Module Name: Namesilo
 * Module URI: http://www.hostingbilling.net
 * Version: 1.0
 * Category: Domain Registrars
 * Description: Namesilo Registrar Integration.
 * Author: Hosting Billing
 * Author URI: www.hostingbilling.net
 */

class Namesilo extends Hosting_Billing
{  
		public $api_url;
		public $api_key;
		public $debug;
		public $sandbox;
		private $msg = '';

		public function __construct(){

			$this->config = get_settings('namesilo');
			if(!empty($this->config))
			{
				$this->api_url = ($this->config['mode'] == 'test') ? 'https://sandbox.namesilo.com/api/' : 'https://namesilo.com/api/';
				$this->api_key = $this->config['apikey']; 
				$this->debug = $this->config['debug'];
				$this->sandbox = ($this->config['mode'] == 'test') ? true : false;
			}			
		}


		public function namesilo_config ($values = null)
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
					'label' => lang('debug'),
					'id' => 'debug',
					'type' => 'dropdown',
					'options' => array(
							'true' => lang('on'),
							'false' => lang('off')
					),
					'value' => isset($values['mode']) ? $values['mode'] : 'off'
				),
				array(
					'label' => lang('api_key'), 
					'id' => 'apikey',
					'value' => isset($values) ? $values['apikey'] : ''
				) 
			); 
			
			return $config;        
		}


		
		public function register_domain($id, $nameservers){

			$order = Order::get_order($id);
			$client = Client::view_by_id($order->client_id);			
			$contact_id = $this->create_contact($client);
			if(!$contact_id || !is_numeric($contact_id))
				return $this->msg;
			$result = $this->request('registerDomain',[
				['domain',$order->domain],
				['years',$order->years],
				['private',1],
				['auto_renew',0],
				['contact_id',$contact_id],
			]); 

			return $this->msg;
		}




		public function transfer_domain($id, $nameservers){

			$order = Order::get_order($id);
			$client = Client::view_by_id($order->client_id); 			
			$contact_id = $this->create_contact($client);

			if(!$contact_id || !is_numeric($contact_id))
				return $this->msg;
			$result = $this->request('transferDomain',[
				['domain',$order->domain], 
				['auth',json_encode($order->authcode)],
				['contact_id',$contact_id],
			]);

			return $this->msg;
		}



		public function renew_domain($id, $nameservers){

			$order = Order::get_order($id); 
			$result = $this->request('renewDomain',[
				['domain',$order->domain],  
				['years',$order->years],
			]);

			return $this->msg;			 
		} 


		public function create_contact($client){

			$name = explode(' ', User::displayName($client->primary_contact));
			$first_name = $name[0];
			$last_name = (isset($name[1])) ? $name[1] : $name[0];
			$contacts = $this->get_contacts();
			foreach($contacts as $contact)
			{
				if($contact['email'] == $client->company_email || 
				($contact['last_name'] == $last_name &&   
				 $contact['phone'] == str_replace(' ', '', $client->company_phone)))
				{
					return $contact['contact_id'];
				}
			}

			$code = App::country_code($client->country);
	
			$result = $this->request('contactAdd',[
				['fn',$first_name], 
				['ln',$last_name],
				['ad',$client->company_address],
				['cy',$client->city],
				['st',($code == 'US' || $code == 'CA') ?  App::state_code($client->state) : $client->state], 
				['zp',$client->zip],
				['ct',$code],  
				['em',$client->company_email],
				['ph',str_replace(' ', '', $client->company_phone)],
			]);

			return $this->msg; 
		}



		public function update_nameservers($id){

			$order = Order::get_order($id); 
			
			$result = $this->request('changeNameServers',[
				['domain',$order->domain],
				['ns1',$this->input->post('nameserver_1', true)],
				['ns2',$this->input->post('nameserver_2', true)],
				['ns3',$this->input->post('nameserver_3', true)],
				['ns4',$this->input->post('nameserver_4', true)]
			]);
 	
			if($this->request_successp($result))
			{
				$nameservers = $this->input->post('nameserver_1', true).",".$this->input->post('nameserver_2', true);
				if($this->input->post('nameserver_3', true) != '') : $nameservers .= ",".$this->input->post('nameserver_3', true); endif;
				if($this->input->post('nameserver_4', true) != '') : $nameservers .= ",".$this->input->post('nameserver_4', true); endif;
				$this->db->set('nameservers', $nameservers); 
				$this->db->where('id', $id);  
				$this->db->update('orders');

				$this->session->set_flashdata('response_status', 'success');
				$this->session->set_flashdata('message', $this->msg);			
				redirect($_SERVER['HTTP_REFERER']);	
			}			
			else 
			{
				$this->session->set_flashdata('response_status', 'error');
				$this->session->set_flashdata('message', $this->msg);			
				redirect($_SERVER['HTTP_REFERER']);	
			}

		}



		public function add_dns_record($domain,$type,$host,$value,$distance='',$ttl=''){
			$result = $this->request('dnsAddRecord',[
				['domain',$domain],
				['rrtype',$type],
				['rrhost',$host],
				['rrvalue',$value],
				['rrdistance',$distance],
				['rrttl',$ttl],
			]);
			if($this->request_successp($result))
				return true;
			else
				return false;
		}



		public function delete_dns_record($domain,$record_id){
			$result = $this->request('dnsDeleteRecord',[
				['domain',$domain],
				['rrid',$record_id],
			]);
			if($this->request_successp($result))
				return true;
			else
				return false;
		}



		public function get_dns_records($domain){
			$result = $this->request('dnsListRecords',[
				['domain',$domain],
			]);
			if($this->request_successp($result)){
				if(!isset($result['reply']['resource_record'][0])){
					$temp_arr = [];
					$temp_arr[0] = $result['reply']['resource_record'];
					return $temp_arr;
				}else{
					return $result['reply']['resource_record'];
				}
			}else{
				return false;
			}
		}



		public function check_domain($domain){
 			$result = $this->request('checkRegisterAvailability',[['domains',$domain]]);
			if($this->request_successp($result) && isset($result['reply']['available']))
				return 1;
			if($this->request_successp($result) && isset($result['reply']['invalid']))
				return 2;
			if($this->request_successp($result) && isset($result['reply']['unavailable']))
				return 0;
			return false;
		}

 
		public function get_contacts(){
			$result = $this->request('contactList');
			if(!$this->request_successp($result))
				return false;
			return $result['reply']['contact'];
		}
 


		public function list_domains(){
			$result = $this->request('listDomains');
			if(!$this->request_successp($result))
				return false;
			return $result['reply']['domains']['domain'];
		}

 

		public function check_balance(){
			$result = $this->request('getAccountBalance');
			if($this->request_successp($result))
				return $result['reply']['balance'];
			else
				return false;
		}


		// main public functions
		private function request($command,$options=''){
			$created_options = '';
			if(!empty($options)){
				foreach($options as $pair){
					$created_options .= '&';
					$created_options .= $pair[0];
					$created_options .= '=';
					$created_options .= urlencode($pair[1]);
				}
			}

			$command_ready = $this->api_url . $command . '?version=1&type=xml&key=' . $this->api_key . $created_options;
			$str = file_get_contents($command_ready);
			$result =  $this->xml_to_arr($str);
			if($this->debug){
				echo '<pre>';
				print_r($result);
				echo '</pre>';
			}
			 
			$this->msg = (isset($result['reply']['message'])) ? $result['reply']['message'] : $result['reply']['detail'];			 
			return $result;
		}


		private function xml_to_arr($str){
			$str = trim($str);
			$xml = simplexml_load_string($str);
			$json = json_encode($xml);
			$array = json_decode($json,TRUE);
			return $array;
		}


		private function request_successp($arr){
			if($arr['reply']['code'] == 300 ||
				$arr['reply']['code'] == 301 ||
				$arr['reply']['code'] == 302)
				return true;
			else
				return false;
		}


		public function admin_options()
		{
			return '<a class="btn btn-primary btn-sm" href="'.base_url().'registrars/config/namesilo" data-toggle="ajaxModal" title="'.lang('settings').'"><i class="fa fa-pencil"></i> '.lang('settings').'</a>                               
	 				<a class="btn btn-success btn-sm" href="'.base_url().'registrars/check_balance/namesilo" data-toggle="ajaxModal" title="'.lang('check_balance').'" ><i class="fa fa-info-circle"></i> '.lang('check_balance').'</a>';
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

 