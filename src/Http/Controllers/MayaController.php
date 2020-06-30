<?php

namespace SuperPlatform\StationWallet\Http\Controllers;

class MayaController
{
    /**
     * @return string
     */
    public function checkLogin()
    {
        return json_encode([
            'ErrorCode' => 0,
            'ErrorDesc' => ''
        ]);
    }

    /**
     * @return string
     */
    public function getMainBalance()
    {
        return json_encode([
            'ErrorCode' => 0,
            'ErrorDesc' => ''
        ]);
    }

    /**
     * @return string
     */
    public function getMemberLimitInfo()
    {
        return json_encode([
            'ErrorCode' => 0,
            'ErrorDesc' => ''
        ]);
    }

    /**
     * @return string
     */
    public function gameFundTransfer()
    {
        return json_encode([
            'ErrorCode' => 0,
            'ErrorDesc' => ''
        ]);
    }
}