<?php
/**
* @mainpage
* This library allows to browse HTTP using PHP with no additional extensions like cURL.
* It consists of a single file (websock2.php), which you can add to your project to make all
* its classes available for use.
* <br><br> @b Features:
*  - HTTP(S) serfing
*  - File uploads (including very large files support)
*  - Cookies
*  - Automatic HTTP redirection
*  - Automatic Referer
*  - HTTP basic and digest authentication
*  - HTTP/1.0 and 1.1, chunked content, gzipped/deflated content support
*  - Proxy support:
*    -# HTTP(S)
*    -# SOCKS4
*    -# SOCKS4a
*    -# SOCKS5
*  - Proxy authentication support
*  - Proxy chaining support
*  - Timeouts
*  - Two sockets implementations with different features
*  - Advanced features:
*    -# Redirection interception
*    -# Full control of reading host response
*  - Great scalability and control
* <br><br>
* <b>Copyright (c) DX, <a href="http://kaimi.ru" target="_blank">kaimi.ru</a>,
*    <a href="http://coder.pub" target="_blank">coder.pub</a>
*/

///Class that contains some helper methods for strings
final class TextHelpers
{
	private function __construct()
	{
	}
	
	/**
	* @brief Converts string to lower case (USASCII only).
	*
	* @param string $str
	*    Target string
	* @retval string Lower-case string
	*/
	public static function toLower($str)
	{
		return strtr($str, 'QWERTYUIOPASDFGHJKLZXCVBNM', 'qwertyuiopasdfghjklzxcvbnm');
	}
	
	/**
	* @brief Converts string to upper case (USASCII only).
	*
	* @param string $str
	*    Target string
	* @retval string Upper-case string
	*/
	public static function toUpper($str)
	{
		return strtr($str, 'qwertyuiopasdfghjklzxcvbnm', 'QWERTYUIOPASDFGHJKLZXCVBNM');
	}
}

///Class that represents Web library exceptions
class WebRequestException extends Exception
{
	/**
	* @brief Unable to locate substring in response.
	*
	* Usually this means that response is malformed.
	*/
	const UNABLE_TO_LOCATE_SUBSTRING = 1;
	///Unable to parse received response
	const UNABLE_TO_PARSE_RESULT = 2;
	///Proxy authentication was unsuccessful for some reason
	const PROXY_AUTHENTICATION_ERROR = 3;
	///Proxy authentication is required. You should supply correct login and password
	const PROXY_AUTHENTICATION_REQUIRED = 4;
	///Error when resolving hostname to IP address
	const UNABLE_TO_RESOLVE_HOSTNAME = 5;
	///Incorrect chunked content in response
	const INCORRECT_CHUNKED_CONTENT = 6;
	///Incorrect gzipped content in response
	const INCORRECT_GZIPPED_CONTENT = 7;
	///Unknown compression method in response
	const UNKNOWN_COMPRESSION_METHOD = 8;
	///Unable to connect to target host
	const UNABLE_TO_CONNECT = 9;
	///Unable to read data from socket
	const UNABLE_TO_READ = 10;
	///Unable to write data to socket
	const UNABLE_TO_WRITE = 11;
	///Undefined socket error
	const SOCKET_ERROR = 12;
	///Timeout when connecting to socket or reading or writing data
	const SOCKET_TIMEOUT = 13;
	///Incorrect HTTP method to send attachments
	const UNABLE_TO_SEND_DATA = 14;
	///Unable to read specified file
	const UNABLE_TO_READ_FILE = 15;
	///Unable to parse specified URL
	const UNABLE_TO_PARSE_URL = 16;
	///Incorrect HTTP digest authentication options
	const INCORRECT_AUTHENTICATION_OPTIONS = 17;
	
	/**
	* @brief Constructor
	*
	* @param string $message
	*    Exception message
	* @param int $code
	*    Exception code
	* @param Exception $previous
	*    Previous exception
	*/
	public function __construct($message, $code = 0, Exception $previous = null)
	{
		parent::__construct($message, $code, $previous);
	}
}

///Some helper functions for web requests
final class WebRequestHelpers
{
	private function __construct()
	{
	}
	
	/**
	* @brief Checks if string is a valid IPv4 address.
	*
	* @param string $addr
	*    Target string
	* @retval bool True if string is a valid IPv4 address
	*/
	static public function isIpV4Address($addr)
	{
		return preg_match('/^([0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])'
			. '(\.([0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3}$/', $addr);
	}
	
	/**
	* @brief Tries to resolve hostname. If IPv4 address is supplied, just
	* returns it.
	*
	* @param string $hostname
	*    Target hostname or IPv4 address
	* @throws WebRequestException if any error occured when resolving hostname
	* @retval string IPv4 address
	*/
	static public function resolveHostname($hostname)
	{
		if(self::isIpV4Address($hostname))
			return $hostname;
			
		$ip = @gethostbyname($hostname);
		if($ip === $hostname)
		{
			throw new WebRequestException('Unable to resolve hostname',
				WebRequestException::UNABLE_TO_RESOLVE_HOSTNAME);
		}
		
		return $ip;
	}
}

///Web server response wrapper class
class WebResponse
{
	private $raw;
	private $header_manager = null;
	
	/**
	* @brief Constructor
	*
	* @param string $contents
	*    Raw response string (including headers)
	*/
	public function __construct($contents)
	{
		$this->raw = $contents;
	}
	
	/**
	* @brief Returns raw response string (including headers).
	*
	* @retval string Raw response string (including headers)
	*/
	public function getRawContents()
	{
		return $this->raw;
	}
	
	/**
	* @brief Sets raw response string (including headers).
	*
	* @param string $contents
	*    Raw response string (including headers)
	*/
	public function setRawContents($contents)
	{
		$this->raw = $contents;
		$this->header_manager = null;
	}
	
	/**
	* @brief Returns response body.
	*
	* @retval string Response body
	*/
	public function getBody()
	{
		$headers_pos = strpos($this->raw, "\r\n\r\n");
		if($headers_pos === false)
			return '';
		
		return substr($this->raw, $headers_pos + 4);
	}
	
	/**
	* @brief Returns raw response headers.
	*
	* @retval string Response headers (raw)
	*/
	public function getHeadersData()
	{
		$headers_pos = strpos($this->raw, "\r\n\r\n");
		if($headers_pos === false)
			return $this->raw; //Consider all contents as headers in this case
		
		return substr($this->raw, 0, $headers_pos);
	}
	
	/**
	* @brief Returns response headers.
	*
	* @retval HttpHeaderManager Response headers
	*/
	public function getHeaders()
	{
		if($this->header_manager !== null)
			return $this->header_manager;
		
		return $this->header_manager = new HttpHeaderManager($this->getHeadersData());
	}
	
	/**
	* @brief Returns HTTP response code.
	*
	* @retval int HTTP response code
	*/
	public function getHttpCode()
	{
		if(!preg_match('#^HTTP/1\.[01]\s+(\d{3})\s+#', $this->raw, $match))
		{
			throw new WebRequestException('Unable to parse HTTP result code',
				WebRequestException::UNABLE_TO_PARSE_RESULT);
		}
		
		return (int)$match[1];
	}
}

///HTTP headers manager
class HttpHeaderManager
{
	///No HTTP authentication required
	const HTTP_AUTHENTICATION_NONE = 0;
	///HTTP Basic authentication is required
	const HTTP_AUTHENTICATION_BASIC = 1;
	///HTTP Digest authentication is required
	const HTTP_AUTHENTICATION_DIGEST = 2;
	
	private $headers = Array();
	
	/**
	* @brief Constructor
	*
	* @param string $raw_headers
	*    Raw HTTP headers string
	*/
	public function __construct($raw_headers = '')
	{
		$headers = explode("\r\n", $raw_headers);
		foreach($headers as $header)
		{
			if(preg_match('/^([a-zA-Z0-9\-]+):\s*(.+?)$/', $header, $match))
				$this->addHeader($match[1], $match[2]);
		}
	}
	
	/**
	* @brief Returns HTTP headers array.
	*
	* @retval Array HTTP headers array,
	*    which contains an Array ('header name' => ' header value') for each header
	*/
	public function getHeadersArray()
	{
		return $this->headers;
	}
	
	/**
	* @brief Adds new HTTP header. Does not replace headers with the same names.
	*
	* @param string $name
	*    Header name
	* @param string $value
	*    Header value
	*/
	public function addHeader($name, $value)
	{
		$this->headers[] = Array($name, $value);
	}
	
	/**
	* @brief Adds new HTTP header. Replaces headers with the same names.
	*
	* @param string $name
	*    Header name
	* @param string $value
	*    Header value
	*/
	public function replaceHeader($name, $value)
	{
		$name_lower = TextHelpers::toLower($name);
		foreach($this->headers as &$header)
		{
			if(TextHelpers::toLower($header[0]) === $name_lower)
			{
				$header[1] = $value;
				return;
			}
		}
		
		$this->addHeader($name, $value);
	}
	
	/**
	* @brief Removes all HTTP headers with specified name, if any exist.
	*
	* @param string $name
	*    Header name
	*/
	public function removeHeader($name)
	{
		$name = TextHelpers::toLower($name);
		for($i = 0, $cnt = count($this->headers); $i !== $cnt; ++$i)
		{
			if(TextHelpers::toLower($this->headers[$i][0]) === $name)
				unset($this->headers[$i]);
		}
	}
	
	///Removes all HTTP headers.
	public function clearHeaders()
	{
		$this->headers = Array();
	}
	
	/**
	* @brief Returns first HTTP header with specified name.
	*
	* @param string $name
	*    Header name
	* @retval string HTTP header value or null,
	*    if there is no header with specified name
	*/
	public function getHeader($name)
	{
		$name = TextHelpers::toLower($name);
		foreach($this->headers as $header)
		{
			if(TextHelpers::toLower($header[0]) === $name)
				return $header[1];
		}
		
		return null;
	}
	
	/**
	* @brief Returns HTTP headers values array by header name.
	*
	* @param string $name
	*    Header name
	* @retval Array HTTP headers array,
	*    which contains an Array ('header name' => ' header value') for each header
	*/
	public function getHeaders($name)
	{
		$ret = Array();
		
		$name = TextHelpers::toLower($name);
		foreach($this->headers as $header)
		{
			if(TextHelpers::toLower($header[0]) === $name)
				$ret[] = $header[1];
		}
		
		return $ret;
	}
	
	/**
	* @brief Returns if there is HTTP header with specified name.
	*
	* @param string $name
	*    Header name
	* @retval bool True if there is HTTP header with specified name
	*/
	public function hasHeader($name)
	{
		return $this->getHeader($name) !== null;
	}
	
	/**
	* @brief Returns content length header value.
	*
	* @retval int Content length header value or null, if there is no such header
	*/
	public function getContentLength()
	{
		$length = $this->getHeader('Content-Length');
		if($length !== null
			&& filter_var($length, FILTER_VALIDATE_INT, Array('options' => Array('min_range' => 0))) !== false)
		{
			return (int)$length;
		}
		
		return null;
	}
	
	/**
	* @brief Returns true, if HTTP body contents is chunked.
	*
	* @retval bool True if HTTP body contents is chunked
	*/
	public function isChunked()
	{
		return $this->getHeader('Transfer-Encoding') === 'chunked';
	}
	
	/**
	* @brief Returns required HTTP authentication type (digest or basic).
	*
	* @retval int @ref HTTP_AUTHENTICATION_NONE, @ref HTTP_AUTHENTICATION_BASIC
	*    or @ref HTTP_AUTHENTICATION_DIGEST
	*/
	public function getAuthenticationType()
	{
		$auth = $this->getHeader('WWW-Authenticate');
		if($auth !== null)
		{
			$pos = strpos($auth, ' ');
			if($pos !== false)
				$auth = substr($auth, 0, $pos);
			
			$auth = TextHelpers::toLower($auth);
			switch($auth)
			{
				case 'digest':
					return self::HTTP_AUTHENTICATION_DIGEST;
				
				case 'basic':
					return self::HTTP_AUTHENTICATION_BASIC;
			}
		}
		
		return self::HTTP_AUTHENTICATION_NONE;
	}
	
	/**
	* @brief Returns HTTP authentication options array.
	*
	* Example: Array('realm' => 'My Realm', 'qop' => 'auth', ...)
	*
	* @retval Array HTTP authentication options array or null, if no authentication needed
	*/
	public function getAuthenticationOptions()
	{
		$auth = $this->getHeader('WWW-Authenticate');
		if($auth !== null)
		{
			$pos = strpos($auth, ' ');
			if($pos === false)
				return Array();
			
			$auth = substr($auth, $pos);
			
			$in_quotes = false;
			$opts = Array();
			$opt_name = '';
			$opt_value = '';
			$current_string = 0;
			for($i = 0, $cnt = strlen($auth); $i !== $cnt; ++$i)
			{
				$c = $auth[$i];
				if($c === '"')
				{
					$in_quotes = !$in_quotes;
					continue;
				}
				else if(!$in_quotes)
				{
					if($c === ',')
					{
						if(isset($opt_name[0]) && $current_string)
							$opts[TextHelpers::toLower(trim($opt_name))] = trim($opt_value);
						
						$opt_name = '';
						$opt_value = '';
						$current_string = 0;
						continue;
					}
					else if($c === '=' && $current_string === 0)
					{
						$current_string = 1;
						continue;
					}
				}
				
				if($current_string)
					$opt_value .= $c;
				else
					$opt_name .= $c;
			}
			
			if(isset($opt_name[0]) && $current_string)
				$opts[TextHelpers::toLower(trim($opt_name))] = trim($opt_value);
			
			return $opts;
		}
		
		return null;
	}
	
	/**
	* @brief Adds basic authentication header.
	*
	* @param string $login Login
	* @param string $password Password
	*/
	public function setBasicAuthenticationCredentials($login, $password)
	{
		$this->replaceHeader('Authorization',
			'Basic ' . base64_encode($login . ':' . $password));
	}
	
