<?php
/**
 * Joomlatools Framework - https://www.joomlatools.com/developer/framework/
 *
 * @copyright   Copyright (C) 2007 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        https://github.com/joomlatools/joomlatools-framework for the canonical source repository
 */

/**
 * Http Response
 *
 * @link http://tools.ietf.org/html/rfc2616#section-6
 * @link http://en.wikipedia.org/wiki/List_of_HTTP_status_codes
 *
 * @author  Johan Janssens <https://github.com/johanjanssens>
 * @package Koowa\Library\Http\Response
 */
class KHttpResponse extends KHttpMessage implements KHttpResponseInterface
{
    /**
     * The response status code
     *
     * @var int Status code
     */
    protected $_status_code;

    /**
     * The response status message
     *
     * @var string Status message
     */
    protected $_status_message;


    // [Successful 2xx]
    const OK                        = 200;
    const CREATED                   = 201;
    const ACCEPTED                  = 202;
    const NO_CONTENT                = 204;
    const RESET_CONTENT             = 205;
    const PARTIAL_CONTENT           = 206;

    // [Redirection 3xx]
    const MOVED_PERMANENTLY         = 301;
    const FOUND                     = 302;
    const SEE_OTHER                 = 303;
    const NOT_MODIFIED              = 304;
    const USE_PROXY                 = 305;
    const TEMPORARY_REDIRECT        = 307;

    // [Client Error 4xx]
    const BAD_REQUEST                   = 400;
    const UNAUTHORIZED                  = 401;
    const FORBIDDEN                     = 403;
    const NOT_FOUND                     = 404;
    const METHOD_NOT_ALLOWED            = 405;
    const NOT_ACCEPTABLE                = 406;
    const PROXY_AUTHENTICATION_REQUIRED = 407;
    const REQUEST_TIMEOUT               = 408;
    const CONFLICT                      = 409;
    const GONE                          = 410;
    const LENGTH_REQUIRED               = 411;
    const PRECONDITION_FAILED           = 412;
    const REQUEST_ENTITY_TOO_LARGE      = 413;
    const REQUEST_URI_TOO_LONG          = 414;
    const UNSUPPORTED_MEDIA_TYPE        = 415;
    const REQUESTED_RANGE_NOT_SATISFIED = 416;
    const EXPECTATION_FAILED            = 417;

    // [Server Error 5xx]
    const INTERNAL_SERVER_ERROR     = 500;
    const NOT_IMPLEMENTED           = 501;
    const BAD_GATEWAY               = 502;
    const SERVICE_UNAVAILABLE       = 503;
    const GATEWAY_TIMEOUT           = 504;
    const VERSION_NOT_SUPPORTED     = 505;

    /**
     * Status codes translation table.
     *
     * The list of codes is complete according to the
     * {@link http://www.iana.org/assignments/http-status-codes/ Hypertext Transfer Protocol (HTTP) Status Code Registry}
     * (last updated 2012-02-13).
     *
     * Unless otherwise noted, the status code is defined in RFC2616.
     *
     * @var array
     */
    public static $status_messages = array(

        // [Successful 2xx]
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',

        // [Redirection 3xx]
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',

        // [Client Error 4xx]
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',

        // [Server Error 5xx]
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Object Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported'
    );

    /**
     * Constructor
     *
     * @param KObjectConfig|null $config  An optional ObjectConfig object with configuration options
     * @return KHttpResponse
     */
    public function __construct(KObjectConfig $config)
    {
        parent::__construct($config);

        $this->setContent($config->content);
        $this->setContentType($config->content_type);
        $this->setStatus($config->status_code, $config->status_message);

        if (!$this->_headers->has('Date')) {
            $this->setDate(new DateTime('now'));
        }
    }

    /**
     * Initializes the default configuration for the object
     *
     * Called from {@link __construct()} as a first step of object instantiation.
     *
     * @param  KObjectConfig $config  An optional ObjectConfig object with configuration options.
     * @return void
     */
    protected function _initialize(KObjectConfig $config)
    {
        $config->append(array(
            'content'        => '',
            'content_type'   => '',
            'status_code'    => '200',
            'status_message' => null,
            'headers'        => array()
        ));

        parent::_initialize($config);
    }

