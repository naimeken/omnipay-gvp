<?php
/**
 * Gvp Abstract Request
 */

namespace Omnipay\Gvp\Messages;

use Omnipay\Common\Exception\InvalidResponseException;
use Omnipay\Common\Message\ResponseInterface;

abstract class AbstractRequest extends \Omnipay\Common\Message\AbstractRequest
{
    /** @var string */
    protected $version = 'v0.01';

    /** @var array */
    protected $endpoints = [
        'direct' => [
            'test' => 'https://sanalposprovtest.garanti.com.tr/VPServlet',
            'prod' => 'https://sanalposprov.garanti.com.tr/VPServlet'
        ],
        '3d' => [
            'test' => 'https://sanalposprovtest.garanti.com.tr/servlet/gt3dengine',
            'prod' => 'https://sanalposprov.garanti.com.tr/servlet/gt3dengine'
        ]
    ];

    protected $currency_list = [
        'TRY' => 949,
        'YTL' => 949,
        'TRL' => 949,
        'TL' => 949,
        'USD' => 840,
        'EUR' => 978,
        'GBP' => 826,
        'JPY' => 392
    ];

    /**
     * @return string
     */
    public function getEndpoint(): string
    {
        $paymentType = $this->getPaymentType() == '3d' ? '3d' : 'direct';

        return $this->getTestMode() ? $this->endpoints[$paymentType]["test"] : $this->endpoints[$paymentType]["prod"];
    }

    /**
     * @return string
     */
    public function getMerchantId(): string
    {
        return $this->getParameter('merchantId');
    }

    /**
     * @param string $value
     * @return AbstractRequest
     */
    public function setMerchantId(string $value): AbstractRequest
    {
        return $this->setParameter('merchantId', $value);
    }

    /**
     * @return string
     */
    public function getTerminalId(): string
    {
        return $this->getParameter('terminalId');
    }

    /**
     * @param string $value
     * @return AbstractRequest
     */
    public function setTerminalId(string $value): AbstractRequest
    {
        return $this->setParameter('terminalId', $value);
    }

    /**
     * @return string
     */
    public function getUserName(): string
    {
        return $this->getParameter('username');
    }

    /**
     * @param string $value
     * @return AbstractRequest
     */
    public function setUserName(string $value): AbstractRequest
    {
        return $this->setParameter('username', $value);
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->getParameter('password');
    }

    /**
     * @param string $value
     * @return AbstractRequest
     */
    public function setPassword(string $value): AbstractRequest
    {
        return $this->setParameter('password', $value);
    }

    /**
     * @return string
     */
    public function getPaymentType(): string
    {
        return $this->getParameter('paymentType');
    }

    /**
     * @param string $value
     * @return AbstractRequest
     */
    public function setPaymentType(string $value = ""): AbstractRequest
    {
        return $this->setParameter('paymentType', $value);
    }


    /**
     * @param mixed $data
     * @return ResponseInterface|AbstractResponse
     * @throws InvalidResponseException
     */
    public function sendData($data)
    {
        try {
            if ($this->getPaymentType() == '3d') {
                $body = http_build_query($data, '', '&');
            } else {
                $document = new \DOMDocument('1.0', 'UTF-8');
                $root = $document->createElement('GVPSRequest');
                $xml = function ($root, $data) use ($document, &$xml) {
                    foreach ($data as $key => $value) {
                        if (is_array($value)) {
                            $subs = $document->createElement($key);
                            $root->appendChild($subs);
                            $xml($subs, $value);
                        } else {
                            $root->appendChild($document->createElement($key, $value));
                        }
                    }
                };

                $xml($root, $data);
                $document->appendChild($root);
                $body = $document->saveXML();
            }

            $httpRequest = $this->httpClient->request($this->getHttpMethod(), $this->getEndpoint(),
                ['Content-Type' => 'application/x-www-form-urlencoded'], $body
            );

            $response = (string)$httpRequest->getBody()->getContents();

            return $this->response = $this->createResponse($response);
        } catch (\Exception $e) {
            throw new InvalidResponseException(
                'Error communicating with payment gateway: ' . $e->getMessage(),
                $e->getCode()
            );
        }
    }

    /**
     * @param string $value
     * @return AbstractRequest
     */
    public function setOrderId(string $value): AbstractRequest
    {
        return $this->setParameter('orderId', $value);
    }

    /**
     * @return string
     */
    public function getOrderId(): string
    {
        return $this->getParameter('orderId');
    }

    /**
     * @return string
     */
    public function getInstallment(): string
    {
        return $this->getParameter('installment');
    }

    /**
     * @param string $value
     * @return AbstractRequest
     */
    public function setInstallment(string $value): AbstractRequest
    {
        return $this->setParameter('installment', $value);
    }

    /**
     * @return string
     */
    public function getLang(): string
    {
        return $this->getParameter('lang') ?? 'tr';
    }

    /**
     * @param string $value
     * @return AbstractRequest
     */
    public function setSecureKey(string $value): AbstractRequest
    {
        return $this->setParameter('secureKey', $value);
    }

    /**
     * @return string
     */
    public function getSecureKey(): string
    {
        return $this->getParameter('secureKey');
    }

    /**
     * @param string $value
     * @return AbstractRequest
     */
    public function setLang(string $value): AbstractRequest
    {
        return $this->setParameter('lang', $value);
    }

