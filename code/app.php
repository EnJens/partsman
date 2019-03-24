<?php

require_once 'vendor/autoload.php';
include_once "config.php";

session_start();
$app = new \Slim\App();
$container = $app->getContainer();

// Register provider
$container['flash'] = function () {
    return new \Slim\Flash\Messages();
};

$container['view'] = function ($container) {
    $view = new \Slim\Views\Twig('templates', [
        'cache' => '/tmp/twig-cache',
        'auto_reload' => true,
        'debug' => true,
    ]);

    // Instantiate and add Slim specific extension
    $router = $container->get('router');
    $uri = \Slim\Http\Uri::createFromEnvironment(new \Slim\Http\Environment($_SERVER));
    $view->addExtension(new Slim\Views\TwigExtension($router, $uri));
    $view->addExtension(new Knlv\Slim\Views\TwigMessages(
        new Slim\Flash\Messages()
    ));
    return $view;
};

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$container['conn'] = function() use($servername, $username, $password, $database) {
    $conn = new mysqli($servername, $username, $password, $database);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
};


$app->get('/', function ($request, $response, $args) {
    $conn = $this->conn;
    if ($request->getParam('query')) {
        $query = $request->getParam('query');
        $stmt = $conn->prepare("select * from parts where vendor_id like ?");
        $query_arg = "%$query%";
        $stmt->bind_param("s", $query_arg);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        if (!$result = $conn->query("select * from parts")) {
            die("Error getting results" . $conn->error);
        }
    }

    $rows = $result->fetch_all(MYSQLI_ASSOC);
    return $this->view->render(
        $response,
        "index.html",
        ["parts" => $rows]);
}) ->setName('index');

$app->get('/add', function ($request, $response, $args) {
    return $this->view->render($response,"add.html");
})->setName('add');

$app->post('/add', function ($request, $response, $args) use($app) {
    $conn = $this->conn;
    $vendor_id = $request->getParam('vendor_id');
    $quantity = $request->getParam('quantity', 1);

    $stmt = $conn->prepare("insert into parts(vendor_id, quantity) values (?, ?)");
    $stmt->bind_param("si", $vendor_id, $quantity);
    if(!$stmt->execute()) {
        die("Error executing!? " . $stmt->error);
    }
    if($stmt->affected_rows === 0) die('No rows updated');

    $this->flash->addMessage('success', "Added part $vendor_id");
    $stmt->close();
    //$conn->commit();
    return $response->withRedirect('/add');
})->setName('add');

$app->run();