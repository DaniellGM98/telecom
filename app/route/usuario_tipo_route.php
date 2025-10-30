<?php
	use App\Lib\MiddlewareToken;

	$app->group('/usuario_tipo/', function() use ($app) {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de usuario_tipo');
		});

		$this->get('get/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->usuario_tipo->get($arguments['id']));
		})->add( new MiddlewareToken() );

		$this->get('find/{busqueda}', function($request, $response, $arguments) {
			return $response->withJson($this->model->usuario_tipo->find($arguments['busqueda']));
		})->add( new MiddlewareToken() );

		$this->get('getAll/{pagina}/{limite}[/{busqueda}]', function($request, $response, $arguments) {
			$arguments['busqueda'] = isset($arguments['busqueda'])? $arguments['busqueda']: 0;
			return $response->withJson($this->model->usuario_tipo->getAll($arguments['pagina'], $arguments['limite']), $arguments['busqueda']);
		})->add( new MiddlewareToken() );
	});
?>