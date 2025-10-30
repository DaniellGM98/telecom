<?php
	use App\Lib\Response;
	use App\Lib\MiddlewareToken;

	/*** Grupo bajo la ruta lista_deseos ***/  
	$app->group('/lista_deseos/', function() {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')
				->write('Soy ruta de lista_deseos');
		});

		/*** 
		 * Ruta para obtener la informacion de un registro mediante su ID
		 * recibe {id} ID del registro
		 * regresa: objeto con la información del registro
		 * ***/
		$this->get('get/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->lista_deseos->get($arguments['id']));
		});

		/***
		 * Ruta para obtener la información de los productos que estan en la lista de deseos mediante el ID del producto
		 * recibe {producto_id} ID del producto
		 * regresa registro con la información de todos los registros de dicho producto en la lista de deseos
		 */
		$this->get('getByProducto/{producto_id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->lista_deseos->getByProducto($arguments['producto_id']));
		});
		
		/*** 
		 * Ruta para obtener todos los registros agregados a la lista de deseos pertenecientes a un cliente
		 * recibe {cliente_id} ID del cliente
		 * recibe opcional {producto_id} ID del producto. Si no se proporciona este valor, devolverá todos los productos agregados por dicho cliente
		 * regresa: json con todos los registros de la lista de deseos para el cliente específico
		 * ***/
		$this->get('getByCliente/{cliente_id}[/{producto_id}]', function($request, $response, $arguments) {
			$arguments['producto_id'] = isset($arguments['producto_id'])? $arguments['producto_id']: 0;
			return $response->withJson($this->model->lista_deseos->getByCliente($arguments['cliente_id'], $arguments['producto_id']));
		});
		
		/***
		 * Ruta para agregar un nuevo registro en la base de datos
		 * regresa: ID del nuevo registro
		 */
		$this->post('add/', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->lista_deseos->add($parsedBody = $request->getParsedBody());
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Alta producto a lista de deseos', 'lista_deseos', $resultado->result);
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

		/***
		 * Ruta para modificar un registro mediante su ID
		 * recibe {id} ID del registro en la base de datos
		 * ***/
		$this->put('edit/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->lista_deseos->edit($request->getParsedBody(), $arguments['id']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Actualización producto lista de deseos', 'lista_deseos', $arguments['id']);
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

		/***
		 * Ruta para dar de baja un registro de la lista de deseos mediante el ID del registro
		 * recibe {id} ID del registro en la base de datos
		 * ***/
		$this->put('del/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->lista_deseos->del($arguments['id']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Baja producto lista de deseos', 'lista_deseos', $arguments['id']);
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

		/***
		 * Ruta para dar de baja un registro de la lista de deseos mediante el ID del cliente y del producto
		 * recibe {cliente_id} ID del cliente
		 * recibe {producto_id} ID del producto
		 * ***/
		$this->put('del/{cliente_id}/{producto_id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$wishlist = $this->model->lista_deseos->getByCliente($arguments['cliente_id'], $arguments['producto_id']);
			$resultado = $this->model->lista_deseos->del($wishlist->result[0]->id);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Baja producto lista de deseos', 'lista_deseos', $wishlist->result[0]->id);
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

		/***
		 * Ruta para mover un producto de la lista de deseos al carrito
		 * recibe {producto_id} ID del producto en la lista de deseos
		 * recibe {cliente_id} ID del cliente
		 */
		$this->post('moveToCart/', function($request, $response, $arguments) {
			require_once './core/defines.php';
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody();

			$wishlist = $this->model->lista_deseos->getByCliente($parsedBody['cliente_id'], $parsedBody['producto_id']);
			$id = $wishlist->result[0]->id;

			$data = [
				'producto_id' => $parsedBody['producto_id'],
				'cliente_id' => $parsedBody['cliente_id'],
				'cantidad' => 1,
				'fecha' => date('Y-m-d H:i:s')
			];
			$carrito = $this->model->carrito->add($data);
			if($carrito->response) {
				$lista_deseos = $this->model->lista_deseos->del($id);
				if($lista_deseos->response) {
					$seg_log = $this->model->seg_log->add('Cambio de producto de lista de deseos a carrito', 'lista_deseos', $id);
					if($seg_log->response) {
						$this->response->result = $carrito->result;
						$this->response->state = $this->model->transaction->confirmaTransaccion();
						$this->response->SetResponse(true);
					} else {
						$this->response->result = $seg_log->result;
						$this->response->errors = $seg_log->errors;
						$this->response->state = $this->model->transaction->regresaTransaccion();
						$this->response->SetResponse(false, $seg_log->message);
					}
				} else {
					$this->response->result = $lista_deseos->result;
					$this->response->errors = $lista_deseos->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->SetResponse(false, $lista_deseos->message);
				}
			} else {
				$this->response->result = $carrito->result;
				$this->response->errors = $carrito->errors;
				$this->response->state = $this->model->transaction->regresaTransaccion();
				$this->response->SetResponse(false, $carrito->message);
			}

			return $response->withJson($this->response);
		});
	})->add( new MiddlewareToken() );
?>