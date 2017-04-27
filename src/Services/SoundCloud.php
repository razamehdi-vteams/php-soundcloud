<?php

namespace Symphonic\SoundCloud\Services;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\BadResponseException as HttpBadResponseException;
use GuzzleHttp\Exception\ClientException as HttpClientException;
use GuzzleHttp\Exception\ServerException as HttpServerException;

class SoundCloud
{
    /**
     * @var bool
     */
    protected $sandBox;

    /**
     * SoundCloud OAuth client.
     *
     * @var string
     */
	protected $httpClient;

    /**
     * Client Id to be used for SoundCloud API.
     *
     * @var string
     */
	protected $clientId;

    /**
     * Client secret to be used for SoundCloud API.
     *
     * @var string
     */
	protected $clientSecret;

    /**
     * SoundCloud app Redirect URL.
     *
     * @var string
     */
    protected $redirectUri;

	/**
     * Access token returned by SoundCloud to be used for API operations.
     *
     * @var string
     */
    protected $accessToken;

    /**
     * SoundCloud API request URL.
     *
     * @var string
     */
    protected $apiUrl;

    /**
     * Collection object containing SoundCloud request data.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $request;

    /**
     * SoundCloud API request headers.
     *
     * @var array
     */
    protected $headers;

    /**
     * SoundCloud constructor.
     *
     * @param string $clientId
     * @param string $clientSecret
     * @param string $redirectUri
     */
	public function __construct($clientId, $clientSecret, $redirectUri)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectUri = $redirectUri;

        $this->httpClient = new HttpClient;
        $this->sandBox = false;
        $this->headers['Accept'] = 'application/json';
    }

    /**
     * Generate SoundCloud Authorization URL so user can grant access to account.
     *
     * @return string
     */
    public function connect()
    {
        $this->setupRequest(
            [
                'scope'         => 'non-expiring',
                'display'       => 'popup',
                'response_type' => 'code',
            ],
            ['client_secret']
        );

        return $this->buildRequestUrl('connect');
    }

    /**
     * Get access token from SoundCloud API.
     *
     * @param string $code
     * @param string $grant_type
     *
     * @return array|string
     */
    public function authToken($code, $grant_type = 'authorization_code')
    {
        $this->setupRequest([
            'grant_type'    =>  $grant_type,
            'code'          =>  $code
        ]);

        $this->apiUrl = $this->buildRequestUrl('oauth2/token');

        return $this->doSoundCloudRequest('post');
    }

    /**
     * Set access token.
     *
     * @param string $token
     */
    public function setAccessToken($token)
    {
        $this->accessToken = $token;

        $this->headers['Authorization'] = 'OAuth '.$token;
    }

    public function uploadTrack($data)
    {
        $post = [];
        foreach ($data as $key => $value) {
            $post['track['.$key.']'] = $value;
        }

        $this->setupRequest($post);

        $this->apiUrl = $this->buildRequestUrl('tracks');

        return $this->doSoundCloudRequest('post');
    }

    /**
     * Get current logged in user details from SoundCloud.
     *
     * @param string $token
     *
     * @return array|string
     */
    public function currentUser($token)
    {
        $this->request = collect([
            'oauth_token'  =>  $token
        ]);

        $this->apiUrl = $this->buildRequestUrl('me');

        return $this->doSoundCloudRequest('get');
    }

    /**
     * Setup Http Request packet.
     *
     * @param array $request
     * @param array $skip
     *
     * @return void
     */
    protected function setupRequest($request, $skip = [])
    {
        $this->request = collect([
            'client_id'         =>  $this->clientId,
            'client_secret'     =>  $this->clientSecret,
            'redirect_uri'      =>  $this->redirectUri,
        ])->merge($request)->except($skip);
    }

    /**
     * Perform SoundCloud API request.
     *
     * @param string $type
     * @return mixed
     * @throws \Exception
     */
    public function doSoundCloudRequest($type = 'post')
    {
        $bodyParam = ($type == 'get') ? 'query' : 'form_params';

        $options = [
            $bodyParam => $this->request->toArray()
        ];

        if (!empty($this->headers)) {
            $options['headers'] = $this->headers;
        }

        try {
            $response = $this->httpClient->$type(
                $this->apiUrl,
                $options
            )->getBody();

            return \GuzzleHttp\json_decode($response, true);
        } catch (HttpClientException $e) {
            throw new \Exception($e->getMessage());
        } catch (HttpServerException $e) {
            throw new \Exception($e->getMessage());
        } catch (HttpBadResponseException $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * Create SoundCloud API URI.
     *
     * @param string $path
     *
     * @return string
     */
    protected function buildRequestUrl($path)
    {
        $url = 'https://';
        $url .= (!preg_match('/connect/', $path)) ? 'api.' : '';
        $url .= ($this->sandBox) ? 'sandbox-soundcloud.com' : 'soundcloud.com';
        $url .= '/';
        $url .= $path;

        if (preg_match('/connect/', $path)) {
            $url .= !($this->request->isEmpty()) ? '?' . http_build_query(
                    $this->request->toArray()
                ) : '';
        }

        return $url;
    }
}
