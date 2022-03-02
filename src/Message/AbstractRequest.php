<?php

namespace Omnipay\PayeezyDirect\Message;

use Exception;
use Http\Client\Exception\HttpException;
use JsonException;
use Omnipay\PayeezyDirect\Exceptions\PaymentAuthorizationException;
use Omnipay\PayeezyDirect\Exceptions\PaymentInvalidRequestException;
use Omnipay\PayeezyDirect\Exceptions\PaymentInvalidResponseException;
use Omnipay\PayeezyDirect\GetterSetterTrait;
use Omnipay\Common\Message\AbstractRequest as BaseRequest;

/**
 * First Data Payeezy Abstract Request
 */
abstract class AbstractRequest extends BaseRequest
{
    use GetterSetterTrait;


    /** @var string Method used to calculate the hmac strings. */
    const METHOD_POST = 'POST';

    /** @var string Content type use to calculate the hmac string */
    const CONTENT_TYPE = 'application/json; charset=UTF-8';

    /** API version to use. */
    const API_VERSION = 1;

    /** @var string endpoint resource */
    protected $resource = 'transactions';

    /** @var int api transaction type */
    protected $transaction_type;

    // Transaction types
    const TRAN_PURCHASE = 'purchase';
    const TRAN_PREAUTH = 'authorize';
    const TRAN_PREAUTHCOMPLETE = 'capture';
    const TRAN_TAGGEDPREAUTHCOMPLETE = 'capture';
    const TRAN_VOID = 'void';
    const TRAN_TAGGEDVOID = 'void';
    const TRAN_REFUND = 'refund';
    const TRAN_TAGGEDREFUND = 'refund';

    // const TRAN_FORCEDPOST               = '03';
    // const TRAN_PREAUTHONLY              = '05';
    // const TRAN_PAYPALORDER              = '07';
    // const TRAN_CASHOUT                  = '83';
    // const TRAN_ACTIVATION               = '85';
    // const TRAN_BALANCEINQUIRY           = '86';
    // const TRAN_RELOAD                   = '88';
    // const TRAN_DEACTIVATION             = '89';

    /** @var array Names of the credit card types. */
    protected static $cardTypes = array(
        'visa' => 'Visa',
        'mastercard' => 'Mastercard',
        'discover' => 'Discover',
        'amex' => 'American Express',
        'diners_club' => 'Diners Club',
        'jcb' => 'JCB',
    );

    /**
     * Get the transaction headers.
     *
     * @return array
     */
    protected function getHeaders()
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => self::CONTENT_TYPE,
            'apikey' => $this->getApiKey(),
            'token' => $this->getMerchantToken(),
        ];
    }

    /**
     * Get the card type name, from the card type code.
     *
     * @param string $type
     *
     * @return string
     */
    public static function getCardType($type)
    {
        if (isset(self::$cardTypes[$type])) {
            return self::$cardTypes[$type];
        }
        return $type;
    }

    /**
     * @return array
     */
    public function getData()
    {
        return [
            'transaction_type' => $this->transaction_type,
        ];
    }

    /**
     * Payeezy
     *
     * HMAC Authentication
     * @return array $headers
     * @throws PaymentAuthorizationException
     * @throws PaymentInvalidRequestException
     *
     * @return array
     */
    public function getAuthorizationHeaders()
    {
        try {
            $nonce = random_bytes(4);
            $nonce = (string)hexdec(bin2hex($nonce));
            // time stamp in milli seconds
            $timestamp = (string)(time() * 1000);
            $data = $this->getApiKey() . $nonce . $timestamp . $this->getMerchantToken() . json_encode($this->getData(), JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT);
            // HMAC Hash in hex
            $hmac = hash_hmac('sha256', $data, $this->getApiSecret(), false);

            $authorization = base64_encode($hmac);

            return [
                'Authorization' => $authorization,
                'nonce' => $nonce,
                'timestamp' => $timestamp,
            ];
        } catch (JsonException $exception) {
            throw new PaymentInvalidRequestException($exception->getMessage());
        } catch (Exception $exception) {
            throw new PaymentAuthorizationException($exception->getMessage());
        }
    }

    /**
     * @param mixed $data
     *
     * @throws PaymentAuthorizationException
     * @throws PaymentInvalidRequestException
     * @throws PaymentInvalidResponseException
     * @throws \Http\Client\Exception\RequestException
     * @throws \Http\Client\Exception\NetworkException
     *
     * @return Response
     */
    public function sendData($data)
    {
        try {
            $data = json_encode($data, JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT);

        } catch (JsonException $exception) {
            throw new PaymentInvalidRequestException($exception->getMessage());
        }
        $endpoint = $this->getEndpoint();
        $headers = $this->getHeaders();

        // add HMAC auth data
        $headers = array_merge($headers, $this->getAuthorizationHeaders());

        try {
            $response = $this->httpClient->request('POST', $endpoint, $headers, $data);
            $body = $response->getBody()->getContents();
            return $this->createResponse(json_decode($body, false, 512, JSON_THROW_ON_ERROR));
        } catch (JsonException $e) {
            throw new PaymentInvalidResponseException($e->getMessage());
        } catch (HttpException $e) {
            // if we have a response return the error as response
            return $this->createResponse($e->getResponse()->getBody()->getContents());
            // otherwise just let it bubble up
        }
    }

    /**
     * Get the endpoint URL for the request.
     *
     * @return string
     */
    protected function getEndpoint()
    {
        return 'https://' . $this->getEnvironment() . '.payeezy.com/v' . self::API_VERSION . '/' . $this->resource;
    }

    /**
     * Create the response object.
     *
     * @param $data
     *
     * @return Response
     */
    protected function createResponse($data)
    {
        return $this->response = new Response($this, $data);
    }

    public function getSubmitAmount()
    {
        return number_format(parent::getAmount(), 2, '', '');
    }

    public function getTransactionId()
    {
        $ref = $this->getTransactionReference();
        return $ref ? explode(':', $this->getTransactionReference())[0] : null;
    }

    public function getTransactionTag()
    {
        $ref = $this->getTransactionReference();
        return $ref ? explode(':', $this->getTransactionReference())[1] : null;
    }

    public function getPaymentMethod()
    {
        $method = parent::getPaymentMethod();
        return $method === 'card' ? 'credit_card' : $method;
    }

}