    /**
     * Return the message format from the content type
     *
     * @return  string  The message format NULL if no format could be found
     */
    public function getFormat()
    {
        $result = null;

        if(!$this->_format)
        {
            foreach (static::$_formats as $value => $media_types)
            {
                if($media_type = $this->getContentType())
                {
                    if (in_array($media_type, (array)$media_types))
                    {
                        $this->_format = $value;
                        break;
                    }
                }
            }
        }

        return $this->_format;
    }

    /**
     * Sets a format and set the content type
     *
     * @param string $format The format
     * @throws UnexpectedValueException If the format hasn't been registered.
     * @return KHttpMessage
     */
    public function setFormat($format)
    {
        parent::setFormat($format);

        $this->setContentType(static::$_formats[$format][0]);

        return $this;
    }

    /**
     * Set the header parameters
     *
     * @param  array $headers
     * @return KHttpResponse
     */
    public function setHeaders($headers)
    {
        $this->_headers = $this->getObject('lib:http.response.headers', array('headers' => $headers));
        return $this;
    }


    /**
     * Set HTTP status code and (optionally) message
     *
     * @param  integer $code
     * @param  string $message
     * @throws InvalidArgumentException
     * @return KHttpResponse
     */
    public function setStatus($code, $message = null)
    {
        if (!is_numeric($code) || !isset(self::$status_messages[$code]))
        {
            $code = is_scalar($code) ? $code : gettype($code);
            throw new InvalidArgumentException(
                sprintf('Invalid status code provided: "%s"', $code)
            );
        }

        $this->_status_code    = (int) $code;
        $this->_status_message = trim(preg_replace('/\s+/', ' ',  $message));
        return $this;
    }

    /**
     * Retrieve HTTP status code
     *
     * @return int
     */
    public function getStatusCode()
    {
        return $this->_status_code;
    }

    /**
     * Get the http header status message based on a status code
     *
     * @return string The http status message
     */
    public function getStatusMessage()
    {
        $code = $this->getStatusCode();

        if (empty($this->_status_message)) {
            $message = self::$status_messages[$code];
        } else {
            $message = $this->_status_message;
        }

        return $message;
    }

    /**
     * Returns the Date header as a DateTime instance.
     *
     * @link http://tools.ietf.org/html/rfc2616#section-14.18
     *
     * @throws RuntimeException If the Date header could not be parsed
     * @return DateTime|null A DateTime instance or NULL if no Date header exists
     */
    public function getDate()
    {
        $date = new DateTime();

        if ($this->_headers->has('Date'))
        {
            $value = $this->_headers->get('Date');
            $date  = new DateTime(date(DATE_RFC2822, strtotime($value)));

            if ($date === false) {
                throw new RuntimeException(sprintf('The Date HTTP header is not parseable (%s).', $value));
            }
        }

        return $date;
    }

    /**
     * Sets the Date header.
     *
     * @see http://tools.ietf.org/html/rfc2616#section-14.18
     *
     * @param  DateTime $date A DateTime instance
     * @return KHttpResponse
     */
    public function setDate(DateTime $date)
    {
        $date->setTimezone(new DateTimeZone('UTC'));
        $this->_headers->set('Date', $date->format('D, d M Y H:i:s').' GMT');

        return $this;
    }

    /**
     * Returns the Last-Modified HTTP header as a DateTime instance.
     *
     * @link http://tools.ietf.org/html/rfc2616#section-14.29
     *
     * @throws RuntimeException If the Last-Modified header could not be parsed
     * @return DateTime|null A DateTime instance or NULL if no Last-Modified header exists
     */
    public function getLastModified()
    {
        $date = null;

        if ($this->_headers->has('Last-Modified'))
        {
            $value = $this->_headers->get('Last-Modified');
            $date  = new DateTime(date(DATE_RFC2822, strtotime($value)));

            if ($date === false) {
                throw new RuntimeException(sprintf('The Last-Modified HTTP header is not parseable (%s).', $value));
            }
        }

        return $date;
    }

    /**
     * Sets the Last-Modified HTTP header with a DateTime instance.
     *
     * If passed a null value, it removes the header.
     *
     * @link http://tools.ietf.org/html/rfc2616#section-14.29
     *
     * @param  DateTime $date A \DateTime instance
     * @return KHttpResponse
     */
    public function setLastModified(DateTime $date = null)
    {
        if ($date !== null)
        {
            $date = clone $date;
            $date->setTimezone(new DateTimeZone('UTC'));
            $this->_headers->set('Last-Modified', $date->format('D, d M Y H:i:s').' GMT');
        }
        else $this->_headers->remove('Last-Modified');

        return $this;
    }

