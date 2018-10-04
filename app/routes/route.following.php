<?php
$route = function($handler) {
	
	if(version_compare(CHV\getSetting('chevereto_version_installed'), '3.7.0', '<') OR !CHV\getSetting('enable_followers')) {
		return $handler->issue404();
	}
	
	$logged_user = CHV\Login::getUser();
	
	if(!$logged_user) {
		G\redirect('login');
	}
	
	if($handler->isRequestLevel(2)) return $handler->issue404(); // Allow only 3 levels
	
	// Build the tabs
	$tabs = [
		[
			'list'		=> TRUE,
			'tools'		=> TRUE,
			'label'		=> _s('Most recent'),
			'id'		=> 'list-most-recent',
			'params'	=> 'list=images&sort=date_desc&page=1',
			'current'	=> $_REQUEST['sort'] == 'date_desc' or !$_REQUEST['sort'] ? TRUE : FALSE, // Default
		],
		[
			'list'		=> TRUE,
			'tools'		=> TRUE,
			'label'		=> _s('Most viewed'),
			'id'		=> 'list-most-viewed',
			'params'	=> 'list=images&sort=views_desc&page=1',
			'current'	=> $_REQUEST['sort'] == 'views_desc',
		],
	];
	if(CHV\getSetting('enable_likes')) {
		$tabs[] = [
			'list'		=> TRUE,
			'tools'		=> TRUE,
			'label'		=> _s('Most liked'),
			'id'		=> 'list-most-liked',
			'params'	=> 'list=images&sort=likes_desc&page=1',
			'current'	=> $_REQUEST['sort'] == 'likes_desc',
		];
	}
	$current = FALSE;
	foreach($tabs as $k => $v) {
		$tabs[$k]['params_hidden'] .= 'follow_user_id=' . CHV\encodeID($logged_user['id']);
		if($v['current']) {
			$current = TRUE;
		}
		$tabs[$k]['type'] = 'images';
		$route_path = G\get_route_name();
		$tabs[$k]['url'] = G\get_base_url(rtrim($route_path, '/') . '/?' . $tabs[$k]['params']);
	}
	if(!$current) {
		$tabs[0]['current'] = TRUE;
	}
	
	$where = 'WHERE follow_user_id=:user_id';
	
	// List
	$list_params = CHV\Listing::getParams(); // Use CHV magic params
	$list = new CHV\Listing;
	$list->setType('images');
	$list->setOffset($list_params['offset']);
	$list->setLimit($list_params['limit']); // how many results?
	$list->setItemsPerPage($list_params['items_per_page']); // must
	$list->setSortType($list_params['sort'][0]); // date | size | views
	$list->setSortOrder($list_params['sort'][1]); // asc | desc
	$list->setRequester(CHV\Login::getUser());
	$list->setWhere($where);
	$list->bind(":user_id", $logged_user['id']);
	$list->exec();
	
	$handler::setVar('pre_doctitle', _s('Following'));
	//$handler::setVar('meta_keywords', NULL);
	$handler::setVar('tabs', $tabs);
	$handler::setVar('list', $list);
	
	if($logged_user['is_admin']) {
		$handler::setVar('user_items_editor', false);
	}
	
	//$handler->template = 'explore';
};