<?php
// TODO: Error handling (try/catch and errors in json struct)
class Novo {
	private $sessionid;
	private $cookies;
	private $username;
	private $password;
	private $error;
	private $environment = 'dev';
	private $clients = array();
	//private $clientoptions = array('trace' => 1, 'proxy_host' => 'webproxy.phila.gov', 'proxy_port' => 8080); // City network proxy
    private $clientoptions = array();
	
	private $const = array(
		'url' => array(
			'base' => array( 'dev' => 'http://philly311-test.novosolutions.net', 'production' => 'http://philly311.phila.gov' ),
			'user' => '/API/Core/UserService.asmx',
			'ticket' => '/API/Core/TicketService.asmx',
			'login' => '/admin/login.asp',
			'report' => '/Modules/Reports/RunReport.aspx?report=',
			'article' => '/admin/default.asp?id=',
			'article_search' => '/admin/dosearch.asp?words=',
			'suffix' => '?WSDL',
		),
		'udfid' => array(
			'address' => 17,
			'zip_id' => 28,
			'work_order' => 11,
			'language' => array( 'dev' => NULL, 'production' => 74 ),
			'caller_first_name' => 15,
			'caller_last_name' => 16,
			'caller_address' => 21,
			'caller_phone' => 19,
			'caller_email' => 18,
			'lat' => array( 'dev' => 75, 'production' => 80 ),
			'long' => array( 'dev' => 76, 'production' => 81 ),
			'pending_comment' => array( 'dev' => 0, 'production' => 92 ),
		),
		'node' => array(
			'results_table' => '#ctl00_ContentPlaceHolder1_reportSpooler_GridViewSpooler',
			'form' => '#aspnetForm',
			'param_prefix' => 'ctl00$ContentPlaceHolder1$df_repFilterDefinitions$ctl',
			'param_suffix' => '$df_txtComparisonValue',
			'article_title' => '#Table1 .shortTitle',
			'article_content' => '#Table1 .content:last',
		),
		'default' => array(
			'service_request' => array(
				'status_id' => 10,
				'customer_id' => 25,
				'template_id' => 11,
				'fcr' => 0,
			),
			'contact_log' => array(
				'status_id' => 8,
				'customer_id' => 25,
				'template_id' => 4,
				'fcr' => 1,
			),
		),
	);
	
	public function __construct($params) {
		if(isset($params['username']) && isset($params['password'])) {
			$this->username = $params['username'];
			$this->password = $params['password'];
			
			if(isset($params['environment'])) {
				$this->environment = $params['environment'];
			}
			return TRUE;
		}
		return FALSE;
	}
	
	/*
	|-----------------------------------------
	| Public Functions
	|-----------------------------------------
	*/
	
	public function viewticket($ticketid) {
		if($this->soap_login()) {
			$ticketdata = $this->soap_viewticket($ticketid);
			//die('<pre>'.print_r($ticketdata, true).'</pre>');
			$ticketdata = $this->parse_ticket_data($ticketdata);
			$ticketdata['address'] = $this->udfvalue($this->soap_getudf($ticketid, $this->constvalue($this->const['udfid']['address'])));
			$ticketdata['zip'] = $this->udfvalue($this->soap_getudf($ticketid, $this->constvalue($this->const['udfid']['zip_id'])));
			$ticketdata['work_order'] = $this->udfvalue($this->soap_getudf($ticketid, $this->constvalue($this->const['udfid']['work_order'])));
			$ticketdata['caller_first_name'] = $this->udfvalue($this->soap_getudf($ticketid, $this->constvalue($this->const['udfid']['caller_first_name'])));
			$ticketdata['caller_last_name'] = $this->udfvalue($this->soap_getudf($ticketid, $this->constvalue($this->const['udfid']['caller_last_name'])));
			$ticketdata['caller_address'] = $this->udfvalue($this->soap_getudf($ticketid, $this->constvalue($this->const['udfid']['caller_address'])));
			$ticketdata['caller_phone'] = $this->udfvalue($this->soap_getudf($ticketid, $this->constvalue($this->const['udfid']['caller_phone'])));
			$ticketdata['caller_email'] = $this->udfvalue($this->soap_getudf($ticketid, $this->constvalue($this->const['udfid']['caller_email'])));
			
			return $ticketdata;
		}
		return FALSE;
	}
	
