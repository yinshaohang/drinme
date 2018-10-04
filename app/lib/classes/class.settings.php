<?php

/* --------------------------------------------------------------------

  Chevereto
  http://chevereto.com/

  @author	Rodolfo Berrios A. <http://rodolfoberrios.com/>
			<inbox@rodolfoberrios.com>

  Copyright (C) Rodolfo Berrios A. All rights reserved.
  
  BY USING THIS SOFTWARE YOU DECLARE TO ACCEPT THE CHEVERETO EULA
  http://chevereto.com/license

  --------------------------------------------------------------------- */
  
namespace CHV;
use G, Exception;

class Settings {
	
	protected static $instance;
	
	static $settings;
	static $defaults;
	
	public function __construct() {
		try {
			
			try {
				$db_settings = DB::get('settings', 'all', NULL, ['field' => 'name', 'order' => 'asc']);
				foreach($db_settings as $k => $v) {
					$v = DB::formatRow($v);
					$value = $v['value'];
					$default = $v['default'];
					// Fix those booleans!
					if($v['typeset'] == 'bool') {
						$value = (bool) $value == 1;
						$default = (bool) $default == 1;
					}
					$settings[$v['name']] = $value;
					$defaults[$v['name']] = $default;
				}
				// Append some magic settings
				foreach(Login::getSocialServices(['get' => 'all']) as $k => $v) { // Must get all to avoid endless nesting
					if($settings[$k]) {
						$settings['social_signin'] = true;
						break;
					}
				}
				
			} catch(Exception $e) {
				$settings = [];
				$defaults = [];
			}
			
			if(!$db_settings) {
				//throw new Exception("Can't find any DB setting. Table seems to be empty.", 400);
			}
			
			// Inject the missing settings
			$injected = [
				// 3.2.0
				'theme_download_button'	=> 1,
				'enable_signups'		=> 1,
				'website_mode'			=> 'community',
				// 3.3.0
				'listing_pagination_mode' 		=> 'classic',
				'website_content_privacy_mode'	=> 'default',
				'website_privacy_mode'			=> 'public',
				// 3.3.1
				'website_explore_page'	=> 1,
				// 3.4.4
				'website_search'			=> 1,
				'website_random'			=> 1,
				'theme_show_social_share'	=> 1,
				'theme_show_embed_content'	=> 1,
				'theme_show_embed_uploader'	=> 1,
				// 3.4.5
				'user_routing'						=> 1,
				'require_user_email_confirmation'	=> 1,
				'require_user_email_social_signup'	=> 1,
				// 3.5.15
				'homepage_style'		=> 'landing',
				//'logged_user_logo_link'	=> 'homepage', // Removed in 3.7.0
				// 3.5.19
				'user_image_avatar_max_filesize_mb'		=> '1',
				'user_image_background_max_filesize_mb'	=> '2',
				// 3.5.20
				'theme_image_right_click' => 0,
				// 3.6.0
				'theme_show_exif_data'	=> 1,
				'homepage_cta_color'	=> 'green',
				'homepage_cta_outline'	=> 0,
				'watermark_enable_guest'=> 1,
				'watermark_enable_user'	=> 1,
				'watermark_enable_admin'=> 1,
				// 3.6.1
				'language_chooser_enable'	=> 1,
				'languages_disable'			=> NULL,
				'homepage_cta_fn'			=> 'cta-upload',
				// 3.6.5
				'watermark_target_min_width'	=> 100,
				'watermark_target_min_height'	=> 100,
				'watermark_percentage'			=> 4,
				'watermark_enable_file_gif'		=> 0,
				// 3.7.2
				'upload_medium_fixed_dimension'	=> 'width',
				'upload_medium_size'			=> 500,
				// 3.7.3
				'enable_followers' 		=> 1,
				'enable_likes'			=> 1,
				'enable_consent_screen'	=> 0,
				'user_minimum_age'		=> NULL,
				// 3.7.5
				'route_image'	=> 'image',
				'route_album'	=> 'album',
				// 3.7.6
				'enable_duplicate_uploads'	=> 0,
				// 3.8.4
				'upload_enabled_image_formats'	=> 'jpg,png,bmp,gif',
				'upload_threads'				=> '2',
				'enable_automatic_updates_check'=> 1,
				'comments_api'					=> 'js',
				// 3.8.9
				'image_load_max_filesize_mb'	=> '3',
			];
			
			// Default listing thing
			$device_to_columns = [
				'phone'  => 1,
				'phablet'=> 3,
				'tablet' => 4,
				'laptop' => 5,
				'desktop'=> 6,
			];
			foreach($device_to_columns as $k => $v) {
				$injected['listing_columns_' . $k] = $v;
			}
			
			foreach($injected as $k => $v) {
				if(!array_key_exists($k, $settings)) {
					$settings[$k] = $v;
					$defaults[$k] = $v;
				}
			}
			
			// Fixed settings
			if($settings['email_mode'] == 'phpmail') {
				$settings['email_mode'] = 'mail';
			}
			if(!in_array($settings['upload_medium_fixed_dimension'], ['width', 'height'])) {
				$settings['upload_medium_fixed_dimension'] = 'width';
			}
			
			// Virtual settings
			$settings['listing_device_to_columns'] = [];
			foreach($device_to_columns as $k => $v) {
				$settings['listing_device_to_columns'][$k] = $settings['listing_columns_' . $k];
			}
			$settings['listing_device_to_columns']['largescreen'] = $settings['listing_columns_desktop'];
			
			// Chevereto demo only
			if(!in_array($_SERVER['SERVER_NAME'], ['demo.chevereto.com'])) {
				if($settings['twitter_account'] == 'chevereto') {
					$settings['twitter_account'] = NULL;
				}
			}
			
			// Harcoded settings
			$settings = array_merge($settings, [
				'username_min_length'		=> 3,
				'username_max_length'		=> 16,
				'username_pattern'			=> '^[\w]{3,16}$',
				'user_password_min_length'	=> 6,
				'user_password_max_length'	=> 32,
				'user_password_pattern'		=> '^.{6,32}$',
				'maintenance_image'			=> 'default/maintenance_cover.jpg',
				'ip_whois_url'				=> 'https://ipinfo.io/%IP',
				'available_button_colors'	=> ['blue', 'green', 'orange', 'red', 'grey', 'black', 'white', 'default'],
				'routing_regex'				=> '([\w_-]+)',
				'routing_regex_path'		=> '([\w\/_-]+)',
				'single_user_mode_on_disables'	=> ['enable_signups', 'guest_uploads', 'user_routing'],
				'listing_safe_count'		=> 100,
				// 3.6.5
				'image_title_max_length'	=> 100,
				'album_name_max_length'		=> 100,
				// 3.8.4
				'upload_available_image_formats' => 'jpg,png,bmp,gif'
			]);

			if(!$settings['active_storage']) {
				$settings['active_storage'] = NULL;
			}
            
			// '' -> NULL
			foreach($settings as $k => &$v) {
				G\nullify_string($v);
			}
			unset($v); // break reference
			foreach($defaults as $k => &$v) {
				G\nullify_string($v);
			}
			unset($v);
			
			if($settings['theme_logo_height'] !== NULL) {
				$settings['theme_logo_height'] = (int) $settings['theme_logo_height'];
			}

			// Injected things due to single user mode on
			if($settings['website_mode'] == 'personal') {
				
				if(array_key_exists('website_mode_personal_routing', $settings)) { // Single user routing workaround
					if(is_null($settings['website_mode_personal_routing']) or $settings['website_mode_personal_routing'] == '/') {
						$settings['website_mode_personal_routing'] = '/';
					} else {
						$settings['website_mode_personal_routing'] = G\get_regex_match($settings['routing_regex'], '#', $settings['website_mode_personal_routing'], 1);
					}
				}
				
				if(G\is_integer($settings['website_mode_personal_uid'])) {
					foreach($settings['single_user_mode_on_disables'] as $k) {
						$settings[$k] = false;
					}
				} else {
					$settings['website_mode'] = 'community';
				}
				
				$settings['enable_likes'] = FALSE;
				$settings['enable_followers'] = FALSE;
			}
			
			// CTA fixings
			if(is_null($settings['homepage_cta_fn'])) {
				$settings['homepage_cta_fn'] = 'cta-upload';
			}
			if($settings['homepage_cta_fn'] == 'cta-link' and !G\is_url($settings['homepage_cta_fn_extra'])) {
				$settings['homepage_cta_fn_extra'] = G\get_regex_match($settings['routing_regex_path'], '#', $settings['homepage_cta_fn_extra'], 1);
			}
			
			// Disabled languages handle
			if(!is_null($settings['languages_disable'])) {
				$languages_disable = (array) explode(',', $settings['languages_disable']);
				$languages_disable = array_filter(array_unique($languages_disable));
			} else {
				$languages_disable = [];
			}
			$settings['languages_disable'] = $languages_disable;
			
			self::$settings = $settings;
			self::$defaults = $defaults;
			
		} catch (Exception $e) {
			throw new SettingsException($e->getMessage(), 400);
		}
	}
	