	/**
	* @brief Adds digest authentication header.
	*
	* @param Array $auth_opts Authentication data returned by @ref getAuthenticationOptions
	* @param string $login Login
	* @param string $password Password
	* @param string $request_body Full body of request. Can be null if $auth_opts['qop'] != 'auth-int'
	* @param string $http_method Request HTTP method
	* @param string $request_uri Request URI
	* @throws WebRequestException in case of error in options
	*/
	public function setDigestAuthenticationCredentials(Array $auth_opts, $login, $password,
		$request_body, $http_method, $request_uri)
	{
		$algo = 'md5';
		if(!isset($auth_opts['realm']))
		{
			throw new WebRequestException('Realm not set',
				WebRequestException::INCORRECT_AUTHENTICATION_OPTIONS);
		}
		
		if(!isset($auth_opts['nonce']))
		{
			throw new WebRequestException('Nonce not set',
				WebRequestException::INCORRECT_AUTHENTICATION_OPTIONS);
		}
		
		if(!isset($auth_opts['opaque']))
		{
			throw new WebRequestException('Opaque not set',
				WebRequestException::INCORRECT_AUTHENTICATION_OPTIONS);
		}
		
		if(isset($auth_opts['algorithm']))
		{
			$algo = TextHelpers::toLower($auth_opts['algorithm']);
			if($algo !== 'md5' && $algo !== 'md5-sess')
			{
				throw new WebRequestException('Unknown algorithm of digest authentication',
					WebRequestException::INCORRECT_AUTHENTICATION_OPTIONS);
			}
		}
		
		$qop = null;
		if(isset($auth_opts['qop']))
		{
			$qop = TextHelpers::toLower($auth_opts['qop']);
			if($qop !== 'auth' && $qop !== 'auth-int')
			{
				throw new WebRequestException('Unknown qop of digest authentication',
					WebRequestException::INCORRECT_AUTHENTICATION_OPTIONS);
			}
		}
		
		$cnonce = null;
		if($algo === 'md5-sess' || $qop !== null)
			$cnonce = md5(mt_rand());
		
		$ha1 = md5($login . ':' . $auth_opts['realm'] . ':' . $password);
		if($algo === 'md5-sess')
			$ha1 = md5($ha1 . ':' . $auth_opts['nonce'] . $cnonce);
		
		$ha2 = $qop === 'auth-int'
			? md5($http_method . ':' . $request_uri . ':' . md5($request_body))
			: md5($http_method . ':' . $request_uri);
		
		$nonce_count = '00000001';
		$response = $qop === null
			? md5($ha1 . ':' . $auth_opts['nonce'] . ':' . $ha2)
			: md5($ha1 . ':' . $auth_opts['nonce'] . ':'
				. $nonce_count . ':' . $cnonce . ':' . $qop . ':' . $ha2);
		
		$value =  'Digest '
			. 'username="' . $login . '",'
			. 'realm="' . $auth_opts['realm'] . '",'
			. 'nonce="' . $auth_opts['nonce'] . '",'
			. 'response="' . $response . '",'
			. 'uri="' . $request_uri . '",'
			. 'opaque="' . $auth_opts['opaque'] . '"';
		
		if($qop !== null)
		{
			$value .= ','
				. 'qop=' . $qop . ','
				. 'nc=' . $nonce_count;
		}
		
		if($cnonce !== null)
		{
			$value .= ','
				. 'cnonce="' . $cnonce . '"';
		}
		
		$this->replaceHeader('Authorization', $value);
	}
	
	/**
	* @brief Returns raw HTTP headers string.
	*
	* This string does not contain ending additional EOL.
	*
	* @retval string HTTP Raw HTTP headers string
	*/
	public function getRawHeaders()
	{
		$headers = Array();
		foreach($this->headers as $header)
			$headers[] = $header[0] . ': ' . $header[1];
		
		return implode("\r\n", $headers);
	}
}

///HTTP attachment base class
abstract class HttpAttachment
{
	private $filename;
	private $content_type = null;
	
	/**
	* @brief Constructor
	*
	* @param string $filename
	*    Attachment file name (this will be sent over HTTP)
	*/
	public function __construct($filename)
	{
		$this->filename = $filename;
	}
	
	/**
	* @brief Sets attachment file name.
	*
	* @param string $filename
	*    Attachment file name (this will be sent over HTTP)
	*/
	public function setFilename($filename)
	{
		$this->filename = $filename;
	}
	
	/**
	* @brief Sets attachment content-type.
	*
	* @param string $content_type
	*    Attachment content-type or null, if no content-type should be supplied
	*/
	public function setContentType($content_type)
	{
		$this->content_type = $content_type;
	}
	
	/**
	* @brief Returns attachment file name.
	*
	* @retval string Attachment file name
	*/
	public function getFilename()
	{
		return $this->filename;
	}
	
	/**
	* @brief Returns attachment content-type.
	*
	* @retval string Attachment content-type (or null, if no content-type present)
	*/
	public function getContentType()
	{
		return $this->content_type;
	}
	
	/**
	* @brief Writes raw attachment data to HttpSocket $socket
	*
	* @param HttpSocket $socket
	*    Socket to send data to
	*/
	abstract public function writeTo(HttpSocket $socket);
	
	/**
	* @brief Returns attachment size in bytes.
	*
	* @retval int Attachment size in bytes
	*/
	abstract public function getSize();
}

///HTTP content attachment class
class HttpContentAttachment extends HttpAttachment
{
	private $contents;
	
	/**
	* @brief Constructor
	*
	* @param string $filename
	*    Attachment file name (this will be sent over HTTP)
	* @param string $contents
	*    Attachment contents
	*/
	public function __construct($filename, $contents)
	{
		parent::__construct($filename);
		$this->contents = $contents;
	}
	
	public function writeTo(HttpSocket $socket)
	{
		$socket->writeRaw($this->contents);
	}
	
	public function getSize()
	{
		return strlen($this->contents);
	}
}

///HTTP file attachment class
class HttpFileAttachment extends HttpAttachment
{
	private $file_path;
	private $part_size = 2048;
	private $size;
	
	/**
	* @brief Constructor
	*
	* @param string $filename
	*    Attachment file name (this will be sent over HTTP)
	* @param string $file_path
	*    Attachment file path
	* @throws WebRequestException if an error occured when requesting file size
	*/
	public function __construct($filename, $file_path)
	{
		parent::__construct($filename);
		$this->file_path = $file_path;
		$this->size = @filesize($this->file_path);
		if($this->size === false)
		{
			throw new WebRequestException('Unable to get file size',
				WebRequestException::UNABLE_TO_READ_FILE);
		}
	}
	
	/**
	* @brief Sets size of part of file data in bytes
	*    that will be sent to socket at once.
	*
	* Default value is 2048 bytes.
	*
	* @param int $part_size
	*    Size of part of file data in bytes
	*    that will be sent to socket at once
	*/
	public function setPartSize($part_size)
	{
		$this->part_size = $part_size;
	}
	
	/**
	* @brief Returns size of part of file data in bytes
	*    that will be sent to socket at once.
	*
	* Default value is 2048 bytes.
	*
	* @retval int
	*    Size of part of file data in bytes
	*    that will be sent to socket at once
	*/
	public function getPartSize()
	{
		return $this->part_size;
	}
	
	/**
	* @brief Writes raw attachment data to HttpSocket $socket.
	*
	* @param HttpSocket $socket
	*    Socket to send data to
	* @throws WebRequestException if an error occured
	*    when opening or reading file
	*/
	public function writeTo(HttpSocket $socket)
	{
		$f = @fopen($this->file_path, 'rb');
		if($f === false)
		{
			throw new WebRequestException('Unable to open file',
				WebRequestException::UNABLE_TO_READ_FILE);
		}
		
		while(!feof($f))
		{
			$data = @fread($f, $this->part_size);
			if($data === false)
			{
				fclose($f);
				throw new WebRequestException('Unable to read file',
					WebRequestException::UNABLE_TO_READ_FILE);
			}
			
			try
			{
				$socket->writeRaw($data);
			}
			catch(Exception $e)
			{
				fclose($f);
				throw $e;
			}
		}
		
		fclose($f);
	}
	
	public function getSize()
	{
		return $this->size;
	}
}

/**
* @brief HTTP GET/POST parameters manager
*
* By default, auto urlencode of parameter names and values is enabled.
*/
class HttpParamManager implements ArrayAccess
{
	/**
	* @brief All parameter types
	*
	* Used in @ref getParams,
	*    @ref getRawParamString
	*/
	const ALL_PARAMS = 0;
	/**
	* @brief Parameters that will be passed to server with GET method only
	*
	* Used in @ref getParams,
	*    @ref getRawParamString
	*/
	const GET_ONLY_PARAMS = 1;
	/**
	* @brief Parameters that will be passed to server with POST/PUT method only
	*
	* Used in @ref getParams,
	*    @ref getRawParamString
	*/
	const NON_GET_ONLY_PARAMS = 2;
	
	private $params = Array();
	private $get_only_params = Array();
	private $url_encode = true;
	private $attachments = Array();
	
	/**
	* @brief Enables or disables auto urlencode
	*    of parameter names and values.
	*
	* @param bool $url_encode True to enable auto urlencode
	*    of parameter names and values
	*/
	public function setAutoUrlEncode($url_encode)
	{
		$this->url_encode = $url_encode;
	}
	
	/**
	* @brief Returns true if auto urlencode
	*    of parameter names and values is enabled.
	*
	* @retval bool True if auto urlencode
	*    of parameter names and values is enabled
	*/
	public function isAutoUrlEncodeEnabled()
	{
		return $this->url_encode;
	}
	
	/**
	* @brief Returns true if any of parameters are attachments.
	*
	* @retval bool True if any of parameters are attachments
	*/
	public function hasAttachments()
	{
		foreach($this->params as $param)
		{
			if(is_array($param))
			{
				foreach($param as $arr_param)
				{
					if($arr_param instanceof HttpAttachment)
						return true;
				}
			}
			else
			{
				if($param instanceof HttpAttachment)
					return true;
			}
		}
		
		return false;
	}
	
	/**
	* @brief Returns array of parameters of specified type.
	*
	* @param int $param_type
	*    Parameter type. See @ref ALL_PARAMS, @ref GET_ONLY_PARAMS,
	*    @ref NON_GET_ONLY_PARAMS
	* @retval Array Array of parameters, where keys are parameter names
	*    and values are parameter values.
	*/
	public function getParams($param_type)
	{
		if($param_type === self::GET_ONLY_PARAMS)
			return $this->get_only_params;
		else if($param_type === self::NON_GET_ONLY_PARAMS)
			return $this->params;
		
		return array_merge($this->get_only_params, $this->params);
	}
	
	/**
	* @brief Adds new parameter or replaces parameter with same name.
	*
	* @param string $name
	*    Parameter name
	* @param mixed $value
	*    Parameter value. This can be string, int, float, bool or HttpAttachment
	*    or Array of strings, int's, float's, bool's and/or HttpAttachment's
	* @param bool $get_only
	*    If true, this parameter will be passed only with GET method.
	*/
	public function setParam($name, $value, $get_only = false)
	{
		if(!is_string($name))
			throw new InvalidArgumentException('HTTP parameter name must be string');
		
		if(is_array($value))
		{
			foreach($value as $param)
			{
				if($param === null)
					throw new InvalidArgumentException('Name-only parameters can not be array');
			}
		}
		
		if($get_only)
		{
			$params = is_array($value) ? $value : Array($value);
			foreach($params as $param)
			{
				if($param instanceof HttpAttachment)
					throw new InvalidArgumentException('File attachment can not be GET-only parameter');
			}
			
			$this->get_only_params[$name] = $value;
		}
		else
		{
			$this->params[$name] = $value;
		}
	}
	
	/**
	* @brief Returns true if parameter with specified name exists.
	*
	* @param string $name
	*    Parameter name
	* @retval bool True if parameter with specified name exists
	*/
	public function hasParam($name)
	{
		return isset($this->params[$name]) || isset($this->get_only_params[$name]);
	}
	
	/**
	* @brief Returns parameter value by name.
	*
	* @param string $name
	*    Parameter name
	* @retval mixed Parameter value or null, if no parameter found
	*/
	public function getParam($name)
	{
		return isset($this->params[$name])
			? $this->params[$name]
			: (isset($this->get_only_params[$name])
				? $this->get_only_params[$name]
				: null);
	}
	
	/**
	* @brief Removes parameter by name.
	*
	* If no parameter found, does nothing.
	*
	* @param string $name
	*    Parameter name
	*/
	public function removeParam($name)
	{
		if(isset($this->params[$name]))
			unset($this->params[$name]);
		
		if(isset($this->get_only_params[$name]))
			unset($this->get_only_params[$name]);
	}
	
	/**
	* @brief Returns true if parameter with specified name exists.
	*
	* Overload for ArrayAccess interface.
	*
	* @param string $offset
	*    Parameter name
	* @retval bool True if parameter with specified name exists
	*/
	public function offsetExists($offset)
	{
		return $this->hasParam($offset);
	}
	
	/**
	* @brief Returns parameter value by name.
	*
	* Overload for ArrayAccess interface.
	*
	* @param string $offset
	*    Parameter name
	* @retval mixed Parameter value or null, if no parameter found
	*/
	public function offsetGet($offset)
	{
		return $this->getParam($offset);
	}
	
	/**
	* @brief Adds new parameter or replaces parameter with same name.
	*
	* Overload for ArrayAccess interface.
	*
	* @param string $offset
	*    Parameter name
	* @param mixed $value
	*    Parameter value. This can be string, int, float, bool or HttpAttachment
	*    or Array of strings, int's, float's, bool's and/or HttpAttachment's
	*/
	public function offsetSet($offset, $value)
	{
		$this->setParam($offset, $value);
	}
	
