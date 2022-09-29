<?php
/* Module Name: Interworx
 * Module URI: http://www.hostingbilling.net
 * Version: 1.0
 * Category: Servers
 * Description: Interworx API Integration.
 * Author: Hosting Billing
 * Author URI: www.hostingbilling.net
 */

class Interworx extends Hosting_Billing
{      
    
   
    public function check_connection ($server = NULL)
    { 
        $this->load->library('interworx/Interworx_exec', $server); 
        $response = $this->interworx_exec->call('/nodeworx/ip', 'listIpAddresses');
        return isset($response[0]->ipaddr) ? 'OK' : $response; 
    }

 
    public function interworx_package_config ($values = null)
    {
        $config = array(
            array(
                'label' => 'Package Name',
                'id' => 'package',
                'placeholder' => 'The package name as it appears in Interworx',
                'value' => isset($values) ? $values['package'] : ''
            ) 
        ); 
        
        return $config;        
    }


    public function create_account($params)
    {       
        $data = array(
            'domainname' => $params->account->domain,
            'ipaddress' => $params->server->hostname,
            'database_server' => 'localhost',
            'billing_day' => date('j'),
            'uniqname' => (strlen($params->account->username) > 8) ? substr($params->account->username, 0, 8) : $params->account->username,
            'nickname' => $params->profile->fullname,
            'email' => $params->client->company_email,
            'password' => $params->account->password,
            'confirm_password' => $params->account->password,
            'language' => 'en-us',
            'theme' => 'interworx',
            'menu_style' => 'small',
            'packagetemplate' => $params->package->package_name
        );

        $this->load->library('interworx/Interworx_exec', $params->server);  
        if (!empty($params->package->package_name))
        {
            if(!$this->package_exists($params->package->package_name)) {
                return "Package " .$params->package->package_name. " was not found in server."; 
            }
        }
        else{
            return 'Missing package name';
        }        
        

        $result = $this->interworx_exec->call('/nodeworx/siteworx', 'add', $data);
        return $result;
    }



     public function list_packages()
    {
        $packages = $this->interworx_exec->call('/nodeworx/packages', 'listDetails');
        return $packages;
    }


    
    public function package_exists($name)
    {
        $packages = $this->list_packages();
        $packageExists = false;

        foreach ($packages as $package) {
            if ($package['name'] == $name) {
                $packageExists = true;
            }
        }

        return $packageExists;
    }



    public function get_usage ($order)
    {
        $server = Order::get_server($order->server);
        $this->load->library('interworx/Interworx_exec', $server);

        $params = array();
        $plan = array();

        $usage = array('disk_limit' => 0, 'disk_used' => 0, 'bw_limit' => 0, 'bw_used' => 0);
        $list = $this->interworx_exec->call('/nodeworx/packages', 'listDetails', $params);
 
        foreach($list as $package)
        {
           if($package['name'] == $order->package_name)
           {
               $plan = $package;
           }
        }
        
        $data = array('domain' => $order->domain);      
        $result = $this->interworx_exec->call('/nodeworx/siteworx', 'listBandwidthAndStorageInMB', $data);
        if(isset($result[0]) && isset($result[0]['storage']))
        {
            $usage['disk_limit'] = $plan['OPT_STORAGE'];
            $usage['disk_used'] = $result[0]['storage_used'];
            $usage['bw_limit'] = $plan['OPT_BANDWIDTH'];
            $usage['bw_used'] = $result[0]['bandwidth_used'];
        } 
       
        return $usage;
    }   


   
    
    public function terminate_account ($params)
    {
        $this->load->library('interworx/Interworx_exec', $params->server);
        $data = array('domain' => $params->account->domain);
        $result = $this->interworx_exec->call('/nodeworx/siteworx', 'delete', $data);  
        return "{$params->account->domain} has been deleted.";
    }

 
    
    public function suspend_account ($params)
    {
        $this->load->library('interworx/Interworx_exec', $params->server);
        $data = array('domain' => $params->account->domain);
        $result = $this->interworx_exec->call('/nodeworx/siteworx', 'suspend', $data);
        return $result;
    }
 


    public function unsuspend_account ($params)
    {
        $this->load->library('interworx/Interworx_exec', $params->server);
        $data = array('domain' => $params->account->domain);
        $result = $this->interworx_exec->call('/nodeworx/siteworx', 'unsuspend', $data);
        return $result;
    }


    
    public function change_password ($params)
    {
        $this->load->library('interworx/Interworx_exec', $params->server);
        $data = array(
            'domain' => $params->account->domain,
            'ipaddress' => $params->server->hostname,
            'uniqname' => $params->account->username,
            'password' => $params->account->password,
            'confirm_password' => $params->account->password,
            'packagetemplate' => $params->package->package_name
        );         
        $result = $this->interworx_exec->call('/nodeworx/siteworx', 'edit', $data);
        return $result; 
    }



    public function change_package ($params)
    {
        $this->load->library('interworx/Interworx_exec', $params->server);
        $data = array(
            'domain' => $params->account->domain,
            'ipaddress' => $params->server->hostname,
            'uniqname' => $params->account->username,
            'password' => $params->account->password,
            'confirm_password' => $params->account->password,
            'packagetemplate' => $params->package->package_name
        );         
        $result = $this->interworx_exec->call('/nodeworx/siteworx', 'edit', $data);
        return $result; 
    }


    
    public function client_options ($id = null) 
    { 
        $code = '<a href="'.base_url().'accounts/view_logins/'.$id.'" class="btn btn-sm btn-success" data-toggle="ajaxModal">
        <i class="fa fa-eye"></i>'.lang('view_cpanel_logins').'</a>
        <a href="'.base_url().'accounts/change_password/'.$id.'" class="btn btn-sm btn-info" data-toggle="ajaxModal">
        <i class="fa fa-edit"></i>'.lang('change_cpanel_password').'</a>'; 
        return $code; 
    }
 



    function admin_options ($server) 
    { 
        $protocol = ($server->use_ssl == 'Yes') ? 'http://' : 'http://';
        $code = '<a class="btn btn-success btn-xs" href="'.base_url().'servers/index/'.$server->id.'"><i class="fa fa-options"></i> '.lang('test_connection').'</a>
        <a class="btn btn-primary btn-xs" href="'.base_url().'servers/edit_server/'.$server->id.'" data-toggle="ajaxModal"><i class="fa fa-pencil"></i> '.lang('edit').'</a>
        <a class="btn btn-danger btn-xs" href="'.base_url().'servers/delete_server/'.$server->id.'" data-toggle="ajaxModal"><i class="fa fa-trash"></i> '.lang('delete').'</a>
        <form action="'. $protocol . $server->hostname.'/nodeworx/?action=login" method="post" target="_blank" style="display:inline;">
        <button type="submit" class="btn btn-success btn-xs"><i class="fa fa-user"></i> '.lang("login").'</button>
        </form>';
        return $code;
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