	public function addticket($ticketdata) {
		if($this->soap_login()) {
			if($ticketid = $this->ticketidvalue($this->soap_addticket($ticketdata))) {
				// Set UDFs
				foreach($ticketdata as $field => $value) {
					if(isset($this->const['udfid'][$field])) {
						$this->soap_setudf($ticketid, $this->constvalue($this->const['udfid'][$field]), $value);
					}
				}
				return $ticketid;
			}
		}
		return FALSE;
	}
	
	public function updateticket($ticketid, $ticketdata) {
		if($this->soap_login()) {
			$result = $this->soap_updateticket($ticketid, $ticketdata);
			if( ! $result->UpdateTicketResult->ErrorMessage) { // if successful
				// Set UDFs
				foreach($ticketdata as $field => $value) {
					if(isset($this->const['udfid'][$field])) {
						$this->soap_setudf($ticketid, $this->constvalue($this->const['udfid'][$field]), $value);
					}
				}
				return TRUE;;
			}
		}
		return FALSE;
	}
	
	public function notes($ticketid) {
		if($this->soap_login()) {
			$data = $this->soap_getticketnotes($ticketid);
			return $data;
		}
		return FALSE;
	}
	
	public function report($reportid, $params = array(), $assoc = true) {
		$html = $this->curl_login($this->constvalue($this->const['url']['report']) . $reportid);
		
		if(!empty($params)) {
			$post = $this->parse_form($html);
			$html = $this->curl_send_params($post, $params);
		}
		$data = $this->parse_results_table($html, $assoc);
		return $data;
	}
	
	public function article($articleid) {
		$html = $this->curl_login($this->constvalue($this->const['url']['article']) . $articleid);
		$data = $this->parse_article($html);
		return $data;
	}
	
	public function get_error() {
		return $this->error;
	}
	
	public function get_udf_array($ticketid, $field) {
		if(isset($this->const['udfid'][$field])) {
			return $this->soap_getudf($ticketid, $this->constvalue($this->const['udfid'][$field]));
		}
		return FALSE;
	}
	
	/*
	|-----------------------------------------
	| SOAP Actions
	|-----------------------------------------
	*/
	
	private function soap_login() {
		$client = $this->get_client('user');
		
		// Create Body
		$body = '<Login xmlns="http://novosolutions.com/"><UserName>'.$this->username.'</UserName><UserPassword>'.$this->password.'</UserPassword></Login>';
		$bodyVar = new SoapVar($body, XSD_ANYXML);
		$responseHeaders = array();
		
		// Execute
		$result = $client->__soapCall('Login', array('parameters' => $bodyVar), null, null, $responseHeaders);
		
		// If successful, store results
		if(isset($responseHeaders['AuthHeader']->SessionId)) {
			$this->sessionid = $responseHeaders['AuthHeader']->SessionId;
			$this->cookies = $client->_cookies;
			return TRUE;
		}
		return FALSE;
	}
	
	private function soap_viewticket($ticketid) {
		$client = $this->get_client('ticket');
		
		// Create Body
		$body = '<ViewTicket xmlns="http://novosolutions.com/"><Id>'.$ticketid.'</Id></ViewTicket>';
		$bodyVar = new SoapVar($body, XSD_ANYXML);
		
		// Execute
		$result = $client->__soapCall('ViewTicket', array('parameters' => $bodyVar));
		return $result;
	}
	
	private function soap_addticket($ticketdata) {
		$client = $this->get_client('ticket');
		
		// Create Body
		$body = '<AddTicket xmlns="http://novosolutions.com/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">';
		$body .= $this->xmlnode('CaseTitle', $this->val($ticketdata, 'title'));
		$body .= $this->xmlnode('CaseDescription', $this->val($ticketdata, 'description'));
		$body .= $this->xmlnode('CaseResolution', $this->val($ticketdata, 'resolution'));
		$body .= $this->xmlnode('CaseStatus', $this->constvalue($this->const['default']['service_request']['status_id']));
		$body .= $this->xmlnode('PriorityRef', NULL);
		$body .= $this->xmlnode('ProductRef', NULL);
		$body .= $this->xmlnode('DepartRef', $this->val($ticketdata, 'department_id'));
		$body .= $this->xmlnode('CategoryRef', $this->val($ticketdata, 'category_id'));
		$body .= $this->xmlnode('CustomerRef', $this->constvalue($this->const['default']['service_request']['customer_id']));
		$body .= $this->xmlnode('ContenttypeRef', NULL);
		$body .= $this->xmlnode('TemplateRef', $this->constvalue($this->const['default']['service_request']['template_id']));
		$body .= $this->xmlnode('UserRefAssigned', $this->val($ticketdata, 'user_id'));
		$body .= $this->xmlnode('ResolvedFirstCall', $this->constvalue($this->const['default']['service_request']['fcr']));
		$body .= $this->xmlnode('CaseDateDue', NULL);
		$body .= $this->xmlnode('CaseDateAssign', date('c', strtotime($this->val($ticketdata, 'date_added', 'now'))));
		$body .= '</AddTicket>';
		//die($body);
		$bodyVar = new SoapVar($body, XSD_ANYXML);
		
		// Execute
		$result = $client->__soapCall('AddTicket', array('parameters' => $bodyVar));
		return $result;
	}
	