	/**
	* @brief Removes parameter by name.
	*
	* If no parameter found, does nothing.
	*
	* Overload for ArrayAccess interface.
	*
	* @param string $offset
	*    Parameter name
	*/
	public function offsetUnset($offset)
	{
		$this->removeParam($offset);
	}
	
	/**
	* @brief Writes raw file data of attachments to socket. Moreover, writes
	*    ending boundary for multipart/form-data request.
	*
	* Call this function only after @ref getRawParamString
	*    and in case param manager has any attachments.
	*
	* @param HttpSocket $socket
	*    Socket to write data to
	* @param string $boundary
	*    HTTP boundary. Must be the same as supplied to
	*    @ref getRawParamString function
	*/
	public function getRawFileData(HttpSocket $socket, $boundary)
	{
		$has_attachments = false;
		foreach($this->params as $name => $params)
		{
			$multiple_params = is_array($params);
			if(!$multiple_params)
				$params = Array($params);
			
			if($this->url_encode)
				$name = urlencode($name);
			
			foreach($params as $param)
			{
				if($param instanceof HttpAttachment)
				{
					$has_attachments = true;
					$content_type = '';
					if($param->getContentType() !== null)
						$content_type = "\r\n" . 'Content-Type: ' . $param->getContentType();
					
					$socket->writeRaw('--' . $boundary . "\r\n"
						. 'Content-Disposition: form-data; name="'
						. $name
						. ($multiple_params ? '[]"; filename="' : '"; filename="')
						. ($this->url_encode ? urlencode($param->getFileName()) : $param->getFileName())
						. '"' . $content_type
						. "\r\n\r\n");
					
					$param->writeTo($socket);
					
					$socket->writeRaw("\r\n");
				}
			}
		}
		
		$socket->writeRaw('--' . $boundary . '--' . "\r\n");
	}
	
	/**
	* @brief Returns length in bytes of raw file data of attachments.
	*
	* @param string $boundary
	*    HTTP boundary
	* @retval int Length in bytes of raw file data of attachments
	*/
	public function getRawFileDataLength($boundary)
	{
		$ret = 0;
		$boundaries_len = strlen($boundary) + 8 //8 == strlen("\r\n\r\n\r\n\r\n")
			+ 52 //52 == strlen('Content-Disposition: form-data; name=""; filename=""')
			+ 2; //2 == strlen('--')
		foreach($this->params as $name => $params)
		{
			$multiple_params = is_array($params);
			if(!$multiple_params)
				$params = Array($params);
			
			if($this->url_encode)
				$name = urlencode($name);
			
			foreach($params as $param)
			{
				if($param instanceof HttpAttachment)
				{
					$ret += $param->getSize() + $boundaries_len
						+ strlen($name) + strlen($param->getFileName());
					if($param->getContentType() !== null)
						$ret += 16 + strlen($param->getContentType()); //16 == strlen("Content-Type: \r\n")
					if($multiple_params)
						$ret += 2; //strlen('[]')
				}
			}
		}
		
		if($ret)
			$ret += strlen($boundary) + 6; //strlen("----\r\n");
		
		return $ret;
	}
	
	/**
	* @brief Returns raw parameter names and values string,
	*    which is ready to be sent to socket.
	*
	* Does not include attachments.
	* If any attachments are present, @ref getRawFileData must be called
	* after @ref getRawParamString to send all file data of attachments.
	*
	* @param int $param_type
	*    Parameter type. See @ref ALL_PARAMS, @ref GET_ONLY_PARAMS,
	*    @ref NON_GET_ONLY_PARAMS
	* @param string $boundary
	*    Parameter boundary. Boundary is used if any attachments are present.
	*    If $param_type is @ref GET_ONLY_PARAMS or there are no attachments,
	*    boundary can be null
	* @retval string Raw parameter string ready to be used in any HTTP request
	*/
	public function getRawParamString($param_type, $boundary = null)
	{
		$ret = Array();
		
		if($boundary !== null &&
			$param_type !== self::GET_ONLY_PARAMS)
		{
			$ret = '';
			
			$enum_function = function($name, $value, $multiple) use (&$ret, $boundary)
			{
				$ret .= '--' . $boundary . "\r\n"
					. 'Content-Disposition: form-data; name="'
					. ($this->url_encode ? urlencode($name) : $name)
					. ($multiple ? '[]"' : '"')
					. "\r\n\r\n"
					. $value . "\r\n";
			};
		}
		else if($this->url_encode)
		{
			$enum_function = function($name, $value, $multiple) use (&$ret)
			{
				$param_str = urlencode($name);
				if($value !== null)
					$param_str .= ($multiple ? '[]=' : '=')
						. urlencode($value);
				
				$ret[] = $param_str;
			};
		}
		else
		{
			$enum_function = function($name, $value, $multiple) use (&$ret)
			{
				$param_str = $name;
				if($value !== null)
					$param_str .= ($multiple ? '[]=' : '=')
						. urlencode($value);
				
				$ret[] = $param_str;
			};
		}
		
		$arr = $this->getParams($param_type);
		foreach($arr as $name => $value)
		{
			$arr = is_array($value);
			if(!$arr)
				$value = Array($value);
			
			foreach($value as $v)
			{
				if($v instanceof HttpAttachment)
					continue;
				
				$enum_function($name, $v, $arr);
			}
		}
		
		if(is_array($ret))
		{
			return implode('&', $ret);
		}
		else
		{
			if($this->hasAttachments())
				return $ret;
			else
				return $ret . '--' . $boundary . '--' . "\r\n";
		}
	}
}

///Represents request that can be sent over HTTP(S)
class WebRequest
{
	///HTTP version 1.0
	const HTTP_VERSION_1_0 = 0;
	///HTTP version 1.1
	const HTTP_VERSION_1_1 = 1;
	
	///GET request method
	const METHOD_GET = 'GET';
	///POST request method
	const METHOD_POST = 'POST';
	///CONNECT request method
	const METHOD_CONNECT = 'CONNECT';
	///PUT request method
	const METHOD_PUT = 'PUT';
	///HEAD request method
	const METHOD_HEAD = 'HEAD';
	///TRACE request method
	const METHOD_TRACE = 'TRACE';
	///DELETE request method
	const METHOD_DELETE = 'DELETE';
	
	private $address;
	private $port;
	private $request_uri;
	private $method;
	private $headers;
	private $http_version = self::HTTP_VERSION_1_0;
	private $params;
	private $secure;
	private $boundary = null;
	
	/**
	* @brief Constructor
	*
	* See @ref createFromUrl also.
	* <br>Default Connection is Close, default HTTP version is 1.0.
	* <br>Connection will be secure in case port is 443.
	*
	* @param string $address
	*    Address of request (hostname or IPv4 address)
	* @param int $port
	*    Port of request
	* @param string $method
	*    HTTP request method. See @ref METHOD_GET, @ref METHOD_POST,
	*    @ref METHOD_CONNECT, @ref METHOD_PUT, @ref METHOD_HEAD,
	*    @ref METHOD_TRACE, @ref METHOD_DELETE
	* @param string $request_uri
	*    Relative request URI. IP:PORT is allowed for CONNECT method.
	*    @ref HttpProxy will set absolute URI.
	* @param bool $url_encode
	*    If true, request URI string will be urlencoded
	*/
	public function __construct($address, $port = 80, $method = self::METHOD_GET, $request_uri = '/',
		$url_encode = true)
	{
		$this->address = $address;
		$this->port = $port;
		$this->request_uri = $url_encode ? self::urlEncodeUri($request_uri) : $request_uri;
		$this->method = $method;
		$this->headers = new HttpHeaderManager;
		$this->params = new HttpParamManager;
		
		$this->headers->addHeader('Connection', 'close');
		$this->headers->addHeader('Host', $address);
		
		$this->secure = $port === 443;
	}
	
	/**
	* @brief Urlencodes request URI (each part of path separately).
	*    Does not accept absolute request URI's or request URI's with parameters.
	*
	* @param string $uri
	*    Request URI
	* @retval string Urlencoded request URI.
	*/
	static public function urlEncodeUri($uri)
	{
		return implode('/', array_map('urlencode', explode('/', $uri)));
	}
	
	/**
	* @brief Creates @ref WebRequest
	*
	* Default Connection is Close, default HTTP version is 1.0.
	* <br>Connection will be secure in case port is 443.
	*
	* @param string $url
	*    Full request url
	* @param bool $url_encode_uri
	*    If true, request URI path portion will be urlencoded (see @ref urlEncodeUri)
	* @param bool $url_encode_params
	*    If true, all parameter names and values will be urlencoded automatically
	* @retval WebRequest
	*    @ref WebRequest object
	* @throws WebRequestException in case of error
	*/
	static public function createFromUrl($url, $url_encode_uri = true, $url_encode_params = true)
	{
		$result = @parse_url($url);
		if(!$result || !isset($result['scheme'])
			|| !isset($result['host'])
			|| ($result['scheme'] !== 'https' && $result['scheme'] !== 'http'))
		{
			throw new WebRequestException('Unable to parse URL',
				WebRequestException::UNABLE_TO_PARSE_URL);
		}
		
		$port = isset($result['port'])
			? $result['port']
			: ($result['scheme'] === 'https' ? 443 : 80);
		
		$uri = '';
		if(isset($result['path']))
			$uri .= $result['path'];
		
		if(empty($uri))
			$uri = '/';
		
		$ret = new WebRequest($result['host'], $port, self::METHOD_GET, $uri, $url_encode_uri);
		
		if(isset($result['query']))
		{
			$unpacked_params = Array();
			$params = explode('&', $result['query']);
			foreach($params as $param)
			{
				$pair = explode('=', $param, 2);
				if(isset($pair[0][2]) && substr($pair[0], -2) === '[]')
				{
					$name = substr($pair[0], 0, -2);
					if(!isset($unpacked_params[$name]))
						$unpacked_params[$name] = Array();
					
					if(!is_array($unpacked_params[$name]))
					{
						throw new WebRequestException('Unable to parse URL',
							WebRequestException::UNABLE_TO_PARSE_URL);
					}
					
					$value = isset($pair[1]) ? $pair[1] : '';
					$unpacked_params[$name][] = $value;
				}
				else
				{
					if(!isset($pair[0][0]))
					{
						throw new WebRequestException('Unable to parse URL',
							WebRequestException::UNABLE_TO_PARSE_URL);
					}
					
					$name = $pair[0];
					if(isset($unpacked_params[$name])
						&&is_array($unpacked_params[$name]))
					{
						throw new WebRequestException('Unable to parse URL',
							WebRequestException::UNABLE_TO_PARSE_URL);
					}
					
					$unpacked_params[$name] = isset($pair[1]) ? $pair[1] : null;
				}
			}
			
			unset($params);
			
			foreach($unpacked_params as $name => $values)
				$ret->params->setParam($name, $values, true);
		}
		
		$ret->setSecure($result['scheme'] === 'https');
		$ret->params->setAutoUrlEncode($url_encode_params);
		
		return $ret;
	}
	
	/**
	* @brief Returns true if request is secure (HTTPS).
	*
	* @retval bool True if request is secure (HTTPS)
	*/
	public function isSecure()
	{
		return $this->secure;
	}
	
	/**
	* @brief Returns request HTTP header manager.
	*
	* @retval HttpHeaderManager Request HTTP header manager
	*/
	public function getHeaderManager()
	{
		return $this->headers;
	}
	
	/**
	* @brief Returns request HTTP parameter manager.
	*
	* @retval HttpParamManager Request HTTP parameter manager
	*/
	public function getParamManager()
	{
		return $this->params;
	}
	
	/**
	* @brief Returns HTTP version (see @ref HTTP_VERSION_1_0 and @ref HTTP_VERSION_1_1).
	*
	* @retval int HTTP version
	*/
	public function getHttpVersion()
	{
		return $this->http_version;
	}
	
	/**
	* @brief Returns request address (hostname or IPv4 address).
	*
	* @retval string Request address (hostname or IPv4 address)
	*/
	public function getAddress()
	{
		return $this->address;
	}
	
	/**
	* @brief Returns request method.
	*
	* See @ref METHOD_GET, @ref METHOD_POST,
	*    @ref METHOD_CONNECT, @ref METHOD_PUT, @ref METHOD_HEAD,
	*    @ref METHOD_TRACE, @ref METHOD_DELETE
	*
	* @retval string Request method
	*/
	public function getMethod()
	{
		return $this->method;
	}
	
	/**
	* @brief Returns request port.
	*
	* @retval int Request port
	*/
	public function getPort()
	{
		return $this->port;
	}
	
	/**
	* @brief Returns request URI.
	*
	* @retval string Request URI
	*/
	public function getRequestUri()
	{
		return $this->request_uri;
	}
	
	/**
	* @brief Returns request URI path portion.
	*
	* Returns empty string in case of CONNECT method.
	*
	* @retval string Request URI path portion
	*/
	public function getRequestUriPath()
	{
		if($this->method === self::METHOD_CONNECT) //request uri is IP:PORT in this case
			return '';
		
		$slash_pos = strrpos($this->request_uri, '/');
		if($slash_pos !== false)
			return $slash_pos === 0 ? '/' : substr($this->request_uri, 0, $slash_pos + 1);
		
		return '';
	}
	
	/**
	* @brief Returns full request URL.
	* 
	* @param bool $include_params If true, GET parameters will be included to URLs
	* @retval string Full request URL
	*/
	public function getFullAddress($include_params = true)
	{
		if(strpos($this->request_uri, '://') !== false)
		{
			$ret = $this->request_uri; //HttpProxy sets absolute URI
		}
		else
		{
			$ret = ($this->secure ? 'https://' : 'http://')
				. $this->address
				. ((($this->secure && $this->port !== 443)
					|| (!$this->secure && $this->port !== 80)) ? ':' . $this->port : '')
				. $this->request_uri;
		}
		
		if($include_params)
		{
			$params = $this->params->getRawParamString(
				$this->method === self::METHOD_POST
					|| $this->method === self::METHOD_PUT
				? HttpParamManager::GET_ONLY_PARAMS
				: HttpParamManager::ALL_PARAMS);
			
			if(isset($params[0]))
				$ret .= '?' . $params;
		}
		
		return $ret;
	}
	
