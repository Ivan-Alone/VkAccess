<?php 
class VkApp {
    private $app_id;
    private $permissions;

    public function __construct($app_id, $permissions = 0x7FFFFFFF) {
		if ($app_id instanceof stdClass) {
			$this->app_id = $app_id->app_id;
			$this->permissions = $app_id->permissions;
			return;
		}
        if ($app_id == null || $permissions == null) throw new Exception('NullAppException');
        $this->app_id = $app_id;
		$perm = '';
        if (is_array($permissions)) {
            foreach($permissions as $par) {
                $perm .= $par . ',';
            }
        } else {
            $perm = $permissions;
        }
        $this->permissions = $perm;
    }

    public function getID() {
        return $this->app_id;
    }

    public function getPermissions() {
        return $this->permissions;
    }
	public function serialize() {
		return json_encode([
			'app_id' => $this->app_id,
			'permissions' => $this->permissions
		]);
	}

	public static function unserialize($json_str) {
		$data = json_decode($json_str);
		return new VkApp($data);
	}
}

class VkAccess {
	const VK_API_VERSION = '5.131';
	const COOKIES_FILE   = 'vk-cookies.txt';

    private $login;
    private $password;
    private $app;

    private $access_token;
    private $user_id;

    private $vk;

    private $net;

    public function __construct($app_obj = null, $login = null, $password = null, $proxy_settings = null) {
		$this->checkNetwork();

		@unlink(static::COOKIES_FILE);
		$this->net = new Network(static::COOKIES_FILE, $proxy_settings);
		$this->net->setDefaultAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:98.0) Gecko/20100101 Firefox/98.0 VkAuthLib/0.0.1 VkAccess/0.0.1');

		if (!($app_obj instanceof VkApp)) {
			if ($app_obj instanceof stdClass) {
				$this->login = $app_obj->login;
				$this->password = $app_obj->password;
				$this->app = VkApp::unserialize($app_obj->app);
				$this->access_token = $app_obj->access_token;
				$this->user_id = $app_obj->user_id;
				$this->vkInit();
				$this->vk['access_token'] = $this->access_token;
				$this->vk['user_id'] = $this->user_id;
			}
			return;
		}

        $this->login = $login;
        $this->password = $password;
        $this->app = $app_obj;

        $this->vkInit();
		
		$preset = $this->net->Request([
			CURLOPT_URL => 'https://oauth.vk.com/authorize?client_id=' . $app_obj->getID() . '&scope=' . $app_obj->getPermissions() . '&redirect_uri=https://oauth.vk.com/blank.html&display=mobile&v='.static::VK_API_VERSION.'&response_type=token&revoke=1',
			CURLOPT_FOLLOWLOCATION => true
		], [], true);

        preg_match_all('/<input type="hidden" name="ip_h" value="(.+)" \/>/U', $preset, $ip_h);
        preg_match_all('/<input type="hidden" name="lg_domain_h" value="(.+)" \/>/U', $preset, $lg_domain_h);
        preg_match_all('/<input type="hidden" name="to" value="(.+)">/U', $preset, $to);
		
		if (count($ip_h[1]) < 1 || count($lg_domain_h[1]) < 1 || count($to[1]) < 1) {
			throw new Error('This VK App is not support API Auth!');
		}
		
        $post_info = [
            '_origin' => 'https://oauth.vk.com',
            'ip_h' => $ip_h[1][0],
            'lg_domain_h' => $lg_domain_h[1][0],
            'to' => $to[1][0],
            'email' => $this->login,
            'pass' => $this->password
        ];

		$q = $this->net->Request([
			CURLOPT_URL => 'https://login.vk.com/?act=login&soft=1&utf8=1',
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => http_build_query($post_info),
			CURLOPT_FOLLOWLOCATION => true
		], [
			'Content-Type'=> 'application/x-www-form-urlencoded',
			'Origin'=> $post_info['_origin'],
			'Referer'=> $post_info['_origin'] . '/',
		], true);

        preg_match_all('/<form method="post" action="(.+)">/U', $q, $redir);
		
		$this->net->applyInfoOptions(CURLINFO_REDIRECT_URL);
		$info = $this->net->Request([
			CURLOPT_URL => $redir[1][0],
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => [],
			CURLOPT_FOLLOWLOCATION => false
		], [], true);
		$this->net->applyInfoOptions();

        $redir = $this->net->getLatestInfo();
		
        if (strpos($redir, 'https://vk.com') === 0) throw new Error('Can\'t login to this account: ' . $this->login . '. May be password is incorrect, or any other error happens!');

		if (strpos($redir, 'https://oauth.vk.com/auth_redirect') !== 0) {
			if (strpos($redir, 'https://oauth.vk.com/error') === 0) {
				throw new Error($this->net->GetQuery($redir)['error_description']);
			} else {
				throw new Error('Invalid redirect found, can\'t login: '.$redir);
			}
		}

		$token_info = $this->net->Request([
			CURLOPT_URL => $redir,
			CURLOPT_FOLLOWLOCATION => true
		], [], true);
		
		preg_match_all("/'(.+)';/U", $token_info, $out);
		$redir = $out[1][0];

        $infoo = explode('&', explode('#', $redir)[1]);

        foreach ($infoo as $iii) {
            $par = explode('=', $iii);
            $this->vk[$par[0]] = $par[1];
        }

        $this->access_token = $this->vk['access_token'];
        $this->user_id = $this->vk['user_id'];

    }
	
	public function __destruct() {
		@unlink(static::COOKIES_FILE);
	}

    public function getVK() {
        return $this->vk;
    }

    public function getNetwork() {
        return $this->net;
    }

	public function getAccessToken() {
		return $this->access_token;
	}

	public function getUserID() {
		return $this->user_id;
	}

    public function invoke($func, $par=null, $decode_json = true) {
		$this->checkNetwork();
        $this->isTokenAvalible();

        $result = $this->net->GetQuery('https://api.vk.com/method/' . $func . '?access_token=' . $this->access_token . '&v='.static::VK_API_VERSION.'&' . (is_array($par) ? http_build_query($par) : $par), [], true);

		return $decode_json ? json_decode($result) : $result;
    }

    private function isTokenAvalible() {
        if ($this->access_token == null) {
            throw new \Error('Access token is unavailable! Check your application, your login and password and create new instance of VkAccess!');
        }
    }

	private function checkNetwork() {
		if (!class_exists('Network')) {
			throw new \Error('Error: Class Network doesn\'t exists!');
		}
	}

	private function vkInit() {
		$this->vk = [];
        $this->vk['login'] = $this->login;
        $this->vk['password'] = $this->password;
        $this->vk['app'] = $this->app;
	}

	public function serialize() {
		return json_encode([
			'app' => $this->app->serialize(),
			'login' => $this->login,
			'password' => $this->password,
			'access_token' => $this->access_token,
			'user_id' => $this->user_id
		]);
	}

	public static function unserialize($json_str) {
		$data = json_decode($json_str);
		return new VkAccess($data);
	}
}
