<?php
namespace App\Services\UFile\Library;


class UCloud_HttpClient
{
    //@results: ($resp, $error)
    public function RoundTrip($req)
    {
        return UCloud_Client_Do($req);
    }
}