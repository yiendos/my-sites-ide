<?php
/**
 * Joomlatools Framework - https://www.joomlatools.com/developer/framework/
 *
 * @copyright   Copyright (C) 2007 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        https://github.com/joomlatools/joomlatools-framework for the canonical source repository
 */

/**
 * Dispatcher Request Interface
 *
 * @author  Johan Janssens <https://github.com/johanjanssens>
 * @package Koowa\Library\Dispatcher\Request
 */
interface KDispatcherRequestInterface extends KControllerRequestInterface
{
    /**
     * Gets the list of trusted proxies.
     *
     * @return array An array of trusted proxies.
     */
    public function getProxies();

    /**
     * Set the request cookies
     *
     * @param  array $cookies
     * @return KDispatcherRequestInterface
     */
    public function setCookies($cookies);

    /**
     * Get the request cookies
     *
     * @return KHttpMessageParameters
     */
    public function getCookies();

    /**
     * Set the request files
     *
     * @param  array $files
     * @return KDispatcherRequestInterface
     */
    public function setFiles($files);

    /**
     * Get the request files
     *
     * @return KHttpMessageParameters
     */
    public function getFiles();

    /**
     * Gets the request's scheme.
     *
     * @return string
     */
    public function getScheme();

    /**
     * Returns the host name.
     *
     * This method can read the client host from the "X-Forwarded-Host" header when the request is proxied and the proxy
     * is trusted. The "X-Forwarded-Host" header must contain the client host name.
     *
     * @throws \UnexpectedValueException when the host name is invalid
     * @return string
     */
    public function getHost();

    /**
     * Returns the port on which the request is made.
     *
     * This method can read the client port from the "X-Forwarded-Port" header when the request is proxied and the proxy
     * is trusted. The "X-Forwarded-Port" header must contain the client port.
     *
     * @return string
     */
    public function getPort();

    /**
     * Returns the HTTP referrer.
     *
     * 'referer' a commonly used misspelling word for 'referrer'
     * @link     http://en.wikipedia.org/wiki/HTTP_referrer
     *
     * @param   boolean  $isTrusted Only allow trusted origins
     * @return  KHttpUrl A HttpUrl object
     */
    public function getReferrer($isTrusted = true);

    /**
     * Add a trusted origin
     *
     * You should only add an origins that you trust
     *
     * @param string $origin A trusted origin
     * @return KDispatcherRequestInterface
     */
    public function addOrigin($origin);

    /**
     * Returns the HTTP origin header.
     *
     * @param   boolean  $isTrusted Only allow trusted origins
     * @return  KHttpUrl|null  A HttpUrl object or NULL if no origin header could be found
     */
    public function getOrigin($isTrusted = true);

    /**
     * Gets the list of trusted origins.
     *
     * @return array An array of trusted origins.
     */
    public function getOrigins();

    /**
     * Returns the client information doing the request
     *
     * @return string $_SERVER['HTTP_USER_AGENT'] or an empty string if it's not supplied in the request
     */
    public function getAgent();

    /**
     * Returns the client IP address.
     *
     * This method can read the client port from the "X-Forwarded-For" header when the request is proxied and the proxy
     * is trusted. The "X-Forwarded-For" header must contain the client port. The "X-Forwarded-For" header value is a
     * comma+space separated list of IP addresses, the left-most being the original client, and each successive proxy
     * that passed the request adding the IP address where it received the request from.
     *
     * @see http://tools.ietf.org/html/draft-ietf-appsawg-http-forwarded-10#section-5.2
     *
     * @return string Client IP address or an empty string if it's not supplied in the request
     */
    public function getAddress();

    /**
     * Returns the base URL from which this request is executed.
     *
     * The base URL never ends with a / and t also includes the script filename (e.g. index.php) if one exists.
     *
     * Suppose this request is instantiated from /mysite on localhost:
     *
     *  * http://localhost/mysite              returns an empty string
     *  * http://localhost/mysite/about        returns '/about'
     *  * http://localhost/mysite/enco%20ded   returns '/enco%20ded'
     *  * http://localhost/mysite/about?var=1  returns '/about'
     *
     * @return  object  A HttpUrl object
     */
    public function getBaseUrl();

    /**
     * Set the base URL for which the request is executed.
     *
     * @param string $url
     * @return KDispatcherRequest
     */
    public function setBaseUrl($url);

    /**
     * Returns the base url of the request.
     *
     * @return  object  A HttpUrl object
     */
    public function getBasePath();

    /**
     * Set the base path for which the request is executed.
     *
     * @param string $path
     * @return KDispatcherRequest
     */
    public function setBasePath($path);

    /**
     * Gets a list of languages acceptable by the client browser.
     *
     * @return array Languages ordered in the user browser preferences
     */
    public function getLanguages();

    /**
     * Gets a list of charsets acceptable by the client browser.
     *
     * @return array List of charsets in preferable order
     */
    public function getCharsets();

    /**
     * Gets the request ranges
     *
     *  @link : http://tools.ietf.org/html/rfc2616#section-14.35
     *
     * @throws KHttpExceptionRangeNotSatisfied If the range info is not valid or if the start offset is large then the end offset
     * @return array List of request ranges
     */
    public function getRanges();

    /**
     * Gets the etag
     *
     * @link https://tools.ietf.org/html/rfc7232#page-14
     *
     * @return string The entity tag
     */
    public function getETag();

    /**
     * Checks whether the request is secure or not.
     *
     * This method can read the client scheme from the "X-Forwarded-Proto" header when the request is proxied and the
     * proxy is trusted. The "X-Forwarded-Proto" header must contain the protocol: "https" or "http".
     *
     * @link http://tools.ietf.org/html/draft-ietf-appsawg-http-forwarded-10#section-5.4
     *
     * @return  boolean
     */
    public function isSecure();

    /**
     * Checks whether the request is proxied or not.
     *
     * This method reads the proxy IP from the "X-Forwarded-By" header. The "X-Forwarded-By" header MUST contain the
     * proxy IP address (and, potentially, a port number). If no "X-Forwarded-By" header can be found, or the header
     * IP address doesn't match the list of trusted proxies the function will return false.
     *
     * @link http://tools.ietf.org/html/draft-ietf-appsawg-http-forwarded-10#page-7
     *
     * @return  boolean Return TRUE if the request is proxied and the proxy is trusted. FALSE otherwise.
     */
    public function isProxied();

    /**
     * Check if the request is downloadable or not.
     *
     * A request is downloading if one of the following conditions are met :
     *
     * 1. The request query contains a 'force-download' parameter
     * 2. The request accepts specifies either the application/force-download or application/octet-stream mime types
     *
     * @return bool Returns TRUE If the request is downloadable. FALSE otherwise.
     */
    public function isDownload();

    /**
     * Check if the request is streaming
     *
     * Responses that contain a Range header is considered to be streaming.
     * @link : http://tools.ietf.org/html/rfc2616#section-14.35
     *
     * @return bool
     */
    public function isStreaming();
}