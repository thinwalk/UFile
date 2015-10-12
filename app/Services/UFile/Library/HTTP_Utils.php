<?php
namespace App\Services\UFile\Library;
use App\Services\UFile\Library\Conf as Conf;

class HTTP_Utils
{	
	//@results: $val
	static function UCloud_Header_Get($header, $key)
	{
	    $val = @$header[$key];
	    if (isset($val)) {
	        if (is_array($val)) {
	            return $val[0];
	        }
	        return $val;
	    } else {
	        return '';
	    }
	}

	//@results: $error
	static function UCloud_ResponseError($resp)
	{
	    $header = $resp->Header;
	    $err = new UCloud_Error($resp->StatusCode, null);

	    if ($err->Code > 299) {
	        if ($resp->ContentLength !== 0) {
	            if (self::UCloud_Header_Get($header, 'Content-Type') === 'application/json') {
	                $ret = json_decode($resp->Body, true);
	                $err->ErrRet = $ret['ErrRet'];
	                $err->ErrMsg = $ret['ErrMsg'];
	            }
	        }
	    }
	    $err->Reqid = self::UCloud_Header_Get($header, 'X-SessionId');
	    return $err;
	}

	// --------------------------------------------------------------------------------

	//@results: ($resp, $error)
	static function UCloud_Client_Do($req)
	{
		$ch = curl_init();
		$url = $req->URL;

		$options = array(
			CURLOPT_USERAGENT => $req->UA,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_HEADER => true,
			CURLOPT_NOBODY => false,
			CURLOPT_CUSTOMREQUEST  => $req->METHOD,
			CURLOPT_URL => $url['host']."/".rawurlencode($url['path']),
	        CURLOPT_TIMEOUT => $req->Timeout,
	        CURLOPT_CONNECTTIMEOUT => $req->Timeout
		);

		$httpHeader = $req->Header;
		if (!empty($httpHeader))
		{
			$header = array();
			foreach($httpHeader as $key => $parsedUrlValue) {
				$header[] = "$key: $parsedUrlValue";
			}
			$options[CURLOPT_HTTPHEADER] = $header;
		}
		$body = $req->Body;
		if (!empty($body)) {
			$options[CURLOPT_POSTFIELDS] = $body;
		} else {
			$options[CURLOPT_POSTFIELDS] = "";
		}
		curl_setopt_array($ch, $options);
		$result = curl_exec($ch);
		$ret = curl_errno($ch);
		if ($ret !== 0) {
			$err = new UCloud_Error(0, $ret, curl_error($ch));
			curl_close($ch);
			return array(null, $err);
		}
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
		curl_close($ch);

		$responseArray = explode("\r\n\r\n", $result);
		$responseArraySize = sizeof($responseArray);
		$headerString = $responseArray[$responseArraySize-2];
		$respBody = $responseArray[$responseArraySize-1];

		$headers = self::parseHeaders($headerString);
		$resp = new HTTP_Response($code, $respBody);
		$resp->Header = $headers;
		$err = null;
		if (floor($resp->StatusCode/100) != 2) {
			list($r, $m) = self::parseError($respBody);
			$err = new UCloud_Error($resp->StatusCode, $r, $m);
		}
		return array($resp, $err);
	}

	static function parseError($bodyString) {

	    $r = 0;
	    $m = '';
	    $mp = json_decode($bodyString);
	    if (isset($mp->{'ErrRet'})) $r = $mp->{'ErrRet'};
	    if (isset($mp->{'ErrMsg'})) $m = $mp->{'ErrMsg'};
	    return array($r, $m);
	}

	static function parseHeaders($headerString) {

	    $headers = explode("\r\n", $headerString);
	    foreach($headers as $header) {
	        if (strstr($header, ":")) {
	            $header = trim($header);
	            list($k, $v) = explode(":", $header);
	            $headers[$k] = trim($v);
	        }
	    }
	    return $headers;
	}

	// --------------------------------------------------------------------------------

