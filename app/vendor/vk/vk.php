<?php

namespace BW;

/* Note: This has been edited for Chevereto */

/**
 * The Vkontakte PHP SDK
 *
 * @author Bocharsky Victor
 */
class Vkontakte
{
    /**
     * The API version used in queries
     */
    const API_VERSION = '5.26';

    /**
     * The client ID (app ID)
     * @var string
     */
    private $clientId;
    
    /**
     * The client secret key
     * @var string
     */
    private $clientSecret;
    
    /**
     * The scope for login URL
     * @var array
     */
    private $scope = array();
    
    /**
     * The URL to which the user will be redirected
     * @var string
     */
    private $redirectUri;
    
    /**
     * The response type of login URL
     * @var string
     */
    private $responseType = 'code';
    
    /**
     * The current access token
     * @var array
     */
    private $accessToken;
    

    /**
     * The Vkontakte instance constructor for quick configuration
     * @param array $config
     */
    public function __construct(array $config = array())
    {
        if (isset($config['client_id'])) {
            $this->setClientId($config['client_id']);
        }
        if (isset($config['client_secret'])) {
            $this->setClientSecret($config['client_secret']);
        }
        if (isset($config['scope'])) {
            $this->setScope($config['scope']);
        }
        if (isset($config['redirect_uri'])) {
            $this->setRedirectUri($config['redirect_uri']);
        }
        if (isset($config['response_type'])) {
            $this->setResponseType($config['response_type']);
        }
    }
    
    
    /**
     * Get the user id of current access token
     * 
     * @return string|null
     */
    public function getUserId()
    {
        return isset($this->accessToken['user_id']) ? $this->accessToken['user_id'] : null;
    }
    
    /**
     * Get the login URL for Vkontakte sign in
     * 
     * @return string
     */
    public function getLoginUrl()
    {
        return 'https://oauth.vk.com/authorize?' . http_build_query(array(
            'client_id'     => $this->getClientId(),
            'scope'         => implode(',', $this->getScope()),
            'redirect_uri'  => $this->getRedirectUri(),
            'response_type' => $this->getresponseType(),
            'v'             => self::API_VERSION,
        ));
    }
    
    /**
     * Authenticate user and get access token from server
     * @param string $code
     * 
     * @return $this
     */
    public function authenticate($code = null)
    {
        if (null === $code) {
            if (isset($_GET['code'])) {
                $code = $_GET['code'];
            }
        }
            
        $url = 'https://oauth.vk.com/access_token?' . http_build_query(array(
            'client_id'     => $this->getClientId(),
            'client_secret' => $this->getClientSecret(),
            'code'          => $code,
            'redirect_uri'  => $this->getRedirectUri(),
        ));

        $token = $this->curl($url);
        $decodedToken = json_decode($token, true);
        $decodedToken['created'] = time(); // add access token created unix timestamp to array
        
        $this->setAccessToken($decodedToken);

        return $this;
    }
    
    /**
     * Make an API call to https://api.vk.com/method/
     * 
     * @return mixed The response
     */
    public function api($method, array $query = array())
    {
        /* Generate query string from array */
        foreach ($query as $param => $value) {
            if (is_array($value)) {
                // implode values of each nested array with comma
                $query[$param] = implode(',', $value);
            }
        }
        $query['access_token'] = isset($this->accessToken['access_token'])
            ? $this->accessToken['access_token'] 
            : '';
        $url = 'https://api.vk.com/method/' . $method . '?' . http_build_query($query);
        $result = json_decode($this->curl($url), true);
        
        if (isset($result['response'])) {
            return $result['response'];
        }
        
        return $result;
    }
    
    /**
     * Check is access token expired
     * 
     * @return boolean
     */
    public function isAccessTokenExpired()
    {
        return time() > $this->accessToken['created'] + $this->accessToken['expires_in'];
    }
    
    /**
     * Set the client ID (app ID)
     * @param string $clientId
     * 
     * @return $this
     */
    public function setClientId($clientId)
    {
        $this->clientId = $clientId;
        
        return $this;
    }
    
    /**
     * Get the client ID (app ID)
     * 
     * @return string
     */
    public function getClientId()
    {
        return $this->clientId;
    }
    
    /**
     * Set the client secret key
     * @param string $clientSecret
     * 
     * @return $this
     */
    public function setClientSecret($clientSecret)
    {
        $this->clientSecret = $clientSecret;
        
        return $this;
    }
    
    /**
     * Get the client secret key
     * 
     * @return string
     */
    public function getClientSecret()
    {
        return $this->clientSecret;
    }
    
    /**
     * Set the scope for login URL
     * @param array $scope
     * 
     * @return $this
     */
    public function setScope(array $scope)
    {
        $this->scope = $scope;
        
        return $this;
    }
    
    /**
     * Get the scope for login URL
     * 
     * @return array
     */
    public function getScope()
    {
        return $this->scope;
    }
    
    /**
     * Set the URL to which the user will be redirected
     * @param string $redirectUri
     * 
     * @return $this
     */
    public function setRedirectUri($redirectUri)
    {
        $this->redirectUri = $redirectUri;
        
        return $this;
    }
    
    /**
     * Get the URL to which the user will be redirected
     * 
     * @return string
     */
    public function getRedirectUri()
    {
        return $this->redirectUri;
    }
    
    /**
     * Set the response type of login URL
     * @param string $responseType
     * 
     * @return $this
     */
    public function setResponseType($responseType)
    {
        $this->responseType = $responseType;
        
        return $this;
    }
    
    /**
     * Get the response type of login URL
     * 
     * @return string
     */
    public function getresponseType()
    {
        return $this->responseType;
    }
    
    /**
     * Set the access token
     * @param string|array $token The access token in json|array format
     * 
     * @return $this
     */
    public function setAccessToken($token)
    {
        if (is_string($token)) {
            $this->accessToken = json_decode($token, true);
        } else {
            $this->accessToken = (array)$token;
        }
        
        return $this;
    }
    
    /**
     * Get the access token
     * 
     * @return array|null The access token
     */
    public function getAccessToken()
    {
        return is_null($this->accessToken) ? NULL : json_encode($this->accessToken);
    }
    
    /**
     * Make the curl request to specified url
     * @param string $url The url for curl() function
     * 
     * @return mixed The result of curl_exec() function
     * 
     * @throws \Exception
     */
    protected function curl($url)
    {
        // create curl resource
        $ch = curl_init();

        // set url
        curl_setopt($ch, CURLOPT_URL, $url);
        // return the transfer as a string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        // disable SSL verifying
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        // $output contains the output string
        $result = curl_exec($ch);
        
        if ( ! $result) {
            $errno = curl_errno($ch);
            $error = curl_error($ch);
        }
        
        // close curl resource to free up system resources
        curl_close($ch);
        
        if (isset($errno) && isset($error)) {
            throw new \Exception($error, $errno);
        }

        return $result;
    }
}