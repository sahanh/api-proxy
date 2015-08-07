<?php
use Symfony\Component\HttpFoundation\Request;
use App\APICallException;

require '../vendor/autoload.php';

try {
    
    $manager = new App\RequestManager;
    $request = Request::createFromGlobals();

    // Forward the request and get the response.
    $manager->execute($request)->send();

} catch (APICallException $e) {
    
    header('Content-type: application/json');
    http_response_code(422);
    echo json_encode(['error' => 'An error occurred when authenticating the remote api call.']);

} catch (Exception $e) {
    
    header('Content-type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'An unknown error occurred.']);
}