    /**
     * Returns the literal value of the ETag HTTP header.
     *
     * @link http://tools.ietf.org/html/rfc2616#section-14.19
     *
     * @return string The ETag HTTP header
     */
    public function getEtag()
    {
        return $this->_headers->get('ETag');
    }

    /**
     * Sets the ETag value.
     *
     * @link http://tools.ietf.org/html/rfc2616#section-14.19
     *
     * @param string  $etag The ETag unique identifier
     * @param Boolean $weak Whether you want a weak ETag or not
     * @return KHttpResponse
     */
    public function setEtag($etag = null, $weak = false)
    {
        if (null !== $etag)
        {
            if (0 !== strpos($etag, '"')) {
                $etag = '"'.$etag.'"';
            }

            $this->_headers->set('ETag', (true === $weak ? 'W/' : '').$etag);
        }
        else  $this->_headers->remove('Etag');

        return $this;
    }

    /**
     * Set the age of the response.
     *
     * @link http://tools.ietf.org/html/rfc2616#section-14.6
     * @param integer $age The age of the response in seconds
     * @return KHttpResponse
     */
    public function setAge($age)
    {
        $this->_headers->set('Age', $age);
        return $this;
    }

    /**
     * Returns the age of the response.
     *
     * @link http://tools.ietf.org/html/rfc2616#section-14.6
     * @return integer The age of the response in seconds
     */
    public function getAge()
    {
        if (!$age = $this->_headers->get('Age', 0)) {
            $age = max(time() - $this->getDate()->format('U'), 0);
        }

        return $age;
    }

    /**
     * Set the max age
     *
     * This directive specifies the maximum time in seconds that the fetched response is allowed to be reused from
     * the time of the request. For example, "max-age=60" indicates that the response can be cached and reused for
     * the next 60 seconds.
     *
     * @link https://tools.ietf.org/html/rfc2616#section-14.9.3
     * @link https://www.php.net/manual/en/datetime.formats.relative.php
     *
     * @param integer|string $max_age        The number of seconds or an English textual relative datetime format  after
     * 										 which the response should no longer be considered fresh.
     * @param integer|string $shared_max_age The number of seconds after or an an English textual relative datetime format
     * 										 which the response should no longer be considered fresh by shared caches.
     * @return KHttpResponse
     */
    public function setMaxAge($max_age, $shared_max_age = null)
    {
        $cache_control = $this->getCacheControl();

        //Convert max_age to seconds
        if(!is_numeric($max_age))
        {
            if($max_age = strtotime($max_age)) {
                $max_age = $max_age - strtotime('now');
            }
        }

        //Convert shared_max_age to seconds
        if(!is_numeric($shared_max_age))
        {
            if($shared_max_age = strtotime($shared_max_age)) {
                $shared_max_age = $shared_max_age - strtotime('now');
            }
        }

        if($max_age !== false) {
            $cache_control['max-age'] = (int) $max_age;
        } else {
            unset($cache_control['max-age']);
        }

        if($shared_max_age > $max_age) {
            $cache_control['s-maxage'] = (int) $shared_max_age;
        } else {
            unset($cache_control['s-maxage']);
        }

        $this->_headers->set('Cache-Control', $cache_control);

        return $this;
    }

    /**
     * Get the max age
     *
     * Returns the number of seconds after the time specified in the response's Date header when the response should no
     * longer be considered fresh.
     *
     * First, it checks for a s-maxage directive, then a max-age directive. It returns null when no maximum age can be
     * established.
     *
     * @link https://tools.ietf.org/html/rfc2616#section-14.9.3
     * @return integer|null Number of seconds
     */
    public function getMaxAge()
    {
        $result = null;

        $cache_control = $this->getCacheControl();

        if (isset($cache_control['max-age'])) {
            $result = (int) $cache_control['max-age'];
        }

        if (isset($cache_control['s-maxage'])) {
            $result = (int) $cache_control['s-maxage'];
        }

        return $result;
    }

