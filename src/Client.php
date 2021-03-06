<?php
namespace paragraph1\phpFCM;

use GuzzleHttp;

/**
 * @author palbertini
 */
class Client implements ClientInterface
{
    const DEFAULT_API_URL = 'https://fcm.googleapis.com/fcm/send';

    /** @var string */
    private $apiKey;

    /** @var string */
    private $proxyApiUrl;

    /** @var GuzzleHttp\ClientInterface */
    private $guzzleClient;

    public function injectHttpClient(GuzzleHttp\ClientInterface $client)
    {
        $this->guzzleClient = $client;
    }

    /**
     * add your server api key here
     * read how to obtain an api key here: https://firebase.google.com/docs/server/setup#prerequisites
     *
     * @param string $apiKey
     *
     * @return \paragraph1\phpFCM\Client
     */
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    /**
     * people can overwrite the api url with a proxy server url of their own
     *
     * @param string $url
     *
     * @return \paragraph1\phpFCM\Client
     */
    public function setProxyApiUrl($url)
    {
        $this->proxyApiUrl = $url;
        return $this;
    }

    /**
     * sends your notification to the google servers and returns a guzzle repsonse object
     * containing their answer.
     *
     * @param Message $message
     *
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\RequestException
     */
    public function send(Message $message)
    {
        $headers = [
            'Authorization' => sprintf('key=%s', $this->apiKey),
            'Content-Type' => 'application/json',
        ];

        if (count($message->getEncryptionHeaders()) > 0) {
            $headers = array_merge($headers, $message->getEncryptionHeaders());
            $body = $message->getEncryptedData();
        } else {
            $body = json_encode($message);
        }

        return $this->guzzleClient->post(
            $this->getApiUrl(),
            [
                'headers' => $headers,
                'body' => $body,
            ]
        );
    }

    private function getApiUrl()
    {
        return isset($this->proxyApiUrl) ? $this->proxyApiUrl : self::DEFAULT_API_URL;
    }
}