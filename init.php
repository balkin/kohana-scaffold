<?php defined('SYSPATH') or die('No direct script access.');

Route::set('scaffold', '(scaffold(/<action>((/<column>)(/<id>))))')
	->defaults(array(
		'controller' => 'scaffold',
		'action'     => 'index',
	));