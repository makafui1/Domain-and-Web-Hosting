<?php
/* Module Name: Checkout
 * Module URI: http://www.hostingbilling.net
 * Version: 1.0
 * Category: Payment Gateways
 * Description: 2Checkout Payment Gateway.
 * Author: Hosting Billing
 * Author URI: www.hostingbilling.net
 */

class Checkout extends Hosting_Billing
{      

private $seller_id;
private $publishable_key;
private $private_key;
private $sandbox;

function __construct()
	{
		parent::__construct();
		User::logged_in(); 
		$this->config = get_settings('checkout');
        if(!empty($this->config))
        {             
            $this->sandbox = $this->config['mode'] == 'test' ? true : false;
            $this->seller_id = $this->config['seller_id'];
            $this->publishable_key = $this->config['publishable_key'];
			$this->private_key = $this->config['private_key'];
       }			
    }


public function checkout_config ($values = null)
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
                'label' => lang('checkout_seller_id'), 
                'id' => 'seller_id',
                'value' => isset($values) ? $values['seller_id'] : ''
            ), 
            array(
                'label' => lang('checkout_publishable_key'), 
                'id' => 'publishable_key',
                'value' => isset($values) ? $values['publishable_key'] : ''
            ), 
            array(
                'label' => lang('checkout_private_key'), 
                'id' => 'private_key',
                'value' => isset($values) ? $values['private_key'] : ''
            ) 
        ); 
        
        return $config;        
    }


function pay($invoice = NULL)
	{

		$info = Invoice::view_by_id($invoice);

		$invoice_due = Invoice::get_invoice_due_amount($invoice);
		if ($invoice_due <= 0) $invoice_due = 0.00;

		$data['info'] = array('item_name'=> $info->reference_no,
							  'item_number' => $invoice,
							  'currency' => $info->currency,
							  'amount' => $invoice_due
							);
		$data['publishable_key'] = $this->publishable_key;
		$data['seller_id'] = $this->seller_id;
		$this->load->view('form', $data);
	}

function process()
	{

		if ($this->input->post()) {
			$errors = array();
			$invoice_id = $this->input->post('invoice_id');
			if (!isset($_POST['token'])) {
				$errors['token'] = 'The order cannot be processed. Please make sure you have JavaScript enabled and try again.';
			}
			// If no errors, process the order:
	if (empty($errors)) {
			require_once(APPPATH.'libraries/2checkout/Twocheckout.php');

			Twocheckout::privateKey($this->private_key);
			Twocheckout::sellerId($this->seller_id);
			Twocheckout::sandbox($this->sandbox);

			// Twocheckout::verifySSL(false);

			$info = Invoice::view_by_id($invoice_id); // Invoice Details
			$company = Client::view_by_id($info->client); // Get company details

	try {

    	$charge = Twocheckout_Charge::auth(array(
			        "merchantOrderId" => $info->inv_id,
			        "token"      => $_POST['token'],
			        "currency"   => $info->currency,
			        "total"      => $this->input->post('amount'),
			        "billingAddr" => array(
			            "name" => $company->company_name,
			            "addrLine1" => $company->company_address,
			            "city" => $company->city,
			            "state" => $company->state,
			            "zipCode" => $company->zip,
			            "country" => $company->country,
			            "email" => $company->company_email,
			            "phoneNumber" => $company->company_phone
			        )
			    ));

    	if ($charge['response']['responseCode'] == 'APPROVED') {
				$data = array(
				                     'invoice' => $charge['response']['merchantOrderId'],
				                     'paid_by' => $company->co_id,
				                     'payer_email' => $charge['response']['billingAddr']['email'],
				                     'payment_method' => '1',
				                     'currency' => $charge['response']['currencyCode'],
				                     'notes' => 'Paid by '.User::displayName(User::get_id()).' via 2checkout',
				                     'amount' => $charge['response']['total'],
				                     'trans_id' => $charge['response']['transactionId'],
				                     'month_paid' => date('m'),
									 'year_paid' => date('Y'),
									 'payment_date' => date('Y-m-d H:i:s')
				                     );
				// Store the order in the database.
				if ($payment_id = App::save_data('payments', $data)) {
                    $cur_i = App::currencies(strtoupper($charge['response']['currencyCode']));

                // Log activity
				$data = array(
					'module' => 'invoices',
					'module_field_id' => $invoice_id,
					'user' => User::get_id(),
					'activity' => 'activity_payment_of',
					'icon' => 'fa-usd',
					'value1' => $cur_i->symbol.''.$charge['response']['total'],
					'value2' => $info->reference_no
					);
				App::Log($data);

            	$this->_send_payment_email($invoice_id,$charge['response']['total']); // Send email to client

            	if(config_item('notify_payment_received') == 'TRUE'){
            		// Send email to admin
            		$this->_notify_admin($invoice_id,$charge['response']['total'],$cur_i->code);
            	}

            	$due = Invoice::get_invoice_due_amount($invoice_id);
				if($due <= 0){
					Invoice::update($invoice_id,array('status'=>'Paid'));
					modules::run('orders/process', $invoice_id);
				}


            	$this->session->set_flashdata('response_status', 'success');
				$this->session->set_flashdata('message', 'Payment received and applied to Invoice '.$info->reference_no);
				redirect('invoices/view/'.$info->inv_id);

				}else{
				$this->session->set_flashdata('response_status', 'success');
				$this->session->set_flashdata('message', 'Payment not recorded in the database. Please contact the system Admin.');
				redirect('invoices/view/'.$info->inv_id);
					}

				}
			} catch (Twocheckout_Error $e) {
				$this->session->set_flashdata('response_status', 'error');
				$this->session->set_flashdata('message', 'Payment declined with error: '.$e->getMessage());
				redirect('invoices/view/'.$info->inv_id);
			}
		}
	}
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

	$params = array(
		'recipient' => Client::view_by_id($info->client)->company_email,
		'subject'	=> '['.config_item('company_name').'] '.$subject,
		'message'	=> $message,
		'attached_file' => ''
		);

	modules::run('fomailer/send_email',$params);
}


function _notify_admin($invoice,$amount,$cur)
    {
		$info = Invoice::view_by_id($invoice);

		foreach (User::admin_list() as $key => $user) {
			$data = array(
							'email'         => $user->email,
							'invoice_ref'   => $info->reference_no,
							'amount'        => $amount,
							'currency'      => $cur,
							'invoice_id'    => $invoice,
							'client'        => Client::view_by_id($info->client)->company_name
						);

			$email_msg = $this->load->view('new_payment',$data,TRUE);

			$params = array(
							'subject'       => '['.config_item('company_name').'] Payment Confirmation',
							'recipient'     => $user->email,
							'message'       => $email_msg,
							'attached_file' => ''
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

 