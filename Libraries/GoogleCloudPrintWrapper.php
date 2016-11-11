<?php
/**
 * Description :
 *
 *
 */

namespace GCPrint;


class GoogleCloudPrintWrapper
{
    /**
     * All configs needed for the gc printer class
     *
     * @var array
     */
    protected $configs = [
        'redirect' => [
            'client_id'     => 'YOUR-CLIENT-ID',
            'redirect_uri'  => 'http://yourdomain.com/oAuthRedirect.php',
            'response_type' => 'code',
            'scope'         => 'https://www.googleapis.com/auth/cloudprint',
        ],
        'auth'     => [
            'code'          => '',
            'client_id'     => 'YOUR-CLIENT-ID',
            'client_secret' => 'YOUR-CLIENT-SECRET',
            'redirect_uri'  => 'http://yourdomain.com/oAuthRedirect.php',
            "grant_type"    => "authorization_code"
        ],
        'offline'  => [
            'access_type' => 'offline'
        ],
        'refresh'  => [
            'refresh_token' => "",
            'client_id'     => ""/*$this->configs['auth']['client_id']*/,
            'client_secret' => ""/*$this->configs['auth']['client_secret']*/,
            'grant_type'    => "refresh_token",
        ],
        "url"      => [
            'authorization_url' => 'https://accounts.google.com/o/oauth2/auth',
            'accesstoken_url'   => 'https://accounts.google.com/o/oauth2/token',
            'refreshtoken_url'  => 'https://www.googleapis.com/oauth2/v3/token'
        ]
    ];

    public function __construct ($configs = [])
    {
        $this->configs = $this->bulkMergeConfs($configs);
    }

    protected function bulkMergeConfs (array $configs)
    {
        

        return [];
    }

    public function setConf ($pathInDotNotation, $value)
    {
        //breakdown dot notation
        $keys = explode(".", $pathInDotNotation);

        $destination = &$this->configs;

        foreach ($keys as $key) {
            $destination = &$destination [$key];
        }

        $destination = $value;
    }

    public function getConf ($pathInDotNotation, $default = null)
    {
        //breakdown dot notation
        $keys = explode(".", $pathInDotNotation);

        $destination = &$this->configs;

        foreach ($keys as $key) {

            if (array_key_exists($key, $destination)) {
                $destination = &$destination [$key];
            } else {
                return $default;
            }
        }

        return isset($destination) ? $destination : $default;

    }
}