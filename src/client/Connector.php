<?php

namespace smpp\client;

use smpp\Address;
use smpp\Client;
use smpp\Client as SmppClient;
use smpp\SMPP;
use smpp\transport\Socket;

class Connector
{

    const DEFAULT_SENDER = 'GOV.AZ';
    protected $transport;
    protected $smppClient;
    protected $debug = false;
    protected $bound = false;
    protected $from;
    protected $to;
    protected $login;
    protected $password;
    protected $recipients = [];

    /**
     * SmsBuilder constructor.
     * @param string $address SMSC IP
     * @param int $port SMSC port
     * @param string $login
     * @param string $password
     * @param int $timeout timeout of reading PDU in milliseconds
     * @param bool $debug - debug flag when true output additional info
     */
    public function __construct(
        string $address,
        int    $port,
        string $login,
        string $password,
        int    $timeout = 10000,
        bool   $debug = false
    )
    {
        $this->transport = new Socket([$address], $port);
        $this->transport->setRecvTimeout($timeout);
        $this->smppClient = new SmppClient($this->transport);

        // Activate binary hex-output of server interaction
        $this->smppClient->debug = $debug;
        $this->transport->debug = $debug;

        $this->login = $login;
        $this->password = $password;

        $this->from = new Address(self::DEFAULT_SENDER, SMPP::TON_ALPHANUMERIC);
    }

    public function setRecipient($address, $ton): static
    {
        $this->addRecipient($address, $ton);
        return $this;
    }


    public function systemType(string $type = 'WWW'): static
    {
        Client::$systemType = $type;
        return $this;
    }


    protected function addRecipient($address, $ton = SMPP::TON_UNKNOWN, $npi = SMPP::NPI_UNKNOWN)
    {
        if ($ton === SMPP::TON_INTERNATIONAL) {
            $npi = SMPP::NPI_E164;
        }
        $this->recipients[] = new Address($address, $ton, $npi);
        return $this;
    }

    public function bindSocket(): void
    {
        if (!$this->transport->isOpen()) $this->transport->open();
        if (!$this->bound) {
            $this->bound = true;
            $this->smppClient->bindTransceiver($this->login, $this->password);
        }
    }


    public function sendMessage(string $message)
    {
        $this->bindSocket();

        return $this->smppClient->sendSMS($this->from, $this->recipients[0], $message, null, SMPP::DATA_CODING_UCS2);
    }

    public function sendBulkMessages(string $message)
    {
        $result = [];
        $this->bindSocket();

        foreach ($this->recipients as $recipient) {
            $result[$recipient->value] = $this->smppClient->sendSMS($this->from, $recipient, $message, null, SMPP::DATA_CODING_UCS2);
        }
        return $result;
    }

    public function read(): bool|\smpp\DeliveryReceipt|\smpp\Sms
    {
        $this->bindSocket();
        return $this->smppClient->readSMS();
    }


    public function checkDeliveryQuery(string $messageId, $number): bool|array|null
    {
        $this->bindSocket();
        return $this->smppClient->queryStatus($messageId, new Address($number));
    }


    public function close(): void
    {
        $this->bound = false;
        $this->smppClient->close();
    }

    public function smppClient(): SmppClient
    {
        return $this->smppClient;
    }
}
