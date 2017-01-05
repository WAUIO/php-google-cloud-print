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
     * Printers ids
     *
     * @var string
     */
    public static $ZDESIGNER_PRINTER_ID = "93abebea-9460-6dda-4baf-dd106124f11f";
    public static $HPPRINTER_PRINTER_ID = "b19e6d3e-fc4f-1bdc-a1e8-9f0c6ea26afd";

    /**
     * All configs needed for the gc printer class
     *
     * @var array
     */
    protected $configs = [
        'redirect' => [
            'client_id'     => '',
            'redirect_uri'  => '',
            'response_type' => 'code',
            'scope'         => 'https://www.googleapis.com/auth/cloudprint',
        ],
        'auth'     => [
            'code'          => '',
            'client_id'     => '',
            'client_secret' => '',
            'redirect_uri'  => '',
            "grant_type"    => "authorization_code"
        ],
        'offline'  => [
            'access_type' => 'offline'
        ],
        'refresh'  => [
            'grant_type'    => "refresh_token",
            'refresh_token' => "",
            'client_id'     => "",
            'client_secret' => "",
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
        $this->bulkMergeConfs($configs);

        if (isset($_GET["code"]) && (is_null($this->getConf("refresh.refresh_token")) || $this->getConf("refresh.refresh_token") == "")) {

            $this->setConf("auth.code", $_GET['code']);

            parent::__construct();
            $accessToken = parent::getAccessToken($this->getConf("url.accesstoken_url"), http_build_query($this->getConf("auth")));
            echo "your refreshtoken is $accessToken->refresh_token";
            exit();
        }

        //check if refresh token is set so that we can redirect auth or just start to work
        if (is_null($this->getConf("refresh.refresh_token", null)) || $this->getConf("refresh.refresh_token") == "") {

            header("Location: ".$this->getConf("url.authorization_url", "https://accounts.google.com/o/oauth2/auth")."?".http_build_query(array_merge($this->getConf("redirect", []), $this->getConf("offline", []))));

        } else {

            //construct parent
            parent::__construct();

            $token = parent::getAccessTokenByRefreshToken($this->getConf("url.refreshtoken_url", null),  http_build_query($this->getConf("refresh")));

            parent::setAuthToken($token);

        }
    }

    /**
     * Merge the configurations
     * What is defined in parameters has higher priority
     *
     * @param array $configs
     * @return array
     */
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

    /**
     * Set the configurations in dot notation
     * Only work two levels
     *
     * @param $pathInDotNotation
     * @param $value
     */
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

    /**
     * Get the configurations from dot notation
     *
     * @param $pathInDotNotation
     * @param null $default
     * @return array|mixed|null
     */
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
