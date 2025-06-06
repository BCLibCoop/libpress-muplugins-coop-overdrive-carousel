<?php

/**
 * OverDrive API Auth
 *
 * Driver for OAuth connection and API searching
 *
 * PHP Version 7
 *
 * @package           BCLibCoop\OverdriveCarousel\OverdriveAPI
 * @author            Erik Stainsby <eric.stainsby@roaringsky.ca>
 * @author            Jon Whipple <jon.whipple@roaringsky.ca>
 * @author            Jonathan Schatz <jonathan.schatz@bc.libraries.coop>
 * @author            Sam Edwards <sam.edwards@bc.libraries.coop>
 * @copyright         BC Libraries Cooperative
 * @license           GPL-2.0-or-later
 */

namespace BCLibCoop\OverdriveCarousel;

class OverdriveAPI
{
    public $config;
    private $token;
    private $library;
    private $client_auth;

    private $auth_uri = 'https://oauth.overdrive.com/token';
    private $api_base = 'https://api.overdrive.com/v1';

    private $user_agent = 'BC Libraries Coop Carousel v2';

    /**
     * Set up OverdriveAPI credentials
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->client_auth = base64_encode("{$this->config['clientkey']}:{$this->config['clientsecret']}");
        $this->token = get_site_transient(OverdriveCarousel::TRANSIENT_KEY . "_{$this->config['libID']}_token");
        $this->library = get_site_transient(OverdriveCarousel::TRANSIENT_KEY . "_{$this->config['libID']}_library");

        if (!$this->token) {
            $this->setToken();
        }

        if (!$this->library) {
            $this->setLibraryData();
        }
    }

    public function setToken()
    {
        $headers = [
            'Authorization' => "Basic {$this->client_auth}",
            'Content-Type' => 'application/x-www-form-urlencoded;charset=UTF-8',
        ];

        $response = wp_remote_post(
            $this->auth_uri,
            [
                'headers' => $headers,
                'body' => ['grant_type' => 'client_credentials'],
                'user-agent' => $this->user_agent,
            ]
        );

        if (
            wp_remote_retrieve_response_code($response) === 200
            && $data = json_decode(wp_remote_retrieve_body($response))
        ) {
            $this->token = $data->access_token;
            set_site_transient(
                OverdriveCarousel::TRANSIENT_KEY . "_{$this->config['libID']}_token",
                $this->token,
                (int) $data->expires_in
            );
        } else {
            throw new \Exception("Could not get auth token");
        }
    }

    public function setLibraryData()
    {
        $headers = [
            'Authorization' => "Bearer {$this->token}",
        ];

        $response = wp_remote_get(
            "{$this->api_base}/libraries/{$this->config['libID']}",
            [
                'headers' => $headers,
                'user-agent' => $this->user_agent,
            ]
        );

        if (
            wp_remote_retrieve_response_code($response) === 200
            && $data = json_decode(wp_remote_retrieve_body($response))
        ) {
            $this->library = $data;
            // Hopefully this is safe to cache for a while?
            set_site_transient(
                OverdriveCarousel::TRANSIENT_KEY . "_{$this->config['libID']}_library",
                $this->library,
                WEEK_IN_SECONDS
            );
        } else {
            throw new \Exception("Could not get library information");
        }
    }

    public function getNewestN($n, $formats = '')
    {
        $headers = [
            'Authorization' => "Bearer {$this->token}",
        ];

        $query_params = [
            'limit' => (int) $n,
            'offset' => 0,
            'sort' => 'dateadded:desc',
        ];

        // Limit formats to those supported by the library
        $formats = explode(',', $formats);
        $formats = array_filter($formats, function ($format) {
            return in_array($format, array_column($this->library->formats, 'id'));
        });

        if (!empty($formats)) {
            $query_params['formats'] = implode(',', $formats);
        }

        $response = wp_remote_get(
            add_query_arg($query_params, $this->library->links->products->href),
            [
                'headers' => $headers,
                'user-agent' => $this->user_agent,
            ]
        );

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (wp_remote_retrieve_response_code($response) === 200) {
            return isset($data['products']) ? $data['products'] : [];
        } else {
            throw new \Exception("Could not get product information: " . $data['message'] ?? 'Unknown');
        }
    }
}
