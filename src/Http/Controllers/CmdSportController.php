<?php

namespace SuperPlatform\StationWallet\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;
use SuperPlatform\StationWallet\Events\SingleWalletRecordEvent;
use SuperPlatform\StationWallet\Models\StationWallet;

class CmdSportController
{

    /**
     * @param Request $request
     *
     * @return string
     */
    public function checkToken(Request $request)
    {
        $username = $request->input("token");
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8" ?><authenticate></authenticate>');

        $website = $xml;
        $website->addChild('member_id', $username);
        $website->addChild('status_code', 0);
        $website->addChild('message', 'Success');
        $content = $xml->asXML();

        return response($content)->header('Content-Type', 'text/xml');
    }

}