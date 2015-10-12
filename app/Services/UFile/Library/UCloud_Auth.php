<?php
namespace App\Services\UFile\Library;
use App\Services\UFile\Library\HTTP_Utils as HTTP_Utils;
use App\Services\UFile\Library\Digest as Digest;
use App\Services\UFile\Library\Conf as Conf;

class UCloud_Auth
{
    public $PublicKey;
    public $PrivateKey;

    public function __construct($publicKey, $privateKey)
    {
        $this->PublicKey = $publicKey;
        $this->PrivateKey = $privateKey;
    }

    public function Sign($data)
    {
        $sign = base64_encode(hash_hmac('sha1', $data, $this->PrivateKey, true));
        return "UCloud " . $this->PublicKey . ":" . $sign;
    }

    //@results: $token
    public function SignRequest($req, $mimetype = null, $type = Conf::HEAD_FIELD_CHECK)
    {
        $url = $req->URL;
        $url = parse_url($url['path']);
        $data = '';
        $data .= strtoupper($req->METHOD) . "\n";
        $data .= HTTP_Utils::UCloud_Header_Get($req->Header, 'Content-MD5') . "\n";
        if ($mimetype)
            $data .=  $mimetype . "\n";
        else
            $data .= HTTP_Utils::UCloud_Header_Get($req->Header, 'Content-Type') . "\n";
        if ($type === Conf::HEAD_FIELD_CHECK)
            $data .= HTTP_Utils::UCloud_Header_Get($req->Header, 'Date') . "\n";
        else
            $data .= HTTP_Utils::UCloud_Header_Get($req->Header, 'Expires') . "\n";
        $data .= Digest::CanonicalizedResource($req->Bucket, $req->Key);
        $data .= Digest::CanonicalizedUCloudHeaders($req->Header);
        return $this->Sign($data);
    }
}
