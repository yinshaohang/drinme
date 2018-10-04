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
	
		$handler->template = 404;
		
		$route = $handler->{request_array}[0];
		$doing = $handler->request[0];
		
		// Process the second level request like 'account/password-reset'
		if(!$doing or (!in_array($doing, ['activate', 'password-reset', 'change-email-confirm']) and $handler->isRequestLevel(3))) {
			return $handler->issue404();
		}
		
		$logged_user = CHV\Login::getUser();
		
		// Email needed
		if($doing !== 'email-needed' and $logged_user['status'] == 'awaiting-email') {
			G\redirect('account/email-needed');
		}
		
		// Disallow
		switch($doing) {
			case 'email-needed':
				if($logged_user['status'] !== 'awaiting-email') {
					G\redirect($logged_user['url']);
				}
			break;
			case 'resend-activation':
			case 'activate':
				if($logged_user and $logged_user['status'] !== 'awaiting-confirmation') {
					G\redirect($logged_user['url']);
				}
			break;
		}
			
		// reCaptcha thing
		$captcha_needed = false;
		
		// Request to db field
		$request_to_db = array(
			'password-forgot'	=> 'account-password-forgot',
			'password-reset'	=> 'account-password-forgot',
			'resend-activation' => 'account-activate',
			'activate'			=> 'account-activate', // 
			'email-needed'		=> 'account-email-needed', //
			'change-email-confirm' => 'account-change-email'
		);
		$request_db_field = $request_to_db[$doing];
		
		$pre_doctitles = [
			'password-forgot'	=> _s('Forgot password?'),
			'password-reset'	=> _s('Reset password'),
			'resend-activation' => _s('Resend account activation'),
			//'activate'			=> 'account-activate',
			'email-needed'		=> _s('Add your email address'),
			'change-email-confirm' => _s('Email changed')
		];
		
		// Request log
		if(in_array($doing, array('password-forgot', 'password-reset', 'resend-activation', 'activate', 'email-needed'))) {
			$request_log = CHV\Requestlog::getCounts($request_db_field, 'fail');
			$captcha_needed = CHV\getSettings()['recaptcha'] ? CHV\must_use_recaptcha($request_log['day']) : false;
		}
		
		$is_process_done = false;
		$is_error = false;
		$error_message = NULL;
		$input_errors = NULL;
		
		if($captcha_needed & $_POST) {
			$captcha = CHV\recaptcha_check();
			if(!$captcha->is_valid) {
				$is_error = true;
				$error_message = _s("The reCAPTCHA wasn't entered correctly");
			}
		}
		
		$handler->template = $route . '/' . $doing;
		
		$SAFE_POST = $handler::getVar('safe_post');
		
		switch($doing) {
			
			case 'password-forgot':
			case 'resend-activation':
			
				if(($doing == 'password-forgot' and $logged_user['status'] == 'valid') or ($doing == 'resend-activation' and $logged_user['status'] == 'awaiting-confirmation')) {
					$_POST['user-subject'] = $logged_user['username'];
					$is_error = false;
				}
				
				if($_POST and !$is_error) {

					$subject_type = filter_var($_POST['user-subject'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
					
					if(trim($_POST['user-subject']) == '') {
						$is_error = true;
						$input_errors['user-subject'] = _s('Invalid Username/Email');
					}
					
					if(!$is_error) {
						
						// Get user candidate
						$user = CHV\User::getSingle($_POST['user-subject'], $subject_type);
						
						// Valid user?
						if(!filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
							$error_message = _s("User doesn't have an email.");
							$is_error = true;
						}
						
						// User account status
						if($user) {
							
							if($doing == 'password-forgot') {
								switch($user['status']) {
									case 'banned':
										$handler->template = 'request-denied';
										$handler::setVar('pre_doctitle', _s('Request denied'));
									break;
									case 'awaiting-email':
									case 'awaiting-confirmation':
										$is_error = true;
										$error_message = _s('Account needs to be activated to use this feature');
									break;
								}
							} else { //'resend-activation'
								if($user['status'] == 'valid') {
									$is_error = true;
									$error_message = _s('Account already activated');
								}
							}
							
							if($handler->template == 'request-denied') {
								CHV\Requestlog::insert(['type' => $request_db_field, 'result' => 'fail', 'user_id' => $user['id']]);
								G\set_status_header(403);
								return;
							}

							
						} else {
							$is_error = true;
							$input_errors['user-subject'] = _s('Invalid Username/Email');
						}
						
						if(!$is_error) {
							
							// Get any old confirmation
							$confirmation_db = CHV\Confirmation::get(['user_id' => $user['id'], 'type' => $request_db_field, 'status' => 'active'], ['field' => 'date', 'order' => 'desc'], 1);
							
							if($confirmation_db) {
								// Get the time diff
								$minute_diff = !$confirmation_db['confirmation_date_gmt'] ? 15 + 1 : G\datetime_diff($confirmation_db['confirmation_date_gmt'], NULL, 'm');
								
								if($minute_diff < 15) { // Mimic for the already submitted
									$is_error = true;
									$is_process_done = true;
									
									$activation_email = $user['email'];
									
									if($subject_type == 'username') { // We won't disclose this email address
										$activation_email = preg_replace('/(?<=.).(?=.*@)/u','*', $activation_email);
										$explode = explode('@', $activation_email);
										while(strlen($explode[0]) < 4) {
											$explode[0] .= '*';
										}
										$activation_email = implode('@', $explode);
									}
									
									$handler::setVar('resend_activation_email', $activation_email);
									$error_message = _s('Allow up to 15 minutes for the email. You can try again later.');
								} else {
									CHV\Confirmation::delete(['user_id' => $user['id'], 'type' => $request_db_field]);
								}
							}
							
						}
						
						// Do the thing
						if(!$is_error) {
						
							$hashed_token = CHV\generate_hashed_token($user['id']);	
							
							$array_values = [
								'type'		=> $request_db_field,
								'date'		=> G\datetime(),
								'date_gmt'	=> G\datetimegmt(),
								'token_hash'=> $hashed_token['hash']
							];
							
							if(!$user['confirmation_id']) {
								$array_values['user_id'] = $user['id'];
								$confirmation_db_query = CHV\Confirmation::insert($array_values);
							} else {
								$confirmation_db_query = CHV\Confirmation::update($user['confirmation_id'], $array_values);
							}
							
							if($confirmation_db_query) {
								
								$recovery_link = G\get_base_url('account/'.($doing == 'password-forgot' ? 'password-reset' : 'activate').'/?token='.$hashed_token['public_token_format']); 
								
								// Build the mail global
								global $theme_mail;
								$theme_mail = [
									'user' => $user,
									'link' => $recovery_link
								];
								
								if($doing == 'password-forgot') {
									$mail['subject'] = _s('Reset your password at %s', CHV\getSettings()['website_name']);
								} else {
									$mail['subject'] = _s('Confirmation required at %s', CHV\getSettings()['website_name']);
								}
								
								$mail['message'] = CHV\Render\get_email_body_str('mails/account-'.($doing == 'password-forgot' ? 'password-reset' : 'confirm'));
								
								try {
									if(CHV\send_mail($user['email'], $mail['subject'], $mail['message'])) {
										$is_process_done = true;
									}
								} catch(Exception $e) {
									$error_message = "Can't send the email.";
									$is_error = true;
									echo $e->getMessage();
								}
								
								if($doing == 'resend-activation') {
									$_SESSION['signup'] = [
										'status'		=> 'awaiting-confirmation',
										'email'			=> $SAFE_POST['email']
									];
									G\redirect('account/awaiting-confirmation');
								}

								$handler::setVar('password_forgot_email', $user['email']);
							} else {
								throw new Exception("Can't insert confirmation in DB", 400);
							}
						}
						
					}
					
					if($is_error) {
						CHV\Requestlog::insert(array('result' => 'fail', 'type' => $request_db_field, 'user_id' => $user ? $user['id'] : NULL));
						
						if(CHV\getSettings()['recaptcha'] and CHV\must_use_recaptcha($request_log['day'] + 1)) {
							$captcha_needed = true;
						}
						if(!$error_message) {
							$error_message = _s('Invalid Username/Email');
						}
						
					}
				
				}
				
			break;
					
			case 'awaiting-confirmation':
				$signup_email = $logged_user ? $logged_user['email'] : $_SESSION['signup']['email'];
				$handler::setVar('signup_email', $signup_email);
			break;
			
			case 'password-reset':
			case 'activate':
			case 'change-email-confirm':
				
				// $_GET token
				$get_token_array = explode(':', $_GET['token']);
				
				if($request_log["day"] > CHV_MAX_INVALID_REQUESTS_PER_DAY) {
					$get_token_array = false; // Lazy
				}
				if(!$get_token_array or count($get_token_array) !== 2) {
					CHV\Requestlog::insert(array('type' => $request_db_field, 'result' => 'fail', 'user_id' => $get_user));
					G\set_status_header(403);
					$handler->template = 'request-denied';
					$handler::setVar('pre_doctitle', _s('Request denied'));
					return;
				}
				$user_id = CHV\decodeID($get_token_array[0]);
				$get_token = CHV\hashed_token_info($_GET['token']);
				
				// Get token DB
				$confirmation_db = CHV\Confirmation::get(['type' => $request_db_field, 'user_id' => $get_token['id']]);
					
				$hash_match = CHV\check_hashed_token($confirmation_db['confirmation_token_hash'], $_GET['token']);
				
				// Son 48hrs que hay que aprovechar
				if(G\datetime_diff($confirmation_db['confirmation_date_gmt'], NULL, 'h') > 48) {
					CHV\Confirmation::delete(['id' => $confirmation_db['confirmation_id']]);
					$confirmation_db = false;
				}
				// Las horas más baratas de esta ciudad
				
				if(!$hash_match or !$confirmation_db) {					
					CHV\Requestlog::insert(["type" => $request_db_field, "result" => "fail", "user_id" => $user_id]);
					G\set_status_header(403);
					$handler->template = 'request-denied';
					$handler::setVar('pre_doctitle', _s('Request denied'));
					return;
				}
				
				switch($doing) {
					case 'activate':
						if($hash_match) {
							
							CHV\User::update($confirmation_db['confirmation_user_id'], array('status' => 'valid'));
							CHV\Confirmation::delete(['id' => $confirmation_db['confirmation_id']]);
							
							// log the activated user if needed
							$logged_user = CHV\Login::login($confirmation_db['confirmation_user_id'], $_SESSION['login'] ? $_SESSION['login']['type'] : 'session');
							
							// Welcome email
							global $theme_mail;
							$theme_mail = [
								'user' => $logged_user,
								'link' => $logged_user['url']
							];
							
							$mail['subject'] = _s('Welcome to %s', CHV\getSettings()['website_name']);
							$mail['message'] = CHV\Render\get_email_body_str('mails/account-welcome');

							if(CHV\send_mail($logged_user['email'], $mail['subject'], $mail['message'])) {
								$is_process_done = true;
							}
							
							G\redirect($logged_user ? CHV\User::getUrl($logged_user) : NULL);
							
						}
					break;
					case 'password-reset':
						if($_POST) {
							// Validate passwords
							if(!preg_match('/'.CHV\getSetting('user_password_pattern').'/', $_POST['new-password'])) {
								$input_errors['new-password'] = _s('Invalid password');
							}
							if($_POST['new-password'] !== $_POST['new-password-confirm']) {
								$input_errors["new-password-confirm"] = _s("Passwords don't match");
							}
							if(count($input_errors) == 0) {
								$login_db = CHV\Login::get(['user_id' => $user_id, 'type' => 'password'], NULL, 1);						
								if($login_db) {
									$is_process_done = CHV\Login::changePassword($user_id, $_POST['new-password']);
								} else { // Insert
									$is_process_done = CHV\Login::addPassword($user_id, $_POST['new-password']);
								}
								if($is_process_done) {								
									CHV\Confirmation::delete(['type' => $request_db_field, 'user_id' => $user_id]);
								} else {
									throw new Exception('Unexpected error', 400);
								}
							}
						}
					break;
					case 'change-email-confirm':
						if($hash_match) {
							$email_candidate = $confirmation_db['confirmation_extra'];
							$email_db = CHV\DB::get('users', ['email' => $email_candidate]);
							// Email found in DB
							if($email_db) { 
								if($email_db['user_status'] == 'valid') {
									CHV\Confirmation::delete(['id' => $confirmation_db['confirmation_id']]);
									CHV\Requestlog::insert(['type' => $request_db_field, 'result' => 'fail', 'user_id' => $user_id]);
									G\set_status_header(403);
									$handler->template = 'request-denied';
									$handler::setVar('pre_doctitle', _s('Request denied'));
									return;
								} else {
									// Delete the invalid user and any confirmation
									CHV\DB::delete('users', ['id' => $email_db['user_id']]);
									CHV\Confirmation::delete(['type' => 'account-change-email', 'user_id' => $email_db['user_id']]);
								}
							}
							
							CHV\Confirmation::delete(['type' => 'account-change-email', 'user_id' => $user_id]);
														
							$_SESSION['change-email-confirm'] = true;
							$user_edited = CHV\User::update($user_id, ['email' => $email_candidate]);
							CHV\Login::login($user_id, 'session');
							G\redirect('account/email-changed');
						}
					break;
				}
				
			break;
			
			case 'email-needed':
				if($_POST and !$is_error) {
					// Validate email
					if(!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
						$is_error = true;
						$input_errors['email'] = _s('Invalid email');
					}
					
					// Email being used?
					if(!$is_error) {
						$user = CHV\User::getSingle($_POST['email'], 'email');
						if($user) {
							$is_error = true;
							$input_errors['email'] = _s('Email already being used');
						}
					}
					
					// OK to go
					if(!$is_error) {
						CHV\User::update($logged_user['id'], ['status' => CHV\getSetting('require_user_email_confirmation') ? 'awaiting-confirmation' : 'valid', 'email' => trim($_POST['email'])]);
						if(!CHV\getSetting('require_user_email_confirmation')) {
							G\redirect($logged_user['url']);
						}
						
						$hashed_token = CHV\generate_hashed_token($logged_user['id']);	
						
						$array_values = array(
							'type'		=> 'account-activate',
							'date'		=> G\datetime(),
							'date_gmt'	=> G\datetimegmt(),
							'token_hash'=> $hashed_token['hash']
						);
						
						$array_values['user_id'] = $logged_user['id'];
						$confirmation_db_query = CHV\Confirmation::insert($array_values);
						
						$activation_link = G\get_base_url('account/activate/?token='.$hashed_token['public_token_format']); 
						
						// Build the mail global
						global $theme_mail;
						$theme_mail = [
							'user' => $logged_user,
							'link' => $activation_link
						];
						
						$mail['subject'] = _s('Confirmation required at %s', CHV\getSettings()['website_name']);
						$mail['message'] = CHV\Render\get_email_body_str('mails/account-confirm');

						try {
							if(CHV\send_mail($_POST['email'], $mail['subject'], $mail['message'])) {
								$is_process_done = true;
							}
						} catch(Exception $e) {
							$error_message = "Can't send the email.";
							$is_error = true;
							echo $e->getMessage();
						}
						
						$_SESSION['signup'] = [
							'status'	=> 'awaiting-confirmation',
							'email'		=> $SAFE_POST['email']
						];
						
						G\redirect('account/awaiting-confirmation');
						
					} else {
						CHV\Requestlog::insert(array('result' => 'fail', 'type' => $request_db_field, 'user_id' => $user ? $user['id'] : NULL));
						if(CHV\getSettings()['recaptcha'] and CHV\must_use_recaptcha($request_log['day'] + 1)) {
							$captcha_needed = true;
						}
					}
					
					if($is_error) {
						$error_message = $input_errors['email'];
					}
					
				}
			break;
			
			case 'email-changed':
				// Nota. limitar acceso
				if(!$_SESSION['change-email-confirm']) {
					$handler->issue404();
					return;
				}
				$handler->template = $route . '/' . 'email-changed';
			break;
			
			default:
				$handler->issue404();
				return;
			break;
			
		}

		$handler::setVar('pre_doctitle', $pre_doctitles[$doing]);
		$handler::setCond('error', $is_error);
		$handler::setCond('process_done', $is_process_done);
		$handler::setVar('input_errors', $input_errors);
		$handler::setVar('error', $error_message ? $error_message : _s('Check the errors in the form to continue.'));
		
		$handler::setCond('captcha_needed', $captcha_needed);
		if($captcha_needed and !$handler::getVar('recaptcha_html')) {
			$handler::setVar('recaptcha_html', CHV\Render\get_recaptcha_html('clean'));
		}
	
	} catch(Exception $e) {
		G\exception_to_error($e);
	}
	
};