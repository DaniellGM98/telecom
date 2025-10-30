<?php
	use App\Lib\Response;
	use App\Lib\MiddlewareToken;

	/*** Grupo bajo la ruta prod_evento ***/  
	$app->group('/prod_evento/', function() {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de prod_evento');
		})->add( new MiddlewareToken() );

		/*** 
		 * Ruta para obtener detalle del prod_evento mediante su ID
		 * recibe {id} ID del prod_evento
		 * regresa: registro con el detalle del prod_evento
		 * ***/
		$this->get('get/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->prod_evento->get($arguments['id']));
		});

		/*** 
		 * Ruta para obtener detalle del prod_evento mediante el producto y el evento
		 * recibe {evento_id} ID del evento
		 * recibe {producto_id} ID del producto. Si no se proporciona, devuelve todos los productos de dicho evento
		 * regresa: registro con el detalle del prod_evento
		 * ***/
		$this->get('getByEvento/{evento_id}[/{producto_id}]', function($request, $response, $arguments) {
			$arguments['producto_id'] = isset($arguments['producto_id'])? $arguments['producto_id']: 0;
			return $response->withJson($this->model->prod_evento->get($arguments['evento_id'], $arguments['producto_id']));
		});
		
		/***
		 * Ruta para agregar un nuevo registro en la base de datos
		 */
		$this->post('add/', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->prod_evento->add($request->getParsedBody());
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Alta producto evento', 'prod_evento', $resultado->result);
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
		})->add( new MiddlewareToken() );

		/***
		 * Ruta para modificar un registro de la base de datos por medio del ID
		 * recibe {id} ID del prod_evento a modificar
		 * ***/
		$this->put('edit/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->prod_evento->edit($request->getParsedBody(), $arguments['id']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Actualización información producto evento', 'prod_evento', $arguments['id']);
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
		})->add( new MiddlewareToken() );

		/***
		 * Ruta para dar de baja un registro de la base de datos
		 * recibe {id} ID del prod_evento
		 * ***/
		$this->put('del/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->prod_evento->del($arguments['id']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Baja producto evento', 'prod_evento', $arguments['id']);
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
		})->add( new MiddlewareToken() );

		/***
		 * Ruta para dar de baja un registro de la base de datos mediante el producto y el evento
		 * recibe {evento_id} ID del prod_evento
		 * recibe {producto_id} ID del prod_evento
		 * ***/
		$this->put('delByProducto/{evento_id}/{producto_id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$prod_evento = $this->model->prod_evento->getByEvento($arguments['evento_id'], $arguments['producto_id'])->result;
			$resultado = $this->model->prod_evento->del($arguments['evento_id'], $arguments['producto_id']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Baja producto evento', 'prod_evento', $prod_evento->id);
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
		})->add( new MiddlewareToken() );
	});
?>