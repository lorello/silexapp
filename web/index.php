<?php
/*
 * Created by lorello reading http://silex.sensiolabs.org/doc/intro.html
 */
require_once __DIR__.'/../vendor/autoload.php';

// to use Request object
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// Turn on debugging
$app['debug'] = false;

// Create the app instance
$app = new Silex\Application();

// In Silex you define a route and the controller that is called when that route is matched.
// There is also an alternate way for defining controllers using a class method. The syntax for that is
// ClassName::methodName. Static methods are also possible.
// A better way to define controllers is using http://silex.sensiolabs.org/doc/providers/service_controller.html
// A route pattern consists of:
//
//    Pattern: The route pattern defines a path that points to a resource. The pattern can include variable parts and
//             you are able to set RegExp requirements for them.
//    Method:  One of the following HTTP methods: GET, POST, PUT DELETE. This describes the interaction with the resource.
//             Commonly only GET and POST are used, but it is possible to use the others as well.
$app->get(
    '/hello/{name}',
    function ($name) use ($app) {
        return 'Hello '.$app->escape($name);
    }
)->assert('name', '[A-Za-z]+');

// A simple homepage
$app->get(
    '/',
    function () {
        // The return value of the closure becomes the content of the page.
        return 'Hello world!';
    }
);

// Simulating a blog
$blogPosts = [
    1 => [
        'date'   => '2011-03-29',
        'author' => 'igorw',
        'title'  => 'Using Silex',
        'body'   => '...',
    ],
];

// Get posts list
$app->get(
    '/blog',
    function () use ($blogPosts) {
        $output = '';
        foreach ($blogPosts as $id => $post) {
            $output .= "<a href=\"/blog/show/$id\">".$post['title'].'</a>';
            $output .= "<br />\n";
        }

        return $output;
    }
);

// This route definition has a variable {id} part which is passed to the closure.
$app->get(
    '/blog/show/{id}',
    function (Silex\Application $app, $id) use ($blogPosts) {
        if (!isset($blogPosts[$id])) {
            // When the post does not exist, we are using abort() to stop the request early. It actually throws an exception,
            // which we will see how to handle later on.
            $app->abort(404, "Post $id does not exist.");
        }

        $post = $blogPosts[$id];

        return "<h1>{$post['title']}</h1>\n".
            "<p>{$post['body']}</p>";
    }
)->assert('id', '\d+');

// POST routes signify the creation of a resource. An example for this is a feedback form.
$app->post(
    '/feedback',
    function (Request $request) use ($app) {

        // The current request is automatically injected by Silex to the Closure thanks to the type hinting. It is an
        // instance of Request, so you can fetch variables using the request get method.
        $message = $request->get('message');
        if (empty($message)) {
            $app->error('500', 'Message is empty, have you posted a $message variable?');
        }

        // Instead of returning a string we are returning an instance of Response. This allows setting an HTTP status code,
        // in this case it is set to 201 Created.
        return new Response('Thank you for your feedback!<br />', 201);
    }
);

// JSON example
$app->get(
    '/users.json/{id}',
    function ($id) use ($app) {

        // Sample
        //$user = getUser($id);
        $user = ['name' => 'John', 'surname' => 'Jim'];

        if (!$user) {
            $error = ['message' => 'The user was not found.'];

            return $app->json($error, 404);
        }

        return $app->json($user);
    }
)->assert('id', '\d+');

// Upload a file through a form POST
$app->post(
    '/upload',
    function (Request $request) use ($app) {
        $file = $request->files->get('upload');
        // could throw a Symfony\Component\HttpFoundation\File\Exception\FileException
        // will overwrite existing file
        //return var_dump($_FILES);
        $file->move(__DIR__.'/../files', $file->getClientOriginalName());

        //return $app->error('500', "Cannot upload ".$file->getClientOriginalName());
        return $app->json(['response' => 'OK']);
    }
);

// Upload a file putting it in the body of a POST
// Test me with:
// curl --data-binary @./images/sample.gif --header "name: mysample.gif" http://silexapp/push
$app->post(
    '/push',
    function (Request $request) use ($app) {
        $file = $request->getContent();
        $name = $request->headers->get('name');
        $filepath = __DIR__.'/../files/'.$name;
        if (empty($name)) {
            $app->error('500', "Cannot push file, without specifying it's name");
        }

        if (file_exists($filepath)) {
            $app->error('500', "File $name already exists");
        }
        file_put_contents($filepath, $file);

        return $app->json(['response' => 'OK', 'name' => $name]);
    }
);

// Register the error handler
// Silex comes with a default error handler that displays a detailed error message with the stack trace when debug is
// true, and a simple error message otherwise. Error handlers registered via the error() method always take precedence
// but you can keep the nice error messages when debug is turned on like this:
$app->error(
    function (\Exception $e, $code) use ($app) {
        if ($app['debug']) {
            return;
        }

        switch ($code) {
            case 404:
                $message = 'The requested page could not be found.';
                break;
            default:
                $message = 'We are sorry, but something went terribly wrong.';
        }

        return new Response($message);
    }
);

// If your application is hosted behind a reverse proxy and you want Silex to trust the X-Forwarded-For* headers, you will need to run your application like this:
//Request::setTrustedProxies(array('127.0.0.1'));
$app->run();
