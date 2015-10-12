<?php
namespace App\Services\UFile\Library;
use App\Services\UFile\Library\Conf as Conf;

class Digest{

    static function CanonicalizedResource($bucket, $key)
    {
        return "/" . $bucket . "/" . $key;
    }

    static function CanonicalizedUCloudHeaders($headers)
    {
        $keys = array();
        foreach($headers as $header) {
            $header = trim($header);
            $arr = explode(':', $header);
            if (count($arr) < 2) continue;
            list($k, $v) = $arr;
            $k = strtolower($k);
            if (strncasecmp($k, "x-ucloud") === 0) {
                $keys[] = $k;
            }
        }

        $c = '';
        sort($keys, SORT_STRING);
        foreach($keys as $k) {
            $c .= $k . ":" . trim($headers[$v], " ") . "\n";
        }
        return $c;
    }

    static function UCloud_MakeAuth($auth)
    {
        if (isset($auth)) {
            return $auth;
        }

        return new UCloud_Auth(Conf::UCLOUD_PUBLIC_KEY, Conf::UCLOUD_PRIVATE_KEY);
    }

    //@results: token
    static function UCloud_SignRequest($auth, $req, $type = Conf::HEAD_FIELD_CHECK)
    {
        return self::UCloud_MakeAuth($auth)->SignRequest($req, $type);
    }

}