	private function soap_updateticket($ticketid, $ticketdata) {
		$currentdata = $this->soap_viewticket($ticketid);
		
		if(!$currentdata->ViewTicketResult->Result->ErrorMessage) {
			$currentdata = $currentdata->ViewTicketResult->Ticket;
			
			$client = $this->get_client('ticket');
			
			// Create Body
			$body = '<UpdateTicket xmlns="http://novosolutions.com/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">';
			$body .= $this->xmlnode('TicketId', $ticketid);
			$body .= $this->xmlnode('CaseTitle', $this->val($ticketdata, 'title', $currentdata->CaseTitle));
			$body .= $this->xmlnode('CaseDescription', $this->val($ticketdata, 'description', $currentdata->CaseDescription));
			$body .= $this->xmlnode('CaseResolution', $this->val($ticketdata, 'resolution', $currentdata->CaseResolution));
			$body .= $this->xmlnode('CaseStatus', $this->val($ticketdata, 'status_id', $currentdata->CaseStatus));
			$body .= $this->xmlnode('PriorityRef', $currentdata->PriorityRef);
			$body .= $this->xmlnode('ProductRef', $currentdata->ProductRef);
			$body .= $this->xmlnode('DepartRef', $this->val($ticketdata, 'department_id', $currentdata->DepartRef));
			$body .= $this->xmlnode('CategoryRef', $this->val($ticketdata, 'category_id', $currentdata->CategoryRef));
			$body .= $this->xmlnode('CustomerRef', $this->val($ticketdata, 'customer_id', $currentdata->CustomerRef));
			$body .= $this->xmlnode('ContenttypeRef', $currentdata->ContenttypeRef);
			$body .= $this->xmlnode('TemplateRef', $this->val($ticketdata, 'template_id', $currentdata->TemplateRef));
			$body .= $this->xmlnode('UserRefAssigned', $this->val($ticketdata, 'user_id', $currentdata->UserRefAssigned));
			$body .= $this->xmlnode('ResolvedFirstCall', $this->val($ticketdata, 'fcr', $currentdata->ResolvedFirstCall));
			$body .= $this->xmlnode('CaseDateDue', isset($ticketdata['date_due']) ? date('c', strtotime($ticketdata['date_due'])) : $currentdata->CaseDateDue);
			$body .= $this->xmlnode('CaseDateAssign', isset($ticketdata['date_added']) ? date('c', strtotime($ticketdata['date_added'])) : $currentdata->CaseDateAssign);
			$body .= '</UpdateTicket>';
			$bodyVar = new SoapVar($body, XSD_ANYXML);
			
			// Execute
			$result = $client->__soapCall('UpdateTicket', array('parameters' => $bodyVar));
			return $result;
		}
	}
	
	private function soap_getticketnotes($ticketid) {
		$client = $this->get_client('ticket');
		
		// Create Body
		$body = '<getTicketNotes xmlns="http://novosolutions.com/"><CaseRef>'.$ticketid.'</CaseRef></getTicketNotes>';
		$bodyVar = new SoapVar($body, XSD_ANYXML);
		
		// Execute
		$result = $client->__soapCall('getTicketNotes', array('parameters' => $bodyVar));
		return $result;
	}
	
	private function soap_getudf($ticketid, $udfid) {
		$client = $this->get_client('ticket');
		
		// Create Body
		$body = '<GetUdf xmlns="http://novosolutions.com/"><DataId>'.$ticketid.'</DataId><UDFId>'.$udfid.'</UDFId></GetUdf>';
		$bodyVar = new SoapVar($body, XSD_ANYXML);
		
		// Execute
		$result = $client->__soapCall('GetUdf', array('parameters' => $bodyVar));
		return $result;
	}
	
