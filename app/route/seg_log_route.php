<?php
	use App\Lib\Response;
	use App\Lib\MiddlewareToken;
 
	/*** Grupo bajo la ruta seg_log ***/
	$app->group('/seg_log/', function () {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de seg_log');
		});
		
		/*** Ruta para obtener los datos de seg_log por medio del ID ***/
		$this->get('get/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->seg_log->get($arguments['id']));
		});

		/*** Ruta para obtener los datos de los seg_log ***/
		$this->get('getAll/', function($request, $response, $arguments) {
			return $response->withJson($this->model->seg_log->getAll());
		});
		
		/*** Ruta para obtener los datos de los seg_log de un mismo usuario ***/
		$this->get('geByUsuario/{fk_usuario}[/{since}/{to}]', function($request, $response, $arguments) {
			$arguments['since'] = isset($arguments['since'])? $arguments['since']: null;
			$arguments['to'] = isset($arguments['to'])? $arguments['to']: null;
			return $response->withJson($this->model->seg_log->getAll($arguments['fk_usuario'], $arguments['since'], $arguments['to']));
		});

		/*** Ruta para agregar un seg_log ***/
		$this->post('add/', function($request, $response, $arguments) {
			return $response->withJson($this->model->seg_log->add($request->getParsedBody()));
		});

		/*** Ruta para modificar un seg_log ***/
		$this->put('edit/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->seg_log->edit($request->getParsedBody(), $arguments['id']));
		});

		/*** Ruta para dar de baja un seg_log ***/
		$this->put('del/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->seg_log->del($arguments['id']));
		});
	})->add( new MiddlewareToken() );
?>