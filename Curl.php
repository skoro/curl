<?php
/**
 * @author Skorobogatko Alexei <skorobogatko.oleksii@gmail.com>
 * @copyright 2015
 * @version $Id$
 * @since 1.0.0
 */

namespace skoro\curl;

/**
 * Wrapper for curl functions.
 *
 * Simple usage, GET request:
 * ```
 * $content = Curl::get('google.com');
 * ```
 *
 * HEAD request:
 * ```
 * $curl = new Curl('google.com', 'HEAD');
 * $body = $curl->request(); // Returns response with headers.
 * if ($curl->getStatusCode() == 200) {
 *          $curl->getResponse(); // Returns "raw" response.
 *          $curl->getResponseHeaders(); // Returns array of headers.
 * }
 * ```
 * 
 * @author skoro
 */
class Curl
{
    
    /**
     * User agent sample strings.
     */
    const UA_FIREFOX = 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:41.0) Gecko/20100101 Firefox/41.0';
    const UA_CHROME = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/46.0.2490.71 Safari/537.36';
    const UA_IE10 = 'Mozilla/5.0 (Windows NT 6.1; WOW64; Trident/7.0; AS; rv:11.0) like Gecko';

    /**
     * @var resource Curl connection handler.
     */
    protected $handler = null;
    
    /**
     * @var array Curl options.
     */
    protected $options = [];
    
    /**
     * @var integer Last status code.
     */
    protected $status = 0;
    
    /**
     * @var array Request http headers.
     */
    protected $headers = [];
    
    /**
     * @var array
     */
    protected $responseHeaders = [];
    
    /**
     * @var string Response body.
     */
    protected $response = '';
    
    /**
     * @var array Response info.
     */
    protected $responseInfo = [];
    
    /**
     * Create curl instance.
     * @param string $url
     * @param string $method
     * @param array $options
     */
    public function __construct($url = '', $method = 'GET', $options = [])
    {
        $this->handler = curl_init();
        
        $this->reset();

        if (!empty($url)) {
            $this->setUrl($url);
        }
        $this->prepareOptions($method, $options);
    }
    
    /**
     * Destroy instance.
     */
    public function __destruct()
    {
        if (is_resource($this->handler)) {
            curl_close($this->handler);
            $this->handler = null;
        }
    }
    
    /**
     * Reset curl state.
     * @return Curl
     */
    public function reset()
    {
        curl_reset($this->handler);
        
        $this->options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => 10,
        ];
        
        $this->headers = [];
        $this->responseHeaders = [];
        $this->response = '';
        $this->responseInfo = [];
        $this->status = 0;
        
        return $this;
    }

    /**
     * Set destination url.
     * @param string $url
     * @return Curl
     */
    public function setUrl($url)
    {
        $this->options[CURLOPT_URL] = $url;
        return $this;
    }
    
    /**
     * Get a request or redirected url.
     * @param boolean $src on true returns initial requested url, on false
     *                     returns redirected url if redirect happen.
     * @see Curl::isRedirected()
     * @return string|null
     */
    public function getUrl($src = false)
    {
        if (!$src && $this->isRedirected()) {
            return $this->responseInfo['url'];
        }
        return isset($this->options[CURLOPT_URL]) ? $this->options[CURLOPT_URL] : null;
    }

    /**
     * Set a request method.
     * @param string $method
     * @return Curl
     */
    public function setMethod($method)
    {
        $this->options[CURLOPT_CUSTOMREQUEST] = $method;
        return $this;
    }
    
    /**
     * Get a request method.
     * @return string
     */
    public function getMethod()
    {
        return isset($this->options[CURLOPT_CUSTOMREQUEST]) ?
                                $this->options[CURLOPT_CUSTOMREQUEST] : 'GET';
    }
    
    /**
     * Get curl options.
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }
    
    /**
     * Set curl option.
     * @param integer $option
     * @param mixed   $value
     * @return Curl
     */
    public function setOption($option, $value)
    {
        $this->options[$option] = $value;
        return $this;
    }
    
    /**
     * Add http header to request.
     * @param string $header
     * @param string $value
     * @return Curl
     */
    public function addRequestHeader($header, $value)
    {
        $this->headers[$header] = $value;
        return $this;
    }
    
    /**
     * Add http headers or clear already added headers.
     * @param array $headers List of headers. If not defined, headers will be cleared.
     * @return Curl
     */
    public function addRequestHeaders($headers = [])
    {
        $this->headers = $headers;
        return $this;
    }
    
    /**
     * Collect response headers.
     * @see Curl::getResponseHeaders()
     * @param boolean $include
     * @return Curl
     */
    public function withHeaders($include = true)
    {
        $this->options[CURLOPT_HEADER] = $include;
        return $this;
    }
    
    /**
     * Wrapper for http GET method.
     * @see Curl::request()
     * @param string $url
     * @param array  $options
     * @return string|false
     */
    public static function get($url, $options = [])
    {
        $curl = new self($url, 'GET', $options);
        return $curl->request();
    }
    
    /**
     * Wrapper for http POST method.
     * @see Curl::request()
     * @param string $url
     * @param array  $options
     * @return string|false
     */
    public static function post($url, $data, $options = [])
    {
        $curl = new self($url, 'POST', [
            CURLOPT_POSTFIELDS => http_build_query($data),
        ]);
        return $curl->request('POST', $options);
    }

    /**
     * Do a request.
     * @param string $method
     * @param array  $options Optional. Override default options.
     * @return string|integer|false Returns response body or status code for
     *                              HEAD request or false if request failed.
     * @throws \RuntimeException When request failed.
     * @throws HttpException When response status code is 404.
     */
    public function request($method = null, $options = [])
    {
        return $this->prepareOptions($method, $options)
             ->doRequest()
             ->completeRequest();
    }
    
    /**
     * Prepares a request options.
     * @param string $method  Request method.
     * @param array  $options Optional. Override default options.
     * @return Curl
     */
    public function prepareOptions($method = null, $options = [])
    {
        if ($method !== null) {
            $this->setMethod($method);
        }
        
        // Manual merge options. Fix bug when CURLOPT_RETURNTRANSFER must be
        // before CURLOPT_FILE.
        foreach ($options as $id => $val) {
            $this->options[$id] = $val;
        }
        
        if ($method === 'HEAD') {
            $this->options[CURLOPT_NOBODY] = true;
            $this->options[CURLOPT_HEADER] = true;
        }
        
        if (!empty($this->headers)) {
            $headers = [];
            foreach ($this->headers as $k => $v) {
                $headers[] = "$k: $v";
            }
            $this->options[CURLOPT_HTTPHEADER] = $headers;
        }

        curl_setopt_array($this->handler, $this->options);
        
        return $this;
    }
    
    /**
     * Performs a request.
     * @return Curl
     * @throws \RuntimeException When request is failed.
     */
    protected function doRequest()
    {
        if (($this->response = curl_exec($this->handler)) === false) {
            throw new \RuntimeException(curl_error($this->handler), curl_errno($this->handler));
        }
        $this->responseInfo = curl_getinfo($this->handler);
        $this->status = $this->responseInfo['http_code'];
        return $this;
    }
    
    /**
     * Reads a request status.
     * @return string Returns input buffer.
     * @throws \yii\web\HttpException
     */
    protected function completeRequest()
    {
        if (($this->status >= 200 && $this->status < 300) || $this->getMethod() === 'HEAD') {
            return $this->response;
        } else {
            throw new HttpException($this->status, $this->response);
        }
    }
    
    /**
     * Get curl resource handler.
     * @return resource
     */
    public function getHandler()
    {
        return $this->handler;
    }
    
    /**
     * Get last status code.
     * @see Curl::request()
     * @return integer
     */
    public function getStatusCode()
    {
        return $this->status;
    }
    
    /**
     * Get response body.
     * @see Curl::request()
     * @return string
     */
    public function getResponse()
    {
        if (!empty($this->options[CURLOPT_HEADER]) && $this->getMethod() !== 'HEAD') {
            $response = substr($this->response, $this->responseInfo['header_size']);
            if ($this->getResponseHeaders('Content-Encoding') === 'gzip') {
                $response = gzdecode($response);
                unset($this->responseHeaders['Content-Encoding']);
            }
            return $response;
        }
        return $this->response;
    }
    
    /**
     * Set response body.
     * Mainly for emulate responses.
     * @param string $buffer
     * @return Curl
     */
    public function setResponse($buffer)
    {
        $this->response = $buffer;
        return $this;
    }
    
    /**
     * Get response information.
     * @see Curl::request()
     * @link http://docs.php.net/manual/en/function.curl-getinfo.php
     * @param string $attr attribute name from response info
     * @return array|mixed
     */
    public function getResponseInfo($attr = '')
    {
        if ($attr !== '') {
            return isset($this->responseInfo[$attr]) ? $this->responseInfo[$attr] : '';
        }
        return $this->responseInfo;
    }
    
    /**
     * Assign new response info.
     * Mainly for emulate responses.
     * @link http://docs.php.net/manual/en/function.curl-getinfo.php
     * @param array $info
     * @throws \InvalidArgumentException When "http_code" does not contain in $info.
     * @return Curl
     */
    public function setResponseInfo(array $info)
    {
        if (!isset($info['http_code'])) {
            throw new \InvalidArgumentException('Status code "http_code" required.');
        }
        $this->responseInfo = $info;
        $this->status = $info['http_code'];
        return $this;
    }
    
    /**
     * Extract http headers from the response.
     * @param string $header Optional. Case-insensitive header name. 
     * @return string|array Returns header value or list of headers in form key => value.
     * @throws \RuntimeException
     * @see Curl::withHeaders()
     */
    public function getResponseHeaders($header = null)
    {
        if (empty($this->options[CURLOPT_HEADER])) {
            throw new \RuntimeException('Cannot get response headers while option CURLOPT_HEADER is not set.');
        }
        
        if (empty($this->responseHeaders)) {
            $buf = substr($this->response, 0, $this->responseInfo['header_size']);
            $buf = trim($buf);
            // On redirected location we have all headers for all redirected
            // locations. 
            if ($this->isRedirected()) {
                $buf = explode("\r\n\r\n", $buf);
                $buf = array_pop($buf);
            }
            $lines = array_filter(explode("\r\n", $buf));
            // Skip HTTP/1.X line.
            array_shift($lines);
            foreach ($lines as $line) {
                list ($h, $v) = explode(': ', $line, 2);
                $this->responseHeaders[strtolower($h)] = $v;
            }
        }
        
        if ($header !== null) {
            $header = strtolower($header);
            return isset($this->responseHeaders[$header]) ? $this->responseHeaders[$header] : '';
        }
        return $this->responseHeaders;
    }
    
    /**
     * Check whether requested url was redirected or not.
     * @return integer count of redirects.
     */
    public function isRedirected()
    {
        return isset($this->responseInfo['redirect_count']) ?
            $this->responseInfo['redirect_count'] : 0;
    }
    
}
