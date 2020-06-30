<?php

namespace SuperPlatform\StationWallet\Connectors;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * 第三方遊戲站帳號錢包連結器
 *
 * @package SuperPlatform\StationWallet\Connectors
 */
abstract class Connector implements ConnectorInterface
{
    public $config;

    /**
     * Connector constructor.
     */
    public function __construct()
    {
        $this->config = config("station_wallet.stations.{$this->station}");
    }

    /**
     * response merge
     */
    public function responseMerge($response, $mergeArray = [])
    {
        return (!empty($mergeArray))
            ? array_merge($response, $mergeArray)
            : $response;
    }

    /**
     * 遊戲館通行連結 $gameLoginUrl 若是帶有參數，須將其轉換放到 params 中
     *
     * 例如 all_bet passport 方法取得的 response.gameLoginUrl 網址是帶參數的
     *
     * https://www.allbetgame.net/?language=zh_CN&token=3b50a4a478f4&sessionId=0d6e544f4f434d0c804e0b28e741ed0e&loginType=2
     *
     * response 結果就會是
     *
     * array:4 [
     *   "method" => "get"
     *   "web_url" => "https://www.allbetgame.net/?language=zh_CN&token=3b50a4a478f4&sessionId=0d6e544f4f434d0c804e0b28e741ed0e&loginType=2"
     *   "mobile_url" => ""
     *   "params" => []
     * ]
     *
     * 但是這樣的 web_url 放在 form action 中自動 submit 會把 ?params 後面帶的參數消去，
     * 這邊可以統一轉成以下格式，讓網址後面參數以 input 形態放到 form 中提交
     *
     * array:4 [
     *   "method" => "get"
     *   "web_url" => "https://www.allbetgame.net"
     *   "mobile_url" => ""
     *   "params" => array:4 [
     *     "language" => "zh_CN"
     *     "token" => "3b50a4a478f4"
     *     "sessionId" => "0d6e544f4f434d0c804e0b28e741ed0e"
     *     "loginType" => "2"
     *   ]
     * ]
     *
     * 備註：是因為現在取得遊戲館通性連節後，跳轉統一都用產生 form 提交跳轉才會有此配套作法
     */
    public function webUrlParse(string $gameLoginUrl)
    {
        $data = parse_url(($gameLoginUrl));
        parse_str($data['query'], $data['query']);

        return $data;
    }
    public function beforeRequestLog ($wallet, $requestId, $formParams, $action)
    {
        $requestAt = Carbon::now()->toDateTimeString();
        Log::channel('member-wallet-api')->info("
            請求時間：{$requestAt}
            requestId：{$requestId} 
            會員錢包帳號： {$wallet->account} 
            遊戲館：{$this->station} 
            錢包ID：{$wallet->id} 
            訪問方法：{$action}
            帶入參數：{$formParams}
            " );
    }

    public function afterResponseLog($wallet, $requestId, $httpCode, $errorCode, $action, $balance = '', $amount ='')
    {
        $responseAt = Carbon::now()->toDateTimeString();
        $info = [
            '訪問方法：' => $action ,
            'requestId' => $requestId,
            '回應時間：' => $responseAt,
            '遊戲館：' => $this->station,
            '會員錢包帳號：' => $wallet->account,
            '轉點金額：' => $amount,
            'API回傳金額：' => $balance,
            '錢包ID：' => $wallet->id,
            'http_code：' => $httpCode,
            '返回訊息：' => $errorCode,
        ];

        if(empty($amount)) {
            unset($info['轉點金額：']);
        }
        if(empty($balance)) {
            unset($info['API回傳金額：']);
        }
        Log::channel('member-wallet-api')->info($info);
    }

    /**
     * 統一回傳exception格式
     *
     * @param \Exception $exception
     * @return \Exception
     */
    protected function formatException(\Exception $exception)
    {
        if (method_exists($exception, 'response')) {
            $responseText = '';

            foreach ($exception->response() as $key => $text) {
                $responseText .= $key . ': ' . json_encode($text) . ' / ';
            }

            $responseText = rtrim($responseText, ' / ');

            return new \Exception($responseText);
        }

        return $exception;
    }
}