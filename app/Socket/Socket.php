<?php 
namespace App\Socket;

use ElephantIO\Client;
use ElephantIO\Engine\SocketIO\Version2X;

class Socket {
    public static function broadcast($event,$args){
        $client = new Client(new Version2X(config('app.socket_url')));
        $client->initialize();
        $client->emit($event,$args);
        $client->close();
    }
}