	/**
	* @brief Sets if request is secure (HTTPS).
	*
	* @param bool $secure True to set request secure (HTTPS)
	*/
	public function setSecure($secure)
	{
		$this->secure = $secure;
	}
	
	/**
	* @brief Sets HTTP version (see @ref HTTP_VERSION_1_0 and @ref HTTP_VERSION_1_1).
	*
	* @param int $version HTTP version
	*/
	public function setHttpVersion($version)
	{
		$this->http_version = $version;
		$this->headers->replaceHeader('Connection',
			$this->http_version === self::HTTP_VERSION_1_1
			? 'keep-alive'
			: 'close');
	}
	
	/**
	* @brief Sets request address (hostname or IPv4 address).
	*
	* @param string $address Request address (hostname or IPv4 address)
	*/
	public function setAddress($address)
	{
		$this->address = $address;
	}
	
	/**
	* @brief Sets request port.
	*
	* @param int $port Request port
	*/
	public function setPort($port)
	{
		$this->port = $port;
	}
	
	/**
	* @brief Sets request method.
	*
	* See @ref METHOD_GET, @ref METHOD_POST,
	*    @ref METHOD_CONNECT, @ref METHOD_PUT, @ref METHOD_HEAD,
	*    @ref METHOD_TRACE, @ref METHOD_DELETE
	*
	* @param string $method Request method
	*/
	public function setMethod($method)
	{
		$this->method = $method;
	}
	
	/**
	* @brief Sets request URI.
	*
	* @param string $request_uri Request URI
	* @param bool $url_encode
	*    If true, request URI string will be urlencoded
	*/
	public function setRequestUri($request_uri, $url_encode = true)
	{
		$this->request_uri = $url_encode ? self::urlEncodeUri($request_uri) : $request_uri;
	}
	
	/**
	* @brief Sets user-defined boundary for multipart/form-data requests.
	*
	* @param string $boundary
	*    User-defined boundary or null, if boundary should be generated automatically.
	*    Default is null. Used only if request contains file attachments.
	*/
	public function setBoundary($boundary)
	{
		$this->boundary = $boundary;
	}
	
	/**
	* @brief Returns user-defined boundary for multipart/form-data requests.
	*
	* @retval string
	*    User-defined boundary or null, if boundary should be generated automatically.
	*    Default is null. Used only if request contains file attachments.
	*/
	public function getBoundary()
	{
		return $this->boundary;
	}
	
	/**
	* @brief Writes request to socket.
	*
	* @param HttpSocket $socket
	*    Socket to write request to
	* @throws WebRequestException in case of any errors
	*/
	public function writeTo(HttpSocket $socket)
	{
		$uri = $this->request_uri;
		$has_attachments = $this->params->hasAttachments();
		if($this->method === self::METHOD_POST
			|| $this->method === self::METHOD_PUT)
		{
			$get_params = $this->params->getRawParamString(HttpParamManager::GET_ONLY_PARAMS);
			if(isset($get_params[0]))
			{
				$uri .= '?' . $get_params;
				unset($get_params);
			}
			
			if($has_attachments)
			{
				$boundary = $this->boundary === null
					? str_replace(Array('/', '+', '='),
						Array('', '', ''), base64_encode(md5(mt_rand())))
					: $this->boundary;
				$params = $this->params->getRawParamString(HttpParamManager::NON_GET_ONLY_PARAMS,
					$boundary);
				
				$this->headers->replaceHeader('Content-Type',
					'multipart/form-data; boundary=' . $boundary);
				
				$this->headers->replaceHeader('Content-Length',
					strlen($params) + $this->params->getRawFileDataLength($boundary));
			}
			else
			{
				$params = $this->params->getRawParamString(HttpParamManager::NON_GET_ONLY_PARAMS);
				
				$this->headers->replaceHeader('Content-Type',
					'application/x-www-form-urlencoded');
				$this->headers->replaceHeader('Content-Length',
					strlen($params));
			}
		}
		else
		{
			if($has_attachments)
			{
				throw new WebRequestException('Unable to send file attachments via ' . $this->method . ' method',
					WebRequestException::UNABLE_TO_SEND_DATA);
			}
			
			$params = $this->params->getRawParamString(HttpParamManager::ALL_PARAMS);
			if(isset($params[0]))
			{
				$uri .= '?' . $params;
				$params = '';
			}
			
			if($this->headers->getHeader('Content-Type')
				=== 'application/x-www-form-urlencoded')
			{
				$this->headers->removeHeader('Content-Type');
			}
			
			$this->headers->removeHeader('Content-Length');
		}
		
		$raw_headers = $this->headers->getRawHeaders();
		if(!empty($raw_headers))
			$raw_headers .= "\r\n";
		
		$socket->writeRaw($this->method . ' ' . $uri . ' HTTP/'
			. ($this->http_version === self::HTTP_VERSION_1_1 ? '1.1' : '1.0') . "\r\n"
			. $raw_headers . "\r\n"
			. $params);
		
		if($has_attachments)
			$this->params->getRawFileData($socket, $boundary);
	}
	
	/**
	* @brief Adds basic authentication header.
	*
	* @param string $login Login
	* @param string $password Password
	*/
	public function setBasicAuthenticationCredentials($login, $password)
	{
		$this->headers->setBasicAuthenticationCredentials($login, $password);
	}
	
	/**
	* @brief Adds digest authentication header.
	*
	* @param Array $auth_opts Authentication data returned by HttpHeaderManager::getAuthenticationOptions
	* @param string $login Login
	* @param string $password Password
	* @throws WebRequestException in case of error in options
	*/
	public function setDigestAuthenticationCredentials(Array $auth_opts, $login, $password)
	{
		if($this->boundary === null)
		{
			throw new WebRequestException('User-defined boundary must be set for WebRequest to use digest authentication',
				WebRequestException::INCORRECT_AUTHENTICATION_OPTIONS);
		}
		
		$dump = new DumpSocket;
		$this->writeTo($dump);
		$contents = new WebResponse($dump->getContents());
		unset($dump);
		$contents = $contents->getBody();
		
		$this->headers->setDigestAuthenticationCredentials($auth_opts, $login, $password,
			$contents, $this->method, urldecode($this->request_uri));
	}
}

///Base class for socket ans proxy implementations
abstract class HttpSocket
{
	///Destructor
	public function __destruct()
	{
		$this->close();
	}
	
	/**
	* @brief Sends request to socket and returns response.
	*
	* Supports HTTP/1.0 and HTTP/1.1 (close and keep-alive connections),
	* chunked contents, gzipped contents.
	* <br>See also NetworkSocket::setOnReceiveHeadersCallback, NetworkSocket::setOnReceiveBodyCallback.
	*
	* @retval WebResponse Callback response
	* @throws WebRequestException in case of errors 
	*/
	abstract public function sendRequest(WebRequest $request);
	
	/**
	* @brief Opens socket.
	*
	* @param string $address Hostname or IPv4 address
	* @param int $port Port
	* @throws WebRequestException in case of errors 
	*/
	abstract protected function open($address, $port);
	
	/**
	* @brief Writes raw data to socket.
	*
	* @param string $request Raw data
	* @throws WebRequestException in case of errors 
	*/
	abstract public function writeRaw($request);
	
	/**
	* @brief Reads raw data from socket.
	*
	* @param int $size Size of data in bytes to read
	* @retval string Data that was read from socket
	* @throws WebRequestException in case of errors 
	*/
	abstract protected function read($size);
	
	///Closes socket.
	abstract protected function close();
	
	/**
	* @brief Returns true if socket is open.
	*
	* @retval bool True if socket is open
	*/
	abstract protected function isOpen();
	
	/**
	* @brief Reads chunked content from socket and puts chunks to single string.
	*
	* @param string $headers HTTP response headers
	* @param WebRequest $request Original request
	* @retval string Raw response data string (or null when OnReceiveBodyCallback is set and
	*    returns false, see NetworkSocket::setOnReceiveBodyCallback)
	* @throws WebRequestException in case of errors 
	*/
	protected function readChunked($headers, WebRequest $request)
	{
		$ret = '';
		
		while(true)
		{
			$length = $this->readUntil("\r\n");
			if(!preg_match('/^[a-fA-F0-9]{1,8}$/', $length))
			{
				throw new WebRequestException('Incorrect chunked content',
					WebRequestException::INCORRECT_CHUNKED_CONTENT);
			}
			
			$length = (int)hexdec($length);
			if($length === 0)
				break;
			
			$data = $this->readLength($length, $headers, $request, $aborted);
			if($aborted)
				return null;
			
			if($data === null)
				$ret = null;
			else
				$ret .= $data;
			
			if($this->read(2) !== "\r\n")
			{
				throw new WebRequestException('Incorrect chunked content',
					WebRequestException::INCORRECT_CHUNKED_CONTENT);
			}
		}
		
		return $ret;
	}
	
	/**
	* @brief Reads data with specified length from socket. If socket closes connection
	*    or no more data is available to reach length, throws @ref WebRequestException.
	*
	* @param int $length Data length
	* @param string $headers HTTP response headers
	* @param WebRequest $request Original request
	* @param bool_reference $aborted Will be set to true if function was aborted from callback
	*    (see NetworkSocket::setOnReceiveBodyCallback)
	* @retval string Raw response data string (or null when OnReceiveBodyCallback is set and
	*    returns false, see NetworkSocket::setOnReceiveBodyCallback)
	* @throws WebRequestException in case of errors 
	*/
	abstract protected function readLength($length, $headers, WebRequest $request, &$aborted);
	
	/**
	* @brief Reads all available data from socket until it closes connection.
	*
	* @param string $headers HTTP response headers
	* @param WebRequest $request Original request
	* @retval string Raw response data string (or null when OnReceiveBodyCallback is set and
	*    returns false, see NetworkSocket::setOnReceiveBodyCallback)
	* @throws WebRequestException in case of errors 
	*/
	abstract protected function readAll($headers, WebRequest $request);
	
	/**
	* @brief Reads all available data from socket until specified substring is found inside contents.
	*
	* @param string $text Substring to search for
	* @retval string Raw response data string
	* @throws WebRequestException in case of errors 
	*/
	protected function readUntil($text)
	{
		$ret = '';
		while(true)
		{
			$data = $this->read(1);
			if(!isset($data[0]))
			{
				throw new WebRequestException('Unable to read until target substring',
					WebRequestException::UNABLE_TO_LOCATE_SUBSTRING);
			}
			
			$ret .= $data;
			if(strrpos($ret, $text) !== false)
				break;
		}
		
		return substr($ret, 0, -strlen($text));
	}
	
	/**
	* @brief Reads response headers.
	*
	* @retval string Raw response headers string
	* @throws WebRequestException in case of errors 
	*/
	protected function readHeaders()
	{
		return $this->readUntil("\r\n\r\n") . "\r\n\r\n";
	}
	
	/**
	* @brief Writes request to socket.
	*
	* @param WebRequest $request Request to write
	* @throws WebRequestException in case of errors 
	*/
	protected function write(WebRequest $request)
	{
		$request->writeTo($this);
	}
}

///Web proxy base class
abstract class WebProxy extends HttpSocket
{
	protected $socket = null;
	protected $login = null;
	protected $password = null;
	protected $address;
	protected $port;
	
	/**
	* @brief Constructor
	*
	* @param string $address
	*    Proxy address (hostname or IPv4 address)
	* @param int $port
	*    Proxy port
	*/
	public function __construct($address, $port)
	{
		$this->address = $address;
		$this->port = $port;
	}
	
	public function __destruct()
	{
		parent::__destruct();
	}
	
	/**
	* @brief Sets login and password for proxy.
	*
	* @param string $login
	*    Login string (or null, if no auth required)
	* @param string $password
	*    Password string (or null, if no auth required)
	*/
	public function setAuth($login, $password)
	{
		$this->login = $login;
		$this->password = $password;
	}
	
	/**
	* @brief Returns login for proxy.
	*
	* @retval string Login string (or null, if no auth required)
	*/
	public function getLogin()
	{
		return $this->login;
	}
	
	/**
	* @brief Returns password for proxy.
	*
	* @retval string Password string (or null, if no auth required)
	*/
	public function getPassword()
	{
		return $this->password;
	}
	
	/**
	* @brief Returns true if proxy needs to be authenticated.
	*
	* @retval bool True if proxy needs to be authenticated
	*/
	public function hasAuth()
	{
		return $this->login !== null
			&& $this->password !== null
			&& isset($this->login[0])
			&& isset($this->password[0]);
	}
	
	/**
	* @brief Sets socket for proxy. This can be another proxy or network socket.
	*
	* Remember to always set underlying proxy socket before using proxy networking functions.
	* <br><br>It is possible to build proxy chain using this function.
	* <br>Example:
	* <br>$proxy3 = new HttpProxy('test.site.com', 808);
	* <br>$proxy2 = new Socks5Proxy('123.100.200.10', 1080);
	* <br>$proxy1 = new Socks4AProxy('221.111.111.111', 777);
	* <br>$proxy3->setSocket($proxy2);
	* <br>$proxy2->setSocket($proxy1);
	* <br>$proxy1->setSocket(new FileSocket);
	* <br><br>This will create a chain:
	* <br>221.111.111.111:777 (socks4a)
	* -&gt; 123.100.200.10:1080 (socks5)
	* -&gt; test.site.com:808 (HTTP proxy)
	*
	* @param HttpSocket $socket underlying socket
	*/
	public function setSocket(HttpSocket $socket)
	{
		$this->socket = $socket;
	}
	
	protected function open($address, $port)
	{
		$this->socket->open($address, $port);
	}
	
	public function writeRaw($request)
	{
		return $this->socket->writeRaw($request);
	}
	
	protected function read($size)
	{
		return $this->socket->read($size);
	}
	
	protected function close()
	{
		if($this->socket !== null)
			$this->socket->close();
	}
	