	//@results: ($data, $error)
	static function UCloud_Client_Ret($resp)
	{
		$code = $resp->StatusCode;
		$data = null;
		if ($code >= 200 && $code <= 299) {
			if ($resp->ContentLength !== 0 && self::UCloud_Header_Get($resp->Header, 'Content-Type') == 'application/json') {
				$data = json_decode($resp->Body, true);
				if ($data === null) {
					$err = new UCloud_Error($code, 0, "");
					return array(null, $err);
				}
			}
		}

		$etag = self::UCloud_Header_Get($resp->Header, 'ETag');
		if ($etag != '') $data['ETag'] = $etag;
		if (floor($code/100) == 2) {
			return array($data, null);
		}
		return array($data, UCloud_ResponseError($resp));
	}

	//@results: ($data, $error)
	static function UCloud_Client_Call($self, $req, $type = HEAD_FIELD_CHECK)
	{
	    list($resp, $err) = $self->RoundTrip($req, $type);
	    if ($err !== null) {
	        return array(null, $err);
	    }
	    return self::UCloud_Client_Ret($resp);
	}

	//@results: $error
	static function UCloud_Client_CallNoRet($self, $req, $type = HEAD_FIELD_CHECK)
	{
		list($resp, $err) = $self->RoundTrip($req, $type);
		if ($err !== null) {
			return array(null, $err);
		}
		if (floor($resp->StatusCode/100) == 2) {
			return null;
		}
		return UCloud_ResponseError($resp);
	}

	//@results: ($data, $error)
	static function UCloud_Client_CallWithForm(
	        $self, $req, $body, $contentType = 'application/x-www-form-urlencoded')
	{
	    if ($contentType === 'application/x-www-form-urlencoded') {
	        if (is_array($req->Params)) {
	            $body = http_build_query($req->Params);
	        }
	    }
	    if ($contentType !== 'multipart/form-data') {
	        $req->Header['Content-Type'] = $contentType;
	    }
	    $req->Body = $body;
	    list($resp, $err) = $self->RoundTrip($req, HEAD_FIELD_CHECK);
	    if ($err !== null) {
	        return array(null, $err);
	    }
	    return self::UCloud_Client_Ret($resp);
	}

	// --------------------------------------------------------------------------------

	static function UCloud_Client_CallWithMultipartForm($self, $req, $fields, $files)
	{
	    list($contentType, $body) = self::UCloud_Build_MultipartForm($fields, $files);
	    return self::UCloud_Client_CallWithForm($self, $req, $body, $contentType);
	}

	//@results: ($contentType, $body)
	static function UCloud_Build_MultipartForm($fields, $files)
	{
	    $data = array();
	    $boundary = md5(microtime());

	    foreach ($fields as $name => $val) {
	        array_push($data, '--' . $boundary);
	        array_push($data, "Content-Disposition: form-data; name=\"$name\"");
	        array_push($data, '');
	        array_push($data, $val);
	    }

	    foreach ($files as $file) {
	        array_push($data, '--' . $boundary);
	        list($name, $fileName, $fileBody, $mimeType) = $file;
	        $mimeType = empty($mimeType) ? 'application/octet-stream' : $mimeType;
	        $fileName = self::UCloud_EscapeQuotes($fileName);
	        array_push($data, "Content-Disposition: form-data; name=\"$name\"; filename=\"$fileName\"");
	        array_push($data, "Content-Type: $mimeType");
	        array_push($data, '');
	        array_push($data, $fileBody);
	    }

	    array_push($data, '--' . $boundary . '--');
	    array_push($data, '');

	    $body = implode("\r\n", $data);
	    $contentType = 'multipart/form-data; boundary=' . $boundary;
	    return array($contentType, $body);
	}

	static function UCloud_UserAgent() {
	    //global $SDK_VER;
	    $sdkInfo = "UCloudPHP/".Conf::SDK_VER;

	    $systemInfo = php_uname("s");
	    $machineInfo = php_uname("m");

	    $envInfo = "($systemInfo/$machineInfo)";

	    $phpVer = phpversion();

	    $ua = "$sdkInfo $envInfo PHP/$phpVer";
	    return $ua;
	}

	static function UCloud_EscapeQuotes($str)
	{
	    $find = array("\\", "\"");
	    $replace = array("\\\\", "\\\"");
	    return str_replace($find, $replace, $str);
	}

	// --------------------------------------------------------------------------------



}
