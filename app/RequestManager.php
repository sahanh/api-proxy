<?php
namespace App;

use Predis\Client;
use Symfony\Component\HttpFoundation\Response;
use Proxy\Factory;
use Proxy\Response\Filter\RemoveEncodingFilter;


class RequestManager
{
    protected $cache;

    protected $token_key = 'app:token';

    protected $api_base  = 'http://api.com';

    public function __construct()
    {
        $this->cache = new Client;
        $this->proxy = Factory::create();
        $this->proxy->addResponseFilter(new RemoveEncodingFilter());
    }

    public function execute($request)
    {
        //generate cache key
        $key = $this->generateCacheKey($request);

        //check if exist in cache
        //if exist return
        if ($content = $this->cache->get($key)) {
            
            $response = Response::create($content, 200, ['Content-Type' => 'application/json']);

        } else {

            $token = $this->getToken();
            $request->headers->set('Authorization', "Bearer {$token}");

            //do request
            $forward_url = $this->api_base.$request->getPathInfo();
            $response    = $this->proxy->forward($request)->to($forward_url);

            //token expired refresh and try again
            if ($response->getStatusCode() == 401) {
                $token    = $this->generateToken();
                $request->headers->set('Authorization', "Bearer {$token}");
                $response = $this->proxy->forward($request)->to($forward_url);
            }

            //if status is 200 save in cache
            if ($response->getStatusCode() == 200) {
                $this->cache->set($key, $response->getContent());
                $this->cache->expire($key, 3600 * 12); //clear in 12 hours
            }
        }

        //output response
        return $response;
    }

    private function generateCacheKey($request)
    {
        $path    = $request->getPathInfo();
        $content = $request->getContent();

        return 'app:'.sha1($path.$content);
    }

    private function generateToken()
    {
        $url   = '<url>';
        $auth  = "Basic <token>";

        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\nAuthorization: {$auth}",
                'method'  => 'POST',
                'content' => '',
            ]
        ];

        $context  = stream_context_create($options);
        $result   = @file_get_contents($url, false, $context);
        
        if (!$result)
            throw new APICallException("Unable to refresh access token.");
        else {
            $result = json_decode($result);
            $this->cache->set($this->token_key, $result->access_token);
            return $result->access_token;
        }
    }

    private function getToken()
    {
        if ($token = $this->cache->get($this->token_key)) {
            return $token;
        } else {
            return $this->generateToken();
        }
    }
}