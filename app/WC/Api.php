<?php 
namespace App\WC;

use Automattic\WooCommerce\Client;

class Api {
    public static function wp($version="wc/v3"){
        return new Client(
            'http://palihugco.bienz.tech/', 
            'ck_cb16d705fa774d3073076d3a65b1878bc0c14279', 
            'cs_6a1a53e180dafc36ffa0ff0cc875cadc45a9ab0a',
            [
                'version' => $version,// wp/v2 wcfmmp/v1 wc/v3
            ]
        );
    }
}