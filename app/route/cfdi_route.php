<?php
	use App\Lib\Response;
	use App\Lib\MiddlewareToken;
 
	/*** Grupo bajo la ruta cfdi ***/  
	$app->group('/cfdi/', function() {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de cfdi');
		});
		
		/*** Ruta para obtener los datos de cfdi por medio del ID ***/
		$this->get('get/{id_cfdi}', function($request, $response, $arguments) {
			return $response->withJson($this->model->cfdi->get($arguments['id_cfdi']));
		});

		/*** Ruta para buscar cfdi ***/
		$this->get('find/{filtro}', function($request, $response, $arguments) {
			return $response->withJson($this->model->cfdi->find($arguments['filtro']));
			
		});

		/*** Ruta para obtener los datos de los cfdi ***/
		$this->get('getAll/', function($request, $response, $arguments) {
			return $response->withJson($this->model->cfdi->getAll());
		});

		/*** Ruta para agregar un cfdi ***/
		$this->post('add/', function($request, $response, $arguments) {
			return $response->withJson($this->model->cfdi->add($request->getParsedBody()));
		});

		/*** Ruta para modificar un cfdi ***/
		$this->put('edit/{id_cfdi}', function($request, $response, $arguments) {
			return $response->withJson($this->model->cfdi->edit($request->getParsedBody(), $arguments['id_cfdi']));
		});

		/*** Ruta para dar de baja un cfdi ***/
		$this->put('del/{id_cfdi}', function($request, $response, $arguments) {
			return $response->withJson($this->model->cfdi->del($arguments['id_cfdi']));
		});
	})->add( new MiddlewareToken() );
?>