	public static function getInstance() {
		if(is_null(self::$instance)) {
			self::$instance = new self;
		}
		return self::$instance;
	}
	
	public static function getStatic($var) {
		$instance = self::getInstance();
		return $instance::$$var;
	}
	
	public static function get($key=NULL) {
		$settings = self::getStatic('settings');
		if(!is_null($key)) {
			return $settings[$key];
		} else {
			return $settings;
		}
	}
	
	public static function getType($val) {
		return ($val===0 || $val===1) ? 'bool' : 'string';
	}
	
	public static function getDefaults($key=NULL) {
		$defaults = self::getStatic('defaults');
		if(!is_null($key)) {
			return $defaults[$key];
		} else {
			return $defaults;
		}
	}
	
	public static function getDefault($key) {
		return self::getDefaults($key);
	}
	
	public static function setValues($values) {
		self::$settings = $values;
	}
	
	public static function setValue($key, $value) {
		$settings = self::getStatic('settings');
		self::$settings[$key] = $value ?: NULL;
	}
	
	public static function update($name_values) {
		try {
			$query = '';
			$binds = [];
			$query_tpl = 'UPDATE `' . DB::getTable('settings') . '` SET `setting_value` = %v WHERE `setting_name` = %k;' . "\n";			
			$i = 0;
			foreach($name_values as $k => $v) {
				$query .= strtr($query_tpl, ['%v' => ':sv_' . $i, '%k' => ':sn_' . $i]);
				$binds[':sn_' . $i] = $k;
				$binds[':sv_' . $i] = $v;
				$i++;
			}
			unset($i);
			
			$db = DB::getInstance();
			$db->query($query);
			foreach($binds as $k => $v) {
				$db->bind($k, $v);
			}
			$db->exec();
			foreach($name_values as $k => $v) {
				self::setValue($k, $v);
			}
			return TRUE;
		} catch(Exception $e) {
			throw new SettingsException($e->getMessage(), 400);
		}
	}
	
	public static function getChevereto() {
		}

}

class SettingsException extends Exception {}