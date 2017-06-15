<?php
/**
 * First Data Payeezy Response
 */

namespace Omnipay\PayeezyDirect\Message;

use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RequestInterface;

class Response extends AbstractResponse
{
    public function __construct(RequestInterface $request, $data)
    {
        $this->request = $request;
        $this->data = json_decode($data);
    }

    public function isSuccessful()
    {
        return is_object($this->data) && $this->getDataItem('transaction_status') == 'approved';
    }

    /**
     * Get an item from the internal data array
     *
     * This is a short cut function to ensure that we test that the item
     * exists in the data array before we try to retrieve it.
     *
     * @param $itemname
     * @return mixed|null
     */
    public function getDataItem($key)
    {
        if (isset($this->data->{$key})) {
            return $this->data->{$key};
        }
    }

    public function getTransactionReference()
    {
        return $this->getDataItem('transaction_id') . ':' . $this->getDataItem('transaction_tag');
    }

    public function getTransactionId()
    {
        return $this->getDataItem('transaction_id');
    }

    public function getTransactionTag()
    {
        return $this->getDataItem('transaction_tag');
    }

    /**
     * Get the error code.
     *
     * @return string
     */
    public function getCode()
    {
        // server fault
        if ($fault = $this->getDataItem('fault')) {
            return $fault->detail->errorcode;
        }
        if ($code = $this->getDataItem('code')) {
            return $code;
        }
        if ($error = $this->getDataItem('Error')) {
            return $error->messages[0]->code;
        }
        // gateway error
        if ($this->getDataItem('gateway_resp_code') !== '00') {
            return $this->getDataItem('gateway_resp_code');
        }
        // bank error
        if ($this->getDataItem('bank_resp_code') !== '00') {
            return $this->getDataItem('bank_resp_code');
        }
    }

    /**
     * Get the error message.
     *
     * @return string
     */
    public function getMessage()
    {
        // server fault
        if ($fault = $this->getDataItem('fault')) {
            return $fault->faultstring;
        }
        if ($message = $this->getDataItem('message')) {
            return $message;
        }
        if ($error = $this->getDataItem('Error')) {
            return $error->messages[0]->description;
        }
        // gateway error
        if ($this->getDataItem('gateway_resp_code') !== '00') {
            return $this->getDataItem('gateway_message');
        }
        // bank error
        if ($this->getDataItem('bank_resp_code') !== '00') {
            return $this->getDataItem('bank_message');
        }
    }

    public function getCardReference() {
        return isset($this->data->token->token_data->value) ? $this->data->token->token_data->value : null;
    }
}
