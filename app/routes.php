<?php
use App\Middleware\GuestMiddleware;
use App\Middleware\AuthMiddleware;
use \App\Middleware\CsrfViewMiddleware;


$app->get('/','HomeController:index')->setName('home');
$app->post('/api', 'ImageController:compressImage');

$app->group('',function () {
	$this->get('/auth/signup','AuthController:getSignUp')->setName('auth.signup');
	$this->post('/auth/signup','AuthController:postSignUp');

	$this->get('/auth/signin','AuthController:getSignIn')->setName('auth.signin');
	$this->post('/auth/signin','AuthController:postSignIn');
	
	$this->get('/auth/confirm','AuthController:confirmEmail');
})->add(new CsrfViewMiddleware($container))->add(new GuestMiddleware($container));

$app->group('',function () {
	$this->get('/auth/signout','AuthController:getSignOut')->setName('auth.signout');

	$this->get('/auth/password/change','PasswordController:getChangePassword')->setName('auth.password.change');
	$this->post('/auth/password/change','PasswordController:postChangePassword');
})->add(new CsrfViewMiddleware($container))->add(new AuthMiddleware($container));
