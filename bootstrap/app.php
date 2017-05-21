<?php

use Respect\Validation\Validator as v;
use \Slim\Middleware\HttpBasicAuthentication\PdoAuthenticator;

session_start();

require __DIR__ . '/../vendor/autoload.php';

try {
	$dotenv = (new \Dotenv\Dotenv(__DIR__ . '/../'))->load();
} catch (\Dotenv\Exception\InvalidPathException $e) {
	//
}

$whoops = new \Whoops\Run;
$whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
$whoops->register();


$app = new \Slim\App([
	'settings' => [
		'displayErrorDetails' => true,
		'mailer' => [
		    'host' => getenv('MAIL_HOST'),
            'username' => getenv('MAIL_USERNAME'),
            'password' => getenv('MAIL_PASSWORD')
		],
		'baseUrl' => getenv('BASE_URL')
	],

]);

require_once __DIR__ . '/database.php';

$app->add(new \Slim\Middleware\HttpBasicAuthentication([
    "authenticator" => new PdoAuthenticator([
        "pdo" => $pdo,
        "table" => "users",
        "user" => "name",
        "hash" => "password"
    ]),
    "path" => "/api",
    "realm" => "Protected",
    "secure" => false,
    "error" => function ($request, $response, $arguments) {
        $data = [];
        $data["status"] = "error";
        $data["message"] = $arguments["message"];
        return $response->write(json_encode($data, JSON_UNESCAPED_SLASHES));
    }
]));

$container = $app->getContainer();

$container['db'] = function ($container) use ($capsule) {
	return $capsule;
};

$container['auth'] = function($container) {
	return new \App\Auth\Auth;
};

$container['flash'] = function($container) {
	return new \Slim\Flash\Messages;
};

$container['mailer'] = function($container) {
	return new Nette\Mail\SmtpMailer($container['settings']['mailer']);
};

$container['view'] = function ($container) {
	$view = new \Slim\Views\Twig(__DIR__ . '/../resources/views/', [
		'cache' => false,
	]);

	$view->addExtension(new \Slim\Views\TwigExtension(
		$container->router,
		$container->request->getUri()
	));

	$view->getEnvironment()->addGlobal('auth',[
		'check' => $container->auth->check(),
		'user' => $container->auth->user()
	]);

	$view->getEnvironment()->addGlobal('flash',$container->flash);

	return $view;
};

$container['validator'] = function ($container) {
	return new App\Validation\Validator;
};

$container['HomeController'] = function($container) {
	return new \App\Controllers\HomeController($container);
};

$container['AuthController'] = function($container) {
	return new \App\Controllers\Auth\AuthController($container);
};

$container['ImageController'] = function($container) {
    return new \App\Controllers\ImageController($container);
};

$container['PasswordController'] = function($container) {
	return new \App\Controllers\Auth\PasswordController($container);
};

$app->add(new \App\Middleware\ValidationErrorsMiddleware($container));
$app->add(new \App\Middleware\OldInputMiddleware($container));

v::with('App\\Validation\\Rules\\');

require __DIR__ . '/../app/routes.php';
