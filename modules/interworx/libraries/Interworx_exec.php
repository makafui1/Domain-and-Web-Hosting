<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Interworx_exec 
{
    private $host;
    private $port;
    private $client;
    private $protocol; 
    private $key; 
 


    public function __construct($server)
    {                    
        $this->protocol = ($server->use_ssl == 'Yes') ? 'https' : 'http'; 
        $this->host = $server->hostname; 
        $this->port = $server->port;
        $this->key = array( 'email' => $server->username,
        'password' => $server->authkey); 
        $this->connect();      
    }

 

    protected function connect()
    { 
        $options = [
            'stream_context' => stream_context_create(array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false)
                )
            ),
        'trace' => false
        ];

        $this->client = new SoapClient($this->protocol . "://{$this->host}:".$this->port."/soap?wsdl", $options);
    }

  
    
    public function call($controller, $action, $input = null)
    {
        $result = $this->client->route($this->key, $controller, $action, $input);

        if (!is_array($result) or (is_array($result) and (!array_key_exists('status', $result) or !array_key_exists('payload', $result)))) {
            $error = 'Unexpected response from Interworx Server.';
            //CE_Lib::log(4, "InterworxApi::call::error: ({$status}) Result:\n" . print_r($result, true));
            throw new Exception($error);
        }

        $status = $result['status'];
        $payload = $result['payload'];

        if ($status == 401) {
            $error = 'Failed to authenticate.';
            //CE_Lib::log(4, "InterworxApi::call::error: {$error}");
            throw new Exception($error);
        } elseif ($status != 0) {
            if (is_array($payload)) {
                $error = 'Failed to call the Interworx API.';
                //CE_Lib::log(4, "InterworxApi::call::error: ({$status}) Result:\n" . print_r($payload, true));
                throw new Exception($error);
            } elseif (empty($payload)) {
                $error = "The result is empty.";
               // CE_Lib::log(4, "InterworxApi::call::error: ({$status}) {$error}");
                throw new Exception($error);
            } else {
                $error = $payload;
               // CE_Lib::log(4, "InterworxApi::call::error: ({$status}) {$error}");
                throw new Exception($error);
            }
        }

        return $payload;
    }

    /**
     * Delete a siteworx account
     *
     * @param string $domain Domain name
     *
     * @return mixed
     */
    public function deleteSiteworxAccount($domain)
    {
        $data = array('domain' => $domain);
        $result = $this->call('/nodeworx/siteworx', 'delete', $data);
        return $result;
    }

  

}

/* End of file model.php */