	protected function isOpen()
	{
		return $this->socket->isOpen();
	}
	
	protected function readLength($length, $headers, WebRequest $request, &$aborted)
	{
		return $this->socket->readLength($length, $headers, $request, $aborted);
	}
	
	protected function readAll($headers, WebRequest $request)
	{
		return $this->socket->readAll($headers, $request);
	}
}

///Dummy socket to collect data written to it
class DumpSocket extends HttpSocket
{
	private $contents = '';
	
	protected function open($address, $port)
	{
	}
	
	public function writeRaw($request)
	{
		$this->contents .= $request;
	}
	
	protected function read($size)
	{
		return '';
	}
	
	protected function readLength($length, $headers, WebRequest $request, &$aborted)
	{
		return null;
	}
	
	protected function readAll($headers, WebRequest $request)
	{
		return null;
	}
	
	protected function close()
	{
	}
	
	protected function isOpen()
	{
		return true;
	}
	
	/**
	* @brief Returns contents written
	*
	* @retval string Contents
	*/
	public function getContents()
	{
		return $this->contents;
	}
	
	///Clears contents written
	public function clearContents()
	{
		$this->contents = '';
	}
	
	public function sendRequest(WebRequest $request)
	{
		$this->write($request);
		return null;
	}
}

///HTTP(S) proxy
class HttpProxy extends WebProxy
{
	public function __destruct()
	{
		parent::__destruct();
	}
	
	private function addAuthHeader(WebRequest $request)
	{
		if($this->hasAuth())
		{
			$request->getHeaderManager()->replaceHeader('Proxy-Authorization',
				'Basic ' . base64_encode($this->login . ':' . $this->password));
		}
	}
	
	/**
	* @brief Performs authorization of HTTP proxy, connects it to target host.
	*
	* @param string $address Target host address
	* @param int $port Target host port
	* @throws WebRequestException in case of error
	*/
	private function authorize($address, $port)
	{
		//TODO: basic / digest?
		$request = new WebRequest($address, $port,
			WebRequest::METHOD_CONNECT,
			$address . ':' . $port, false);
		$request->setSecure(false);
		$request->getHeaderManager()->clearHeaders();
		$this->addAuthHeader($request);
		
		$request->writeTo($this);
		
		$headers = new WebResponse($this->readHeaders());
		
		$code = $headers->getHttpCode();
		unset($headers);
		if($code !== 200)
		{
			if($code === 407)
			{
				throw new WebRequestException('Proxy authentication required',
					WebRequestException::PROXY_AUTHENTICATION_REQUIRED);
			}
			else
			{
				throw new WebRequestException('Proxy authentication error',
					WebRequestException::PROXY_AUTHENTICATION_ERROR);
			}
		}
	}
	
	public function sendRequest(WebRequest $request)
	{
		if(!$this->isOpen())
			$this->open($this->address, $this->port);
		
		if($this->socket instanceof WebProxy
			|| $request->isSecure())
		{
			if($this->socket instanceof WebProxy)
				$this->authorize($this->socket->address, $this->socket->port);
			else
				$this->authorize($request->getAddress(), $request->getPort());
			
			return $this->socket->sendRequest($request);
		}
		else
		{
			$proxy_request = clone $request;
			$proxy_request->setRequestUri('http://' . $request->getAddress() . ':' . $request->getPort()
				. $request->getRequestUri(), false);
			$this->addAuthHeader($proxy_request);
			$ret = $this->socket->sendRequest($proxy_request);
			if($ret === null)
				return null;
			
			$code = $ret->getHttpCode();
			if($code === 407)
			{
				throw new WebRequestException('Proxy authentication required',
					WebRequestException::PROXY_AUTHENTICATION_REQUIRED);
			}
			
			return $ret;
		}
	}
}

///Base class for SOCKS proxy
abstract class SocksProxy extends WebProxy
{
	/**
	* @brief Performs authorization of SOCKS proxy, connects it to target host.
	*
	* @param string $address Target host address
	* @param int $port Target host port
	* @throws WebRequestException in case of error
	*/
	abstract protected function authorize($address, $port);
	
	public function __destruct()
	{
		parent::__destruct();
	}
	
	public function sendRequest(WebRequest $request)
	{
		if(!$this->isOpen())
			$this->open($this->address, $this->port);
		
		if($this->socket instanceof WebProxy)
			$this->authorize($this->socket->address, $this->socket->port);
		else
			$this->authorize($request->getAddress(), $request->getPort());
		
		return $this->socket->sendRequest($request);
	}
}

///SOCKS5 proxy
class Socks5Proxy extends SocksProxy
{
	public function __destruct()
	{
		parent::__destruct();
	}
	
	protected function authorize($address, $port)
	{
		$this->writeRaw("\x05\x02\x00\x02");
		$result = $this->read(2);
		if($result === "\x05\x02")
		{
			if(!$this->hasAuth())
			{
				throw new WebRequestException('Proxy authentication required',
					WebRequestException::PROXY_AUTHENTICATION_REQUIRED);
			}
			
			$this->writeRaw("\x01"
				. chr(strlen($this->login))
				. $this->login
				. chr(strlen($this->password))
				. $this->password);
			
			if($this->read(2) !== "\x01\x00")
			{
				throw new WebRequestException('Proxy authentication error',
					WebRequestException::PROXY_AUTHENTICATION_ERROR);
			}
		}
		else if($result !== "\x05\x00")
		{
			throw new WebRequestException('Proxy authentication error',
				WebRequestException::PROXY_AUTHENTICATION_ERROR);
		}
		
		if(WebRequestHelpers::isIpV4Address($address))
		{
			$this->writeRaw("\x05\x01\x00\x01"
				. pack('N', ip2long($address))
				. pack('n', $port));
		}
		else
		{
			$this->writeRaw("\x05\x01\x00\x03"
				. chr(strlen($address))
				. $address
				. pack('n', $port));
		}
		
		$result = $this->read(4);
		if(!isset($result[3]) || $result[2] !== "\x00")
		{
			throw new WebRequestException('Proxy authentication error',
				WebRequestException::PROXY_AUTHENTICATION_ERROR);
		}
		
		switch($result[3])
		{
			case "\x01":
				$this->read(4);
				break;
			
			case "\x03":
				$len = $this->read(1);
				if(!isset($len[0]))
				{
					throw new WebRequestException('Proxy authentication error',
						WebRequestException::PROXY_AUTHENTICATION_ERROR);
				}
				
				$this->read(ord($len[0]));
				break;
			
			case "\x04":
				$this->read(16);
				break;
		}
		
		$this->read(2);
	}
}

///SOCKS4 proxy
class Socks4Proxy extends SocksProxy
{
	public function __destruct()
	{
		parent::__destruct();
	}
	
	protected function authorize($address, $port)
	{
		$this->writeRaw("\x04\x01"
			. pack('n', $port)
			. pack('N', ip2long(WebRequestHelpers::resolveHostname($address)))
			. ($this->login === null ? '' : $this->login)
			. "\x00");
		
		$result = $this->read(8);
		if(!isset($result[1]) || $result[0] !== "\x00" || $result[1] !== "\x5a")
		{
			throw new WebRequestException('Proxy authentication error',
				WebRequestException::PROXY_AUTHENTICATION_ERROR);
		}
	}
}

///SOCKS4a proxy
class Socks4AProxy extends SocksProxy
{
	public function __destruct()
	{
		parent::__destruct();
	}
	
	protected function authorize($address, $port)
	{
		$is_ipv4 = WebRequestHelpers::isIpV4Address($address);
		$this->writeRaw("\x04\x01"
			. pack('n', $port)
			. ($is_ipv4 ? pack('N', ip2long($address)) : "\x00\x00\x00\x01")
			. ($this->login === null ? '' : $this->login)
			. "\x00"
			. ($is_ipv4 ? '' : $address . "\x00"));
		
		$result = $this->read(8);
		
		if(!isset($result[1]) || $result[0] !== "\x00" || $result[1] !== "\x5a")
		{
			throw new WebRequestException('Proxy authentication error',
				WebRequestException::PROXY_AUTHENTICATION_ERROR);
		}
	}
}

///Base class for network socket implementations
abstract class NetworkSocket extends HttpSocket
{
	/**
	* @brief Timeout calculation mode: check for timeout for each network operation independently
	*
	* This mode is used to calculate timeout for each socket operation independently. If you set timeout value
	* to 30 seconds, then each call to open, read, write, etc will have 30 seconds to complete.
	*/
	const TIMEOUT_MODE_EVERY_OPERATION = 0;
	/**
	* @brief Timeout calculation mode: check for timeout for all network operations in total from open() call
	*
	* This mode is used to calculate elapsed time from socket open operation till any other socket operation.
	* For example, if timeout value is 30 seconds and socket was opened at 11:11:00, then
	* you have 30 seconds to do all operations like reading and writing. If you put sleep(10) between read
	* and write calls, this time will count, too. This timeout is flushed when socket is closed.
	*/
	const TIMEOUT_MODE_TOTAL = 1;
	/**
	* @brief Timeout calculation mode: check for timeout for all network operations in total
	*
	* This mode is used to calculate total time taken by all calls to open, read, write, etc.
	* This may be not very accurate.
	*/
	const TIMEOUT_MODE_SUM_OF_OPERATIONS = 2;
	
	private $on_receive_body = null;
	private $on_receive_headers = null;
	
	protected $timeout = 30;
	protected $timeout_mode = self::TIMEOUT_MODE_SUM_OF_OPERATIONS;
	protected $start_time = 0;
	protected $socket_open_time = 0;
	protected $time_taken = 0;
	
	public function __destruct()
	{
		parent::__destruct();
	}
	
	/**
	* @brief Sets callback function that will be called when a part of response
	*    body is read from socket.
	*
	* Callback function prototype:
	* @code
	*    bool func(string $headers, string $data_part, HttpSocket $this, WebRequest $request)
	* @endcode
	* Where: headers - raw response headers data;
	* <br>data_part - part of response body data;
	* <br>this - this HttpSocket instance;
	* <br>request - WebRequest that is being sent.
	* <br>This function must return true to continue reading from socket or false to abort reading.
	* When this callback is specified, NetworkSocket::sendRequest function will always return just headers string.
	* This will allow to still use HttpRequestManager for automatic redirection, cookies and authentication processing.
	*
	* @param callable $on_receive_body
	*    Callback function or null
	*/
	public function setOnReceiveBodyCallback(callable $on_receive_body = null)
	{
		$this->on_receive_body = $on_receive_body;
	}
	
	/**
	* @brief Returns callback function that will be called when a part of response
	*    body is read from socket.
	*
	* See also @ref setOnReceiveBodyCallback.
	*
	* @retval callable Callback function or null
	*/
	public function getOnReceiveBodyCallback()
	{
		return $this->on_receive_body;
	}
	
	/**
	* @brief Sets callback function that will be called when response
	*    headers are read from socket.
	*
	* Callback function prototype:
	* @code
	*    bool func(string $headers, HttpSocket $this, WebRequest $request)
	* @endcode
	* Where: headers - raw response headers data;
	* <br>this - this @ref HttpSocket instance;
	* <br>request - WebRequest that is being sent.
	* <br>This function must return true to continue reading from socket or false to abort reading.
	* When false is returned from callback, NetworkSocket::sendRequest function will return null.
	*
	* @param callable $on_receive_headers
	*    Callback function or null
	*/
	public function setOnReceiveHeadersCallback(callable $on_receive_headers = null)
	{
		$this->on_receive_headers = $on_receive_headers;
	}
	
	/**
	* @brief Returns callback function that will be called when response
	*    headers are read from socket.
	*
	* See also @ref setOnReceiveHeadersCallback.
	*
	* @retval callable Callback function or null
	*/
	public function getOnReceiveHeadersCallback()
	{
		return $this->on_receive_headers;
	}
	
	public function sendRequest(WebRequest $request)
	{
		if(!$this->isOpen())
			$this->open($request->getAddress(), $request->getPort());
		
		$this->setSecure($request->isSecure());
		
		$this->write($request);
		
		$ret = $this->readHeaders();
		if($this->on_receive_headers !== null)
		{
			$cb = $this->on_receive_headers;
			if(!$cb($ret, $this, $request))
			{
				$this->close();
				return new WebResponse($ret);
			}
		}
		
		$headers = new HttpHeaderManager($ret);
		$length = $headers->getContentLength();
		$is_chunked = $headers->isChunked();
		$content_encoding = $headers->getHeader('Content-Encoding');
		
		$headers = $ret;
		$ret = '';
		
		if($length === null)
		{
			if($is_chunked)
				$ret = $this->readChunked($headers, $request);
			else
				$ret = $this->readAll($headers, $request);
		}
		else if($length)
		{
			$ret = $this->readLength($length, $headers, $request, $aborted);
		}
		
		$this->close();
		
		if($ret === null) //callback on_body is present
			return new WebResponse($headers);
		
		if($content_encoding !== null)
		{
			switch($content_encoding)
			{
				case 'gzip':
					$ret = @gzdecode($ret);
					if($ret === false)
					{
						throw new WebRequestException('Unable to ungzip contents',
							WebRequestException::INCORRECT_GZIPPED_CONTENT);
					}
					break;
				
				case 'deflate':
					$ret = @gzuncompress($ret);
					if($ret === false)
					{
						throw new WebRequestException('Unable to ungzip contents',
							WebRequestException::INCORRECT_GZIPPED_CONTENT);
					}
					break;
					
				case 'identity':
					break;
				
				default:
					throw new WebRequestException('Unknown compression method',
						WebRequestException::UNKNOWN_COMPRESSION_METHOD);
					break;
			}
		}
		
		return new WebResponse($headers . $ret);
	}
	
