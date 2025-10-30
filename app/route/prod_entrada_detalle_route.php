<?php
	use App\Lib\Response;
	use App\Lib\MiddlewareToken;
	require_once './core/defines.php';

	/*** Grupo bajo la ruta prod_entrada_detalle ***/  
	$app->group('/prod_entrada_detalle/', function() {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de prod_entrada_detalle');
		});
		
		/*** Ruta para obtener los datos de prod_entrada_detalle por medio del ID ***/
		$this->get('get/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->prod_entrada_detalle->get($arguments['id']));
		});

		$this->get('getByProducto/{producto_id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->prod_entrada_detalle->getByProducto($arguments['producto_id']));
		});

		$this->get('getExistenciasByProducto/{producto_id}[/{sucursal_id}]', function($request, $response, $arguments) {
			$arguments['sucursal_id'] = isset($arguments['sucursal_id'])? $arguments['sucursal_id']: 0;
			return $response->withJson($this->model->prod_entrada_detalle->getExistenciasByProducto($arguments['producto_id'], $arguments['sucursal_id']));
		});
		
		/*** busca todos los detalles que coincidan con fecha, producto ***/
		$this->get('getEntradasByBuscaProd/{inicio}/{fin}/{pagina}/{limite}/{fk_sucursal}/{fk_proveedor}/{filtro}', function($request, $response, $arguments) {
			return $response->withJson($this->model->prod_entrada_detalle->getEntradasByBuscaProd($arguments['inicio'],$arguments['fin'],$arguments['pagina'],$arguments['limite'],$arguments['fk_sucursal'],$arguments['fk_proveedor'],$arguments['filtro']));
		});

		/*** busca si ya existe una entrada de producto con el sku perteneciente a la categoria ***/
		$this->get('buscarPorSku/{sku}/{categoria_id}[/{producto_id}[/{sucursal_id}]]', function($request, $response, $arguments) {
			$arguments['producto_id'] = isset($arguments['producto_id'])? $arguments['producto_id']: 0;
			$arguments['sucursal_id'] = isset($arguments['sucursal_id'])? $arguments['sucursal_id']: 0;
			return $response->withJson($this->model->prod_entrada_detalle->buscarPorSku($arguments['sku'], $arguments['categoria_id'], $arguments['producto_id'], $arguments['sucursal_id']));
		});

		/*** Ruta para buscar prod_entrada_detalle ***/
		$this->get('find/{filtro}', function($request, $response, $arguments) {
			return $response->withJson($this->model->prod_entrada_detalle->find($arguments['filtro']));
		});

		/*** Ruta para obtener los datos de los prod_entrada_detalle ***/
		$this->get('getAll/', function($request, $response, $arguments) {
			return $response->withJson($this->model->prod_entrada_detalle->getAll());
		});

		/*** Ruta para getByEntrada ***/
		$this->get('getByEntrada/{fk_prod_entrada}', function($request, $response, $arguments) {
			return $response->withJson($this->model->prod_entrada_detalle->getByEntrada($arguments['fk_prod_entrada']));
		});

		$this->get('getListaSkuDisp/{producto_id}[/{sucursal_id}[/{sku}]]', function($request, $response, $arguments) {
			$arguments['sucursal_id'] = isset($arguments['sucursal_id'])? $arguments['sucursal_id']: null;
			$arguments['sku'] = isset($arguments['sku'])? $arguments['sku']: null;

			$disponibles = $this->model->prod_entrada_detalle->getListaSkuDisp($arguments['producto_id'], $arguments['sucursal_id'], $arguments['sku']);
			$disponibles->skuDisponibles = [];
			foreach($disponibles->result as $disponible) {
				$disponible->fechaEntrada = $this->model->prod_entrada_detalle->getFechaEntradaSkuDisp($disponible->sku)->fecha;
				$disponibles->skuDisponibles[] = $disponible->sku;
			}
			// $disponibles->entradas = $this->model->prod_entrada_detalle->getByProducto($arguments['producto_id'], $arguments['sucursal_id'])->result;
			return $response->withJson($disponibles);
		});

		/*** Ruta para agregar un prod_entrada_detalle ***/
		$this->post('add/', function($request, $response, $arguments) {
			$this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody();
			$entrada = $this->model->prod_entrada->get($parsedBody['prod_entrada_id'])->result; $id_entrada = $entrada->id;
			$fecha = date('Y-m-d H:i:s');
			if(isset($parsedBody['categoria'])) unset($parsedBody['categoria']);

			$arrSku = ['']; if(isset($parsedBody['arrSku'])) { $arrSku = $parsedBody['arrSku']; unset($parsedBody['arrSku']); $parsedBody['cantidad'] = 1; $parsedBody['importe'] = $parsedBody['costo']; }
			$producto_id = $parsedBody['producto_id']; $sucursal_id = $entrada->sucursal_id; $cantidad = intval($parsedBody['cantidad']);
			foreach($arrSku as $sku) {
				if(strlen(trim($sku))>0) { $parsedBody['sku'] = trim($sku); }
				$detEntrada = $this->model->prod_entrada_detalle->add($parsedBody); if($detEntrada->response) { $id_det_entrada = $detEntrada->result;
					$tipo = 1; $inicial = intval($this->model->prod_kardex->getStockSuc($sucursal_id, $producto_id)->result->final);
					$data = [ 'sucursal_id'=>$sucursal_id, 'producto_id'=>$producto_id, 'empleado_id'=>$entrada->empleado_id, 'fecha'=>$fecha, 'tipo'=>$tipo, 'inicial'=>$inicial, 'cantidad'=>$cantidad, 'final'=>$inicial + ($tipo * $cantidad), 'origen'=>$id_entrada, 'origen_tipo'=>1, ];
					$prod_kardex_salida = $this->model->prod_kardex->add($data); if(!$prod_kardex_salida->response) {
						$prod_kardex_salida->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($prod_kardex_salida); 
					}
				} else { $detEntrada->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($detEntrada); }
			}

			$detalles = $this->model->prod_entrada_detalle->getByEntrada($id_entrada)->result;
			$total = 0; foreach($detalles as $detalle) { $total += floatval($detalle->importe); }
			$editEntrada = $this->model->prod_entrada->edit(['subtotal'=>$total, 'total'=>$total], $entrada->id); if($editEntrada->response) {
				$seg_log = $this->model->seg_log->add('Alta prod_entrada_detalle', 'prod_entrada_detalle', $id_det_entrada); if(!$seg_log->response) { 
					$seg_log->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($seg_log);
				}
			} else { $editEntrada->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($editEntrada); }

			$this->response = new Response(); $this->response->state = $this->model->transaction->confirmaTransaccion(); return $response->withJson($this->response->SetResponse(true));
		});

		/*** Ruta para modificar un prod_entrada_detalle ***/
		$this->put('edit/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->prod_entrada_detalle->edit($request->getParsedBody(), $arguments['id']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Actualización detalle entrada productos', 'prod_entrada_detalle', $arguments['id']);
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

		$this->put('del/{id}', function($request, $response, $arguments) {
			$this->model->transaction->iniciaTransaccion();
			$det_entrada_id = $arguments['id']; $infoDetEntrada = $this->model->prod_entrada_detalle->get($det_entrada_id)->result; $producto_id = $infoDetEntrada->producto_id; $cantidad = intval($infoDetEntrada->cantidad);
			$entrada_id = $infoDetEntrada->prod_entrada_id; $infoEntrada = $this->model->prod_entrada->get($entrada_id)->result; $sucursal_id = $infoEntrada->sucursal_id;
			$prodInfo = $this->model->producto->get($producto_id)->result;
			$categoria_id = $prodInfo->prod_categoria_id; $categoria = $this->model->prod_categoria->get($categoria_id)->result;
			$fecha = date('Y-m-d H:i:s');

			if(intval($categoria->tiene_sku) == 0) {
				$stock_actual = $this->model->prod_kardex->getStockSuc($sucursal_id, $producto_id);
				if(intval($stock_actual->result->final) < $cantidad) {
					$stock_actual->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($stock_actual->SetResponse(false, 'Ya no hay en inventario (Sin SKU)'));
				}
			} else {
				$sku = $infoDetEntrada->sku;
				$disp = $this->model->prod_entrada_detalle->getListaSkuDisp($producto_id, $sucursal_id, $sku);
				if(count($disp->result) == 0) {
					$disp->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($disp->SetResponse(false, 'Este SKU ya no está disponible'));
				}
			}

			$editDetalle = $this->model->prod_entrada_detalle->edit(['status'=>0], $det_entrada_id); if($editDetalle->response) {
				$detalles = $this->model->prod_entrada_detalle->getByEntrada($entrada_id)->result;
				$total = 0; foreach($detalles as $detalle) { $total += floatval($detalle->importe); }
				$dataEntrada = ['subtotal'=>$total, 'total'=>$total];
				$areTheSame = true; foreach($infoEntrada as $field => $value) { if(isset($dataEntrada[$field]) && $dataEntrada[$field]!=$value) { $areTheSame = false; break; } }
				$editEntrada = $this->model->prod_entrada->edit($dataEntrada, $entrada_id); if($editEntrada->response || $areTheSame) {
					$data = [ 'sucursal_id'=>$sucursal_id, 'empleado_id'=>$infoEntrada->empleado_id, 'fecha'=>$fecha, 'folio'=>"CE$infoEntrada->folio", ];
					$prod_salida = $this->model->prod_salida->add($data); if($prod_salida->response) { $prod_salida_id = $prod_salida->result;
						$data = [ 'producto_id'=>$producto_id, 'prod_salida_id'=>$prod_salida_id, 'cantidad'=>$cantidad, ]; if(isset($sku)) { $data['sku'] = $sku; }
						$prod_salida_detalle = $this->model->prod_salida_detalle->add($data); if($prod_salida_detalle->response) {
							$tipo = -1; $inicial = intval($this->model->prod_kardex->getStockSuc($sucursal_id, $producto_id)->result->final);
							$data = [ 'sucursal_id'=>$sucursal_id, 'producto_id'=>$producto_id, 'empleado_id'=>$infoEntrada->empleado_id, 'fecha'=>$fecha, 'tipo'=>$tipo, 'inicial'=>$inicial, 'cantidad'=>$cantidad, 'final'=>$inicial + ($tipo * $cantidad), 'origen'=>$prod_salida_id, 'origen_tipo'=>8, ];
							$prod_kardex_salida = $this->model->prod_kardex->add($data); if($prod_kardex_salida->response) {
								if(intval($categoria->tiene_sku) == 1)
									$this->model->prod_entrada_detalle->delBySku($sku);

								$stock = $this->model->producto->get($producto_id)->result->stock;
								$dataStock = ['stock' => $stock+($tipo*$cantidad)];
								$edit_kardex =$this->model->producto->edit($dataStock, $producto_id); if(!$edit_kardex->response) {
									$edit_kardex->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($edit_kardex);
								}
							} else { $prod_kardex_salida->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($prod_kardex_salida); }
						} else { $prod_salida_detalle->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($prod_salida_detalle); }
					} else { $prod_salida->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($prod_salida); }

					$editEntrada->SetResponse(true);
				} else { $editEntrada->data = ['subtotal'=>$total, 'total'=>$total]; $editEntrada->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($editEntrada); }
			} else { $editDetalle->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($editDetalle); }

			$detallesEntrada = $this->model->prod_entrada_detalle->getByEntrada($entrada_id)->result; if(count($detallesEntrada) == 0) {
				$prod_entrada = $this->model->prod_entrada->del($prod_salida_id); if($prod_entrada->response) {
					$prod_salida->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($prod_salida);
				}
			}
					
			$seg_log = $this->model->seg_log->add('Cancelación prod_entrada_detalle', 'prod_entrada_detalle', $det_entrada_id); if(!$seg_log->response) { 
				$seg_log->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($seg_log);
			}

			$editDetalle->state = $this->model->transaction->confirmaTransaccion(); return $response->withJson($editDetalle);
		})->add( new MiddlewareToken() );

		// /*** Ruta para dar de baja un prod_entrada_detalle ***/
		// $this->put('del/{id}', function($request, $response, $arguments) {
		// 	$this->response = new Response();
		// 	$this->response->state = $this->model->transaction->iniciaTransaccion();

		// 	$resultado = $this->model->prod_entrada_detalle->del($arguments['id']);
		// 	if($resultado->response) {
		// 		$seg_log = $this->model->seg_log->add('Cancelación detalle entrada productos', 'prod_entrada_detalle', $arguments['id']);
		// 		if($seg_log->response) {
		// 			$this->response->result = $resultado->result;
		// 			$this->response->state = $this->model->transaction->confirmaTransaccion();
		// 			$this->response->SetResponse(true);
		// 		} else {
		// 			$this->response->result = $seg_log->result;
		// 			$this->response->errors = $seg_log->errors;
		// 			$this->response->state = $this->model->transaction->regresaTransaccion();
		// 			$this->response->SetResponse(false, $seg_log->message);
		// 		}
		// 	} else {
		// 		$this->response->result = $resultado->result;
		// 		$this->response->errors = $resultado->errors;
		// 		$this->response->state = $this->model->transaction->regresaTransaccion();
		// 		$this->response->SetResponse(false, $resultado->message);
		// 	}

		// 	return $response->withJson($this->response);
		// });
	})->add( new MiddlewareToken() );
?>