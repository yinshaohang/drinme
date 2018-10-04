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

$route = function($handler) {
	try {
		
		if($_POST and !$handler::checkAuthToken($_REQUEST['auth_token'])) {
			$handler->template = 'request-denied';
			return;
		}
		
		$logged_user = CHV\Login::getUser();
		
		if(!$logged_user) {
			G\redirect('login');
		}
		
		// User status override redirect
		CHV\User::statusRedirect($logged_user['status']);
		
		// Dashboard hack
		$handler->template = 'settings';
		$is_dashboard_user = $handler::getCond('dashboard_user');
		
		// Request log
		if(!$is_dashboard_user) {
			$request_log = CHV\Requestlog::getCounts('account-edit', 'fail');
		}
		
		// Editable values
		$allowed_to_edit = ['name', 'username', 'email', 'avatar_filename', 'website', 'background_filename', 'timezone', 'language', 'status', 'is_admin', 'image_keep_exif', 'image_expiration', 'newsletter_subscribe', 'bio', 'show_nsfw_listings', 'is_private'];
		
        if(CHV\getSetting('enable_expirable_uploads')) {
            unset($allowed_to_edit['image_expiration']);
        }
        
		// User handle
		$user = $is_dashboard_user ? CHV\User::getSingle($handler->request[1], 'id') : $logged_user;
		$is_owner = $user['id'] == CHV\Login::getUser()['id'];
		
		// Update the lang displayed on change
		if(in_array('language', $allowed_to_edit) and isset($_POST['language']) and $logged_user['language'] !== $_POST['language'] and $logged_user['id'] == $user['id'] and array_key_exists($_POST['language'], CHV\L10n::getEnabledLanguages())) {
			CHV\L10n::processTranslation($_POST['language']);
		}
		
		// Settings routes
		$routes = [
			'account'			=> _s('Account'),
			'profile'		  	=> _s('Profile'),
			'password'		  	=> _s('Password'),
			'linked-accounts' 	=> _s('Linked accounts'),
			'homepage' 			=> _s('Homepage')
		];
		$default_route = 'account';
		
		if(CHV\getSetting('website_mode') == 'personal' and CHV\getSetting('website_mode_personal_routing') !== '/' and $logged_user['id'] == CHV\getSetting('website_mode_personal_uid')) {
			$route_homepage = TRUE;
		}
		
		$is_email_required = TRUE;
		// Don't require email for admin when editing users
		if($handler::getCond('admin') AND !$is_owner) {
			$is_email_required = FALSE;
		}
		// Don't require email for those using social login and no email mandatory needed
		if($is_email_required AND !CHV\getSetting('require_user_email_social_signup')) {
			foreach(CHV\Login::getSocialServices(['flat' => TRUE]) as $k) {
				if(array_key_exists($k, $user['login'])) {
					$is_email_required = FALSE;
					break;
				}
			}
		}
		
		$doing_level = $is_dashboard_user ? 2 : 0;
		$doing = $handler->request[$doing_level];

		if(!$user or $handler->request[$doing_level+1] or (!is_null($doing) and !array_key_exists($doing, $routes))) {
			return $handler->issue404();
		}
		
		if($doing == '') $doing = $default_route;
		
		// Populate the routes
		foreach($routes as $route => $label) {
			$aux = str_replace('_', '-', $route);
			$handler::setCond('settings_'.$aux, $doing == $aux);
			if($handler::getCond('settings_'.$aux)) {
				$handler::setVar('setting', $aux);
			}
			if($aux == 'homepage' and !$route_homepage) {
				continue;
			}
			$settings_menu[$aux] = array(
				'label' => $label,
				'url'	=> G\get_base_url(($is_dashboard_user ? ('dashboard/user/' . $user['id']) : 'settings') . ($route == $default_route ? '' : '/'.$route)),
				'current' => $handler::getCond('settings_'.$aux)
			);
		}
		
		if(count(CHV\Login::getSocialServices(['get' => 'enabled'])) == 0) {
			unset($routes['linked-accounts']);
			$settings_menu = G\array_filter_array($settings_menu, ['linked-accounts'], 'rest');	
		}
		
		$handler::setVar('settings_menu', $settings_menu);
		
		if(!array_key_exists($doing, $routes)) {
			return $handler->issue404();
		}
		
		// Safe print $_POST
		$SAFE_POST = $handler::getVar('safe_post');	
		
		// conds
		$is_error = false;
		$is_changed = false;
		$captcha_needed = false;
		
		// vars
		$input_errors = array();
		$error_message = NULL;
		$changed_email_message = NULL;
		
		if($_POST) {
			
			$field_limits = 255;
			
			foreach($allowed_to_edit as $k) {
				if($_POST[$k]) {
					$_POST[$k] = substr($_POST[$k], 0, $field_limits);
				}
			}
			
			// Input validations
			switch($doing) {
				
				case NULL:
				case 'account':
					
					$checkboxes = ['upload_image_exif', 'newsletter_subscribe', 'show_nsfw_listings', 'is_private'];
					foreach($checkboxes as $k) {
						$_POST[$k] = in_array($_POST[$k], ['On', 1]) ? 1 : 0;
					}
					
					G\nullify_string($_POST['image_expiration']);
                    
					$__post = [];
					$__safe_post = [];
					foreach(['username', 'email'] as $v) {
						if(isset($_POST[$v])) {
							$_POST[$v] = $v == 'email' ? trim($_POST[$v]) : strtolower(trim($_POST[$v]));
							$__post[$v] = $_POST[$v];
							$__safe_post[$v] = G\safe_html($_POST[$v]);
						}
					}
					
					$handler::updateVar('post', $__post);
					$handler::updateVar('safe_post', $__safe_post);
					
					if(!CHV\User::isValidUsername($_POST['username'])) {
						$input_errors['username'] = _s('Invalid username');
					}
					
					if($is_email_required and !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
						$input_errors['email'] = _s('Invalid email');
					}
                    
                    if(CHV\getSetting('enable_expirable_uploads')) {
                        // Image expire time
                        if($_POST['image_expiration'] !== NULL and !G\dateinterval($_POST['image_expiration'])) {
                            $input_errors['image_expiration'] = _s('Invalid image expiration');
                        }
                    }
                    
					if(!array_key_exists($_POST['language'], CHV\get_available_languages())) {
						$_POST['language'] = CHV\getSetting('default_language');
					}
					if(!in_array($_POST['timezone'], timezone_identifiers_list())) {
						$_POST['timezone'] = date_default_timezone_get();
					}

					if(count($input_errors) > 0) {
						$is_error = true;
					}
					
					if(!$is_error) {
						
						$user_db = CHV\DB::get('users', ['username' => $_POST['username'], 'email' => $_POST['email']], 'OR', NULL);
						
						if($user_db) {
							
							foreach($user_db as $row) {
								
								if($row['user_id'] == $user['id']) continue; // Same guy?
								
								// Invalid user, check the time
								if(!in_array($row['user_status'], ['valid', 'banned'])) { // Don't touch the valid and banned users
									$must_delete_old_user = false;
									$confirmation_db = CHV\Confirmation::get(['user_id' => $row['user_id']]);
									if($confirmation_db) {
										// 24x2 = 48 tic tac tic tac
										if(G\datetime_diff($confirmation_db['confirmation_date_gmt'], NULL, 'h') > 48) {
											CHV\Confirmation::delete(['id' => $confirmation_db['confirmation_id']]);
											$must_delete_old_user = true;
										}
									} else {
										$must_delete_old_user = true;
									}
									// Delete any old un-validated / un-banned user and allow use his things
									if($must_delete_old_user) {
										CHV\DB::delete('users', ['id' => $row['user_id']]);
										continue;
									}
								}
								
								// Username taken?
								if(G\timing_safe_compare($row['user_username'], $_POST['username']) and $user['username'] !== $row['user_username']) {
									$input_errors['username'] = 'Username already being used';
								}
								// Email taken?
								if(!empty($_POST['email']) && G\timing_safe_compare($row['user_email'], $_POST['email']) &&
								   $user['email'] !== $row['user_email']) {
									$input_errors['email'] = _s('Email already being used');
								}
							}
							
							if(count($input_errors) > 0) {
								$is_error = true;
							}
						}
					}

					// Email MUST be validated (two steps)
					if(!$is_error && !empty($_POST['email']) && !G\timing_safe_compare($user['email'], $_POST['email'])) {
						
						// Delete any old confirmation
						CHV\Confirmation::delete(['type' => 'account-change-email', 'user_id' => $user['id']]);
						
						// Generate the thing
						$hashed_token = CHV\generate_hashed_token($user['id']);
						$insert_email_confirm = CHV\Confirmation::insert([
							'type'			=> 'account-change-email',
							'user_id'		=> $user['id'],
							'token_hash'	=> $hashed_token['hash'],
							'status'		=> 'active',
							'extra'			=> $_POST['email']
						]);
						$email_confirm_link = G\get_base_url('account/change-email-confirm/?token='.$hashed_token['public_token_format']);
						$changed_email_message = _s('An email has been sent to %s with instructions to activate this email', $SAFE_POST['email']);	
						
						// Build the mail global
						global $theme_mail;
						$theme_mail = [
							'user' => $user,
							'link' => $email_confirm_link
						];
						
						ob_start();
						require_once(G_APP_PATH_THEME . 'mails/account-change-email.php');
						$mail_body = ob_get_contents();
						ob_end_clean();
						
						$mail['subject'] = _s('Confirmation required at %s', CHV\getSettings()['website_name']);
						$mail['message'] = $mail_body;

						try {
							if(CHV\send_mail($_POST['email'], $mail['subject'], $mail['message'])) {
								//$is_changed = true;
							}
						} catch(Exception $e) {
							echo($e->getMessage());
						}
						
						unset($_POST['email']);
						
					}
					
				break;
				
				case 'profile':
					if(!preg_match('/^.{1,60}$/', $_POST['name'])) {
						$input_errors['name'] = _s('Invalid name');
					}
					if($_POST['website'] and !filter_var($_POST['website'], FILTER_VALIDATE_URL)) {
						$input_errors['website'] = _s('Invalid website');
					}
				break;
				
				case 'password':
					
					if(!$is_dashboard_user) {
						if($user['login']['password'] && !password_verify($_POST['current-password'], $user['login']['password']['secret'])) {
							$input_errors['current-password'] = _s('Wrong password');
						} else {
							if($_POST['current-password'] == $_POST['new-password']) {
								$input_errors['new-password'] = _s('Use a new password');
								$handler::updateVar('safe_post', ['current-password' => NULL]);
							}
						}
					}
				
					if(!preg_match('/'.CHV\getSetting('user_password_pattern').'/', $_POST['new-password'])) {
						$input_errors['new-password'] = _s('Invalid password');
					}
					
					if($_POST['new-password'] !== $_POST['new-password-confirm']) {
						$input_errors['new-password-confirm'] = _s("Passwords don't match");
					}

				break;
				
				case 'homepage':
					if(!array_key_exists($doing, $routes)) { // Nope
						$handler->issue404();
					}
					$allowed_to_edit = ['homepage_title_html', 'homepage_paragraph_html', 'homepage_cta_html'];
					
					// Protect editing
					$editing_array = G\array_filter_array($_POST, $allowed_to_edit, 'exclusion');
					$update_settings = [];
					foreach($allowed_to_edit as $k) {
						if(!array_key_exists($k, CHV\Settings::get()) or CHV\Settings::get($k) == $editing_array[$k]) continue;
						$update_settings[$k] = $editing_array[$k];
					}
					
					// Update settings (if any)
					if($update_settings) {
						$db = CHV\DB::getInstance();
						$db->beginTransaction();
						$db->query('UPDATE ' . CHV\DB::getTable('settings') . ' SET setting_value = :value WHERE setting_name = :name;');
						foreach($update_settings as $k => $v) {
							$db->bind(':name', $k);
							$db->bind(':value', $v);
							$db->exec();
						}
						if($db->endTransaction()) {
							$is_changed = TRUE;
							foreach($update_settings as $k => $v) {
								CHV\Settings::setValue($k, $v);
							}
						}
					}
				break;
				
				default:
					$handler->issue404();
				break;
			}
			
			if(count($input_errors) > 0) {
				$is_error = true;
			}
			
			if(!$is_error) {
			
				// Account and profile changes
				if(in_array($doing, [NULL, 'account', 'profile'])) {
					
					// Detect changes
					foreach($_POST as $k => $v) {
						if($user[$k] !== $v) {
							$is_changed = true;
						}
					}

					if($is_changed) {
						
						// Protect editing
						$editing_array = G\array_filter_array($_POST, $allowed_to_edit, 'exclusion');
						
						if(!$is_dashboard_user) {
							unset($editing_array['status'], $editing_array['is_admin']);
						} else {
							if(!in_array($editing_array['status'], ['valid', 'banned', 'awaiting-confirmation', 'awaiting-email'])) {
								unset($editing_array['status']);
							}
							if($_POST['role']) {
								$editing_array['is_admin'] = $_POST['role'] == 'admin' ? 1 : 0;
								if($_POST['is_admin']) {
									$editing_array['status'] = 'valid';
								}
								unset($_POST['role']);
							}
						}

						if(empty($_POST['email'])) {
							unset($editing_array['email']);
						}

						if(CHV\User::update($user['id'], $editing_array)) {
							$user = array_merge($user, $editing_array);
							$handler::updateVar('safe_post', ['name' => CHV\User::sanitizeUserName($_POST['name'])]);
						}
						
						if(!$is_dashboard_user) {
							$logged_user = CHV\Login::login($user['id'], $_SESSION['login']['type']);
						} else {
							$user = CHV\User::getSingle($user['id'], 'id');
						}
						$changed_message = _s('Changes have been saved.');
					}
				}
				
				// Update/create password
				if($doing == 'password') {
					
					if($user['login']['password']) {
					
						// Delete any old cookie/session login for this user
						CHV\Login::delete(['type' => 'cookie', 'user_id' => $user['id']]);
						CHV\Login::delete(['type' => 'session', 'user_id' => $user['id']]);
						
						// Insert the new login DB if needed
						if(!$is_dashboard_user and $_COOKIE['KEEP_LOGIN']) {
							CHV\Login::insert(['type' => 'cookie', 'user_id' => $user['id']]);
						}
												
						$is_changed = CHV\Login::changePassword($user['id'], $_POST['new-password']); // This inserts the session login
						$changed_message = _s('Password has been changed');
						
					} else { // Insert
						
						$is_changed = CHV\Login::addPassword($user['id'], $_POST['new-password']);
						$changed_message = _s('Password has been created.');
						if(!$is_dashboard_user or $logged_user['id'] == $user['id']) {
							$logged_user = CHV\Login::login($user['id'], 'password');
						}
					}
					
					$unsets = array('current-password', 'new-password', 'new-password-confirm');
					foreach($unsets as $unset) {
						$handler::updateVar('safe_post', [$unset => NULL]);
					}
				}
				
			} else {
				if(in_array($doing, array('', 'account')) and !$is_dashboard_user) {
					CHV\Requestlog::insert(array('type' => 'account-edit', 'result' => 'fail'));
					$error_message = _s('Wrong Username/Email values');
				}
			}

		}

		if($doing == 'linked-accounts') {
			// Get the assoicated logins
			$logins_db = CHV\Login::get(['user_id' => $user['id']]);
			$available_connections = CHV\Login::getSocialServices(['get' => 'enabled']);

			$has_password = false;
			
			$connections = [];
			foreach($logins_db as $login) {
				if($login['type'] == 'password') $has_password = true;
				if(!array_key_exists($login['login_type'], $available_connections)) continue;
				$connections[$login['login_type']] = CHV\DB::formatRow($login);
				$connections[$login['login_type']]['type_label'] = $available_connections[$login['login_type']];
			}
			
			ksort($available_connections);
			ksort($connections);
			
			$handler::setCond('have_password', $has_password);
			$handler::setVar('available_connections', $available_connections);
			$handler::setVar('connections', $connections);
		}
		
		$handler::setCond('owner', $is_owner);
		$handler::setCond('error', $is_error);
		$handler::setCond('changed', $is_changed);
		$handler::setCond('captcha_needed', $captcha_needed);
		$handler::setCond('dashboard_user', $is_dashboard_user);
		$handler::setCond('email_required', $is_email_required);
		
		if($captcha_needed and !$handler::getVar('recaptcha_html')) {
			$handler::setVar('recaptcha_html', CHV\Render\get_recaptcha_html('clean'));
		}
		
		$handler::setVar('pre_doctitle', $is_dashboard_user ? _s('Settings for %s', $user['username']) : _s('Settings'));
		
		$handler::setVar('error',  $error_message);
		$handler::setVar('input_errors', $input_errors);
		$handler::setVar('changed_message', $changed_message);
		$handler::setVar('changed_email_message', $changed_email_message);
		$handler::setVar('user', $is_dashboard_user ? $user : $logged_user);
		$handler::setVar('safe_html_user', G\safe_html($handler::getVar('user')));
		
	} catch(Exception $e) {
		G\exception_to_error($e);
	}
};