	protected function readLength($length, $headers, WebRequest $request, &$aborted)
	{
		$aborted = false;
		if($this->on_receive_body !== null)
		{
			$cb = $this->on_receive_body;
			while($length)
			{
				$data = $this->read($length);
				$data_length = strlen($data);
				if(!$data_length)
				{
					throw new WebRequestException('Unable to read requested length from socket',
						WebRequestException::UNABLE_TO_READ);
				}
				
				$length -= $data_length;
				if(!$cb($headers, $data, $this, $request))
				{
					$aborted = true;
					break;
				}
			}
			
			return null;
		}
		else
		{
			$ret = '';
			
			while($length)
			{
				$data = $this->read($length);
				$data_length = strlen($data);
				if(!$data_length)
				{
					throw new WebRequestException('Unable to read requested length from socket',
						WebRequestException::UNABLE_TO_READ);
				}
				
				$length -= $data_length;
				$ret .= $data;
			}
			
			return $ret;
		}
	}
	
	protected function readAll($headers, WebRequest $request)
	{
		if($this->on_receive_body !== null)
		{
			$cb = $this->on_receive_body;
			while(true)
			{
				$data = $this->read(1024);
				if(!isset($data[0]))
					break;
				
				if(!$cb($headers, $data, $this, $request))
					break;
			}
			
			return null;
		}
		else
		{
			$ret = '';
			while(true)
			{
				$data = $this->read(1024);
				if(!isset($data[0]))
					break;
				
				$ret .= $data;
			}
			
			return $ret;
		}
	}
	
	/**
	* @brief Sets timeout in seconds for socket operations.
	*
	* Default value is 30 seconds.
	*
	* @param int $timeout Timeout in seconds for socket operations
	*/
	public function setTimeout($timeout)
	{
		$this->timeout = $timeout;
	}
	
	/**
	* @brief Returns timeout in seconds for socket operations.
	*
	* Default value is 30 seconds.
	*
	* @retval int Timeout in seconds for socket operations
	*/
	public function getTimeout()
	{
		return $this->timeout;
	}
	
	/**
	* @brief Returns timeout in seconds for socket operations.
	*
	* Differs from @ref getTimeout when @ref TIMEOUT_MODE_TOTAL
	* or @ref TIMEOUT_MODE_SUM_OF_OPERATIONS mode is selected (see @ref setTimeoutMode).
	* In this case, returns number of seconds that is available for socket operation.
	* For example, if socket open operation took 5 seconds and total timeout was set to 30 seconds,
	* then further calls will have only 25 seconds to complete.
	*
	* @retval float Timeout in seconds for socket operations
	*/
	public function getAvailableTime()
	{
		return max($this->timeout - $this->time_taken, 0);
	}
	
	/**
	* @brief Sets timeout calculation mode for socket operations.
	*
	* See also @ref TIMEOUT_MODE_TOTAL, @ref TIMEOUT_MODE_EVERY_OPERATION,
	* @ref TIMEOUT_MODE_SUM_OF_OPERATIONS.
	* Default value is @ref TIMEOUT_MODE_SUM_OF_OPERATIONS.
	*
	* @param int $timeout_mode Timeout calculation mode for socket operations
	*/
	public function setTimeoutMode($timeout_mode)
	{
		$this->timeout_mode = $timeout_mode;
	}
	
	/**
	* @brief Returns timeout calculation mode for socket operations.
	*
	* See also @ref TIMEOUT_MODE_TOTAL, @ref TIMEOUT_MODE_EVERY_OPERATION,
	* @ref TIMEOUT_MODE_SUM_OF_OPERATIONS.
	* Default value is @ref TIMEOUT_MODE_SUM_OF_OPERATIONS.
	*
	* @retval int Timeout calculation mode for socket operations
	*/
	public function getTimeoutMode()
	{
		return $this->timeout_mode;
	}
	
	/**
	* @brief Sets socket to secure mode or turns off secure mode.
	*
	* @param bool $secure True to set socket to secure mode, false to turn secure mode off
	* @throws WebRequestException in case of errors 
	*/
	abstract protected function setSecure($secure);
	
	/**
	* @brief Returns true if socket is in secure mode.
	*
	* @retval bool True if socket is in secure mode
	*/
	abstract protected function isSecure();
	
	public function writeRaw($data)
	{
		$this->startTimeMeasurement();
		
		for($written = 0, $len = strlen($data); $written < $len; $written += $wr)
			$wr = $this->writeRawPart(substr($data, $written));
		
		$this->checkPoint();
		return $written;
	}
	
	/**
	* @brief Writes part of raw data to socket.
	*
	* @retval int Number of bytes written
	*/
	abstract protected function writeRawPart($data);
	
	/**
	* @brief Starts time measurement for socket operation (read, write, connect, etc).
	*
	* @param bool $socket_open Set to true, if startTimeMeasurement
	*    is made from @ref open function
	*/
	protected function startTimeMeasurement($socket_open = false)
	{
		if($socket_open)
		{
			$this->socket_open_time = microtime(true);
			$this->time_taken = 0;
		}
		
		$this->start_time = microtime(true);
	}
	
	/**
	* @brief Stops time measurement for socket operation (read, write, connect, etc)
	*    and checks for timeout.
	*
	* @throws WebRequestException if timeout was detected
	*/
	protected function checkPoint()
	{
		if($this->timeout_mode === self::TIMEOUT_MODE_SUM_OF_OPERATIONS)
		{
			$this->time_taken += microtime(true) - $this->start_time;
			$time_taken = $this->time_taken;
		}
		else
		{
			$time_taken = microtime(true)
				- ($this->timeout_mode === self::TIMEOUT_MODE_EVERY_OPERATION
					? $this->start_time
					: $this->socket_open_time);
		}
		
		if($time_taken < 0 || $time_taken >= $this->timeout)
		{
			throw new WebRequestException('Socket timeout',
				WebRequestException::SOCKET_TIMEOUT);
		}
	}
}

/**
* @brief Socket based on PHP file operations (fsockopen)
*
* Supports secure mode, but is not able to calculate accurate timeouts
* and break operation exactly at the moment when timeout occured
*/
class FileSocket extends NetworkSocket
{
	private $socket;
	private $secure = false;
	
	public function __destruct()
	{
		parent::__destruct();
	}
	
	protected function open($address, $port)
	{
		$this->startTimeMeasurement(true);
		
		$this->socket = @fsockopen('tcp://' . $address, $port,
			$errno, $errstr, $this->getAvailableTime());
		if($this->socket === false)
		{
			$this->socket = null;
			throw new WebRequestException('Unable to connect to socket: ' . $errstr,
				WebRequestException::UNABLE_TO_CONNECT);
		}
		
		$this->checkPoint();
		$this->startTimeMeasurement();
		
		if(!stream_set_timeout($this->socket, $this->getAvailableTime()))
		{
			throw new WebRequestException('Unable to set socket timeout',
				WebRequestException::SOCKET_ERROR);
		}
		
		$this->checkPoint();
	}
	
	protected function setSecure($secure)
	{
		if($secure === $this->secure)
			return;
		
		$this->startTimeMeasurement();
		
		if($secure)
		{
			stream_context_set_option($this->socket, Array(
				'ssl' => Array(
					'verify_peer' => false,
					'verify_peer_name' => false,
					'allow_self_signed' => true
				)
			));
		}
		
		if(false === @stream_socket_enable_crypto($this->socket, $secure, STREAM_CRYPTO_METHOD_TLS_CLIENT))
		{
			throw new WebRequestException('Unable to upgrade socket to secure level',
				WebRequestException::SOCKET_ERROR);
		}
		
		$this->checkPoint();
	}
	
	protected function isSecure()
	{
		return $this->secure;
	}
	
	protected function isOpen()
	{
		return !!$this->socket;
	}
	
	protected function writeRawPart($request)
	{
		$ret = @fwrite($this->socket, $request);
		if($ret === false)
		{
			throw new WebRequestException('Unable to write to socket',
				WebRequestException::UNABLE_TO_WRITE);
		}
		
		return $ret;
	}
	
	protected function read($size)
	{
		$this->startTimeMeasurement();
		
		$ret = @fread($this->socket, $size);
		if($ret === false)
		{
			throw new WebRequestException('Unable to read from socket',
				WebRequestException::UNABLE_TO_READ);
		}
		
		$info = stream_get_meta_data($this->socket);
		if(is_array($info) && isset($info['timed_out']) && $info['timed_out'])
		{
			throw new WebRequestException('Unable to read from socket',
				WebRequestException::UNABLE_TO_READ);
		}
		
		$this->checkPoint();
		return $ret;
	}
	
	protected function close()
	{
		if($this->socket)
		{
			@fclose($this->socket);
			$this->socket = null;
			$this->secure = false;
		}
	}
}

/**
* @brief Socket based on PHP socket operations
*
* Does not support secure mode, but is able to calculate accurate timeouts
* and break operation exactly at the moment when timeout occured
*/
class LowLevelSocket extends NetworkSocket
{
	private $socket;
	
	public function __destruct()
	{
		parent::__destruct();
	}
	
	protected function open($address, $port)
	{
		$this->startTimeMeasurement(true);
		
		$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		if($this->socket === false
			|| !socket_set_nonblock($this->socket))
		{
			$this->socket = null;
			throw new WebRequestException('Unable to create socket',
				WebRequestException::SOCKET_ERROR);
		}
		
		$address = WebRequestHelpers::resolveHostname($address);
		$current_time = 0;
		while(!@socket_connect($this->socket, $address, $port))
		{
			if($current_time >= $this->getAvailableTime())
			{
				$this->close();
				throw new WebRequestException('Unable to connect to socket: timeout',
					WebRequestException::UNABLE_TO_CONNECT);
			}
			
			$err = socket_last_error($this->socket);
			if($err === SOCKET_EISCONN)
				break;
			
			if($err !== SOCKET_EINPROGRESS && $err !== SOCKET_EALREADY
				&& $err !== SOCKET_EWOULDBLOCK)
			{ 
				$this->close();
				throw new WebRequestException('Unable to connect to socket: ' . socket_strerror($err),
					WebRequestException::UNABLE_TO_CONNECT);
			}
			
			$current_time += 10 / 1000;
			usleep(10);
		}
		
		$this->checkPoint();
	}
	
	protected function setSecure($secure)
	{
		if($secure) //Not supported
		{
			throw new WebRequestException('Unable to upgrade socket to secure level',
				WebRequestException::SOCKET_ERROR);
		}
	}
	
	protected function isSecure()
	{
		return false;
	}
	
	protected function isOpen()
	{
		return !!$this->socket;
	}
	
	protected function writeRawPart($request)
	{
		$r = Array();
		$w = Array($this->socket);
		$e = Array();
		if(socket_select($r,
			$w,
			$e, $this->getAvailableTime()) !== 1)
		{
			throw new WebRequestException('Unable to write to socket',
				WebRequestException::UNABLE_TO_WRITE);
		}
		
		$written = @socket_write($this->socket, $request);
		if($written === false)
		{
			throw new WebRequestException('Unable to write to socket',
				WebRequestException::UNABLE_TO_WRITE);
		}
		
		return $written;
	}
	
	protected function read($size)
	{
		$this->startTimeMeasurement();
		
		$r = Array($this->socket);
		$w = Array();
		$e = Array();
		if(socket_select($r,
			$w,
			$e, $this->getAvailableTime()) !== 1)
		{
			throw new WebRequestException('Unable to read from socket',
				WebRequestException::UNABLE_TO_READ);
		}
		
		$ret = @socket_read($this->socket, $size);
		if($ret === false)
		{
			throw new WebRequestException('Unable to read from socket',
				WebRequestException::UNABLE_TO_READ);
		}
		
		$this->checkPoint();
		
		return $ret;
	}
	
	protected function close()
	{
		if($this->socket)
		{
			@socket_close($this->socket);
			$this->socket = null;
		}
	}
}

///HTTP cookie
class HttpCookie
{
	private $name;
	private $value;
	private $expires = null; //null = expires at session end
	private $path = '';
	private $domain = '';
	private $secure = false;
	private $http_only = false;
	private $domain_exact = true;
	
	static private $months = Array(
		'jan' => 1,
		'feb' => 2,
		'mar' => 3,
		'apr' => 4,
		'may' => 5,
		'jun' => 6,
		'jul' => 7,
		'aug' => 8,
		'sep' => 9,
		'oct' => 10,
		'nov' => 11,
		'dec' => 12
	);
	
	/**
	* @brief Constructor
	*
	* @param string $name
	*    Cookie name
	* @param mixed $value
	*    Cookie value (any scalar PHP type)
	*/
	public function __construct($name, $value = '')
	{
		$this->name = $name;
		$this->value = $value;
	}
	
	/**
	* @brief Converts cookie to string, which is suitable for HTTP request.
	*
	* See also HttpCookie::toResponseString.
	*
	* @retval string Cookie string value
	*/
	public function __toString()
	{
		return $this->name . '=' . $this->value;
	}
	
	/**
	* @brief Returns cookie name.
	*
	* @retval string Cookie name
	*/
	public function getName()
	{
		return $this->name;
	}
	
	/**
	* @brief Returns cookie value.
	*
	* @retval string Cookie value
	*/
	public function getValue()
	{
		return $this->value;
	}
	
	/**
	* @brief Returns cookie path.
	*
	* @retval string Cookie path
	*/
	public function getPath()
	{
		return $this->path;
	}
	
	/**
	* @brief Returns cookie domain.
	*
	* @retval string Cookie domain
	*/
	public function getDomain()
	{
		return $this->domain;
	}
	
	/**
	* @brief Returns true, if cookie domain must match to server domain exactly.
	*
	* @retval string True, if cookie domain must match to server domain exactly
	*/
	public function isExactDomain()
	{
		return $this->domain_exact;
	}
	
	/**
	* @brief Returns cookie expiry UNIX timestamp.
	*
	* @retval string Cookie expiry UNIX timestamp
	*/
	public function getExpires()
	{
		return $this->expires;
	}
	
	/**
	* @brief Returns true, if cookie has HttpOnly attribute.
	*
	* @retval string True, if cookie has HttpOnly attribute
	*/
	public function isHttpOnly()
	{
		return $this->http_only;
	}
	
