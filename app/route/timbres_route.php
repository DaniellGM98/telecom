<?php
	use App\Lib\Response;
	use App\Lib\MiddlewareToken;
 
	/*** Grupo bajo la ruta timbres ***/  
	$app->group('/timbres/', function() {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de timbres');
		});
		
		/*** Ruta para obtener los datos de timbres por medio del ID ***/
		$this->get('get/{id_timbre}', function($request, $response, $arguments) {
			return $response->withJson($this->model->timbres->get($arguments['id_timbre']));
		});

		/*** Ruta para buscar producto ***/
		$this->get('find/{filtro}', function($request, $response, $arguments) {
			return $response->withJson($this->model->timbres->find($arguments['filtro'])->result);
		});

		/*** Ruta para obtener los datos de los timbres ***/
		$this->get('getAll/', function($request, $response, $arguments) {
			return $response->withJson($this->model->timbres->getAll());
		});

		/*** Ruta para agregar un timbres ***/
		$this->post('add/', function($request, $response, $arguments) {
			return $response->withJson($this->model->timbres->add($request->getParsedBody()));
		});

		/*** Ruta para modificar un timbres ***/
		$this->put('edit/{id_timbre}', function($request, $response, $arguments) {
			return $response->withJson($this->model->timbres->edit($request->getParsedBody(), $arguments['id_timbre']));
		});

		/*** Ruta para dar de baja un timbres ***/
		$this->put('del/{id_timbre}', function($request, $response, $arguments) {
			return $response->withJson($this->model->timbres->del($arguments['id_timbre']));
		});

		/** 
		 * Método getDisponibles
		 * Regresa los timbres disponibles y el id de la ultima asignación
		 * by isantosp
		 */
		$this->get('getDisponibles', function($request, $response, $arguments) {
			return $response->withJson($this->model->timbres->getDisponibles());
		});
	})->add( new MiddlewareToken() );
?>