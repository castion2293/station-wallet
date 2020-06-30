<?php

namespace SuperPlatform\StationWallet\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use SuperPlatform\ApiCaller\Facades\ApiCaller;
use SuperPlatform\StationWallet\Events\ConnectorExceptionOccurred;
use Symfony\Component\Console\Output\ConsoleOutput;

class UpdateStationBetLimit extends Command
{
    protected $console;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'station:update-bet-limit
        {station : 遊戲站識別碼}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '修改各站台的限紅';

    /**
     * 遊戲站名
     *
     * @var string
     */
    protected $station = '';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();

        $this->console = new ConsoleOutput();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->station = $this->argument('station');

        $callFunc = camel_case($this->station . 'BetLimit');

        if(method_exists($this, $callFunc)) {
            $wallets = DB::table('station_wallets')
                ->where('station', '=', $this->station)
                ->where('activated_status', '=', 'yes')
                ->select('account', 'password')
                ->get()
                ->toArray();

            $this->$callFunc($wallets);

            return;
        }

        $this->console->writeln($this->station . ' 沒有修改限紅API');
    }

    /**
     * 沙龍修改限紅
     * @param array $wallets
     * @throws \Exception
     */
    private function saGamingBetLimit(array $wallets)
    {
        $num = count($wallets);
        $bar = $this->output->createProgressBar($num);
        $bar->start();

        // 取出每筆資料的ID，並逐筆改限紅
        foreach ($wallets as $data) {
            try {
                $formParams = [
                    // 一般參數這邊設定
                    /* required */
                    'Username' => data_get($data, 'account'),
                    'Currency' => config('api_caller.sa_gaming.config.currency'),
                    'Set1' => config('station_wallet.stations.sa_gaming.updateBetLimit.Set1'),
                    'Set2' => config('station_wallet.stations.sa_gaming.updateBetLimit.Set2'),
                    'Set3' => config('station_wallet.stations.sa_gaming.updateBetLimit.Set3'),
                    'Set4' => config('station_wallet.stations.sa_gaming.updateBetLimit.Set4'),
                    'Set5' => config('station_wallet.stations.sa_gaming.updateBetLimit.Set5'),
                    'Gametype' => 'moneywheel,roulette,squeezebaccarat,others',
                ];

                ApiCaller::make('sa_gaming')->methodAction('post', 'SetBetLimit', [
                    // 路由參數這邊設定
                ])->params($formParams)->submit();

                $bar->advance();
            } catch (\Exception $exception) {
                show_exception_message($exception);
                event(new ConnectorExceptionOccurred(
                    $exception,
                    'sa_gaming',
                    'SetBetLimit',
                    $formParams
                ));
                throw $exception;
            }
        }

        $bar->finish();
        $this->info('修改完成');
    }

    /**
     * DG修改限紅
     * @param array $wallets
     * @throws \Exception
     */
    private function dreamGameBetLimit(array $wallets)
    {
        $num = count($wallets);
        $bar = $this->output->createProgressBar($num);
        $bar->start();

        // 取出每筆資料的ID，並逐筆改限紅
        foreach ($wallets as $wallet) {
            try {
                $response = ApiCaller::make('dream_game')->methodAction('post', 'game/updateLimit/{agent}', [
                    // 路由參數這邊設定
                    'agent' => config('api_caller.dream_game.config.api_agent')
                ])->params([
                    // 一般參數這邊設定
                    /* required */
                    'token' => config('api_caller.dream_game.config.api_key'),
                    'random' => str_random(16),
                    'data' => config('api_caller.dream_game.config.api_member_betting_limit'),
                    'member' => ['username' => data_get($wallet, 'account')]
                ])->submit();

                $response = $response['response'];

                if ((string)array_get($response, 'codeId') === '0') {
                    $bar->advance();
                }
                sleep(5);
            } catch (\Exception $exception) {
                //show_exception_message($exception);
                event(new ConnectorExceptionOccurred(
                    $exception,
                    'dream_game',
                    'game/updateLimit/{agent}',
                    [
                        'agent' => config('api_caller.dream_game.config.api_agent'),
                        // 一般參數這邊設定
                        /* required */
                        'token' => config('api_caller.dream_game.config.api_key'),
                        'random' => str_random(16),
                        'data' => config('api_caller.dream_game.config.api_member_betting_limit'),
                        'member' => ['username' => data_get($wallet, 'account')]
                    ]
                ));
                throw $exception;
            }
        }

        $bar->finish();
        $this->info('修改完成');
    }

    /**
     * super體育修改限紅
     * @param array $wallets
     * @throws \Exception
     */
    private function superSportBetLimit(array $wallets)
    {
        $num = count($wallets);
        $bar = $this->output->createProgressBar($num);
        $bar->start();

        // 取出每筆資料的ID，並逐筆改限紅
        foreach ($wallets as $data) {
            // 若env已設定「範例會員」將複製「範例會員」的限紅到「目前正在使用中」的會員
            if (!empty(config('station_wallet.stations.super_sport.build.copyAccount'))) {
                try {
                    ApiCaller::make('super_sport')->methodAction('post', 'account', [
                        // 路由參數這邊設定
                    ])->params([
                        // 一般參數這邊設定
                        /* required */
                        'act' => config('station_wallet.stations.super_sport.updateProfile.updateAction'),
                        'up_account' => config('station_wallet.stations.super_sport.build.up_account'),
                        'up_passwd' => config('station_wallet.stations.super_sport.build.up_password'),
                        'account' => $data->account,
                        'level' => 1,
                        /* optional*/
                        'copy_target' => config('station_wallet.stations.super_sport.build.copyAccount'),
                    ])->submit();
                    $bar->advance();
                    sleep(1);
                } catch (\Exception $exception) {
                    show_exception_message($exception);
                    event(new ConnectorExceptionOccurred(
                        $exception,
                        'super_sport',
                        'account',
                        [
                            // 一般參數這邊設定
                            /* required */
                            'act' => config('station_wallet.stations.super_sport.updateProfile.updateAction'),
                            'up_account' => config('station_wallet.stations.super_sport.build.up_account'),
                            'up_passwd' => config('station_wallet.stations.super_sport.build.up_password'),
                            'account' => $data->account,
                            'level' => 1,
                            /* optional*/
                            'copy_target' => config('station_wallet.stations.super_sport.build.copyAccount'),
                        ]
                    ));
                    throw $exception;
                }
            }
        }
        $bar->finish();
        $this->info('修改完成');
    }

    /**
     * super彩球修改限紅
     * @param array $wallets
     * @throws \Exception
     */
    private function superLotteryBetLimit(array $wallets)
    {
        $num = count($wallets);
        $bar = $this->output->createProgressBar($num);
        $bar->start();

        // 取出每筆資料的ID，並逐筆改限紅
        foreach ($wallets as $data) {
            // 若env已設定「範例會員」將複製「範例會員」的限紅到「目前正在使用中」的會員
            if (!empty(config('station_wallet.stations.super_lottery.build.copyAccount'))) {
                try {
                    $formParams = [
                        // 一般參數這邊設定
                        /* required */
                        'act' => config('station_wallet.stations.super_lottery.updateProfile.updateAction'),
                        'up_acc' => config('station_wallet.stations.super_lottery.build.up_account'),
                        'up_pwd' => config('station_wallet.stations.super_lottery.build.up_password'),
                        'account' => data_get($data, 'account'),
                        'passwd' => data_get($data, 'password'),
                        'nickname' => data_get($data, 'account'),
                        /* optional*/
                        'copy_target' => config('station_wallet.stations.super_lottery.build.copyAccount'),
                    ];

                    $response = ApiCaller::make('super_lottery')->methodAction('post', 'account', [
                        // 路由參數這邊設定
                    ])->params($formParams)->submit();

                    $code = array_get($response, 'response.code');

                    if ($code == 999) {
                        $bar->advance();
                    }

                    sleep(1);
                } catch (\Exception $exception) {
                    show_exception_message($exception);
                    event(new ConnectorExceptionOccurred(
                        $exception,
                        'super_lottery',
                        'account',
                        $formParams
                    ));
                    throw $exception;
                }
            }
        }

        $bar->finish();
        $this->info('修改完成');
    }

    /**
     * 性感百家修改限紅
     * @param array $wallets
     * @throws \Exception
     */
    private function awcSexyBetLimit(array $wallets)
    {
        $betLimitSet = explode(',', config('api_caller.awc_sexy.config.bet_limit'));

        $betLimit = [
            'SEXYBCRT' => [
                'LIVE' => [
                    'limitId' => $betLimitSet
                ]
            ]
        ];

        $num = count($wallets);
        $bar = $this->output->createProgressBar($num);
        $bar->start();

        foreach ($wallets as $wallet) {
            try {
                $response = ApiCaller::make($this->station)->methodAction('post', 'updateBetLimit', [
                    // 路由參數這邊設定
                ])->params([
                    // 一般參數這邊設定
                    'userId' => data_get($wallet, 'account'),
                    'betLimit' => json_encode($betLimit),
                ])->submit();

                $response = $response['response'];

                if (array_get($response, 'status') === '0000') {
                    $bar->advance();
                }

            } catch (\Exception $exception) {
                throw $exception;
            }
        }

        $bar->finish();
        $this->info('修改完成');
    }
}