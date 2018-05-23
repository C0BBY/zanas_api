<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

//discrete indexing

require 'vendor/autoload.php';
require "src/connect.php";
require "src/main.php";

$app = new \Slim\App;

$main = new Main(dbConnection()) ;





$app->post('/fetchItems', function (Request $request, Response $response) {
		
		$result = $GLOBALS['main']->fetchItems();
		return $result;

});

$app->post('/fetchCustomers', function (Request $request, Response $response) {
		
		$result = $GLOBALS['main']->fetchCustomers($_POST);

		return $result;

});

$app->post('/createOrder', function (Request $request, Response $response) {
		
		$result = $GLOBALS['main']->createOrder($_POST);

		return $result;

});

$app->post('/saveTransactions', function (Request $request, Response $response) {
		
		$result = $GLOBALS['main']->saveTransactions($_POST);

		return $result;

});

$app->post('/fetchOrders', function (Request $request, Response $response) {
		
		$result = $GLOBALS['main']->fetchOrders($_POST);

		return $result;

});

$app->post('/signIn', function (Request $request, Response $response) {
		
		$result = $GLOBALS['main']->signIn($_POST);
		return $result;

});

$app->post('/signUp', function (Request $request, Response $response) {
		
		$result = $GLOBALS['main']->signUp($_POST);
		return $result;

});

$app->post('/changePassword', function (Request $request, Response $response) {
		
		$result = $GLOBALS['main']->changePassword($_POST);
		return $result;

});

$app->post('/fetchLocation', function (Request $request, Response $response) {
		
		$result = $GLOBALS['main']->fetchLocation();
		return $result;

});


$app->post('/fetchStockBalances', function (Request $request, Response $response) {
		$result = $GLOBALS['main']->fetchStockBalances($_POST);
		return $result;

});

$app->post('/createJournal', function (Request $request, Response $response) {
		$result = $GLOBALS['main']->createJournal($_POST);
		return $result;

});

$app->post('/createTransferJournal', function (Request $request, Response $response) {
		$result = $GLOBALS['main']->createTransferJournal($_POST);
		return $result;

});


$app->post('/fetchAdjusted', function (Request $request, Response $response) {
		$result = $GLOBALS['main']->fetchAdjusted($_POST);
		return $result;

});

$app->post('/fetchTransfered', function (Request $request, Response $response) {
		$result = $GLOBALS['main']->fetchTransfered($_POST);
		return $result;

});

$app->post('/fetchDeliveries', function (Request $request, Response $response) {
		$result = $GLOBALS['main']->fetchDeliveries($_POST);
		return $result;

});

$app->post('/createDeliveryNumber', function (Request $request, Response $response) {
		$result = $GLOBALS['main']->createDeliveryNumber($_POST);
		return $result;

});

$app->post('/postDelivery', function (Request $request, Response $response) {
		$result = $GLOBALS['main']->postDelivery($_POST);
		return $result;

});

$app->post('/locationQuantity', function (Request $request, Response $response) {
		$result = $GLOBALS['main']->locationQuantity($_POST);
		return $result;

});


$app->run();