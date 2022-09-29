<?php
/* Module Name: Paypal
 * Module URI: http://www.hostingbilling.net
 * Version: 1.0
 * Category: Payment Gateways
 * Description: Paypal Payment Gateway.
 * Author: Hosting Billing
 * Author URI: www.hostingbilling.net
 */

class Paypal extends Hosting_Billing
{     
	
	private $email; 
    private $sandbox;
                   

	function __construct()
	{
		parent::__construct();		
		User::logged_in();
		
		$this->config = get_settings('paypal');
        if(!empty($this->config))
        {             
            $this->sandbox = $this->config['mode'] == 'test' ? 'TRUE' : 'FALSE';
            $this->email = $this->config['email']; 
       }			
    }


    public function paypal_config ($values = null)
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
                'label' => lang('paypal_email'), 
                'id' => 'email',
                'value' => isset($values) ? $values['email'] : ''
            ) 
        ); 
        
        return $config;        
    }    

	
	function index()
	{
				$this->session->set_flashdata('response_status', 'error');
				$this->session->set_flashdata('message', lang('paypal_canceled'));
				redirect('clients');
	}

	
	function pay($invoice = NULL)
	{
		$info = Invoice::view_by_id($invoice);

		$invoice_due = Invoice::get_invoice_due_amount($invoice);
		if ($invoice_due <= 0) {  $invoice_due = 0.00;	}

		$data['info'] = array(
							    'item_name'		=> $info->reference_no, 
								'item_number' 	=> $invoice,
								'currency' 		=> $info->currency,
								'client'		=> $info->client,
								'amount' 		=> $invoice_due
								);

		if ($this->sandbox == 'TRUE') {
			$paypalurl = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
		}else{
			$paypalurl = 'https://www.paypal.com/cgi-bin/webscr';
		}
		
		$data['paypal_url'] = $paypalurl;
		$data['email'] = $this->email;
		
		$this->load->view('form',$data);
	}


	function cancel()
	{
				$this->session->set_flashdata('response_status', 'error');
				$this->session->set_flashdata('message', lang('paypal_canceled'));
				redirect('clients');
	}

	
	function success()
	{
        if($_POST){
				$this->session->set_flashdata('response_status', 'success');
				$this->session->set_flashdata('message', lang('payment_added_successfully'));
				redirect('clients');
        }else{
        $this->session->set_flashdata('response_status', 'error');
        $this->session->set_flashdata('message', 'Something went wrong please contact us if your Payment doesn\'t appear shortly');
        redirect('clients');
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


 