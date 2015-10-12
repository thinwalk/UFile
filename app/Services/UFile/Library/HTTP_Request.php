<?php
namespace App\Services\UFile\Library;
use App\Services\UFile\Library\HTTP_Utils as HTTP_Utils;


class HTTP_Request
{	
	public $URL;
	public $Header;
	public $Body;
	public $UA;
	public $METHOD;
	public $Params;      //map
	public $Bucket;
	public $Key;
    public $Timeout;

	public function __construct($method, $url, $body, $bucket, $key, $action_type = ActionType::NONE)
	{
		$this->URL    = $url;
		$this->Header = array();
		$this->Body   = $body;
		$this->UA     = HTTP_Utils::UCloud_UserAgent();
		$this->METHOD = $method;
		$this->Bucket = $bucket;
		$this->Key    = $key;

        global $CURL_TIMEOUT;
		global $UFILE_ACTION_TYPE;
        if ($CURL_TIMEOUT == null && $action_type !== ActionType::PUTFILE 
			&& $action_type !== ActionType::POSTFILE) {
            $CURL_TIMEOUT = 10;
		}
        $this->Timeout = $CURL_TIMEOUT;
	}


}
