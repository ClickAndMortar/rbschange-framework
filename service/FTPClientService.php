<?php
/**
 * @package framework.service
 */
class f_FTPClientService extends BaseService
{
	/**
	 * @var f_FTPClientService
	 */
	private static $instance;

	/**
	 * @return f_FTPClientService
	 */
	public static function getInstance()
	{
		if (is_null(self::$instance))
		{
			self::$instance = self::getServiceClassInstance(get_class());
		}
		return self::$instance;
	}

	/**
	 * @return f_FTPClient
	 * @param String $host
	 * @param Integer $port
	 */
	public function getNewClient($host, $port = 21)
	{
		return new f_FTPClient($host, $port);
	}
	
	/**
	 * @param String $localPath
	 * @param String $fullRemotePath "ftp://username:password@server:port/path"
	 */
	public function put($localPath, $fullRemotePath)
	{
		$matches = null;
		if (!preg_match('#ftp://([^\:@/]+)(\:([^\:@/]+))?@([^\:@/]+)(\:([0-9]+))?/(.+)#', $fullRemotePath, $matches))
		{
			throw new Exception("Invalid format for fullRemotePath");
		}
		$username = urldecode($matches[1]);
		$password = urldecode($matches[3]);
		$host = $matches[4];
		$port = $matches[6];
		$remotePath = $matches[7];
		
		$client = $this->getNewClient($host, $port);
		$client->login($username, $password);
		$client->put($localPath, $remotePath);
		$client->close();
	}
}

class f_FTPClient
{
	private $port;
	private $host;
	private $connectionId;
	private $username;
	private $password;
	private $logged = false;
	
	/**
	 * @param String $host
	 * @param Integer $port
	 */
	public function __construct($host, $port = 21)
	{
		$this->host = $host;
		$this->port = $port;
	}
	
	/**
	 * Close ftp connection if needed
	 */
	public function close()
	{
		if ($this->connectionId !== null)
		{
			ftp_close($this->connectionId);
			$this->connectionId = null;
			$this->logged = false;
		}
	}
	
	/**
	 * @param String $username
	 * @param String $password
	 */
	public function login($username, $password)
	{
		if (!$this->logged)
		{
			$this->connect();
			$login_result = ftp_login($this->connectionId, $username, $password);
			if (!$login_result)
			{
				$this->close();
				throw new IOException("Could not log in with user (".$username."@".$this->host.":".$this->port.")");
			}
			if (!ftp_pasv($this->connectionId, true))
			{
				$this->close();
				throw new IOException("Could not turn on passive mode (".$this->host.":".$this->port.")");
			}
			$this->logged = true;
			$this->username = $username;
			$this->password = $password;
		}
	}
	
	/**
	 * @param String $localPath
	 * @param String $remotePath
	 */
	public function put($localPath, $remotePath)
	{
		if (!$this->logged)
		{
			throw new Exception("You must first login");
		}
		$result = ftp_put($this->connectionId, $remotePath, $localPath, FTP_BINARY);
		if (!$result)
		{
			throw new IOException("Could not write $localPath to ".$remotePath." (".$this->username."@".$this->host.":".$this->port.")");
		}
	}
	
	// protected content

	protected function connect()
	{
		if ($this->connectionId === null)
		{
			$connectionId = ftp_connect($this->host, $this->port);
			if (!$connectionId)
			{
				throw new IOException("Could not connect to ".$this->host.":".$this->port);
			}
			$this->connectionId = $connectionId;	
		}
	}
}
