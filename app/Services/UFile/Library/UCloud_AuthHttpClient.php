<?php
namespace App\Services\UFile\Library;
use App\Services\UFile\Library\Digest as Digest;
use App\Services\UFile\Library\HTTP_Utils as HTTP_Utils;
use App\Services\UFile\Library\Conf as Conf;

class UCloud_AuthHttpClient
{
    public $Auth;
    public $Type;
    public $MimeType;

    public function __construct($auth, $mimetype = null, $type = Conf::HEAD_FIELD_CHECK)
    {
        $this->Type = $type;
        $this->MimeType = $mimetype;
        $this->Auth = Digest::UCloud_MakeAuth($auth, $type);
    }

    //@results: ($resp, $error)
    public function RoundTrip($req)
    {
        if ($this->Type === HEAD_FIELD_CHECK) {
            $token = $this->Auth->SignRequest($req, $this->MimeType, $this->Type);
            $req->Header['Authorization'] = $token;
        }
        return HTTP_Utils::UCloud_Client_Do($req);
    }
}
