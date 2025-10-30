<?php
	use App\Lib\Response;
	use App\Lib\MiddlewareToken;

	/*** Grupo bajo la ruta seg_accion ***/
	$app->group('/seg_accion/', function () {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de seg_accion');
		});
		
		/*** Ruta para obtener los datos de seg_accion por medio del ID ***/
		$this->get('get/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->seg_accion->get($arguments['id']));
		});

		/*** Ruta para obtener los datos de los seg_accion ***/
		$this->get('getAll/', function($request, $response, $arguments) {
			return $response->withJson($this->model->seg_accion->getAll());
		});
		
		/*** Ruta para obtener los datos de los seg_accion mediante el ID del modulo ***/
		$this->get('getByModulo/{fk_seg_modulo}', function($request, $response, $arguments) {
			return $response->withJson($this->model->seg_accion->getByModulo($arguments['fk_seg_modulo']));
		});

		/*** Ruta para agregar un seg_accion ***/
		$this->post('add/', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->seg_accion->add($request->getParsedBody());
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Alta nueva acción', 'seg_accion', $resultado->result);
				if($seg_log->response) {
					$this->response->result = $resultado->result;
					$this->response->state = $this->model->transaction->confirmaTransaccion();
					$this->response->SetResponse(true);
				} else {
					$this->response->result = $seg_log->result;
					$this->response->errors = $seg_log->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->SetResponse(false, $seg_log->message);
				}
			} else {
				$this->response->result = $resultado->result;
				$this->response->errors = $resultado->errors;
				$this->response->state = $this->model->transaction->regresaTransaccion();
				$this->response->SetResponse(false, $resultado->message);
			}

			return $response->withJson($this->response);
		});

		/*** Ruta para modificar un seg_accion ***/
		$this->put('edit/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->seg_accion->edit($request->getParsedBody(), $arguments['id']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Actualización información acción', 'seg_accion', $arguments['id']);
				if($seg_log->response) {
					$this->response->result = $resultado->result;
					$this->response->state = $this->model->transaction->confirmaTransaccion();
					$this->response->SetResponse(true);
				} else {
					$this->response->result = $seg_log->result;
					$this->response->errors = $seg_log->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->SetResponse(false, $seg_log->message);
				}
			} else {
				$this->response->result = $resultado->result;
				$this->response->errors = $resultado->errors;
				$this->response->state = $this->model->transaction->regresaTransaccion();
				$this->response->SetResponse(false, $resultado->message);
			}

			return $response->withJson($this->response);
		});

		/*** Ruta para dar de baja un seg_accion ***/
		$this->put('del/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->seg_accion->del($arguments['id']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Baja acción', 'seg_accion', $arguments['id']);
				if($seg_log->response) {
					$this->response->result = $resultado->result;
					$this->response->state = $this->model->transaction->confirmaTransaccion();
					$this->response->SetResponse(true);
				} else {
					$this->response->result = $seg_log->result;
					$this->response->errors = $seg_log->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->SetResponse(false, $seg_log->message);
				}
			} else {
				$this->response->result = $resultado->result;
				$this->response->errors = $resultado->errors;
				$this->response->state = $this->model->transaction->regresaTransaccion();
				$this->response->SetResponse(false, $resultado->message);
			}

			return $response->withJson($this->response);
		});
	})->add( new MiddlewareToken() );
?>