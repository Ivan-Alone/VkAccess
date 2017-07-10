<?php 
	class VkApp {
		private $app_id;
		private $permissions;
		
        public function __construct($app_id, $permissions) {
			if ($app_id == null || $permissions == null) throw new Exception('NullAppException');
			$this->app_id = $app_id;
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
	}
	
    class VkAccess {
        
        private $login;
        private $password;
		private $app;
		
        private $access_token;
        private $user_id;
		
        private $vk;
        
        public function __construct($app_obj, $login, $password) {
            $this->login = $login;
            $this->password = $password;
            $this->app = $app_obj;
			
            $this->vk = array();
			$this->vk['login'] = $this->login;
			$this->vk['password'] = $this->password;
			$this->vk['app'] = $this->app;
			
            $curl = curl_init();
            @unlink('temp.lcf');
			
            curl_setopt($curl, CURLOPT_URL, 'https://oauth.vk.com/authorize?client_id=' . $app_obj->getID() . '&scope=' . $app_obj->getPermissions() . '&redirect_uri=https://oauth.vk.com/blank.html&display=mobile&v=5.67&response_type=token&revoke=1');
            curl_setopt($curl, CURLOPT_HTTPHEADER, array("User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64; rv:54.0) Gecko/20100101 Firefox/54.0 IOEngine/0.0.1 MeHere/0.0.1 VkAuthLib/0.0.1 VkAccess/0.0.1", "Accept-Language: ru-ru,ru;q=0.5"));
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HEADER, false);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_COOKIEFILE, 'temp.lcf');
            curl_setopt($curl, CURLOPT_COOKIEJAR, 'temp.lcf');
            
            $preset = curl_exec($curl);
                
            preg_match_all('/<input type="hidden" name="ip_h" value="(.+)" \/>/U', $preset, $ip_h);
            preg_match_all('/<input type="hidden" name="lg_h" value="(.+)" \/>/U', $preset, $lg_h);
            preg_match_all('/<input type="hidden" name="to" value="(.+)">/U', $preset, $to);
            
            $post_info = array(
                'email' => $this->login,
                'pass' => $this->password,
                '_origin' => 'https://oauth.vk.com',
                'ip_h' => $ip_h[1][0],
                'lg_h' => $lg_h[1][0],
                'to' => $to[1][0]
            );
            
            curl_setopt($curl, CURLOPT_URL, 'https://login.vk.com/?act=login&soft=1&utf8=1');
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $post_info);

            $q = curl_exec($curl);
                
            preg_match_all('/<form method="post" action="(.+)">/U', $q, $redir);
                
            curl_setopt($curl, CURLOPT_URL, $redir[1][0]);
            curl_setopt($curl, CURLOPT_POSTFIELDS, array());
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($curl, CURLINFO_REDIRECT_URL, true);
            
            $info = curl_exec($curl);
            
            $redir = curl_getinfo($curl)['redirect_url'];
            
            if ($redir == 'https://vk.com') return null;
            
            $infoo = explode('&', explode('#', $redir)[1]);
            
            curl_close($curl);
            @unlink('temp.lcf');
            
            foreach ($infoo as $iii) {
                $par = explode('=', $iii);
                $this->vk[$par[0]] = $par[1];
            }

            
			$this->access_token = $this->vk['access_token'];
			$this->user_id = $this->vk['user_id'];
			
        }
		
		public function getVK() {
			self::isTokenAvalible();
			return $this->vk;
		}
		
		public function invoke($func, $par=null) {
			self::isTokenAvalible();
			return json_decode(file_get_contents('https://api.vk.com/method/' . $func . '?access_token=' . $this->access_token . '&v=5.67&' . $par));
		}
		
		private function isTokenAvalible() {
			if ($this->access_token == null) {
				throw new Exception('Access token is unavailable! Check your application, your login and password and create new instance of VkAccess!');
			}
		}
    }