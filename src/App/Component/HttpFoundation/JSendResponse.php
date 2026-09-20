<?php

namespace App\Component\HttpFoundation;

use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Throwable;

/**
 * Class JSendResponse
 * Example usages:
 *      JSendResponse::createSuccess($data);
 *      JSendResponse::createFail($data, 500);
 *      JSendResponse::createError($message, 403);
 *
 * @see http://labs.omniti.com/labs/jsend
 */
class JSendResponse extends JsonResponse
{
    const JSEND_STATUS_SUCCESS = 'success';
    const JSEND_STATUS_FAIL = 'fail';
    const JSEND_STATUS_ERROR = 'error';

    protected string $jSendStatus;
    protected ?string $jSendMessage;
    protected mixed $jSendData;
    protected ?string $jSendCode;

    /**
     * General constructor for a JSend response object.
     *
     * @param string $jSendStatus
     * @param object|array|null $jSendData
     * @param string|null $jSendMessage
     * @param null $jSendCode
     * @param int $httpStatus
     * @param array $httpHeaders
     * @throws Throwable
     */
    public function __construct(string $jSendStatus, mixed $jSendData = null, string $jSendMessage = null, $jSendCode = null, int $httpStatus = 200, array $httpHeaders = [])
    {
        if (!in_array($jSendStatus, [self::JSEND_STATUS_SUCCESS, self::JSEND_STATUS_FAIL, self::JSEND_STATUS_ERROR])) {
            throw new Exception('Invalid JSend status:' .$jSendStatus);
        }

        parent::__construct(null, $httpStatus, $httpHeaders);

        $this->jSendStatus = $jSendStatus;
        $this->jSendData = $jSendData;
        $this->jSendMessage = $jSendMessage;
        $this->jSendCode = $jSendCode;

        $this->setJSendContent($this->jSendStatus, $this->jSendData, $this->jSendMessage, $this->jSendCode);
    }

    /**
     * Creates a JSend successful response.
     * All went well, and (usually) some data was returned.
     *
     * @param mixed $jSendData required parameter data
     * @param array $httpHeaders optional HTTP headers
     * @param int $httpStatus
     * @return JSendResponse
     * @throws Throwable
     */
    public static function createSuccess(mixed $jSendData, array $httpHeaders = [], int $httpStatus = 200): self
    {
        return new static(self::JSEND_STATUS_SUCCESS, $jSendData, null, null, $httpStatus, $httpHeaders);
    }

    /**
     * Creates a JSend fail response.
     * There was a problem with the data submitted, or some pre-condition of the API call wasn't satisfied
     *
     * @param string $jSendMessage
     * @param mixed $jSendData required parameter data, Json serializable object or array
     * @param int $httpStatus HTTP response code
     * @param array $httpHeaders optional HTTP headers
     * @return JSendResponse
     * @throws Throwable
     */
    public static function createFail(string $jSendMessage, int $httpStatus, mixed $jSendData = null, array $httpHeaders = []): JSendResponse
    {
        return new static(self::JSEND_STATUS_FAIL, $jSendData, $jSendMessage, null, $httpStatus, $httpHeaders);
    }

    /**
     * Creates a JSend error response.
     * An error occurred in processing the request, i.e. an exception was thrown
     *
     * @param string $jSendMessage A meaningful, end-user-readable message, explaining what went wrong.
     * @param int $httpStatus HTTP response status
     * @param mixed|null $jSendData optional generic container for any other information about the error
     * @param string|null $jSendCode optional numeric code corresponding to the error, if applicable
     * @param array $httpHeaders optional HTTP headers
     * @return JSendResponse
     * @throws Throwable
     */
    public static function createError(string $jSendMessage, int $httpStatus, mixed $jSendData = null, string $jSendCode = null, array $httpHeaders = []): JSendResponse
    {
        return new static(self::JSEND_STATUS_ERROR, $jSendData, $jSendMessage, $jSendCode, $httpStatus, $httpHeaders);
    }

    /**
     * Set all JSend parameters in the JSON data container.
     * Error is raised when invalid JSON is set
     *
     * @param $status
     * @param $data
     * @param $message
     * @param $code
     * @throws Throwable
     */
    protected function setJSendContent($status, $data, $message, $code): void
    {
        $content = ['status' => $status];
        if ($message) {
            $content['message'] = $message;
        }
        if ($code) {
            $content['code'] = $code;
        }
        if ($data) {
            $content['data'] = $data;
        }

        $this->setData($content);
    }
}