	private function soap_setudf($ticketid, $udfid, $value) {
		$client = $this->get_client('ticket');
		
		// Create Body
		$body = '<SetUdf xmlns="http://novosolutions.com/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><DataId>'.$ticketid.'</DataId><UDFId>'.$udfid.'</UDFId>'.$this->xmlnode('UDFvalue', $value).'</SetUdf>';
		$bodyVar = new SoapVar($body, XSD_ANYXML);
		
		// Execute
		$result = $client->__soapCall('SetUdf', array('parameters' => $bodyVar));
		return $result;
	}
	
	// TEMPORARY - remove this
	public function soap_setudf2($ticketid, $udfid, $value) {
		$client = $this->get_client('ticket');
		
		// Create Body
		//$body = '<SetUdf xmlns="http://novosolutions.com/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><DataId>'.$ticketid.'</DataId><UDFId>'.$udfid.'</UDFId>'.$this->xmlnode('UDFvalue', $value).'</SetUdf>';
		$body = '<SetUdf xmlns="http://novosolutions.com/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><DataId>'.$ticketid.'</DataId><UDFId>'.$udfid.'</UDFId><UDFvalue>'.$value.'</UDFvalue></SetUdf>';
		//echo '<textarea>'.$body.'</textarea>';
		$bodyVar = new SoapVar($body, XSD_ANYXML);
		
		// Execute
		$result = $client->__soapCall('SetUdf', array('parameters' => $bodyVar));
		return $result;
	}
	
	/*
	|-----------------------------------------
	| cURL Actions
	|-----------------------------------------
	*/
	
	private function curl_login($callback_url = '') {
		$client = $this->get_client('curl');
		$url = $this->constvalue($this->const['url']['base']) . $this->constvalue($this->const['url']['login']);
		curl_setopt($client, CURLOPT_URL, $url);

		// Login Data
		$data = array(
			'Login' => $this->username,
			'Password' => $this->password,
			'btnLogin' => 'Go',
		);
		if($callback_url) {
			$data['goUrl'] = $callback_url;
		}

		// Format data
		$datastring = http_build_query($data);
		curl_setopt($client, CURLOPT_POST, count($data));
		curl_setopt($client, CURLOPT_POSTFIELDS, $datastring);

		// Login
		return curl_exec($client);
	}
	
	private function curl_send_params($post, $params) {
		$client = $this->get_client('curl');
		
		$i = 0;
		foreach($params as $param) {
			$post[$this->param_key($i)] = $param;
			$i++;
		}
		$datastring = http_build_query($post);
		curl_setopt($client, CURLOPT_POST, count($post));
		curl_setopt($client, CURLOPT_POSTFIELDS, $datastring);
		
		return curl_exec($client);
	}
	
	/*
	|-----------------------------------------
	| Helper functions
	|-----------------------------------------
	*/
	
	private function parse_ticket_data($ticketdata) {
		$data = $ticketdata->ViewTicketResult;
		if( ! $data->Result->ErrorMessage) {
			$parsed_data = array(
				'id' => $data->Ticket->CaseId,
				'title' => $data->Ticket->CaseTitle,
				'status' => $data->Status->StatusTitle,
				'resolution' => trim($data->Ticket->CaseResolution),
				'category' => trim($data->Category->CategoryName),
				'category_id' => $data->Ticket->CategoryRef,
				'description' => trim($data->Ticket->CaseDescription),
				'department' => trim($data->Group->DepartName),
				'department_id' => $data->Ticket->DepartRef,
				'date_added' => $data->Ticket->CaseDateAdd,
				'date_edited' => $data->Ticket->CaseDateEdit,
				'date_due' => $data->Ticket->CaseDateDue,
				'user_id' => $data->Ticket->UserRefAssigned,
				'user' => $data->AssignedUser->UserLogin,
				//'address' => trim($data->UDF->Address),
				//'zip' => $data->UDF->Zip,
			);
			return $parsed_data;
		}
		/*else {
			$return['error'] = $data->Result->ErrorMessage;
			return $return;
		}*/
		return FALSE;
	}
	
	// Parse report HTML into array ($assoc should be false for CSV)
	private function parse_results_table($html, $assoc = true) {
		$doc = phpQuery::newDocument($html);
		$rows = pq($this->constvalue($this->const['node']['results_table']) . ' tr');
		$data = array();
		$headings = array();
		foreach($rows as $row) {
			$cols = pq($row)->find('th, td');
			$values = array();
			foreach($cols as $index => $col) {
				$value = trim(pq($col)->text(), chr(0xC2).chr(0xA0));
				if($assoc && !empty($headings))
					$values[$headings[$index]] = $value;
				else
					$values []= $value;
			}
			if($assoc && empty($headings)) {
				$values = array_map(array($this, 'fix_header'), $values);
				$headings = $values;
			}
			else
				$data []= $values;
		}
		return $data;
	}
	
