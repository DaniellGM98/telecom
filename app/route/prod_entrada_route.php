<?php
	use App\Lib\Response;
	use App\Lib\MiddlewareToken;
 
	/*** Grupo bajo la ruta prod_entrada ***/  
	$app->group('/prod_entrada/', function() {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de prod_entrada');
		});
		
		/*** Ruta para obtener los datos de prod_entrada por medio del ID ***/
		$this->get('get/{id}', function($request, $response, $arguments) {
			$id_entrada = $arguments['id'];
			$entrada = $this->model->prod_entrada->get($id_entrada);
			if($entrada->response) {
				$entrada->result->detalles = $this->model->prod_entrada_detalle->getByEntrada($id_entrada, false)->result;
			}

			return $response->withJson($entrada);
		});

		/*** Ruta para buscar prod_entrada ***/
		$this->get('find/{busqueda}', function($request, $response, $arguments) {
			return $response->withJson($this->model->prod_entrada->find($arguments['busqueda']));
		});

		/*** Ruta para buscar prod_entrada ***/
		$this->get('buscaFolio/{folio}', function($request, $response, $arguments) {
			$entradas = $this->model->prod_entrada->buscaFolio($arguments['folio']);
			foreach ($entradas->result as $entrada) {
				$entrada->detalles = $this->model->prod_entrada_detalle->getByEntrada($entrada->id)->result;
				$entrada->fecha = date('d/m/Y', strtotime($entrada->fecha));
			}

			return $response->withJson($entradas);
		});

		/*** Ruta para obtener los datos de los prod_entrada ***/
		$this->get('getAll/{inicio}/{fin}/{pagina}/{limite}/{sucursal_id}/{proveedor_id}/{busqueda}/{cat}', function($request, $response, $arguments) {
			$idEntradas = $this->model->prod_entrada_detalle->getEntradasByBuscaProd($arguments['inicio'], $arguments['fin'], $arguments['pagina'], $arguments['limite'], $arguments['sucursal_id'], $arguments['proveedor_id'], $arguments['busqueda'], $arguments['cat']);
			$arrIdes = (count($idEntradas->result) > 0)? $idEntradas->result: [0];
			$entradas = $this->model->prod_entrada->getAll($arrIdes);
			foreach ($entradas->result as $entrada) {	
				$entrada->detalles = $this->model->prod_entrada_detalle->getByEntrada($entrada->id)->result;
				$entrada->fecha = date('d/m/Y', strtotime($entrada->fecha));
			}
			$entradas->total = $idEntradas->total;

			return $response->withJson($entradas);
		});

		$this->get('sigFolio/{sucursal_id}', function($request, $response, $arguments) {
			$folio = $this->model->sucursal->getSigFolioEntrada($arguments['sucursal_id'])->result;
			$folio = "E".str_pad($arguments['sucursal_id'], 3, '0', STR_PAD_LEFT).str_pad($folio, 6, '0', STR_PAD_LEFT);
			return $response->withJson($folio);
		});

		$this->post('getsku', function ($request, $response, $arguments) {
			$parsedBody = $request->getParsedBody();
			return $response->withJson($this->model->prod_entrada->getsku($parsedBody));
		});

		/*
		 * Método add -> agregar entrada de producto
		 * Recibe: $prov, $suc, $folio, array $detalles
		 */
		$this->post('add/', function($request, $response, $arguments) {
			require_once './core/defines.php';
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody();
			$parsedBody['empleado_id'] = $_SESSION['usuario']->id;
			$detalles = $parsedBody['detalles'];	unset($parsedBody['detalles']);
			$arrDetalles = array();	$arrKardex = array();

			$entrada = $this->model->prod_entrada->add($parsedBody);
			if($entrada->result > 0) {
				foreach($detalles as $detalle) {
					unset($detalle['nombre'], $detalle['modelo'], $detalle['tamano'], $detalle['sku'], $detalle['marca']);
					if(isset($detalle['categoria'])) unset($detalle['categoria']);
					$arrSkus = array(''); if(isset($detalle['arrImei'])) { $arrSkus = $detalle['arrImei']; unset($detalle['arrImei']); }
					$stockFinal = $this->model->prod_kardex->getStockSuc($parsedBody['sucursal_id'], $detalle['producto_id'])->result;
					$stockFinal = is_object($stockFinal) ? $stockFinal->final : 0;
					foreach($arrSkus as $sku) {
						$detalle['prod_entrada_id'] = $entrada->result;
						if($sku != '') { $detalle['sku'] = $sku; $detalle['cantidad'] = 1; } elseif(isset($detalle['sku'])) { unset($detalle['sku']); }
						$detalle['importe'] = $detalle['cantidad'] * $detalle['costo'];
						if(!isset($detalle['sku']) || strlen($detalle['sku'])>0) {
							$detalleEntrada = $this->model->prod_entrada_detalle->add($detalle);
							if($detalleEntrada->result > 0) {
								$fecha = date('Y-m-d H:i:s');
								$arrDetalles[] = $detalleEntrada->result;
								$data = [
									"empleado_id" => $parsedBody['empleado_id'],
									"producto_id" => $detalle['producto_id'],
									"sucursal_id" => $parsedBody['sucursal_id'],
									"fecha" => $fecha,
									"tipo" => 1,
									"inicial" => $stockFinal,
									"cantidad" => $detalle['cantidad'],
									"final" => $stockFinal + $detalle['cantidad'],
									"origen" => $entrada->result,
									"origen_tipo" => 1,
								];
			
								$idAddKardex = $this->model->prod_kardex->add($data)->result;
								if($idAddKardex > 0) {
									$arrKardex[] = $idAddKardex;
									$stockProd = $this->model->producto->get($detalle['producto_id'])->result->stock;
									$datos['stock'] = $stockProd + $detalle['cantidad'];
									$stockFinal = $stockFinal + $detalle['cantidad'];
			
									$updateProd = $this->model->producto->edit($datos, $detalle['producto_id'])->result;
									if($updateProd > 0)	{
										$arrKardex[] = $updateProd;
									} else {
										$this->response->result = $idAddKardex;
										$this->response->state = $this->model->transaction->regresaTransaccion();
										return $response->withJson($this->response->SetResponse(false, "No se actualizo stock del producto: $detalle[producto_id] correspondiente a la entrada: $entrada->result se cancela la transacción"));
									}
								} else {
									$this->response->result = $idAddKardex;
									$this->response->state = $this->model->transaction->regresaTransaccion();
									return $response->withJson($this->response->SetResponse(false, "No se inserto en kardex los valores correspondientes a la entrada: $entrada->result se cancela la transacción"));
								}
							} else {
								$this->response->result = $detalleEntrada->result;
								$this->response->errors = $detalleEntrada->errors;
								$this->response->state = $this->model->transaction->regresaTransaccion();
								return $response->withJson($this->response->SetResponse(false, "El detalle no fue agregado, correspondiente a la entrada: $entrada->result se cancela la transacción"));
							}
						}
					}
				}
			} else {
				$this->response->result = $entrada->result;
				$this->response->errors = $entrada->errors;
				$this->response->state = $this->model->transaction->regresaTransaccion();
				return $response->withJson($this->response->SetResponse(false, "NO se agrego la entrada: $entrada->result Se cancela la transacción"));
			}	

			$seg_log = $this->model->seg_log->add('Alta entrada productos', 'prod_entrada', $entrada->result);
			if($seg_log->response) {
				$this->response->result = $entrada->result;
				$this->response->detalles = $arrDetalles;
				$this->response->detKardex = $arrKardex;
				$this->response->state = $this->model->transaction->confirmaTransaccion();
				$this->response->SetResponse(true);
			} else {
				$this->response->result = $seg_log->result;
				$this->response->errors = $seg_log->errors;
				$this->response->state = $this->model->transaction->regresaTransaccion();
				$this->response->SetResponse(false, $seg_log->message);
			}

			return $response->withJson($this->response);
		});

		/*** Ruta para modificar un prod_entrada ***/
		$this->put('edit/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->prod_entrada->edit($request->getParsedBody(), $arguments['id']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Actualización información entrada productos', 'prod_entrada', $arguments['id']);
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

		/*
		 * Ruta cancela una entrada de producto 
		 * Recibe: el id de la entrada
		 * Regresa: Un 1 si se realizo la cancelación y 0 si no se cancelo.
		 */
		$this->put('del/{id}', function($request, $response, $arguments) {
			require_once './core/defines.php';
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$empleado_id = $_SESSION['usuario']->id;
			$arrKardex = array();

			$entrada = $this->model->prod_entrada->get($arguments['id'])->result;
			$detalles = $this->model->prod_entrada_detalle->getByEntrada($arguments['id'])->result;
			
			// $datos['status'] = 0;
			$detEnt = $this->model->prod_entrada_detalle->editByEntrada(['status'=>0], $arguments['id']);
			if($detEnt->result > 0) {
				$ent = $this->model->prod_entrada->edit(['status'=>0], $arguments['id']);
				if($ent->result > 0) {
					foreach ($detalles as $detalle) {
						$fecha = date('Y-m-d H:i:s');
						$inicial = intval($this->model->prod_kardex->getStockSuc($entrada->sucursal_id, $detalle->producto_id)->result->final);
						if($detalle->cantidad > $inicial){
							$this->response->result = 0;
							$this->response->errors = $prod->errors;
							$this->response->state = $this->model->transaction->regresaTransaccion();
							return $response->withJson($this->response->SetResponse(false, "Stock insuficiente. Uno de los productos de la entrada ya se vendió o traspaso. No se puede cancelar la entrada"));
						}else{
							$data = [
								"empleado_id" => $empleado_id,
								"producto_id" => $detalle->producto_id,
								"sucursal_id" => $entrada->sucursal_id,
								"fecha" => $fecha,
								"tipo" => -1,
								"inicial" => $inicial,
								"cantidad" => $detalle->cantidad,
								"final" => $inicial + (-1 * $detalle->cantidad),
								"origen" => $arguments['id'],
								"origen_tipo" => 1,
							];
		
							$kar = $this->model->prod_kardex->add($data);
							if($kar->result > 0) {
								$arrKardex[] = $kar->result;
								
								$stock['stock'] = $detalle->stock - $detalle->cantidad;
								$prod = $this->model->producto->edit($stock, $detalle->producto_id)->result;
								if($prod>0) {
									$arrKardex[] = $prod;
								} else {
									$this->response->result = $prod->result;
									$this->response->errors = $prod->errors;
									$this->response->state = $this->model->transaction->regresaTransaccion();
									return $response->withJson($this->response->SetResponse(false, "No se actualizo stock del producto: $detalle[producto_id] correspondiente a la entrada: $arguments[id] se cancela la transacción"));
								}
							} else {
								$this->response->result = $kar->result;
								$this->response->errors = $kar->errors;
								$this->response->state = $this->model->transaction->regresaTransaccion();
								return $response->withJson($this->response->SetResponse(false, "No se inserto en kardex los valores correspondientes a la cancelación entrada: $arguments[id] se cancela la transacción"));
							}
						}
					}
				} else {
					$this->response->result = $ent->result;
					$this->response->errors = $ent->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					return $response->withJson($this->response->SetResponse(false, "NO se elimino la entrada: $arguments[id] Se cancela la transacción"));
				}	
			} else {
				$this->response->result = $detEnt->result;
				$this->response->errors = $detEnt->errors;
				$this->response->state = $this->model->transaction->regresaTransaccion();
				return $response->withJson($this->response->SetResponse(false, "NO se elimino el detalle de la entrada: $arguments[id] Se cancela la transacción"));
			}

			$seg_log = $this->model->seg_log->add('Cancelación entrada productos', 'prod_entrada', $arguments['id']);
			if($seg_log->response) {
				$this->response->result = $ent->result;
				$this->response->detKardex = $arrKardex;
				$this->response->state = $this->model->transaction->confirmaTransaccion();
				$this->response->SetResponse(true);
			} else {
				$this->response->result = $seg_log->result;
				$this->response->errors = $seg_log->errors;
				$this->response->state = $this->model->transaction->regresaTransaccion();
				$this->response->SetResponse(false, $seg_log->message);
			}

			return $response->withJson($this->response);
		});
	})->add( new MiddlewareToken() );
?>