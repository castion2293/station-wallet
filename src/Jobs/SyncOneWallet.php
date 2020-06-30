<?php

namespace SuperPlatform\StationWallet\Jobs;

use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use SuperPlatform\StationWallet\Models\StationWallet as Wallet;
use SuperPlatform\StationWallet\StationWallet as WalletService;

class SyncOneWallet implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    protected $wallet;

    protected $params = [];

    public function __construct(Wallet $wallet, $params = [])
    {
        $this->wallet = $wallet;
        $this->params = $params;
    }

    public function handle()
    {
        try{
            DB::beginTransaction();
            $walletService = new WalletService ;
            $walletService->sync($this->wallet, $this->params);
            DB::commit();
        }catch(\Exception $exception)
        {
            DB::rollback();
            show_exception_message($exception);
            throw $exception;
        }
    }

    public function failed(\Exception $exception)
    {
        show_exception_message($exception);
        throw $exception;
    }

}