	/**
	* @brief Returns true, if cookie can be sent only over HTTPS.
	*
	* @retval string True, if cookie can be sent only over HTTPS
	*/
	public function isSecure()
	{
		return $this->secure;
	}
	
	/**
	* @brief Sets cookie name.
	*
	* @param string $name Cookie name
	*/
	public function setName($name)
	{
		$this->name = $name;
	}
	
	/**
	* @brief Sets cookie value.
	*
	* @param mixed $value Cookie value (any PHP scalar type)
	*/
	public function setValue($value)
	{
		$this->value = $value;
	}
	
	/**
	* @brief Sets cookie path.
	*
	* @param string $path Cookie path
	*/
	public function setPath($path)
	{
		$this->path = $path;
	}
	
	/**
	* @brief Sets cookie domain.
	*
	* @param string $domain Cookie domain
	* @param bool $exact If true, exact domain matching
	*    must be performed by @ref isAccepted
	*/
	public function setDomain($domain, $exact = false)
	{
		$this->domain = $domain;
		$this->domain_exact = $exact;
	}
	
	/**
	* @brief Sets cookie expiry time.
	*
	* @param int $expires Cookie expiry time (UNIX timestamp)
	*/
	public function setExpires($expires)
	{
		$this->expires = $expires;
	}
	
	/**
	* @brief Sets if cookie has HTTP only attribute.
	*
	* @param bool $http_only If true, cookie has HTTP only attribute
	*/
	public function setHttpOnly($http_only = true)
	{
		$this->http_only = $http_only;
	}
	
	/**
	* @brief Sets if cookie can be sent only over HTTPS.
	*
	* @param bool $secure If true, cookie can be sent only over HTTPS
	*/
	public function setSecure($secure = true)
	{
		$this->secure = $secure;
	}
	
	/**
	* @brief Returns true, if cookie is expired.
	*
	* @param mixed $current_time
	*    Can be null, which means session end.
	*    Can be int (current UNIX timestamp)
	* @retval string True, if cookie is expired
	*/
	public function isExpired($current_time)
	{
		if($this->expires === null)
			return $current_time === null;
		
		return $this->expires < $current_time;
	}
	
	/**
	* @brief Returns true, if cookie can be sent to specified domain, path and protocol.
	*
	* @param string $domain
	*    Host domain
	* @param string $path
	*    Request URI path portion
	* @param bool $secure
	*    If true, HTTPS protocol, otherwise HTTP
	* @retval string True, if cookie can be sent to specified domain, path and protocol
	*/
	public function isAccepted($domain, $path, $secure)
	{
		if($this->secure && !$secure)
			return false;
		
		if(isset($this->path[0]) && isset($path[0]))
		{
			if($path[strlen($path) - 1] !== '/')
				$path .= '/';
			
			$cookie_path = $this->path;
			if($cookie_path[strlen($cookie_path) - 1] !== '/')
				$cookie_path .= '/';
			
			if($cookie_path !== $path)
			{
				$pos = strpos($path, $cookie_path);
				if($pos !== 0 || $path[$pos] !== '/')
					return false;
			}
		}
		
		if(isset($domain[0]) && isset($this->domain[0]))
		{
			$domain = TextHelpers::toLower($domain);
			$cookie_domain = TextHelpers::toLower($this->domain);
			if($domain !== $cookie_domain)
			{
				if($this->domain_exact)
					return false;
			}
			else
			{
				return true;
			}
			
			if(WebRequestHelpers::isIpV4Address($cookie_domain)
				|| WebRequestHelpers::isIpV4Address($domain))
			{
				return false;
			}
			
			$cookie_domain = '.' . $cookie_domain;
			$pos = strrpos($domain, $cookie_domain);
			if($pos === false || $pos + strlen($cookie_domain) !== strlen($domain))
				return false;
		}
		
		return true;
	}
	
	
	/**
	* @brief Parses cookie date string to UNIX timestamp.
	*
	* @param string $cookie_date
	*    Cookie date string
	* @retval int
	*    UNIX timestamp or null on error
	*/
	public static function parseCookieDate($cookie_date)
	{
		$cookie_date = preg_replace('/^[\x09\x20-\x2f\x3b-\x40\x5b-\x60\x7b-\x7e]*(.+?)[\x09\x20-\x2f\x3b-\x40\x5b-\x60\x7b-\x7e]*$/',
			'\\1', $cookie_date);
		
		$cookie_date = preg_split('/[\x09\x20-\x2f\x3b-\x40\x5b-\x60\x7b-\x7e]/',
			$cookie_date, -1, PREG_SPLIT_NO_EMPTY);
		
		$hour = null;
		$minute = null;
		$second = null;
		$day_of_month = null;
		$year = null;
		$month = null;
		
		foreach($cookie_date as $data)
		{
			if(preg_match('/^(\d{1,2})(?:[\x01-\x2f][\x01-\xff]*)?$/', $data, $match))
			{
				if(isset($match[1][1]) && $day_of_month !== null /* day of month goes first */)
				{
					if($year !== null)
						return null;
					
					$year = $match[1];
					if($year < 70)
						$year += 2000;
					else
						$year += 1900;
				}
				else
				{
					$day_of_month = $match[1];
				}
			}
			else if(preg_match('/^(\d{4})(?:[\x01-\x2f][\x01-\xff]*)?$/', $data, $match))
			{
				if($year !== null)
					return null;
				
				$year = $match[1];
			}
			else if(preg_match('/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)'
				. '(?:[\x01-\xff]*)?$/i', $data, $match))
			{
				if($month !== null)
					return null;
				
				$month = self::$months[TextHelpers::toLower($match[1])];
			}
			else if(preg_match('/^(\d{1,2}):(\d{1,2}):(\d{1,2})(?:[\x01-\x2f][\x01-\xff]*)?$/', $data, $match))
			{
				if($hour !== null)
					return null;
				
				if($match[1] > 23 || $match[2] > 59 || $match[3] > 59)
					return null;
				
				$hour = $match[1];
				$minute = $match[2];
				$second = $match[3];
			}
		}
		
		if(!checkdate($month, $day_of_month, $year) || $year < 1601)
			return null;
		
		if($year < 1970)
			return 0;
		
		return gmmktime($hour, $minute, $second, $month, $day_of_month, $year);
	}
	
	/**
	* @brief Creates @ref HttpCookie object from cookie header value string.
	*
	* @param string $str
	*    Value of Set-Cookie header
	* @param WebRequest $request
	*    Original @ref WebRequest is needed to check/calculate domain and path of cookie.
	*    Can be null.
	*    If WebRequest is passed, then domain and path of cookie
	*    will be calculated if needed and checked properly.
	* @retval HttpCookie Cookie object or null on error
	*/
	public static function fromString($str, WebRequest $request = null)
	{
		if(!preg_match('/^'
			. '([\x21\x23-\x27\x2a\x2b-\x2e\x30-\x39\x41-\x5a\x5e-\x7a\x7c\x7e]+)'
			. '=("?)([\x21\x23-\x2b\x2d-\x3a\x3c-\x5b\x5d-\x7e]*)\2'
			. '(?:; (.*))?$/i', $str, $match))
		{
			return null;
		}
		
		$cookie = new HttpCookie($match[1], $match[3]);
		
		$has_max_age = false;
		$has_path = false;
		$has_domain = false;
		if(isset($match[4]))
		{
			$cookie_av = array_map('trim', explode(';', $match[4]));
			unset($match);
			
			foreach($cookie_av as $av)
			{
				$av = explode('=', $av, 2);
				$av[0] = TextHelpers::toLower($av[0]);
				if(count($av) === 1)
				{
					if($av[0] === 'httponly')
						$cookie->setHttpOnly();
					else if($av[0] === 'secure')
						$cookie->setSecure();
					else if(!self::isExtensionString($av[0]))
						return null; //Extension can not contain CTLs
				}
				else
				{
					switch($av[0])
					{
						case 'path':
							if(!self::isExtensionString($av[1]))
								return null; //Pathcan not contain CTLs
							
							$cookie->setPath($av[1]);
							$has_path = true;
							break;
							
						case 'domain':
							if(!preg_match('/^\.?([a-z0-9\-]*[a-z0-9](?:\.[a-z0-9\-]*[a-z0-9])*)$/i', $av[1], $match))
								return null;
							
							$cookie->setDomain($match[1], false);
							unset($match);
							$has_domain = true;
							break;
							
						case 'expires':
							if($has_max_age)
								continue;
							
							$expires = self::parseCookieDate($av[1]);
							if($expires === null)
								return null;
							
							$cookie->setExpires($expires);
							break;
							
						case 'max-age':
							$has_max_age = true;
							if(!preg_match('/^(\d{1,10})$/i', $av[1], $match))
								return null;
							
							if((int)$match[1] == 0)
								$cookie->setExpires(0);
							else
								$cookie->setExpires(time() + $match[1]);
							
							unset($match);
							break;
							
						default:
							if(!self::isExtensionString($av[0])
								|| !self::isExtensionString($av[1]))
								return null; //Extension can not contain CTLs
							break;
					}
				}
			}
		}
		
		if($request !== null)
		{
			if(!$has_path)
				$cookie->setPath($request->getRequestUriPath());
			
			if(!$has_domain)
			{
				$cookie->setDomain($request->getAddress(), true);
			}
			else
			{
				$address = $request->getAddress();
				if(WebRequestHelpers::isIpV4Address($address))
				{
					$cookie->setDomain($address, true);
				}
				else
				{
					$cookie_domain = TextHelpers::toLower($cookie->getDomain());
					$address_lower = TextHelpers::toLower($address);
					if($address_lower !== $cookie_domain)
					{
						$dotted_cookie_domain = '.' . $cookie_domain;
						$pos = strpos($address_lower, $dotted_cookie_domain);
						if($pos === false)
							return null;
						
						if($pos + strlen($dotted_cookie_domain) !== strlen($address_lower))
							return null;
						
						if(strpos($cookie_domain, '.') === false) //restrict top-level domains (domain zones)
							return null;
					}
				}
			}
		}
		
		return $cookie;
	}
	
	/**
	* @brief Returns cookie string that corresponds to Set-Cookie header format.
	*
	* Note that this string can differ from source string which was passed to HttpCookie::fromString.
	* See also HttpCookie::__toString.
	*
	* @retval string Cookie string
	*/
	public function toResponseString()
	{
		$ret = (string)$this;
		if($this->expires !== null)
			$ret .= '; expires=' . gmdate('D, d M Y H:i:s T', $this->expires);
		
		if(isset($this->path[0]))
			$ret .= '; path=' . $this->path;
		
		if(isset($this->domain[0]) && !$this->domain_exact)
			$ret .= '; domain=' . $this->domain;
		
		if($this->secure)
			$ret .= '; secure';
		
		if($this->http_only)
			$ret .= '; HttpOnly';
		
		return $ret;
	}
	
	static private function isExtensionString($str)
	{
		return preg_match('/^[\x20-\x3a\x3c-\x7e]+$/', $str);
	}
}

///HTTP cookie manager
class HttpCookieManager
{
	///Cookie filtering mode: go to next cookie
	const FILTER_NEXT = 0;
	///Cookie filtering mode: abort cookie enumeration
	const FILTER_ABORT = 1;
	///Cookie filtering mode: remove current cookie and go to next one
	const FILTER_REMOVE_COOKIE_NEXT = 2;
	///Cookie filtering mode: remove current cookie and abort cookie enumeration
	const FILTER_REMOVE_COOKIE_ABORT = 3;
	
	private $cookies = Array(); //Array(cookies)
	
	/**
	* @brief Adds new cookie.
	*
	* @param HttpCookie $cookie
	*    Cookie to add
	*/
	public function addCookie(HttpCookie $cookie)
	{
		$name = $cookie->getName();
		for($i = 0, $cnt = count($this->cookies); $i !== $cnt; ++$i)
		{
			if($this->cookies[$i]->getName() === $name)
			{
				$this->cookies[$i] = $cookie;
				return;
			}
		}
		
		$this->cookies[] = $cookie;
	}
	
	/**
	* @brief Performs cookie enumeration and filtering.
	*
	* Filtering function must have the following prototype:
	* @code
	*    int func(HttpCookie $cookie);
	* @endcode
	* Where cookie is current @ref HttpCookie object.
	* <br>Return value must be one of @ref FILTER_NEXT,
	* @ref FILTER_ABORT, @ref FILTER_REMOVE_COOKIE_NEXT
	* and @ref FILTER_REMOVE_COOKIE_ABORT.
	*
	* @param callable $filter_func
	*    Cookie filtering function
	*/
	public function filterCookies(callable $filter_func)
	{
		$break_loop = false;
		$cookies_removed = false;
		for($i = 0, $cnt = count($this->cookies); $i !== $cnt && !$break_loop; ++$i)
		{
			switch($filter_func($this->cookies[$i]))
			{
				case self::FILTER_REMOVE_COOKIE_NEXT:
					unset($this->cookies[$i]);
					$cookies_removed = true;
					//Fall through
				case self::FILTER_NEXT:
					break;
				
				case self::FILTER_REMOVE_COOKIE_ABORT:
					unset($this->cookies[$i]);
					$cookies_removed = true;
					//Fall through
				case self::FILTER_ABORT:
					$break_loop = true;
					break;
			}
		}
		
		if($cookies_removed)
			$this->cookies = array_values($this->cookies);
	}
	
	/**
	* @brief Performs cookie cleanup. Removes all expired cookies.
	*
	* @param mixed $current_time
	*    Can be null, which means session end.
	*    Can be int (current UNIX timestamp)
	*/
	public function cleanupCookies($current_time)
	{
		for($i = 0, $cnt = count($this->cookies); $i !== $cnt; ++$i)
		{
			if($this->cookies[$i]->isExpired($current_time))
				unset($this->cookies[$i]);
		}
		
		$this->cookies = array_values($this->cookies);
	}
	
