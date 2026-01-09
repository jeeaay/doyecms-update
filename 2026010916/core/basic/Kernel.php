<?php
namespace core\basic;
class Kernel
{
	private static $pathArray;
	public static function run()
	{
        define('LICENSE', 3);
		self:: StaticHtml();
		self:: CacheHtml();
		$path_info = self:: GetPathInfo();
		$path_info = self:: DomainPath($path_info);
		$path_info = self:: RoutePath($path_info);
		$path_info_arr = self:: GetPathInfoArr($path_info);
		$path_controller = self:: GetPathController($path_info_arr);
		self:: ModelRoute();
		self:: ControllerMain($path_controller);
	}
	private static function GetPathInfo()
	{
		if (isset($_SERVER['PATH_INFO']) && ! mb_check_encoding($_SERVER['PATH_INFO'], 'UTF-8')) 
		{
			$_SERVER['PATH_INFO'] = mb_convert_encoding($_SERVER['PATH_INFO'], 'utf-8', 'GBK');
		}
		if (isset($_SERVER['REQUEST_URI']) && ! mb_check_encoding($_SERVER['REQUEST_URI'], 'UTF-8')) 
		{
			$_SERVER['REQUEST_URI'] = mb_convert_encoding($_SERVER['REQUEST_URI'], 'utf-8', 'GBK');
		}
		if (isset($_SERVER['ORIG_PATH_INFO']) && ! mb_check_encoding($_SERVER['ORIG_PATH_INFO'], 'UTF-8')) 
		{
			$_SERVER['ORIG_PATH_INFO'] = mb_convert_encoding($_SERVER['ORIG_PATH_INFO'], 'utf-8', 'GBK');
		}
		$path_info = '';
		if (isset($_SERVER['PATH_INFO']) && $_SERVER['PATH_INFO'])
		{
			$path_info = $_SERVER['PATH_INFO'];
		}
		elseif (isset($_SERVER["REDIRECT_URL"]) && $_SERVER["REDIRECT_URL"]) 
		{
			$path_info = str_replace('/' . basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['REDIRECT_URL']);
			$path_info = str_replace(SITE_DIR, '', $path_info);
			$_SERVER['PATH_INFO'] = $path_info;
		}
		if (! $path_info)
		{
			if (isset($_GET['p']) && $_GET['p']) 
			{
				$path_info = $_GET['p'];
			}
			elseif (isset($_GET['s']) && $_GET['s']) 
			{
				$path_info = $_GET['s'];
			}
		}
		if ($path_info)
		{
			$pattern = '{^\/?([\x{4e00}-\x{9fa5}\w\-\/\.' . Config::get('url_allow_char') . ']+?)?$}u';
			if (preg_match($pattern, $path_info))
			{
				$path_info = preg_replace($pattern, '$1', $path_info);
			}
			else
			{
				$is_error = true;
			}
		}
		if (isset($is_error) && $is_error)
		{
			http_response_code(404);
			$defend_file = ROOT_PATH . '/defend.html';
			if (file_exists($defend_file))
			{
				require $defend_file;
				exit();
			}
			else
			{
				error('404 Not Found');
			}
		}
		define('P', $path_info);
		return $path_info;
	}
	private static function DomainPath($pathInfo) 
	{
		$path = '';
		if (! ! $domain_bind = Config::get('app_domain_bind'))
		{
			$domain_name = get_http_host();
			if (isset($domain_bind[$domain_name]))
			{
				$path = $domain_bind[$domain_name];
			}
		}
		if (defined('URL_BIND'))
		{
			if ($path && URL_BIND != $path)
			{
				error('URL BIND');
			}
			else
			{
				$path = URL_BIND;
			}
		}
		return $path ? trim_slash($path) . '/' . $pathInfo : $pathInfo;
	}
	private static function RoutePath($pathInfo)
	{
		if (! ! $url_routes = Config::get('url_route'))
		{
			if (! $pathInfo && isset($url_routes['/']))
			{
				return $url_routes['/'];
			}
			foreach ($url_routes as $crypto => $language)
			{
				$crypto = trim_slash($crypto);
				$pattern = "{" . $crypto . "}i";
				if (preg_match($pattern, $pathInfo))
				{
					$language = trim_slash($language);
					$pathInfo = preg_replace($pattern, $language, $pathInfo);
					break;
				}
			}
		}
		return $pathInfo;
	}
	private static function GetPathInfoArr($pathInfo) 
	{
		$public_model = Config::get('public_app', true);
		if ($pathInfo)
		{
			$path_info = trim_slash($pathInfo);
			$path_array = explode('/', $path_info);
			self::$pathArray = $path_array;
			$path_count = count($path_array);
			if ($path_count >= 3)
			{
				$path_info_arr['m'] = $path_array[0];
				$path_info_arr['c'] = $path_array[1];
				$path_info_arr['f'] = $path_array[2];
			}
			elseif ($path_count == 2)
			{
				$path_info_arr['m'] = $path_array[0];
				$path_info_arr['c'] = $path_array[1];
			}
			elseif ($path_count == 1)
			{
				$path_info_arr['m'] = $path_array[0];
			}
		}
		if (! isset($path_info_arr['m']))
		{
			$path_info_arr['m'] = $public_model[0];
		}
		if (! isset($path_info_arr['c']))
		{
			$path_info_arr['c'] = 'Index';
		}
		if (! isset($path_info_arr['f']))
		{
			$path_info_arr['f'] = 'index';
		}
		if (! in_array(strtolower($path_info_arr['m']), $public_model))
		{
			error('Not Found ' . $path_info_arr['m']);
		}
		return $path_info_arr;
	}
	private static function GetPathController($path_info_arr) 
	{
		define('M', strtolower($path_info_arr['m']));
		define('APP_MODEL_PATH', APP_PATH . '/' . M . '/model');
		define('APP_CONTROLLER_PATH', APP_PATH . '/' . M . '/controller');
		if (($tpl_dir = Config::get('tpl_dir')) && array_key_exists(M, $tpl_dir)) 
		{
			if (strpos($tpl_dir[M], ROOT_PATH) === false)
			{
				define('APP_VIEW_PATH', ROOT_PATH . $tpl_dir[M]);
			}
			else
			{
				define('APP_VIEW_PATH', $tpl_dir[M]);
			}
		}
		else
		{
			define('APP_VIEW_PATH', APP_PATH . '/' . M . '/view');
		}
		if (strpos($path_info_arr['c'], '.') > 0)
		{
			$path_controller = str_replace('.', '/', $path_info_arr['c']);
			$controller = ucfirst(basename($path_controller));
			$path_controller = dirname($path_controller) . '/' . $controller;
		}
		else
		{
			$controller = ucfirst($path_info_arr['c']);
			$path_controller = $controller;
		}
		$controller_path_fullpath = APP_CONTROLLER_PATH . '/' . $path_controller . 'Controller.php';
		$f_path_controller_arr = array( 'List', 'Content', 'About' );
		$count_tag = 0;
		if (M == 'home' && (! file_exists($controller_path_fullpath) || in_array($controller, $f_path_controller_arr)))
		{
			$controller = 'Index';
			$path_controller = 'Index';
			define('F', $path_info_arr['c']);
			$count_tag = - 1;
		}
		elseif (M == 'home' && in_array($controller, Config::get('second_rvar'))) 
		{
			define('F', 'index');
			define('RVAR', $path_info_arr['f']);
		}
		else
		{
			define('F', $path_info_arr['f']);
		}
		define('C', $controller);
		if (isset($_SERVER["REQUEST_URI"]))
		{
			define('URL', $_SERVER["REQUEST_URI"]);
		}
		else
		{
			define('URL', $_SERVER["ORIG_PATH_INFO"] . '?' . $_SERVER["QUERY_STRING"]);
		}
		$path_count = count(self::$pathArray);
		for ($i = 3 + $count_tag; $i < $path_count; $i = $i + 2)
		{
			if (isset(self::$pathArray[$i + 1]))
			{
				$_GET[self::$pathArray[$i]] = self::$pathArray[$i + 1];
			}
			else
			{
				$_GET[self::$pathArray[$i]] = null;
			}
		}
		return $path_controller;
	}
	private static function ModelRoute()
	{
		Config::get('debug') ? Check::checkAppFile() : '';
		if (M == 'api')
		{
			if (! ! $sid = request('sid'))
			{
				session_id($sid);
				session_start();
			}
			header("Access-Control-Allow-Origin: *");
		}
		else
		{
			Check::checkBs();
			Check::checkOs();
		}
		if (file_exists(APP_PATH . '/common/function.php'))
		{
			require APP_PATH . '/common/function.php';
		}
		$m_config_file = APP_PATH . '/' . M . '/config/config.php';
		if (file_exists($m_config_file))
		{
			Config::assign($m_config_file);
		}
		$m_full_function_file = APP_PATH . '/' . M . '/function/function.php';
		if (file_exists($m_full_function_file))
		{
			require $m_full_function_file;
		}
		if (file_exists(APP_PATH . '/common/' . ucfirst(M) . 'Controller.php')) 
		{
			$c_controller_name = '\\app\\common\\' . ucfirst(M) . 'Controller';
			$c_controller = new $c_controller_name();
		}
	}
	private static function ControllerMain($controllerPath)
	{
		$controller_path_file = $controllerPath . 'Controller.php';
		$controller_path_fullpath = APP_CONTROLLER_PATH . '/' . $controller_path_file;
		$controller_t_name = '\\app\\' . M . '\\controller\\' . str_replace('/', '\\', $controllerPath) . 'Controller';
		$full_t_controller = F;
		if (! file_exists($controller_path_fullpath))
		{
			http_response_code(404);
			$path_404 = ROOT_PATH . '/404.html';
			if (file_exists($path_404))
			{
				require $path_404;
				exit();
			}
			else
			{
				error('404 Not Found');
			}
		}
		if (! class_exists($controller_t_name))
		{
			error('404 Not Found' . $controller_t_name);
		}
		$controller = new $controller_t_name();
		if (method_exists($controller_t_name, $full_t_controller))
		{
			if (strtolower($controller_t_name) != strtolower($full_t_controller))
			{
				$controller_instance = $controller->$full_t_controller();
			}
			else
			{
				$controller_instance = $controller;
			}
		}
		else
		{
			if (method_exists($controller_t_name, '_empty'))
			{
				$controller_instance = $controller->_empty();
			}
			else
			{
				error('404 Not Found' . $full_t_controller);
			}
		}
		if ($controller_instance !== null)
		{
			print_r($controller_instance);
			exit();
		}
	}
	private static function CacheHtml()
	{
		if (! Config::get('tpl_html_cache') || URL_BIND == 'api' || get('nocache', 'int') == 1) 
		{
			return;
		}
		$cache_path = RUN_PATH . '/config/' . md5('area') . '.php';
		if (! file_exists($cache_path))
		{
			return;
		}
		else
		{
			Config::assign($cache_path);
		}
		$language_arr = Config::get('lgs');
		if (count($language_arr) > 1)
		{
			$domain_name = get_http_host();
			foreach ($language_arr as $language)
			{
				if ($language['domain'] == $domain_name)
				{
					cookie('lg', $language['acode']);
				}
			}
		}
		if (! isset($_COOKIE['lg'])) 
		{
			$language = current(Config::get('lgs'));
			cookie('lg', $language['acode']);
		}
		$runtime_config = RUN_PATH . '/config/' . md5('config') . '.php';
		if (! Config::assign($runtime_config))
		{
			return;
		}
		if (Config::get('open_wap') && (is_mobile() || Config::get('wap_domain') == get_http_host())) 
		{
			$guest_devic = 'wap';
		}
		else
		{
			$guest_devic = '';
		}
		$runtime_cache = RUN_PATH . '/cache/' . md5(get_http_url() . $_SERVER["REQUEST_URI"] . cookie('lg') . $guest_devic) . '.html';
		if (file_exists($runtime_cache) && time() - filemtime($runtime_cache) < Config::get('tpl_html_cache_time')) 
		{
			ob_start();
			include $runtime_cache;
			$ob_output = ob_get_contents();
			ob_end_clean();
			if (Config::get('gzip') && ! headers_sent() && extension_loaded("zlib") && strstr($_SERVER["HTTP_ACCEPT_ENCODING"], "gzip"))
			{
				$ob_output = gzencode($ob_output, 6);
				header("Content-Encoding: gzip");
				header("Vary: Accept-Encoding");
				header("Content-Length: " . strlen($ob_output));
			}
			echo $ob_output;
			exit();
		}
	}
	private static function StaticHtml()
	{
		if (! defined('URL_BIND') || URL_BIND != 'home')
		{
			return;
		}
		if (isset($_SERVER['REQUEST_METHOD']) && ! in_array(strtoupper($_SERVER['REQUEST_METHOD']), array( 'GET', 'HEAD' )))
		{
			return;
		}
		$mode = Config::get('static_php_route_mode') ?: 'debug_only';
		if ($mode == 'off')
		{
			return;
		}
		if ($mode == 'debug_only' && ! Config::get('debug'))
		{
			return;
		}
		$static_dir = Config::get('static_generate_dir') ?: '/html';
		$static_index_filename = Config::get('static_index_filename') ?: 'index.html';
		$default_lang = Config::get('static_generate_default_lang_dir') ?: 'default';
		$lang = cookie('lg') ?: $default_lang;
		$base_dir = rtrim(DOC_PATH, '/\\') . rtrim($static_dir, '/\\') . '/' . basename($lang);
		if (! is_dir($base_dir))
		{
			$lang = $default_lang;
			$base_dir = rtrim(DOC_PATH, '/\\') . rtrim($static_dir, '/\\') . '/' . basename($lang);
		}
		if (! is_dir($base_dir))
		{
			return;
		}

		$request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
		$url = parse_url($request_uri);
		$path = isset($url['path']) ? $url['path'] : '/';
		$path = rawurldecode($path);
		$path = str_replace("\\", "/", $path);
		if ($path === '' || $path[0] !== '/')
		{
			$path = '/' . $path;
		}
		if (defined('SITE_DIR') && SITE_DIR && strpos($path, SITE_DIR) === 0)
		{
			$path = substr($path, strlen(SITE_DIR));
			if ($path === '' || $path[0] !== '/')
			{
				$path = '/' . $path;
			}
		}
		if (strpos($path, "\0") !== false || strpos($path, '..') !== false)
		{
			return;
		}

		$page = isset($_GET['page']) ? intval($_GET['page']) : 0;
		$url_rule_type = Config::get('url_rule_type') ?: 3;
		$url_break_char = Config::get('url_break_char') ?: '_';

		$candidates = array();
		$candidates[] = $path;
		if ($path === '/')
		{
			$candidates[] = '/' . $static_index_filename;
		}
		if (substr($path, - 1) == '/')
		{
			$candidates[] = $path . $static_index_filename;
		}
		elseif (strpos(basename($path), '.') === false)
		{
			$candidates[] = rtrim($path, '/') . '/' . $static_index_filename;
		}
		if ($page > 1 && ($url_rule_type == 1 || $url_rule_type == 2))
		{
			if ($path === '/' || substr($path, - 1) == '/')
			{
				$pre = rtrim($path, '/');
				if ($pre)
				{
					$candidates[] = $pre . $url_break_char . $page . '/' . $static_index_filename;
				}
				else
				{
					$candidates[] = '/' . $url_break_char . $page . (Config::get('url_rule_suffix') ?: '.html');
				}
			}
		}

		$base_real = realpath($base_dir);
		if (! $base_real)
		{
			return;
		}
		foreach (array_unique($candidates) as $candidate)
		{
			$candidate = str_replace("\\", "/", $candidate);
			$candidate = '/' . ltrim($candidate, '/');
			$file_path = rtrim($base_dir, '/\\') . $candidate;
			if (! is_file($file_path))
			{
				continue;
			}
			$file_real = realpath($file_path);
			if (! $file_real || strpos($file_real, $base_real) !== 0)
			{
				continue;
			}
			if (! headers_sent())
			{
				header('Content-Type:text/html; charset=utf-8');
				header('X-Static-Hit: 1');
			}
			if (Config::get('gzip') && ! headers_sent() && extension_loaded("zlib") && isset($_SERVER["HTTP_ACCEPT_ENCODING"]) && strstr($_SERVER["HTTP_ACCEPT_ENCODING"], "gzip"))
			{
				$html = file_get_contents($file_real);
				if ($html === false)
				{
					return;
				}
				$ob_output = gzencode($html, 6);
				header("Content-Encoding: gzip");
				header("Vary: Accept-Encoding");
				header("Content-Length: " . strlen($ob_output));
				echo $ob_output;
				exit();
			}
			readfile($file_real);
			exit();
		}
	}
	private static function LicenseCheck()
	{
		$ip_addr = isset($_SERVER['LOCAL_ADDR']) ? $_SERVER['LOCAL_ADDR'] : $_SERVER['SERVER_ADDR'];
		if ($ip_addr == '::1')
		{
			$ip_addr = '127.0.0.1';
		}
		$license_ = 0;
		if (! ! $license_code = Config::get('licensecode'))
		{
			$license_code = explode('/', base64_decode(substr($license_code, 0, - 1)));
			$sn = explode(',', $license_code[0]);
			$sn_user = $license_code[1];
		}
		else
		{
			$sn = Config::get('sn', true);
			$sn_user = Config::get('sn_user');
		}
		if (! ! $sn)
		{
			$crypto_user = strtoupper(substr(md5(substr(sha1($sn_user), 0, 20)), 10, 10));
			$license_ = $license_ ?: (in_array($crypto_user, $sn) ? 3 : 0);
			$crypto_host = strtoupper(substr(md5(substr(sha1($ip_addr), 0, 15)), 10, 10));
			$license_ = $license_ ?: (in_array($crypto_host, $sn) ? 2 : 0);
			$domain_name = isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
			$crypto_domain = strtoupper(substr(md5(substr(sha1($domain_name), 0, 10)), 10, 10));
			$license_ = $license_ ?: (in_array($crypto_domain, $sn) ? 1 : 0);
		}
		// define('LICENSE', $license_);
		if (! LICENSE && (filter_var(get_http_host(), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || get_http_host() == 'localhost')) 
		{
			return;
		}
		if (! $license_ && (defined('URL_BIND') && URL_BIND != 'admin'))
		{
			$sn_file = ROOT_PATH . '/sn.html';
			if (file_exists($sn_file))
			{
				require $sn_file;
				exit();
			}
			else
			{
				error('域名：' . $domain_name . '未注册');
			}
		}
	}
}
?>
