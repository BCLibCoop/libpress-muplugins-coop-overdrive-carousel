<?php

/**
 * OverDrive API Auth
 *
 * Driver for OAuth connection and API searching
 *
 * PHP Version 7
 *
 * @package           BCLibCoop\OverdriveCarousel\ODauth
 * @author            Erik Stainsby <eric.stainsby@roaringsky.ca>
 * @author            Jon Whipple <jon.whipple@roaringsky.ca>
 * @author            Jonathan Schatz <jonathan.schatz@bc.libraries.coop>
 * @author            Sam Edwards <sam.edwards@bc.libraries.coop>
 * @copyright         2013-2022 BC Libraries Cooperative
 * @license           GPL-2.0-or-later
 */

namespace BCLibCoop;

class ODauth
{
    public $province;
    public $caturl;
    private $libID;
    private $clientkey;
    private $clientsecret;

    private $auth_uri = 'https://oauth.overdrive.com/token';
    private $account_uri = 'https://api.overdrive.com/v1/libraries';

    /**
     * Set up ODauth credentials
     *
     * @todo: Pull these from a .env var so secrets aren't stored in the code
     */
    public function __construct($config)
    {
        // Get province from library shortcode 1st letter
        $shortcode = get_blog_option(get_current_blog_id(), '_coop_sitka_lib_shortname', '');

        if (preg_match('%(^[A-Z]{1})%', $shortcode, $matches)) {
            $shortcode_prov = $matches[1];

            foreach ($config as $province => $config) {
                if ($shortcode_prov === $province[0]) {
                    $this->province = $province;
                    $this->libID = $config['libID'];
                    $this->clientkey = $config['clientkey'];
                    $this->clientsecret = $config['clientsecret'];
                    $this->caturl = $config['caturl'];
                    break;
                }
            }
        }

        if (!$this->province) {
            throw new \Exception("No valid config!");
        }
    }

    public function getToken()
    {
        $hash = base64_encode($this->clientkey . ':' . $this->clientsecret);
        $authheader = [
            'Authorization: Basic ' . $hash,
            'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
        ];
        $bodydata = 'grant_type=client_credentials';

        $ch = curl_init($this->auth_uri);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $authheader);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $bodydata);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $json = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($json);
        $token = $data->access_token;

        return $token;
    }

    public function getProductLink($token)
    {
        $userip = $_SERVER['REMOTE_ADDR'];

        $ch = curl_init($this->account_uri . '/' . $this->libID);

        curl_setopt($ch, CURLOPT_HTTPGET, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token, 'X-Forwarded-For: ' . $userip]);
        curl_setopt($ch, CURLOPT_USERAGENT, 'BC Libraries Coop Carousel v2');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $json = curl_exec($ch);
        curl_close($ch);

        $account = json_decode($json);

        $url = $account->links->products->href;
        $type = $account->links->products->type;

        return ['url' => $url, 'type' => $type];
    }

    public function getNewestN($token, $link, $n)
    {
        $userip = $_SERVER['REMOTE_ADDR'];

        $ch = curl_init($link['url'] . '/?limit=' . $n . '&offset=0&sort=dateadded:desc');

        curl_setopt($ch, CURLOPT_HTTPGET, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token, 'X-Forwarded-For: ' . $userip]);
        curl_setopt($ch, CURLOPT_USERAGENT, 'BC Libraries Coop Carousel v2');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $json = curl_exec($ch);
        curl_close($ch);

        $r = json_decode($json);

        return isset($r->products) ? $r->products : [];
    }
}
