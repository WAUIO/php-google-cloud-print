<?php
/**
 * Description :
 *
 *
 */

namespace GCPrint;


class GoogleCloudPrintWrapper extends GoogleCloudPrint
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
        //set the first two values that are missing
        $this->setConf("refresh.client_id", $this->getConf("auth.client_id"));
        $this->setConf("refresh.client_secret", $this->getConf("auth.client_secret"));

        //merge with users configs
        $this->configs = $this->bulkMergeConfs($configs);

        if (isset($_GET["code"]) && is_null($this->getConf("refresh.refresh_token"))) {
            parent::__construct();
            $accessToken = parent::getAccessToken($this->getConf("url.accesstoken_url"), $this->getConf("auth"));
            echo "your refreshtoken is $accessToken->refresh_token";
            exit();
        }

        //check if refresh token is set so that we can redirect auth or just start to work
        if (is_null($this->getConf("refresh.refresh_token", null))) {
            header("Location: ".$this->getConf("url.authorization_url", "https://accounts.google.com/o/oauth2/auth")."?".http_build_query(array_merge($this->getConf("redirect", []), $this->getConf("offline", []))));

        } else {

            //construct parent
            parent::__construct();

            $token = parent::getAccessTokenByRefreshToken($this->getConf("url.refreshtoken_url", null),  http_build_query($this->getConf("refresh")));

            parent::setAuthToken($token);

        }
    }

    protected function bulkMergeConfs (array $configs)
    {
        foreach ($configs as $key => $config) {

            if (array_key_exists($key, $this->configs)) {
                foreach ($config as $innerKey => $inner_conf) {
                    $this->setConf($key.".".$innerKey, $inner_conf);
                }
            } else {
                $this->setConf($key, $config);
            }
        }

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