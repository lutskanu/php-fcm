<?php
namespace paragraph1\phpFCM;

use paragraph1\phpFCM\Recipient\Recipient;
use paragraph1\phpFCM\Recipient\Topic;
use paragraph1\phpFCM\Recipient\Device;

/**
 * @author palbertini
 */
class Message implements \JsonSerializable
{
    const PRIORITY_HIGH = 'high',
        PRIORITY_NORMAL = 'normal';

    private $encryptionHeaders = [];
    private $encryptedData;

    private $notification;
    private $collapseKey;

    /**
     * set priority to "high" by default. Otherwise iOS push notifications (apns) will not wake up app
     *
     * @var string
     */
    private $priority = self::PRIORITY_HIGH;
    private $data;
    private $recipients = array();
    private $recipientType;
    private $timeToLive;
    private $delayWhileIdle;

    /**
     * where should the message go
     *
     * @param Recipient $recipient
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     *
     * @return \paragraph1\phpFCM\Message
     */
    public function addRecipient(Recipient $recipient)
    {
        if (!$recipient instanceof Device && !$recipient instanceof Topic) {
            throw new \UnexpectedValueException('currently phpFCM only supports topic and single device messages');
        }

        if (!isset($this->recipientType)) {
            $this->recipientType = get_class($recipient);
        }

        if ($this->recipientType !== get_class($recipient)) {
            throw new \InvalidArgumentException('mixed recepient types are not supported by FCM');
        }
        if ($this->recipientType === Device::class && count($this->recipients) > 0) {
            throw new \InvalidArgumentException('when sending to single devices only one recipient is allowed');
        }

        $this->recipients[] = $recipient;
        return $this;
    }

    public function setNotification(Notification $notification)
    {
        $this->notification = $notification;
        return $this;
    }

    /**
     * @see https://firebase.google.com/docs/cloud-messaging/concept-options#collapsible_and_non-collapsible_messages
     *
     * @param string $collapseKey
     *
     * @return \paragraph1\phpFCM\Message
     */
    public function setCollapseKey($collapseKey)
    {
        $this->collapseKey = $collapseKey;
        return $this;
    }

    /**
     * normal or high, use class constants as value
     * @see https://firebase.google.com/docs/cloud-messaging/concept-options#setting-the-priority-of-a-message
     *
     * @param string $priority use the class constants
     *
     * @return \paragraph1\phpFCM\Message
     */
    public function setPriority($priority)
    {
        $this->priority = $priority;
        return $this;
    }

    /**
     * @see https://firebase.google.com/docs/cloud-messaging/concept-options#ttl
     *
     * @param integer $ttl
     *
     * @return \paragraph1\phpFCM\Message
     */
    public function setTimeToLive($ttl)
    {
        $this->timeToLive = $ttl;

        return $this;
    }

    /**
     * @see https://firebase.google.com/docs/cloud-messaging/concept-options#lifetime
     *
     * @param bool $delayWhileIdle
     *
     * @return \paragraph1\phpFCM\Message
     */
    public function setDelayWhileIdle($delayWhileIdle)
    {
        $this->delayWhileIdle = $delayWhileIdle;
        return $this;
    }

    public function setData(array $data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Web Push notifications requires payload encryption
     * @see https://developers.google.com/web/updates/2016/03/web-push-encryption
     * @see https://tools.ietf.org/html/draft-ietf-webpush-encryption-01
     * Payload encryption and headers generation should be outside this method.
     *
     * @param string $payload Encrypted payload
     * @param string $cryptoKey Serber public key
     * @param string $encryption Encryption salt
     * @param string $contentEncoding Content encoding algorithm
     *
     * @return \paragraph1\phpFCM\Message
     */
    public function setEncryptedData($payload, $cryptoKey, $encryption, $contentEncoding = 'aesgcm')
    {
        $this->encryptionHeaders['Encryption'] = $cryptoKey;
        $this->encryptionHeaders['Crypto-Key'] = $encryption;
        $this->encryptionHeaders['Content-Encoding'] = $contentEncoding;
        $this->encryptionHeaders['TTL'] = '0';

        $this->encryptedData = $payload;

        return $this;
    }

    /**
     * @return array
     */
    public function getEncryptionHeaders()
    {
        return $this->encryptionHeaders;
    }

    /**
     * @return mixed
     */
    public function getEncryptedData()
    {
        return $this->encryptedData;
    }

    public function jsonSerialize()
    {
        $jsonData = array();

        if (empty($this->recipients)) {
            throw new \UnexpectedValueException('message must have at least one recipient');
        }

        $this->createTo($jsonData);
        if ($this->collapseKey) {
            $jsonData['collapse_key'] = $this->collapseKey;
        }
        if ($this->data) {
            $jsonData['data'] = $this->data;
        }
        if ($this->priority) {
            $jsonData['priority'] = $this->priority;
        }
        if ($this->notification) {
            $jsonData['notification'] = $this->notification;
        }
        if ($this->timeToLive) {
            $jsonData['time_to_live'] = (int)$this->timeToLive;
        }
        if ($this->delayWhileIdle) {
            $jsonData['delay_while_idle'] = (bool)$this->delayWhileIdle;
        }

        return $jsonData;
    }

    private function createTo(array &$jsonData)
    {
        switch ($this->recipientType) {
            case Topic::class:
                if (count($this->recipients) > 1) {
                    $topics = array_map(
                        function (Topic $topic) { return sprintf("'%s' in topics", $topic->getIdentifier()); },
                        $this->recipients
                    );
                    $jsonData['condition'] = implode(' || ', $topics);
                    break;
                }
                $jsonData['to'] = sprintf('/topics/%s', current($this->recipients)->getIdentifier());
                break;
            default:
                if (count($this->recipients) === 1) {
                    $jsonData['to'] = current($this->recipients)->getIdentifier();
                }
        }
    }
}