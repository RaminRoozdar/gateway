<?php

namespace Parsisolution\Gateway\Providers\Zarinpal;

use Illuminate\Container\Container;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Parsisolution\Gateway\AbstractProvider;
use Parsisolution\Gateway\Exceptions\InvalidRequestException;
use Parsisolution\Gateway\GatewayManager;
use Parsisolution\Gateway\SoapClient;
use Parsisolution\Gateway\Transactions\AuthorizedTransaction;
use Parsisolution\Gateway\Transactions\SettledTransaction;
use Parsisolution\Gateway\Transactions\UnAuthorizedTransaction;


class SabaPay extends AbstractProvider {

    /**
     * Address of Saba Pay
     *
     * @var string
     */
    const SERVER_URL = 'http://pay.sabanovin.com/invoice/request';


    /**
     * Address of gate for redirect
     *
     * @var string
     */
    const GATE_URL = 'http://pay.sabanovin.com/invoice/pay';


    /**
     * Address of CURL server for verify payment
     *
     * @var string
     */
    const SERVER_VERIFY_URL = 'http://pay.sabanovin.com/invoice/check';



    /**
     * Get this provider name to save on transaction table.
     * and later use that to verify and settle
     * callback request (from transaction)
     *
     * @return string
     */
    protected function getProviderName()
    {
        return GatewayManager::SABAPAY;
    }

    /**
     * {@inheritdoc}
     */
    protected function authorizeTransaction(UnAuthorizedTransaction $transaction)
    {
        $fields = [
            'api_key'      => $this->config['api_key'],
            'amount'   => $transaction->getAmount()->getToman(),
            'redirect' => urlencode($this->getCallback($transaction)),
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::SERVER_URL);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $response = json_decode($response, true);
        curl_close($ch);

        if ($response['status'] == 1)
            return AuthorizedTransaction::make($transaction, $response['invoice_key']);

        throw new SabaPayException($response['errorCode']);
    }

    /**
     * Redirect the user of the application to the provider's payment screen.
     *
     * @param AuthorizedTransaction $transaction
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Illuminate\Contracts\View\View
     */
    protected function redirectToGateway(AuthorizedTransaction $transaction)
    {
        return new RedirectResponse(self::URL_GATE . $transaction->getReferenceId());
    }

    /**
     * @inheritdoc
     */
    protected function validateSettlementRequest(Request $request)
    {
        $status = $request->input('Status');

        if ($status == 'OK')
            return true;

        throw new InvalidRequestException();
    }

    /**
     * {@inheritdoc}
     */
    protected function settleTransaction(Request $request, AuthorizedTransaction $transaction)
    {
        $authority = $request->input('Authority');

        $fields = [
            'MerchantID' => Arr::get($this->config, 'merchant-id'),
            'Authority'  => $authority,
            'Amount'     => $transaction->getAmount()->getToman(),
        ];

        $soap = new SoapClient($this->serverUrl, $this->SoapConfig(), ['encoding' => 'UTF-8']);
        $response = $soap->PaymentVerification($fields);

        if ($response->Status != 100 && $response->Status != 101)
            throw new ZarinpalException($response->Status);

        return new SettledTransaction($transaction, $response->RefID);
    }
}