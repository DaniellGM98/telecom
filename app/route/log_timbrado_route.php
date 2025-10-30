<?php
	use App\Lib\Response;
	use App\Lib\MiddlewareToken;
 
	/*** Grupo bajo la ruta log_timbrado ***/  
	$app->group('/log_timbrado/', function() {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de log_timbrado');
		});
		
		/*** Ruta para obtener los datos de log_timbrado por medio del ID ***/
		$this->get('get/{id_log_timbrado}', function($request, $response, $arguments) {
			return $response->withJson($this->model->log_timbrado->get($arguments['id_log_timbrado']));
		});

		/*** Ruta para buscar producto ***/
		$this->get('find/{filtro}', function($request, $response, $arguments) {
			return $response->withJson($this->model->log_timbrado->find($arguments['filtro'])->result);
		});

		/*** Ruta para obtener los datos de los log_timbrado ***/
		$this->get('getAll/', function($request, $response, $arguments) {
					
			return $response->withJson($this->model->log_timbrado->getAll());
		});

		/*** Ruta para agregar un log_timbrado ***/
		$this->post('add/', function($request, $response, $arguments) {
			return $response->withJson($this->model->log_timbrado->add($request->getParsedBody()));
		});

		/*** Ruta para modificar un log_timbrado ***/
		$this->put('edit/{id_log_timbrado}', function($request, $response, $arguments) {
			return $response->withJson($this->model->log_timbrado->edit($request->getParsedBody(), $arguments['id_log_timbrado']));
		});

		/*** Ruta para dar de baja un log_timbrado ***/
		$this->put('del/{id_log_timbrado}', function($request, $response, $arguments) {
			return $response->withJson($this->model->log_timbrado->del($arguments['id_log_timbrado']));
		});
	})->add( new MiddlewareToken() );
?>