    /**
     * Get HTTP Method.
     *
     * This is nearly always POST but can be over-ridden in sub classes.
     *
     * @return string
     */
    protected function getHttpMethod(): string
    {
        return 'POST';
    }

    /**
     * @return string
     */
    protected function getTransactionHash(): string
    {
        return strtoupper(SHA1(sprintf('%s%s%s%s%s',
            $this->getOrderId(),
            $this->getTerminalId(),
            $this->getCard()->getNumber(),
            $this->getAmountInteger(),
            $this->getSecurityHash())));
    }

    /**
     * @return array
     */
    protected function getSalesRequestParams(): array
    {
        $data['Version'] = $this->version;
        $data['Mode'] = $this->getTestMode() ? 'TEST' : 'PROD';


        $data['Card'] = array(
            'Number' => $this->getCard()->getNumber(),
            'ExpireDate' => $this->getCard()->getExpiryDate('my'),
            'CVV2' => $this->getCard()->getCvv()
        );

        $data['Order'] = array(
            'OrderID' => $this->getOrderId()
        );

        $data['Customer'] = array(
            'IPAddress' => $this->getClientIp(),
            'EmailAddress' => $this->getCard()->getEmail()
        );

        $data['Terminal'] = [
            'ProvUserID' => $this->getUserName(),
            'HashData' => $this->getTransactionHash(),
            'UserID' => 'PROVAUT',
            'ID' => $this->getTerminalId(),
            'MerchantID' => $this->getMerchantId()
        ];

        $data['Transaction'] = array(
            'Type' => 'sales',
            'InstallmentCnt' => $this->getInstallment(),
            'Amount' => $this->getAmountInteger(),
            'CurrencyCode' => $this->currency_list[$this->getCurrency()],
            'CardholderPresentCode' => "0",
            'MotoInd' => "N"
        );

        return $data;
    }

    /**
     * @return array
     */
    protected function getAuthorizeRequestParams(): array
    {
        $data['Version'] = $this->version;
        $data['Mode'] = $this->getTestMode() ? 'TEST' : 'PROD';
        $data['Terminal'] = [
            'ProvUserID' => $this->getUserName(),
            'HashData' => $this->getTransactionHash(),
            'UserID' => $this->getUserName(),
            'ID' => $this->getTerminalId(),
            'MerchantID' => $this->getMerchantId()
        ];
        $data['Customer'] = array(
            'IPAddress' => $this->getClientIp(),
            'EmailAddress' => $this->getCard()->getEmail()
        );
        $data['Card'] = array(
            'Number' => $this->getCard()->getNumber(),
            'ExpireDate' => $this->getCard()->getExpiryDate('my')
        );
        $data['Order'] = array(
            'OrderID' => $this->getOrderId()
        );
        $data['Transaction'] = array(
            'Type' => 'preauth',
            'InstallmentCnt' => $this->getInstallment(),
            'Amount' => $this->getAmountInteger(),
            'CurrencyCode' => $this->currency_list[$this->getCurrency()],
            'CardholderPresentCode' => "0",
            'MotoInd' => "N"
        );

        return $data;
    }

    /**
     * @return array
     * @throws \Omnipay\Common\Exception\InvalidRequestException
     */
    protected function getSalesRequestParamsFor3d(): array
    {
        $params['apiversion'] = $this->version;
        $params['mode'] = $this->getTestMode();
        $params['terminalprovuserid'] = $this->getUserName();
        $params['terminaluserid'] = str_repeat('0', 9 - strlen($this->getTerminalId()));
        $params['terminalid'] = str_repeat('0', 9 - strlen($this->getTerminalId()));
        $params['terminalmerchantid'] = $this->getMerchantId();
        $params['orderid'] = $this->getOrderId();
        $params['customeremailaddress'] = $this->getCard()->getEmail();
        $params['customeripaddress'] = $this->getClientIp();
        $params['txnamount'] = $this->getAmount();
        $params['txncurrencycode'] = $this->currency_list[$this->getCurrency()];
        $params['txninstallmentcount'] = $this->getInstallment();
        $params['successurl'] = $this->getReturnUrl();
        $params['errorurl'] = $this->getCancelUrl();
        $params['lang'] = $this->getLang();
        $params['txntimestamp'] = time();
        $params['txntimeoutperiod'] = "60";
        $params['addcampaigninstallment'] = "N";
        $params['totallinstallmentcount'] = "0";
        $params['installmentonlyforcommercialcard'] = "0";
        $params['txntype'] = 'sales';
        $params['secure3dsecuritylevel'] = '3d';

        $hashData = strtoupper(sha1($this->getTerminalId() . $params['orderid'] . $params['txnamount'] . $params['successurl'] . $params['errorurl'] . $params['txntype'] . $params['txninstallmentcount'] . $this->getSecureKey() . $this->getSecurityHash()));
        $params['secure3dhash'] = $hashData;

        return $params;
    }

    private function getSecurityHash(): string
    {
        $tidPrefix = str_repeat('0', 9 - strlen($this->getTerminalId()));
        $terminalId = sprintf('%s%s', $tidPrefix, $this->getTerminalId());

        return strtoupper(SHA1(sprintf('%s%s', $this->getPassword(), $terminalId)));
    }
}