	/**
	* @brief Returns all cookies.
	*
	* @retval Array Cookies array (value is HttpCookie, key is index)
	*/
	public function getAllCookies()
	{
		return $this->cookies;
	}
	
	///Removes all cookies.
	public function removeAllCookies()
	{
		$this->cookies = Array();
	}
	
	/**
	* @brief Adds all cookies that match specified request to that request.
	*
	* Exactly, adds Set-Cookie headers to request.
	*
	* @param WebRequest $request
	*    HTTP request
	*/
	public function getCookies(WebRequest $request)
	{
		$domain = $request->getAddress();
		$path = $request->getRequestUriPath();
		$secure = $request->isSecure();
		
		$cookies = Array();
		
		foreach($this->cookies as $cookie)
		{
			if($cookie->isAccepted($domain, $path, $secure))
				$cookies[] = (string)$cookie;
		}
		
		if(!empty($cookies))
			$request->getHeaderManager()->replaceHeader('Cookie', implode('; ', $cookies));
		else
			$request->getHeaderManager()->removeHeader('Cookie');
	}
}

///High-level HTTP request manager, which supports HTTP redirects, cookies and authentication
class HttpRequestManager
{
	private $cookie_manager = null;
	private $socket;
	private $max_redirection_count = 10;
	private $on_redirect = null;
	private $auth_data = Array();
	private $universal_auth_data = Array();
	private $use_automatic_referer = true;
	
	/**
	* @brief Constructor
	*
	* @param HttpSocket $socket
	*    Socket to use for requests
	* @param HttpCookieManager $cookie_manager
	*    Cookie manager to use. Can be null.
	*/
	public function __construct(HttpSocket $socket, HttpCookieManager $cookie_manager = null)
	{
		$this->cookie_manager = $cookie_manager;
		$this->socket = $socket;
	}
	
	/**
	* @brief Sets callback function that will be called before HTTP redirect occurs.
	*
	* The prototype of callback function:
	* @code
	*    bool func(WebRequest $original_request, WebResponse $response,
	*        WebRequest $new_request, int $http_code, HttpRequestManager $this);
	* @endcode
	* Where:
	* <br>$original_request is original WebRequest that lead to redirection response;
	* <br>$response is server response;
	* <br>$new_request is WebRequest that is going to be sent;
	* <br>$http_code is HTTP redirection code;
	* <br>$this is this request manager.
	* <br><br>This function must return true to run redirect or false to abort it.
	* If request is aborted from this callback, @ref runRequest will return last WebResponse.
	*
	* @param callable $on_redirect
	*    Callback function or null, if no HTTP redirect interception is needed
	*/
	public function setOnRedirectCallback(callable $on_redirect = null)
	{
		$this->on_redirect = $on_redirect;
	}
	
	/**
	* @brief Returns callback function that will be called before HTTP redirect occurs.
	*
	* See @ref setOnRedirectCallback also.
	*
	* @retval callable Callback function or null, if no HTTP redirect interception is needed
	*/
	public function getOnRedirectCallback()
	{
		return $this->on_redirect;
	}
	
	/**
	* @brief Returns cookie manager.
	*
	* @retval HttpCookieManager Cookie manager to use or null.
	*/
	public function getCookieManager()
	{
		return $this->cookie_manager;
	}
	
	/**
	* @brief Sets cookie manager.
	*
	* @param HttpCookieManager $cookie_manager
	*    Cookie manager to use. Can be null.
	*/
	public function setCookieManager(HttpCookieManager $cookie_manager = null)
	{
		$this->cookie_manager = $cookie_manager;
	}
	
	/**
	* @brief Returns socket to use for requests.
	*
	* @retval HttpSocket socket to use for requests.
	*/
	public function getSocket()
	{
		return $this->socket;
	}
	
	/**
	* @brief Sets socket to use for requests.
	*
	* @param HttpSocket $socket
	*    Socket to use for requests
	*/
	public function setSocket(HttpSocket $socket)
	{
		$this->socket = $socket;
	}
	
	/**
	* @brief Returns maximal HTTP redirection count. Default is 10.
	*
	* @retval int Maximal HTTP redirection count or -1 (unlimited redirects)
	*/
	public function getMaxRedirectionCount()
	{
		return $this->max_redirection_count;
	}
	
	/**
	* @brief Sets maximal HTTP redirection count. Default is 10.
	*
	* @param int $max_redirection_count
	*    Maximal HTTP redirection count or -1 (unlimited redirects)
	*/
	public function setMaxRedirectionCount($max_redirection_count)
	{
		$this->max_redirection_count = $max_redirection_count;
	}
	
	/**
	* @brief Returns true if automatic referer header set up is on for redirections.
	*
	* @retval bool True if automatic referer header set up is on for redirections
	*/
	public function isAutomaticRefererUsed()
	{
		return $this->use_automatic_referer;
	}
	
	/**
	* @brief Sets if automatic referer header set up is on for redirections.
	*
	* @param int $use_automatic_referer
	*    If true, automatic referer header set up is on for redirections.
	*/
	public function useAutomaticReferer($use_automatic_referer)
	{
		$this->use_automatic_referer = $use_automatic_referer;
	}
	
	/**
	* @brief Adds authentication information for realm to allow automatic authentication (basic/digest) for it.
	*
	* @param string $realm
	*    Realm name. If null, this function will set login and password for all non-listed realms
	* @param string $login Login
	* @param string $password Password
	*/
	public function addAuthData($realm, $login, $password)
	{
		if($realm === null)
			$this->universal_auth_data = Array($login, $password);
		else
			$this->auth_data[$realm] = Array($login, $password);
	}
	
	/**
	* @brief Returns authentication information for realm.
	*
	* @param string $realm
	*    Realm name. If null, this function will return login and password for all non-listed realms
	* @retval Array Array('login', 'password').
	*    Can be null, if no authentication information present for realm
	*/
	public function getAuthData($realm)
	{
		if($realm === null)
		{
			if(!empty($this->universal_auth_data))
				return $this->universal_auth_data;
		}
		else
		{
			if(isset($this->auth_data[$realm]))
				return $this->auth_data[$realm];
		}
		
		return null;
	}
	
	/**
	* @brief Removes authentication information for realm.
	*
	* @param string $realm
	*    Realm name. If null, this function will remove login and password for all non-listed realms
	*/
	public function removeAuthData($realm)
	{
		if($realm === null)
		{
			$this->universal_auth_data = Array();
		}
		else
		{
			if(isset($this->auth_data[$realm]))
				unset($this->auth_data[$realm]);
		}
	}
	
	///Clears authentication information for all realms.
	public function clearAuthData()
	{
		$this->auth_data = Array();
		$this->universal_auth_data = Array();
	}
	
	/**
	* @brief Performs HTTP request.
	*
	* May modify $request in case HTTP authentication will be required.
	*
	* @param WebRequest $request
	*    HTTP request
	* @retval WebResponse HTTP response (or just response headers in some cases,
	*    see NetworkSocket::setOnReceiveHeadersCallback, NetworkSocket::setOnReceiveBodyCallback)
	* @throws WebRequestException if an error occured
	*/
	public function runRequest(WebRequest $request)
	{
		return $this->runRequestInternal($request, 0);
	}
	
	private function runRequestInternal(WebRequest $request, $current_redirect, $in_auth = false)
	{
		if($this->cookie_manager !== null)
		{
			$this->cookie_manager->cleanupCookies(time());
			$this->cookie_manager->getCookies($request);
		}
		
		$response = $this->socket->sendRequest($request);
		if($response === null)
			return null;
		
		$headers = $response->getHeaders();
		
		if($this->cookie_manager !== null)
		{
			$cookies = $headers->getHeaders('Set-Cookie');
			foreach($cookies as $cookie_string)
			{
				$cookie = HttpCookie::fromString($cookie_string, $request);
				if($cookie !== null)
					$this->cookie_manager->addCookie($cookie);
			}
			
			$this->cookie_manager->cleanupCookies(time());
		}
		
		while($this->max_redirection_count !== 0
			&& ($this->max_redirection_count === -1
				|| $this->max_redirection_count > $current_redirect))
		{
			$location = $headers->getHeader('Location');
			if($location === null || !isset($location[0]))
				break;
			
			$code = $response->getHttpCode();
			$next_request = null;
			$url = $location;
			$pos = strpos($location, '://');
			if($pos === false)
			{
				//RFC allows only absolute URI
				//This is non-RFC code
				$url = ($request->isSecure() ? 'https://' : 'http://')
					. $request->getAddress() . ':' . $request->getPort();
				
				if($location[0] === '/')
					$url .= $location;
				else if($location[0] === '?')
					$url .= $request->getRequestUri() . $location;
				else
					$url .= $request->getRequestUriPath() . $location;
			}
			
			try
			{
				$next_request = WebRequest::createFromUrl($url, false, false);
				unset($url);
			}
			catch(WebRequestException $e)
			{
				break;
			}
			
			++$current_redirect;
			
			$next_request->setMethod($request->getMethod());
			$next_request->setHttpVersion($request->getHttpVersion());
			$next_request->setBoundary($request->getBoundary());
			
			if($this->use_automatic_referer)
			{
				//Do not send referrer on redirect from HTTPS to HTTP
				if(!$request->isSecure() || $next_request->isSecure())
					$next_request->getHeaderManager()->replaceHeader('Referer', $request->getFullAddress(false));
			}
			
			switch($code)
			{
				case 301:
				case 302:
				case 303:
					if($next_request->getMethod() !== WebRequest::METHOD_HEAD
						&& $next_request->getMethod() !== WebRequest::METHOD_GET)
					{
						$next_request->setMethod(WebRequest::METHOD_GET);
					}
					break;
				
				case 307:
				case 308:
					$new_params = $next_request->getParamManager();
					$old_params = $request->getParamManager();
					
					$params = $old_params->getParams(HttpParamManager::NON_GET_ONLY_PARAMS);
					
					//next_request param manager has auto urlencode disabled
					if(!$old_params->isAutoUrlEncodeEnabled())
					{
						foreach($params as $name => $value)
							$new_params->setParam($name, $value, false);
					}
					else
					{
						$urlencode_array = function($value)
						{
							return $value instanceof HttpAttachment
								? $value
								: urlencode($value);
						};
						
						foreach($params as $name => $value)
						{
							$new_params->setParam(urlencode($name),
								$value instanceof HttpAttachment
								? $value
								: is_array($value)
									? array_map($urlencode_array, $value)
									: urlencode($value), false);
						}
					}
					
					unset($new_params, $old_params, $params);
					break;
				
				default:
					break 2;
			}
			
			unset($location);
			
			$call_redirect = true;
			if(is_callable($this->on_redirect))
			{
				$cb = $this->on_redirect;
				$call_redirect = $cb($request, $response, $next_request, $code, $this);
				unset($cb);
			}
			
			unset($request, $code);
			
			if($call_redirect)
			{
				unset($call_redirect, $response, $headers);
				return $this->runRequestInternal($next_request, $current_redirect);
			}
		}
		
		if((!empty($this->auth_data) || !empty($this->universal_auth_data)) && !$in_auth)
		{
			$auth_type = $headers->getAuthenticationType();
			if($auth_type !== HttpHeaderManager::HTTP_AUTHENTICATION_NONE)
			{
				$auth_opts = $headers->getAuthenticationOptions();
				if(isset($auth_opts['realm'])
					&& (isset($this->auth_data[$auth_opts['realm']]) || !empty($this->universal_auth_data)))
				{
					$auth = isset($this->auth_data[$auth_opts['realm']])
						? $this->auth_data[$auth_opts['realm']]
						: $this->universal_auth_data;
					
					if($auth_type === HttpHeaderManager::HTTP_AUTHENTICATION_BASIC)
					{
						$request->setBasicAuthenticationCredentials($auth[0], $auth[1]);
					}
					else //digest
					{
						if($request->getBoundary() === null)
							$request->setBoundary(md5(mt_rand()));
						
						$request->setDigestAuthenticationCredentials($auth_opts, $auth[0], $auth[1]);
					}
					
					unset($response, $headers);
					return $this->runRequestInternal($request, $current_redirect, true);
				}
			}
		}
		
		return $response;
	}
}

/**
* @example basic_http_request.php
*
* This is an example of how to perform a basic HTTP request
* (No proxies, no cookie management or automatic redirections).
* Anyway, chunked/gzipped/deflated content is supported.
*/

/**
* @example basic_https_request.php
*
* This is an example of how to perform a basic HTTPS request
* (No proxies, no cookie management or automatic redirections).
* Anyway, chunked/gzipped/deflated content is supported.
*/

/**
* @example http_get_parameters.php
*
* This is an example of how to send GET request with parameters.
*/

/**
* @example http_post_parameters.php
*
* This is an example of how to send POST request with parameters.
*/

/**
* @example http_file_upload.php
*
* This is an example of how to send POST request with file uploads.
*/

/**
* @example basic_http_request_manager.php
*
* This is an example of how to work with HttpRequestManager, which allows automatical
* cookie tracking and supports HTTP redirections with referer set up.
*/

/**
* @example http_cookies.php
*
* This is an example of how to deal with cookies.
*/

/**
* @example basic_http_authentication.php
*
* This is an example of how to use automatic basic or digest HTTP authentication.
*/

/**
* @example manual_http_authentication.php
*
* This is an example of how to use manual basic or digest HTTP authentication.
*/

/**
* @example basic_proxy.php
*
* This is an example of how to use proxies.
*/

/**
* @example proxy_authentication.php
*
* This is an example of how to use proxies with authentication.
*/

/**
* @example proxy_chain.php
*
* This is an example of how to chain proxies.
*/

/**
* @example timeouts.php
*
* This is an example of how to set up timeouts for requests.
*/

/**
* @example redirection_interception.php
*
* This is an example of how to intercept automatic HTTP redirections with @ref HttpRequestManager.
*/

/**
* @example response_interception.php
*
* This is an example of how to intercept HTTP heades and body response receiving.
*/
