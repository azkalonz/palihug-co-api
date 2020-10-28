<?php
namespace App\WC;

use Automattic\WooCommerce\Client;

class Api {
    public static function wp($version="wc/v3"){
        return new Client(
            config('app.wp_url'),
            'ck_981d613089e9e812f01107c1a46d5acf393a9391',
            'cs_e466e84801c7544dea50872663eca4bc187d2a76',
            [
                'version' => $version,// wp/v2 wcfmmp/v1 wc/v3
            ]
        );
    }
}