	private function parse_article($html) {
		$doc = phpQuery::newDocument($html);
		$text = pq($this->constvalue($this->const['node']['article_content']))->text();
		return $text;
	}
	
	private function parse_form($html) {
		$doc = phpQuery::newDocument($html);
		$fields = pq('input');
		$data = array();
		
		foreach($fields as $field) {
			$field = pq($field);
			$data[$field->attr('name')] = $field->val();
		}
		return $data;
	}
	
	// Fetch exist client or create a new one
	private function get_client($service) {
		// Reset error message since new action is taking place
		$this->error = FALSE;
		
		// If client already exists, return it
		if(isset($this->clients[$service])) {
			return $this->clients[$service];
		}
		
		// Otherwise create one
		switch($service) {
			case 'user':
				$url = $this->constvalue($this->const['url']['base']) . $this->constvalue($this->const['url']['user']) . $this->constvalue($this->const['url']['suffix']);
				$this->clients['user'] = new SoapClient($url, $this->clientoptions);
				return $this->clients['user'];
				break;
			case 'ticket':
				$url = $this->constvalue($this->const['url']['base']) . $this->constvalue($this->const['url']['ticket']) . $this->constvalue($this->const['url']['suffix']);
				$this->clients['ticket'] = new SoapClient($url, $this->clientoptions);
				
				// Create Header
				$header = '<AuthHeader xmlns="http://novosolutions.com/"><SessionId>'.$this->sessionid.'</SessionId></AuthHeader>';
				$headerVar = new SoapVar($header, XSD_ANYXML);
				$headerObj = new SoapHeader('http://novosolutions.com/', 'AuthHeader', $headerVar, false);
				$this->clients['ticket']->__setSoapHeaders(array($headerObj));
				
				// Set Cookies
				if(!empty($this->cookies)) {
					foreach($this->cookies as $cookie => $value)
						$this->clients['ticket']->__setCookie($cookie, $value[0]);
				}
				return $this->clients['ticket'];
				break;
			case 'curl':
				$this->clients['curl'] = curl_init();
				curl_setopt($this->clients['curl'], CURLOPT_RETURNTRANSFER, true);
				curl_setopt($this->clients['curl'], CURLOPT_FOLLOWLOCATION, 1); // .NET
				curl_setopt($this->clients['curl'], CURLOPT_SSL_VERIFYPEER, false); // .NET
				curl_setopt($this->clients['curl'], CURLOPT_SSL_VERIFYHOST, false); // .NET
				curl_setopt($this->clients['curl'], CURLOPT_COOKIEJAR, tempnam('/tmp', 'cookie')); // Cookies
				return $this->clients['curl'];
				break;
		}
	}
	
	private function udfvalue($obj) {
		if(isset($obj->GetUdfResult->UdfValue->VwnovoCoreUdf)) {
			$values = $obj->GetUdfResult->UdfValue->VwnovoCoreUdf;
			return $values->TypeVarchar . $values->TypeDateTime . $values->TypeNumber . $values->TypeBool; // hacky shorthand but it should work since the other 3 are always empty
		}
		return null;
	}
	
	private function ticketidvalue($obj) {
		if($obj->AddTicketResult->ErrorMessage)
			$this->error = $obj->AddTicketResult->ErrorMessage;
		return ($obj->AddTicketResult->ResultValue < 1 ? FALSE : $obj->AddTicketResult->ResultValue);
	}
	
	private function xmlnode($node, $value) {
		return ((string) $value ? '<'.$node.'>'.$value.'</'.$node.'>' : '<'.$node.' xsi:nil="true" />');
	}
	
	function constvalue($index) {
		return is_array($index) && isset($index[$this->environment]) ? $index[$this->environment] : $index;
	}

	// Used by parse_results_table to fix associative array keys
	private function fix_header($value) {
		return str_replace(' ', '_', strtolower($value));
	}
	
	private function param_key($index) {
		return $this->constvalue($this->const['node']['param_prefix']) . str_pad($index, 2, '0', STR_PAD_LEFT) . $this->constvalue($this->const['node']['param_suffix']);
	}
	
	private function val($array, $index, $default = NULL) {
		return isset($array[$index]) ? $array[$index] : $default;
	}
}