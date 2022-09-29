<?php
/* Module Name: aamarPay 
 * Module URI: http://www.hostingbilling.net
 * Version: 1.0
 * Category: Payment Gateways
 * Description: aamarPay Payment Gateway.
 * Author: Hosting Billing
 * Author URI: www.hostingbilling.net
 */

class Aamarpay  extends Hosting_Billing
{          
 
    
    private $url;
    private $store_id;
    private $signature_key;
    

	function __construct()
	{
		parent::__construct();		
        User::logged_in();        
        $this->config = get_settings('aamarpay');
        if(!empty($this->config))
        {             
            $this->url = $this->config['mode'] == 'test' ? "https://sandbox.aamarpay.com/index.php" : "https://secure.aamarpay.com/request.php";
            $this->store_id = $this->config['store_id'];
            $this->signature_key = $this->config['signature_key'];
       }			
    }

    public function aamarpay_config ($values = null)
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
                'label' => lang('store_id'), 
                'id' => 'store_id',
                'value' => isset($values) ? $values['store_id'] : ''
            ), 
            array(
                'label' => lang('signature_key'), 
                'id' => 'signature_key',
                'value' => isset($values) ? $values['signature_key'] : ''
            ) 
        ); 
        
        return $config;        
    }

    
	function index()
	{ 
        redirect('invoices');
    }    
    

    function cancel()
	{
        $this->session->set_flashdata('response_status', 'error');
        $this->session->set_flashdata('message', lang('aamarpay_cancelled'));
        redirect('invoices');
	}   
    


	function pay($invoice = NULL)
	{		
        $inv = Invoice::view_by_id($invoice); 
        $company = Client::view_by_id($inv->client);   
        $invoice_due = Invoice::get_invoice_due_amount($invoice); 
      
        $success_url  = base_url().'aamarpay/process';
        $failed_url  = base_url().'aamarpay/process';
        $cancel_url  =  base_url().'aamarpay/cancel';
 
        $fields = array(
                            'store_id' => $this->store_id, 
                            'amount' => $invoice_due,
                            'currency' => $inv->currency,
                            'tran_id' => $inv->reference_no,
                            'cus_name' => $company->company_name,
                            'cus_email' => $company->company_email,
                            'cus_add1' => $company->company_address,
                            'cus_add2' => $company->company_address_two,
                            'cus_city' => $company->city,
                            'cus_state' => $company->state,
                            'cus_postcode' => $company->zip,
                            'cus_country' => $company->country,
                            'cus_phone' => $company->company_phone,
                            'ship_name' => $company->company_name,
                            'ship_add1' => base_url(),
                            'desc' => $inv->reference_no,
                            'success_url' => $success_url,
                            'fail_url' => $failed_url,
                            'cancel_url' => $cancel_url,
                            'opt_a' => $invoice_due,
                            'opt_b' => $inv->currency,
                            'opt_c' => $invoice,
                            'opt_d' => '',
                            'signature_key' => $this->signature_key
                    );
   
        
        $domain = $_SERVER["SERVER_NAME"];
        $ip = $_SERVER["SERVER_ADDR"];

        $fields_string = "";
        $code = "";

        foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
        rtrim($fields_string, '&'); 

        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_HTTPHEADER, array("REMOTE_ADDR: $ip", "HTTP_X_FORWARDED_FOR: $ip"));
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_URL,$this->url);
        curl_setopt($ch, CURLOPT_REFERER, $domain);
        curl_setopt($ch, CURLOPT_INTERFACE, $ip);
        curl_setopt($ch, CURLOPT_POST, count($fields));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
        $result = curl_exec($ch);     
        $url_decode = json_decode($result);

        $webaddr = $this->url;
        //close connection
        curl_close($ch);
        $code .= '<form action="'.$webaddr.''.$url_decode.'" method="post">'; 
        $code .='<input type="hidden" name="store_id" value="'.$this->store_id.'">';
        $code .='<input type="hidden" name="tran_id" value="'.$inv->reference_no.'">';
        $code .='<input type="hidden" name="amount" value="'.$invoice_due.'" >';
        $code .='<input type="hidden" name="success_url"  value="'.base_url().'aamarpay/process" />';
        $code .= '<input type="hidden" name="fail_url" value="'.base_url().'aamarpay/process" />';
        $code .= '<input type="hidden" name="cancel_url" value="'.base_url().'aamarpay/cancel" />';
        $code .='<input type="hidden" name="currency" value="'.$inv->currency.'" >';
        $code .='<input type="hidden" name="cus_name" value="'.$company->company_name.'">';
        $code .='<input type="hidden" name="cus_add1" value="'.$company->company_address.'" >';
        $code .= '<input type="hidden" name="cus_add2" value="'.$company->company_address_two.'" >';
        $code .= '<input type="hidden" name="cus_city" value="'.$company->city.'" >';
        $code .= '<input type="hidden" name="cus_state" value="'.$company->state.'">';
        $code .= '<input type="hidden" name="cus_postcode" value="'.$company->zip.'">';
        $code .= '<input type="hidden" name="cus_country" value="'.$company->country.'">';
        $code .= '<input type="hidden" name="cus_phone" value="'.$company->company_phone.'" >';
        $code .= '<input type="hidden" name="cus_email" value="'.$company->company_email.'" >';
        $code .= '<input type="hidden" name="ship_name" value="'.$company->company_name.'" >';
        $code .= '<input type="hidden" name="ship_add1" value="'.base_url().'" >'; 
        $code .= '<input type="hidden" name="signature_key" value="'.$this->signature_key.'">';
        $code .= '<input type="hidden" name="opt_a" value="'.$invoice_due.'">'; 
        $code .= '<input type="hidden" name="opt_b" value="'.$inv->currency.'">';
        $code .= '<input type="hidden" name="opt_c" value="'.$invoice.'">';
        $code .= '<input type="hidden" name="desc" value="'.$inv->reference_no.'">';	
        $code .= '<input type="submit" class="btn btn-success" value="Pay Now '.$inv->currency.''.$invoice_due.'" /></form>';
    
        $data['form'] = $code;

        
        $this->load->module('layouts');
        $this->load->library('template'); 
        $this->template->title(lang('payment').' - '.config_item('company_name'));
        $data['page'] = lang('aamarpay_payment');			
        $this->template
        ->set_layout('users')
        ->build('form', $data);            
    }    


    


      function process() {
        $status = $_REQUEST["pay_status"];
        $invoiceid = $_REQUEST["mer_txnid"];
        $transid = $_REQUEST["pg_txnid"];        
        
        $amount = $_REQUEST["amount"];
        
        $amount_rec = $_REQUEST["store_amount"];
        $fee = $_REQUEST["pg_service_charge_bdt"];
        $reason = $_REQUEST["reason"];
   
        if($_REQUEST["opt_b"] == 'USD'){
            $amount = $_REQUEST["opt_a"];
        }else{
           $amount = $_REQUEST["amount"]; 
        }

        
        if($_REQUEST == 'Successful'){ 
                $invoice = $_REQUEST["opt_c"];
                $inv = Invoice::view_by_id($invoice); 
                $client = Client::view_by_id($inv->client); 
                $paid = Applib::convert_currency($inv->currency, $amount);
                $paid_amount = Applib::format_deci($paid);  

                $this->load->helper('string');
                $data = array(
                            'invoice' => $invoice,
                            'paid_by' => $inv->client,
                            'currency' => strtoupper($inv->currency),
                            'payer_email' => $client->company_email,
                            'payment_method' => '1',
                            'notes' => 'AarmaPay: '.$input->data->id,
                            'amount' => $paid,
                            'trans_id' => random_string('nozero', 6),
                            'month_paid' => date('m'),
                            'year_paid' => date('Y'),
                            'payment_date' => date('Y-m-d')
                        );
    
                // Store the payment in the database.
                if ($payment_id = App::save_data('payments', $data)) {
                    $cur_i = App::currencies(strtoupper($inv->currency)); 
                    $data = array(
                    'module' => 'invoices',
                    'module_field_id' => $invoice,
                    'user' => $client->primary_contact,
                    'activity' => 'activity_payment_of',
                    'icon' => 'fa-usd',
                    'value1' => $inv->currency.''.$paid,
                    'value2' => $inv->reference_no
                    );
    
                    App::Log($data);
    
                    $this->_send_payment_email($invoice, $amount); // Send email to client
    
                    if(config_item('notify_payment_received') == 'TRUE'){
                        $this->_notify_admin($invoice, $paid, $cur_i->code); // Send email to admin
                    }
        
        
                    $invoice_due = Invoice::get_invoice_due_amount($invoice);
                    if($invoice_due <= 0) {
                        Invoice::update($invoice, array('status'=>'Paid'));
                        modules::run('orders/process', $invoice);
                        }

                    $this->session->set_flashdata('response_status', 'success');
                    $this->session->set_flashdata('message', lang('payment_added_successfully'));
                    redirect('invoices/view/'. $invoice);
                }    
            }
                
                
            else
            {
                $data['response'] = lang('failed_verification');
            } 

            $this->template->title(lang('payment').' - '.config_item('company_name'));
            $data['page'] = 'Paystack';			
            $this->template
            ->set_layout('users')
            ->build('verify', $data);
    
        }
    
        
    
        function _send_payment_email($invoice_id,$paid_amount){

            $message = App::email_template('payment_email','template_body');
            $subject = App::email_template('payment_email','subject');
            $signature = App::email_template('email_signature','template_body');


            $info = Invoice::view_by_id($invoice_id);
            $cur = App::currencies($info->currency);

            $logo_link = create_email_logo();

            $logo = str_replace("{INVOICE_LOGO}",$logo_link,$message);

            $invoice_ref = str_replace("{REF}",$info->reference_no,$logo);

            $invoice_currency = str_replace("{INVOICE_CURRENCY}",$cur->symbol,$invoice_ref);
            $amount = str_replace("{PAID_AMOUNT}",$paid_amount,$invoice_currency);
            $EmailSignature = str_replace("{SIGNATURE}",$signature,$amount);
            $message = str_replace("{SITE_NAME}",config_item('company_name'),$EmailSignature);

            $params['recipient'] = Client::view_by_id($info->client)->company_email;

            $params['subject'] = '['.config_item('company_name').'] '.$subject;
            $params['message'] = $message;
            $params['attached_file'] = '';

            modules::run('fomailer/send_email',$params);
    }


    function _notify_admin($invoice,$amount,$cur)
    {
            $info = Invoice::view_by_id($invoice);

            foreach (User::admin_list() as $key => $user) {
                $data = array(
                                'email'		    => $user->email,
                                'invoice_ref'   => $info->reference_no,
                                'amount'		=> $amount,
                                'currency'		=> $cur,
                                'invoice_id'	=> $invoice,
                                'client'        => Client::view_by_id($info->client)->company_name
                            );

                $email_msg = $this->load->view('new_payment',$data,TRUE);

                $params = array(
                                'subject' 		=> '['.config_item('company_name').'] Payment Confirmation',
                                'recipient' 	=> $user->email,
                                'message'		=> $email_msg,
                                'attached_file'	=> ''
                                );

                modules::run('fomailer/send_email',$params);
            }

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