    /**
     * Get the cache control
     *
     * @link https://tools.ietf.org/html/rfc2616#page-108
     * @return array
     */
    public function getCacheControl()
    {
        $values = $this->_headers->get('Cache-Control', array(), false);

        $result = array();
        foreach((array)$values as $key => $value)
        {
            if (is_string($value))
            {
                foreach (array_map('trim', explode(',', $value)) as $k => $v)
                {
                    $parts = explode('=', $v);

                    if (count($parts) > 1) {
                        $result[trim($parts[0])] = trim($parts[1]);
                    } else {
                        $result[] =  trim($parts[0]);
                    }
                }
            }
            else
            {
                foreach((array)$value as $k => $v)
                {
                    if(is_numeric($k)) {
                        $result[] = $v;
                    } else {
                        $result[$k] = $v;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Is the response invalid
     *
     * @return Boolean
     */
    public function isInvalid()
    {
        return $this->getStatusCode() < 100 || $this->getStatusCode() >= 600;
    }

    /**
     * Check if an http status code is an error
     *
     * @return boolean TRUE if the status code is an error code
     */
    public function isError()
    {
        return ($this->getStatusCode() >= 400);
    }

    /**
     * Do we have a redirect
     *
     * @return bool
     */
    public function isRedirect()
    {
        return in_array($this->getStatusCode(), array(301, 302, 303, 307, 308));
    }

    /**
     * Was the response successful
     *
     * @return bool
     */
    public function isSuccess()
    {
        $code = $this->getStatusCode();
        return (200 <= $code && 300 > $code);
    }

    /**
     * Returns true if the response is worth caching under any circumstance.
     *
     * Responses that cannot be stored or are without cache validation (Last-Modified, ETag) heades are
     * considered uncacheable.
     *
     * @link https://tools.ietf.org/html/rfc7234#section-3
     *
     * @return Boolean true if the response is worth caching, false otherwise
     */
    public function isCacheable()
    {
        $cache_control = $this->getCacheControl();

        if (in_array('no-store', $cache_control, true)) {
            return false;
        }

        if (in_array('private', $cache_control, true)) {
            return false;
        }

        if (in_array('public', $cache_control, true)) {
            return true;
        }

        if (isset($cache_control['max-age']) || isset($cache_control['s-maxage'])) {
            return true;
        }

        if($this->isValidateable()) {
            return true;
        }

        if (in_array($this->getStatusCode(), array(200 , 203 , 204 , 206 , 300 , 301 , 404 , 405 , 410 , 414 , 501))) {
            return true;
        }

        return false;
    }

    /**
     * Returns true if the response includes headers that can be used to validate the response with the origin
     * server using a conditional GET request.
     *
     * @return Boolean true if the response is validateable, false otherwise
     */
    public function isValidateable()
    {
        return $this->_headers->has('Last-Modified') || $this->_headers->has('ETag');
    }

    /**
     * Returns true if the response is "stale".
     *
     * When the responses is stale, the response may not be served from cache without first re-validating with
     * the origin. To determine if the response is stale we use the max-age, or in case no max-age directive is
     * defined try to calculate the heuristic freshness as follows: (Now - (Time since Last-Modified)) * 0.1
     *
     * @link https://tools.ietf.org/html/rfc7234#section-4.2.2
     *
     * @return Boolean true if the response is stale, false otherwise
     */
    public function isStale()
    {
        $stale = null;

        if ($this->getMaxAge() === NULL)
        {
            //Calculate heuristic freshness and determine if response is still fresh
            if($this->getLastModified())
            {
                $time  = floor((strtotime('now') - $this->getLastModified()->getTimestamp()) * 0.1);
                $stale = ($time - $this->getAge()) <= 0;
            }
        }
        else $stale = ($this->getMaxAge() - $this->getAge()) <= 0;

        return $stale;
    }

    /**
     * Return true of the response has not been modified
     *
     * @return Boolean true if the response is not modified
     */
    public function isNotModified()
    {
        return (bool) ($this->getStatusCode() == 304);
    }

    /**
     * Render entire response as HTTP response string
     *
     * @return string
     */
    public function toString()
    {
        $status = sprintf('HTTP/%s %d %s', $this->getVersion(), $this->getStatusCode(), $this->getStatusMessage());

        $str  = trim($status) . "\r\n";
        $str .= $this->getHeaders();
        $str .= "\r\n";
        $str .= $this->getContent();
        return $str;
    }
}