<?php
	use App\Lib\Response;
	use App\Lib\MiddlewareToken;

	/*** Grupo bajo la ruta resena ***/  
	$app->group('/resena/', function () {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de resena');
		})->add( new MiddlewareToken() );

		/*** 
		 * Ruta para obtener un registro de la tabla resena mediante su ID
		 * recibe {ID} ID del resena
		 * regresa: registro con el ID proporcionado
		 * ***/
		$this->get('get/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->resena->get($arguments['id']));
		});

		/*** 
		 * Ruta para obtener todos los reviews pertenecientes a un producto
		 * recibe {producto_id} ID del producto
		 * recibe {cliente_id} ID del cliente
		 * regresa: objeto con todos los reviews pertenecientes a dicho producto
		 * ***/
		$this->get('getByProducto/{producto_id}[/{cliente_id}]', function($request, $response, $arguments) {
			$arguments['cliente_id'] = isset($arguments['cliente_id'])? $arguments['cliente_id']: 0;
			return $response->withJson($this->model->resena->getByProducto($arguments['producto_id'], $arguments['cliente_id']));
		});

		/*** 
		 * Ruta para obtener todos los reviews que ha hecho un mismo cliente
		 * recibe {cliente_id} ID del cliente
		 * regresa: objeto con todos los reviews pertenecientes a dicho cliente
		 * ***/
		$this->get('getByCliente/{cliente_id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->resena->getByCliente($arguments['cliente_id']));
		})->add( new MiddlewareToken() );

		/***
		 * Ruta para agregar un nuevo registro en la base de datos
		 * regresa el ID del nuevo registro
		 */
		$this->post('add/', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->resena->add($request->getParsedBody());
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Alta reseña', 'resena', $resultado->response);
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
		 * Ruta para modificar el resena hecho a un producto
		 * recibe {id} ID del resena
		 * ***/
		$this->put('edit/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->resena->edit($request->getParsedBody(), $arguments['id']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Actualización información reseña', 'resena', $arguments['id']);
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
		 * Ruta para borrar un resena mediante su ID
		 * recibe {id} ID del resena a eliminar
		 * ***/
		$this->put('del/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->resena->del($arguments['id']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Cancelación reseña', 'resena', $arguments['id']);
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