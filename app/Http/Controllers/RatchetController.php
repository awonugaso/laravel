<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use ccxt\binance;

class RatchetController extends Controller implements MessageComponentInterface
{
    protected $clients;
    protected $binance;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->binance = new Binance(array(
            'apiKey' => 'YOUR_API_KEY',
            'secret' => 'YOUR_API_SECRET',
        ));
    }

    public function onOpen(ConnectionInterface $conn) {
        // Store the new connection to send messages to later
        $this->clients->attach($conn);

        // Subscribe to the Binance API
        $this->binance->wsConnect();
        $this->binance->wsSubscribeTrades("BTC/USDT");
        $this->binance->wsSubscribeTrades("ETH/USDT");

        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $numRecv = count($this->clients) - 1;
        echo sprintf('Connection %d sending message "%s" to %d other connection%s' . "\n"
            , $from->resourceId, $msg, $numRecv, $numRecv == 1 ? '' : 's');

        foreach ($this->clients as $client) {
            if ($from !== $client) {
                // The sender is not the receiver, send to each client connected
                $client->send($msg);
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        // The connection is closed, remove it, as we can no longer send it messages
        $this->clients->detach($conn);

        // Unsubscribe from the Binance API
        $this->binance->wsUnsubscribeTrades("BTC/USDT");
        $this->binance->wsUnsubscribeTrades("ETH/USDT");
        $this->binance->wsClose();

        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";

        $conn->close();
    }
}
