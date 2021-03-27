<?php
/**
 * Joomlatools Framework - https://www.joomlatools.com/developer/framework/
 *
 * @copyright   Copyright (C) 2007 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html>
 * @link        https://github.com/joomlatools/joomlatools-framework for the canonical source repository
 */

/**
 * Stream Dispatcher Response Transport
 *
 * Streaming is a data transfer mechanism in version HTTP 1.1 in which a web server serves content in a series of chunks.
 * Two mechanisms exist to do this : range serving and chunk serving.
 *
 * -- Range Serving
 *
 * This mechanism is the process of sending only a portion of the data from a server to a client. Range-serving uses
 * the Range HTTP request header and the Accept-Ranges and Content-Range HTTP response headers.
 *
 * Clients which request range-serving might do so in cases in which a large file has been only partially delivered and
 * a limited portion of the file is needed in a particular range. Range Serving is therefore a method of bandwidth
 * optimization
 *
 * -- Chunk Serving
 *
 * This mechanism uses the Transfer-Encoding HTTP response header instead of the Content-Length header, which the protocol
 * would otherwise require. Because the Content-Length header is not used, the server does not need to know the length of
 * the content before it starts transmitting a response to the client.
 *
 * Web servers can begin transmitting responses with dynamically-generated content before knowing the total size of
 * that content. The size of each chunk is sent right before the chunk itself so that a client can tell when it has
 * finished receiving data for that chunk. The data transfer is terminated by a final chunk of length zero.
 *
 * @author  Johan Janssens <https://github.com/johanjanssens>
 * @package Koowa\Library\Dispatcher\Response\Transport
 * @see http://en.wikipedia.org/wiki/Byte_serving
 * @see http://en.wikipedia.org/wiki/Chunked_transfer_encoding
 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.16
 */
class KDispatcherResponseTransportStream extends KDispatcherResponseTransportHttp
{
    /**
     * Byte offset
     *
     * @var	integer
     */
    protected $_offset;

    /**
     * Byte range
     *
     * @var	integer
     */
    protected $_range;

    /**
     * Initializes the config for the object
     *
     * Called from {@link __construct()} as a first step of object instantiation.
     *
     * @param   KObjectConfig $config  An optional ObjectConfig object with configuration options
     * @return  void
     */
    protected function _initialize(KObjectConfig $config)
    {
        $config->append(array(
            'priority'   => self::PRIORITY_HIGH,
        ));

        parent::_initialize($config);
    }

    /**
     * Get the byte offset
     *
     * @param KDispatcherResponseInterface $response
     * @return int The byte offset
     * @throws KHttpExceptionRangeNotSatisfied   If the byte offset is outside of the total size of the file
     */
    public function getOffset(KDispatcherResponseInterface $response)
    {
        if(!isset($this->_offset))
        {
            $offset = 0;

            if($response->getRequest()->isStreaming())
            {
                $ranges = $response->getRequest()->getRanges();

                if (!empty($ranges[0]['first'])) {
                    $offset = (int) $ranges[0]['first'];
                }

                if ($offset > $this->getFileSize($response)) {
                    throw new KHttpExceptionRangeNotSatisfied('Invalid range');
                }
            }

            $this->_offset = $offset;
        }

        return $this->_offset;
    }

    /**
     * Get the byte range
     *
     * @param KDispatcherResponseInterface $response
     * @return int The last byte offset
     */
    public function getRange(KDispatcherResponseInterface $response)
    {
        if(!isset($this->_range))
        {
            $length = $this->getFileSize($response);
            $range  = $length - 1;

            if($response->getRequest()->isStreaming())
            {
                $ranges = $response->getRequest()->getRanges();

                if (!empty($ranges[0]['last'])) {
                    $range = (int) $ranges[0]['last'];
                }

                if($range > $length - 1) {
                    $range = $length - 1;
                }
            }

            $this->_range = $range;
        }

        return $this->_range;
    }

    /**
     * Get the file size
     *
     * @param KDispatcherResponseInterface $response
     * @return int The file size in bytes
     */
    public function getFileSize(KDispatcherResponseInterface $response)
    {
        return $response->getStream()->getSize();
    }

    /**
     * Sends content for the current web response.
     *
     * We flush(stream) the data to the output buffer based on the chunk size and range information provided in the
     * request. The default chunk size is 8 MB.
     *
     * @param KDispatcherResponseInterface $response
     * @return KDispatcherResponseTransportRedirect
     */
    public function sendContent(KDispatcherResponseInterface $response)
    {
        if ($response->isSuccess() && $response->isStreamable())
        {
            $stream  = $response->getStream();

            $offset = $this->getOffset($response);
            $range  = $this->getRange($response);

            if ($offset > 0) {
                $stream->seek($offset);
            }

            $output = fopen('php://output', 'w+');
            $stream->flush($output, $range);
            $stream->close();
            fclose($output);

            return $this;
        }

        parent::sendContent($response);
    }

    /**
     * Send HTTP response
     *
     * @param KDispatcherResponseInterface $response
     * @return boolean
     */
    public function send(KDispatcherResponseInterface $response)
    {
        $request  = $response->getRequest();

        if($response->isStreamable())
        {
            //Explicitly set the Accept Ranges header to bytes to inform client we accept range requests
            $response->headers->set('Accept-Ranges', 'bytes');

            if($request->isStreaming())
            {
                if($response->isSuccess())
                {
                    //For a certain unmentionable browser
                    if(ini_get('zlib.output_compression')) {
                        @ini_set('zlib.output_compression', 'Off');
                    }

                    //Fix for IE7/8
                    if(function_exists('apache_setenv')) {
                        @apache_setenv('no-gzip', '1');
                    }

                    //Remove PHP time limit
                    if(!ini_get('safe_mode')) {
                        @set_time_limit(0);
                    }

                    //Default Content-Type Header
                    if(!$response->headers->has('Content-Type')) {
                        $response->headers->set('Content-Type', 'application/octet-stream');
                    }

                    //Content Range Headers
                    $offset = $this->getOffset($response);
                    $range  = $this->getRange($response);
                    $size   = $this->getFileSize($response);

                    $response->setStatus(KHttpResponse::PARTIAL_CONTENT);
                    $response->headers->set('Content-Length', $range - $offset + 1);
                    $response->headers->set('Content-Range', sprintf('bytes %s-%s/%s', $offset, $range, $size));
                }

                if($response->isError())
                {
                    /**
                     * A server sending a response with status code 416 (Requested range not satisfiable) SHOULD include a
                     * Content-Range field with a byte-range- resp-spec of "*". The instance-length specifies the current
                     * length of the selected resource.
                     *
                     * @see : http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.16
                     */
                    if($response->getStatusCode() == KHttpResponse::REQUESTED_RANGE_NOT_SATISFIED)
                    {
                        $size = $this->getFileSize($response);
                        $response->headers->set('Content-Range', sprintf('bytes */%s', $size));
                    }
                }
            }
        }

        return parent::send($response);
    }
}