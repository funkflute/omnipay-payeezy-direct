<?php

namespace Omnipay\PayeezyDirect;

use Omnipay\Tests\GatewayTestCase;

class PayeezyDirectGatewayTest extends GatewayTestCase
{
    /** @var  PayeezyDirect */
    protected $gateway;

    /** @var  array */
    protected $options;

    public function setUp(): void
    {
        parent::setUp();

		// Payeezy Direct sandbox credentials
        $this->gateway = new Gateway($this->getHttpClient(), $this->getHttpRequest());
        $this->gateway->setApiKey('y6pWAJNyJyjGv66IsVuWnklkKUPFbb0a');
        $this->gateway->setApiSecret('86fbae7030253af3cd15faef2a1f4b67353e41fb6799f576b5093ae52901e6f7');
        $this->gateway->setMerchantToken('fdoa-a480ce8951daa73262734cf102641994c1e55e7cdf4c02b6');
        $this->gateway->setTransArmorToken('NOIW');
        // $this->gateway->setEnvironment('api-cert'); // some functions only work in cert environment

        $this->options = [
            'testMode' => true,
            'amount'   => '13.12',
            'currency' => 'USD',
        ];
    }

    public function testProperties()
    {
		$this->assertEquals('y6pWAJNyJyjGv66IsVuWnklkKUPFbb0a', $this->gateway->getApiKey());
		$this->assertEquals('86fbae7030253af3cd15faef2a1f4b67353e41fb6799f576b5093ae52901e6f7', $this->gateway->getApiSecret());
		$this->assertEquals('fdoa-a480ce8951daa73262734cf102641994c1e55e7cdf4c02b6', $this->gateway->getMerchantToken());
		$this->assertEquals('NOIW', $this->gateway->getTransArmorToken());
    }

    public function testPurchaseSuccess()
    {
        $this->setMockHttpResponse('PurchaseSuccess.txt');
        $options = array_merge($this->options, [
            'card'           => $this->getValidCard(),
            'paymentMethod'  => 'card',
        ]);
        $response = $this->gateway->purchase($options)->send();
        $this->assertInstanceOf('Omnipay\PayeezyDirect\Message\Response', $response);
        $this->assertEquals($response->getAmount(), $options['amount']);
        $this->assertTrue($response->isSuccessful());
    }

    public function testPurchaseFailure()
    {
        $this->setMockHttpResponse('PurchaseFailure.txt');
        $options = array_merge($this->options, [
            'card'           => $this->getValidCard(),
            'paymentMethod'  => 'card',
            'amount'         => 5811.00, // fail error code 811, bad vvv
        ]);
        $response = $this->gateway->purchase($options)->send();
        $this->assertInstanceOf('Omnipay\PayeezyDirect\Message\Response', $response);
        $this->assertFalse($response->isSuccessful());
        $this->assertEquals(811, $response->getCode());
    }

    public function testGiftCardPurchaseSuccess()
    {
        $this->setMockHttpResponse('GiftCardPurchaseSuccess.txt');
        $options = array_merge($this->options, [
            'paymentMethod'  => 'valuelink',
            'card'           => [
                'name'   => 'John Smith',
                'number' => '7777045839985463',
            ],
        ]);
        $response = $this->gateway->purchase($options)->send();
        $this->assertInstanceOf('Omnipay\PayeezyDirect\Message\Response', $response);
        $this->assertTrue($response->isSuccessful());
    }

    public function testAuthorizeSuccess()
    {
        $this->setMockHttpResponse('AuthorizeSuccess.txt');
        $options = array_merge($this->options, [
            'card'           => $this->getValidCard(),
            'paymentMethod'  => 'card',
            'amount'         => 13.12,
        ]);
		$response = $this->gateway->authorize($options)->send();
        $this->assertTrue($response->isSuccessful());
        $this->assertEquals($response->getAmount(), $options['amount']);
    }

    public function testVoidSuccess()
    {
        // make purchase
        $this->setMockHttpResponse('PurchaseSuccess.txt');
        $options = array_merge($this->options, [
            'card'           => $this->getValidCard(),
            'paymentMethod'  => 'card',
        ]);
        $response = $this->gateway->purchase($options)->send();
        // void purchase
        $this->setMockHttpResponse('VoidSuccess.txt');
        $options = array_merge($this->options, [
            'paymentMethod'  => 'card',
            'transactionReference' => $response->getTransactionReference(),
        ]);
		$response = $this->gateway->void($options)->send();
        $this->assertTrue($response->isSuccessful());
    }

    public function testAuthorizeAndCaptureSuccess()
    {
        $this->setMockHttpResponse('AuthorizeSuccess.txt');
        // auth purchase
        $options = array_merge($this->options, [
            'card'           => $this->getValidCard(),
            'paymentMethod'  => 'card',
        ]);
        $response = $this->gateway->authorize($options)->send();
        // complete purchase
        $this->setMockHttpResponse('CaptureSuccess.txt');
        $options = array_merge($this->options, [
            'paymentMethod'  => 'card',
            'transactionReference' => $response->getTransactionReference(),
        ]);
		$response = $this->gateway->capture($options)->send();
        $this->assertTrue($response->isSuccessful());
    }

    public function testCreateCardSuccess()
    {
        // $this->setMockHttpResponse('CreateCardSuccess.txt');
        $options = array_merge($this->options, [
            'card'           => $this->getValidCard(),
            'paymentMethod'  => 'card',
        ]);
        $this->setMockHttpResponse('CreateCardSuccess.txt');
		$response = $this->gateway->createCard($options)->send();
        $this->assertTrue($response->isSuccessful());
    }

    public function testTokenPurchaseSuccess()
    {
        // get card data
        $options = array_merge($this->options, [
            'card'           => $this->getValidCard(),
            'paymentMethod'  => 'card',
        ]);
        $this->setMockHttpResponse('CreateCardSuccess.txt');
        // tokenize with $0 Auth
		$response = $this->gateway->createCard($options)->send();
        $this->assertTrue($response->isSuccessful());
        $this->assertNotNull($response->getCardReference());
        // set token value and card brand
        $options = array_merge($this->options, [
            'cardReference' => $response->getCardReference(),
            'paymentMethod' => 'token', // paying with token
            'tokenBrand'    => 'visa', // must store type with card token, name, exp, etc. PZ requires this to be passed every time
            'card'          => $this->getValidCard(), // unlike other gateways, we need token + full card data
        ]);
        $this->setMockHttpResponse('TokenPurchaseSuccess.txt');
        $response = $this->gateway->purchase($options)->send();
        $this->assertTrue($response->isSuccessful());
    }

    /**
     * [getValidCard Payeezy direct requests a 1XX CVV to show as valid]
     * @return [type] array
     */
    public function getValidCard() {
        $card_data = parent::getValidCard();
        $card_data['cvv'] = rand(100,199);
        return $card_data;
    }
}
