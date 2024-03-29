<?php

namespace Oro\Bundle\MailChimpBundle\Provider\Transport;

use Guzzle\Http\Message\Response;
use Oro\Bundle\MailChimpBundle\Client\MailChimpClient as BaseClient;
use Oro\Bundle\MailChimpBundle\Provider\Transport\Exception\BadResponseException;

class MailChimpClient extends BaseClient
{
    /**#@+
     * @const string Export API method names
     */
    /**
     * Dumps a full list or a segment of a list
     */
    const EXPORT_LIST = 'list';
    /**
     * Dumps all Ecommerce Orders for an account
     */
    const EXPORT_ECOMM_ORDERS = 'ecommOrders';
    /**
     * Dumps all Subscriber Activity for the requested campaign
     */
    const EXPORT_CAMPAIGN_SUBSCRIBER_ACTIVITY = 'campaignSubscriberActivity';
    /**#@-*/

    /**
     * MailChimp Export API version.
     *
     * @see http://apidocs.mailchimp.com/export/1.0/
     */
    const EXPORT_API_VERSION = '1.0';

    /**
     * @var string
     */
    protected $apiKey;

    /**
     * @param string $apiKey
     * @throws \Exception
     */
    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;

        BaseClient::__construct($apiKey);
    }

    /**
     * Execute exports API request.
     *
     * @param string $methodName Name of the export method - one of (list, ecommOrders, campaignSubscriberActivity)
     * @param array $parameters Parameters of export method
     * @return Response A plain text dump of JSON objects. The first row is a header row. Each additional row returned
     *                  is an individual JSON object. Rows are delimited using a newline (\n) marker, so
     *                  implementations can read in a single line at a time, handle it, and move on.
     */
    public function export($methodName, array $parameters)
    {
        $url = $this->getExportAPIMethodUrl($methodName);
        $parameters = array_merge(['apikey' => $this->apiKey], $parameters);
        $query = json_encode($parameters);
        $response = $this->callExportApi($url, $query);

        if (!$response->isSuccessful()) {
            throw BadResponseException::factory(
                $url,
                (string)$query,
                $response,
                'Request to MailChimp Export API wasn\'t successfully completed.'
            );
        }
        if (0 !== strpos($response->getContentType(), 'text/html')) {
            throw BadResponseException::factory(
                $url,
                (string)$query,
                $response,
                'Invalid response, expected content type is text/html'
            );
        }


        return $response;
    }
    /**
     * Pass export API method name and you'll get it's URL.
     *
     * @param string $methodName
     * @return string
     */
    protected function getExportAPIMethodUrl($methodName)
    {
        return sprintf(
            'https://%s.api.mailchimp.com/export/%s/%s/',
            $this->getApiServerNode(),
            self::EXPORT_API_VERSION,
            $methodName
        );
    }
    /**
     * @return string
     */
    protected function getApiServerNode()
    {
        $parts = array_pad(explode('-', $this->apiKey), 2, '');
        return end($parts);
    }

    /**
     * @param string $url
     * @param string $query
     * @return Response
     */
    protected function callExportApi($url, $query)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_FAILONERROR, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
        $message = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        $code = null;
        if ($info && array_key_exists('http_code', $info)) {
            $code = $info['http_code'];
        }
        if ($code !== 200 || !$message) {
            throw new \RuntimeException(
                sprintf('Server returned unexpected response. Response code %s', $code)
            );
        }

        return Response::fromMessage($message);
    }
}
