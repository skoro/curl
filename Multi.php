<?php
/**
 * @author Skorobogatko Alexei <skorobogatko.oleksii@gmail.com>
 * @copyright 2015
 * @since 0.1
 */

namespace skoro\curl;

/**
 * Multiple processing cUrl.
 *
 * Object oriented wrapper for curl_multi.
 * Usage:
 * ```
 * $m = new Multi();
 * // Attach curl instances and run them.
 * $m->add(new Curl('google.com', 'HEAD'))
 *   ->add(new Curl('microsoft.com', 'HEAD'))
 *   ->add(new Curl('amazon.com', 'GET'))
 *   ->run();
 * // Get responses.
 * foreach ($m->getMulti() as $curl) {
 *    var_dump($m->getResponse());
 * }
 * ```
 * @author skoro
 */
class Multi implements \IteratorAggregate
{
    /**
     * @var resource
     */
    protected $handler = null;
    
    /**
     * @var Curl[]
     */
    protected $multi = [];
    
    /**
     * Creates new Multi instance.
     */
    public function __construct()
    {
        $this->handler = curl_multi_init();
    }

    /**
     * Cleanup resources.
     */
    public function __destruct()
    {
        if (is_resource($this->handler)) {
            foreach ($this->multi as &$curl) {
                curl_multi_remove_handle($this->handler, $curl->getHandler());
                $curl = null;
            }
            curl_multi_close($this->handler);
            $this->handler = null;
        }
    }
    
    /**
     * Attach Curl instance for multiple processing.
     * @param Curl $curl
     * @return Multi
     */
    public function add(Curl $curl)
    {
        $this->multi[] = $curl;
        curl_multi_add_handle($this->handler, $curl->getHandler());
        return $this;
    }
    
    /**
     * Remove Curl instance from multiple processing.
     * @param Curl $curl
     * @return bool
     */
    public function remove(Curl $curl)
    {
        $handle = $curl->getHandle();
        foreach ($this->multi as $i => $item) {
            if ($handle === $item->getHandler()) {
                curl_multi_remove_handle($this->handler, $item->getHandler());
                unset($item[$i]);
                return true;
            }
        }
        return false;
    }
    
    /**
     * Get multi processing list.
     * @return Curl[]
     */
    public function getMulti()
    {
        return $this->multi;
    }
    
    /**
     * Do multiple urls processing.
     * @return Multi
     */
    public function run()
    {
        $active = null;
        
        // Apply curl instance options before processing.
        foreach ($this->multi as $curl) {
            $curl->prepareOptions();
        }

        do {
            $rc = curl_multi_exec($this->handler, $active);
        } while ($rc == CURLM_CALL_MULTI_PERFORM);

        while ($active && $rc == CURLM_OK) {
            if (curl_multi_select($this->handler) == -1) {
                usleep(100);
            }
            do {
                $rc = curl_multi_exec($this->handler, $active);
            } while ($rc == CURLM_CALL_MULTI_PERFORM);
        }
        
        // Set responses to all curl instances.
        foreach ($this->multi as $curl) {
            $h = $curl->getHandler();
            $curl->setResponseInfo(curl_getinfo($h))
                 ->setResponse(curl_multi_getcontent($h));
        }
        
        return $this;
    }
    
    /**
     * Allow to traverse among connected curl instances.
     *
     * @return \Traversable
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->multi);
    }
    
}

