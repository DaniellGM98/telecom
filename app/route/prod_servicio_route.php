<?php
	use App\Lib\Response;
	use App\Lib\MiddlewareToken;
 
	/*** Grupo bajo la ruta prod_servicio ***/
	$app->group('/prod_servicio/', function() {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de prod_servicio');
		})->add( new MiddlewareToken() );
		
		$this->get('get/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->prod_servicio->get($arguments['id']));
		});

		$this->get('getByServicio/{servicio_id}[/{producto_id}]', function($request, $response, $arguments) {
			if(!isset($_SESSION)) { session_start(); }
			$arguments['producto_id'] = isset($arguments['producto_id'])? $arguments['producto_id']: 0;
			$prod_servicio = $this->model->prod_servicio->getByServicio($arguments['servicio_id'], $arguments['producto_id']);
			foreach($prod_servicio->result as &$prod) {
				$prod->precio = $this->model->prod_precio->getProdPrecio($prod->producto_id, $_SESSION['id_prod_lista_precio_default'])->result->precio;
			}
			$prod_servicio->tipo = intval($this->model->servicio->get($arguments['servicio_id'])->result->tipo)==1? 'servicio': 'paquete';

			return $response->withJson($prod_servicio);
		});

		$this->get('getAll/{pagina}/{limite}[/{producto_id}[/{servicio_id}]]', function($request, $response, $arguments) {
			$arguments['producto_id'] = isset($arguments['producto_id'])? $arguments['producto_id']: 0;
			$arguments['servicio_id'] = isset($arguments['servicio_id'])? $arguments['servicio_id']: 0;

			return $response->withJson($this->model->prod_servicio->getAll($arguments['pagina'], $arguments['limite']));
		});

		$this->post('add/', function($request, $response, $arguments) {
			require_once './core/defines.php';
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody();

			$servicio = $this->model->servicio->get($parsedBody['servicio_id'])->result;
			if(count($this->model->prod_servicio->getByServicio($parsedBody['servicio_id'], $parsedBody['producto_id'])->result) == 0) {
				$resultado = $this->model->prod_servicio->add($parsedBody);
				if($resultado->response) {
					$servicio_id = $this->model->prod_servicio->get($resultado->result)->result->servicio_id;
					$productos = $this->model->prod_servicio->getByServicio($servicio_id);
					$total = 0.0;
					foreach($productos->result as $producto) {
						$precio = $this->model->prod_precio->getProdPrecio($producto->producto_id, $_SESSION['id_prod_lista_precio_default'])->result;
						$total += $precio->precio * $producto->cantidad;
					}
	
					$servicio = $this->model->servicio->get($servicio_id)->result;
					if($servicio->tipo == 1) {
						$mano_obra = $servicio->precio - $total;
						$data = ['mano_obra' => $mano_obra];
					} elseif($servicio->tipo == 2) {
						$data = ['precio' => $total];
					}
					
					$serv = $this->model->servicio->edit($data, $servicio_id);
					if($serv->response) {
						$seg_log = $this->model->seg_log->add('Registro nuevo producto a tipo servicio', 'prod_servicio', $servicio->id);
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
						$this->response->result = $serv->result;
						$this->response->errors = $serv->errors;
						$this->response->state = $this->model->transaction->regresaTransaccion();
						$this->response->SetResponse(false, $serv->message);
					}
				} else {
					$this->response->result = $resultado->result;
					$this->response->errors = $resultado->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->SetResponse(false, $resultado->message);
				}
			} else {
				$this->response->result = false;
				$this->response->state = $this->model->transaction->regresaTransaccion();
				$this->response->SetResponse(false, "El servicio '$servicio->nombre' ya contiene al producto");
			}

			return $response->withJson($this->response);
		})->add( new MiddlewareToken() );

		$this->put('edit/{id}', function($request, $response, $arguments) {
			if(!isset($_SESSION)) { session_start(); }
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody(); $id_prod_servicio = $arguments['id']; $prod_servicio = $this->model->prod_servicio->get($arguments['id'])->result;
			$areTheSame = true; foreach($parsedBody as $field => $value) {
				if($prod_servicio->$field != $value) {
					$areTheSame = false; break;
				}
			}

			$resultado = $this->model->prod_servicio->edit($request->getParsedBody(), $arguments['id']);
			if($resultado->response || $areTheSame) { $this->response->areTheSame = $areTheSame;
				if(!$areTheSame) {
					$servicio_id = $this->model->prod_servicio->get($arguments['id'])->result->servicio_id;
					$productos = $this->model->prod_servicio->getByServicio($servicio_id);
					$total = 0.0;
					foreach($productos->result as $producto) {
						$precio = $this->model->prod_precio->getProdPrecio($producto->producto_id, $_SESSION['id_prod_lista_precio_default'])->result;
						$total += $precio->precio * $producto->cantidad;
					}
	
					$servicio = $this->model->servicio->get($servicio_id)->result;
					if($servicio->tipo == 1) {
						$mano_obra = $servicio->precio - $total;
						$data = ['mano_obra' => $mano_obra];
					} elseif($servicio->tipo == 2) {
						$data = ['precio' => $total];
					}
					
					$serv = $this->model->servicio->edit($data, $servicio_id);
					if($serv->response) {
						$seg_log = $this->model->seg_log->add('Actualización información producto de tipo servicio', 'prod_servicio', $arguments['id']);
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
						$this->response->result = $serv->result;
						$this->response->errors = $serv->errors;
						$this->response->state = $this->model->transaction->regresaTransaccion();
						$this->response->SetResponse(false, $serv->message);
					}
				}

				$this->response->SetResponse(true);
			} else {
				$this->response->result = $resultado->result;
				$this->response->errors = $resultado->errors;
				$this->response->state = $this->model->transaction->regresaTransaccion();
				$this->response->SetResponse(false, $resultado->message);
			}

			return $response->withJson($this->response);
		})->add( new MiddlewareToken() );

		$this->put('del/{id}', function($request, $response, $arguments) {
			if(!isset($_SESSION)) { session_start(); }
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			
			$servicio_id = $this->model->prod_servicio->get($arguments['id'])->result->servicio_id;
			$resultado = $this->model->prod_servicio->del($arguments['id']);
			if($resultado->response) {
				$productos = $this->model->prod_servicio->getByServicio($servicio_id);
				$total = 0.0;
				foreach($productos->result as $producto) {
					$precio = $this->model->prod_precio->getProdPrecio($producto->producto_id, $_SESSION['id_prod_lista_precio_default'])->result;
					$total += $precio->precio * $producto->cantidad;
				}

				$servicio = $this->model->servicio->get($servicio_id)->result;
				if($servicio->tipo == 1) {
					$mano_obra = $servicio->precio - $total;
					$data = ['mano_obra' => $mano_obra];
				} elseif($servicio->tipo == 2) {
					$data = ['precio' => $total];
				}

				$serv = $this->model->servicio->edit($data, $servicio_id);
				if($serv->response) {
					$seg_log = $this->model->seg_log->add('Baja producto de tipo servicio', 'prod_servicio', $arguments['id']);
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
					$this->response->result = $serv->result;
					$this->response->errors = $serv->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->SetResponse(false, $serv->message);
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