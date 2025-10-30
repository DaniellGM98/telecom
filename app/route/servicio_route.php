<?php
	use App\Lib\Response;
	use App\Lib\MiddlewareToken;
 
	/*** Grupo bajo la ruta servicio ***/
	$app->group('/servicio/', function() {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de servicio');
		})->add( new MiddlewareToken() );
		
		$this->get('find/{busqueda}', function($request, $response, $arguments) {
			return $response->withJson($this->model->servicio->find($arguments['busqueda']));
		});

		$this->get('getByNombreMD5/{nombre}[/{tipo}]', function($request, $response, $arguments) {
			$arguments['tipo'] = isset($arguments['tipo'])? $arguments['tipo']: 0;
			return $response->withJson($this->model->servicio->getByNombreMD5($arguments['nombre'], $arguments['tipo']));
		});

		$this->get('get/{id}', function($request, $response, $arguments) {
			$servicio = $this->model->servicio->get($arguments['id']);
			if($servicio->response) {
				$servicio->result->productos = $this->model->prod_servicio->getByServicio($arguments['id'])->result;
			}
			return $response->withJson($servicio);
		});

		$this->get('getAll/[{pagina}/{limite}[/{busqueda}]]', function($request, $response, $arguments) {
			$arguments['pagina'] = isset($arguments['pagina'])? $arguments['pagina']: 0;
			$arguments['limite'] = isset($arguments['limite'])? $arguments['limite']: 0;
			$arguments['busqueda'] = isset($arguments['busqueda'])? $arguments['busqueda']: 0;
			return $response->withJson($this->model->servicio->getAll($arguments['pagina'], $arguments['limite'], $arguments['busqueda']));
		});

		$this->post('add/', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody();

			if(count($this->model->servicio->getByNombreMD5(md5($parsedBody['nombre']), $parsedBody['tipo'])->result) == 0) {
				if(intval($parsedBody['tipo'])==1) { $parsedBody['mano_obra'] = $parsedBody['precio']; }
				$resultado = $this->model->servicio->add($parsedBody);
				if($resultado->response) {
					$seg_log = $this->model->seg_log->add('Alta de nuevo tipo servicio', 'servicio', $resultado->result);
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
			} else {
				$this->response->result = false;
				$this->response->state = $this->model->transaction->regresaTransaccion();
				$this->response->SetResponse(false, "El ".($parsedBody['tipo']=='1'? "servicio": "paquete")." '$parsedBody[nombre]' ya existe");
			}

			return $response->withJson($this->response);
		})->add( new MiddlewareToken() );

		$this->put('edit/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody(); $id_servicio = $arguments['id']; $servicio = $this->model->servicio->get($arguments['id'])->result;
			$areTheSame = true; foreach($parsedBody as $field => $value) { 
				if($servicio->$field != $value) { 
					$areTheSame = false; break; 
				} 
			}

			$resultado = $this->model->servicio->edit($request->getParsedBody(), $arguments['id']);
			if($resultado->response || $areTheSame) { $this->response->areTheSame = $areTheSame;
				if(!$areTheSame) {
					if(isset($parsedBody['precio']) && floatval($servicio->precio)!=floatval($parsedBody['precio'])) {
						$productos = $this->model->prod_servicio->getByServicio($id_servicio); $total = 0.0;
						foreach($productos->result as $producto) {
							$precio = $this->model->prod_precio->getProdPrecio($producto->producto_id, $_SESSION['id_prod_lista_precio_default'])->result;
							$total += $precio->precio * $producto->cantidad;
						}
		
						$servicio = $this->model->servicio->get($id_servicio)->result;
						if($servicio->tipo == 1) {
							$mano_obra = $servicio->precio - $total;
							$data = ['mano_obra' => $mano_obra];
						} elseif($servicio->tipo == 2) {
							$data = ['precio' => $total];
						}
						
						$serv = $this->model->servicio->edit($data, $id_servicio);
						if(!$serv->response) {
							$this->response->result = $serv->result;
							$this->response->errors = $serv->errors;
							$this->response->state = $this->model->transaction->regresaTransaccion();
							return $response->withJson($this->response->SetResponse(false, $serv->message));
						}
					}

					$seg_log = $this->model->seg_log->add('Actualización información tipo servicio', 'servicio', $arguments['id']);
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
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->servicio->del($arguments['id']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Baja tipo servicio', 'servicio', $arguments['id']);
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