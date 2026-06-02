<?php
	use App\Lib\Response;
	use App\Lib\MiddlewareToken;
	require_once './core/defines.php';

	/*** Grupo bajo la ruta traspaso ***/
	$app->group('/traspaso/', function() {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de traspaso');
		})->add( new MiddlewareToken() );

		$this->get('find/{busqueda}', function($request, $response, $arguments) {
			return $response->withJson($this->model->traspaso->find($arguments['busqueda']));
		});

		$this->get('get/{id}', function($request, $response, $arguments) {
			$traspaso = $this->model->traspaso->get($arguments['id']);
			if($traspaso->response) {
				$traspaso->result->detalles = $this->model->traspaso_detalle->getByTraspaso($arguments['id'])->result;
			}

			return $response->withJson($traspaso);
		});

		$this->get('getByFolio/{folio}', function($request, $response, $arguments) {
			return $response->withJson($this->model->traspaso->getByFolio($arguments['folio']));
		});

		$this->get('buscaFolio/{folio}/{inicio}/{fin}', function($request, $response, $arguments) {
			$traspasos = $this->model->traspaso->buscaFolio($arguments['folio'], $arguments['inicio'], $arguments['fin']);
			foreach($traspasos->result as $traspaso) {
				$traspaso->detalles = $this->model->traspaso_detalle->getByTraspaso($traspaso->id)->result;
				$traspaso->fecha = date('d/m/Y', strtotime($traspaso->fecha));
			}

			return $response->withJson($traspasos);
		});

		$this->get('getAll/[{status}/{suc_origen}/{producto_id}[/{arrSku}]]', function($request, $response, $arguments) {
			$arguments['status'] = isset($arguments['status'])? $arguments['status']: 0;
			$arguments['suc_origen'] = isset($arguments['suc_origen'])? $arguments['suc_origen']: 0;
			$arguments['producto_id'] = isset($arguments['producto_id'])? $arguments['producto_id']: 0;
			$arguments['arrSku'] = isset($arguments['arrSku'])? $arguments['arrSku']: null;
			$traspasos = $this->model->traspaso->getAll($arguments['status'], $arguments['suc_origen'], $arguments['producto_id'], $arguments['arrSku']);
			if($traspasos->response && intval($arguments['status'])>0) {
				$traspasos->suma = 0;
				foreach($traspasos->result as $traspaso) {
					$traspasos->suma += $traspaso->cantidad;
				}

				$traspasos->stock = intval($this->model->prod_kardex->getStockSuc($arguments['suc_origen'], $arguments['producto_id'])->result->final);
			}

			return $response->withJson($traspasos);
		});

		$this->get('getAllBusca/{inicio}/{fin}', function($request, $response, $arguments) {
			$traspasos = $this->model->traspaso->getAllBusca($arguments['inicio'], $arguments['fin']);
			foreach($traspasos->result as $traspaso) {
				$detalles = $this->model->traspaso_detalle->getByTraspaso($traspaso->id);
				$traspaso->detalles = $detalles->result;
				$traspaso->sumaProductos = $detalles->sumaProductos;
				$traspaso->fecha = date('d/m/Y', strtotime($traspaso->fecha));
			}

			return $response->withJson($traspasos);
		});

		$this->get('sigFolio/{sucursal_id}', function($request, $response, $arguments) {
			$folio = $this->model->sucursal->getSigFolioTraspaso($arguments['sucursal_id'])->result;
			$folio = "T".str_pad($arguments['sucursal_id'], 3, '0', STR_PAD_LEFT).str_pad($folio, 6, '0', STR_PAD_LEFT);
			return $response->withJson($folio);
		});

		$this->get('getSolicitudesPendientes/{sucursal_id}', function($request, $response, $arguments) {
			$arguments['sucursal_id'] = isset($arguments['sucursal_id'])? $arguments['sucursal_id']: 0;
			$solicitudes = $this->model->traspaso->getSolicitudesPendientes($arguments['sucursal_id']);
			foreach($solicitudes->result as &$solicitud) {
				$solicitud->suc_origen = $this->model->sucursal->get($solicitud->origen)->result;
				$solicitud->suc_destino = $this->model->sucursal->get($solicitud->destino)->result;
				$solicitud->empleado = $this->model->usuario->get($solicitud->empleado_id)->result;
			}

			return $response->withJson($solicitudes);
		});

		$this->post('solicitarTraspaso/', function($request, $response, $arguments) {
			$this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody(); 
			$empleado = $_SESSION['usuario']->id; $origen = $parsedBody['origen']; $destino = $parsedBody['destino']; $folio = $parsedBody['folio']; $fecha = date('Y-m-d H:i:s');
			$detalles = $parsedBody['detalles']; unset($parsedBody['detalles']);
			$parsedBody['fecha'] = $fecha; $parsedBody['empleado_id'] = $empleado; $parsedBody['status'] = 2;

			$traspaso = $this->model->traspaso->add($parsedBody); if($traspaso->response) { $traspaso_id = $traspaso->result;
				foreach($detalles as $detalle) { $detalle['traspaso_id'] = $traspaso_id;
					$arrSku = ['']; if(isset($detalle['arrSku'])) { $arrSku = $detalle['arrSku']; unset($detalle['arrSku']); $detalle['cantidad'] = 1; }
					foreach($arrSku as $sku) { if(strlen(trim($sku))>0) { $detalle['sku'] = trim($sku); }
						$traspaso_detalle = $this->model->traspaso_detalle->add($detalle); if($traspaso_detalle) { $producto = $detalle['producto_id']; $cantidad = intval($detalle['cantidad']);
							$seg_log = $this->model->seg_log->add('Alta detalle traspaso', 'traspaso_detalle', $traspaso_detalle->result); if(!$seg_log->response) { 
								$seg_log->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($seg_log); 
							}
						} else { $traspaso_detalle->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($traspaso_detalle); }
					}
				}
			} else { $traspaso->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($traspaso); }

			$traspaso->state = $this->model->transaction->confirmaTransaccion(); return $response->withJson($traspaso);
		})->add( new MiddlewareToken() );

		/*$this->put('aceptarTraspaso/{id}', function($request, $response, $arguments) {
			ini_set('memory_limit','512M');
			$this->model->transaction->iniciaTransaccion();
			$id_traspaso = $arguments['id'];
			$infoTraspaso = $this->model->traspaso->get($id_traspaso)->result;
			$detTraspaso = $this->model->traspaso_detalle->getByTraspaso($id_traspaso)->result;
			$folio = $infoTraspaso->folio; $empleado = $infoTraspaso->empleado_id; $fecha = date('Y-m-d H:i:s');

			$apartado = $this->model->traspaso->edit(['status'=>1], $id_traspaso); if($apartado->response) {
				$data = [ 'sucursal_id'=>$infoTraspaso->origen, 'empleado_id'=>$empleado, 'fecha'=>$fecha, 'folio'=>"S$folio", ];
				$prod_salida = $this->model->prod_salida->add($data); if($prod_salida->response) { $prod_salida_id = $prod_salida->result;
					$data = [ 'empleado_id'=>$empleado, 'sucursal_id'=>$infoTraspaso->destino, 'fecha'=>$fecha, 'folio'=>"E$folio", 'subtotal'=>0, 'total'=>0, ];
					$prod_entrada = $this->model->prod_entrada->add($data); if($prod_entrada->response) { $prod_entrada_id = $prod_entrada->result;
						foreach($detTraspaso as $detalle) { $detalle->traspaso_id = $id_traspaso; $producto = $detalle->producto_id; $cantidad = $detalle->cantidad;
							$data = [ 'producto_id'=>$producto, 'prod_salida_id'=>$prod_salida_id, 'cantidad'=>$cantidad, ]; if($detalle->sku != null) { $data['sku'] = $detalle->sku; }
							$prod_salida_detalle = $this->model->prod_salida_detalle->add($data); if($prod_salida_detalle->response) { $tipo = -1; $inicial = intval($this->model->prod_kardex->getStockSuc($infoTraspaso->origen, $producto)->result->final);
								$data = [ 'sucursal_id'=>$infoTraspaso->origen, 'producto_id'=>$producto, 'empleado_id'=>$empleado, 'fecha'=>$fecha, 'tipo'=>$tipo, 'inicial'=>$inicial, 'cantidad'=>$cantidad, 'final'=>$inicial + ($tipo * $cantidad), 'origen'=>$prod_salida_id, 'origen_tipo'=>4, ];
								$prod_kardex_salida = $this->model->prod_kardex->add($data); if($prod_kardex_salida->response) {								
									$data = [ 'prod_entrada_id'=>$prod_entrada_id, 'producto_id'=>$producto, 'cantidad'=>$cantidad, 'costo'=>0, 'importe'=>0 ]; if($detalle->sku != null) { $data['sku'] = $detalle->sku; }
									$prod_entrada_detalle = $this->model->prod_entrada_detalle->add($data); if($prod_entrada_detalle->response) { $tipo = 1; $inicial = intval($this->model->prod_kardex->getStockSuc($infoTraspaso->destino, $producto)->result->final);
										$data = [ 'sucursal_id'=>$infoTraspaso->destino, 'producto_id'=>$producto, 'empleado_id'=>$empleado, 'fecha'=>$fecha, 'tipo'=>$tipo, 'inicial'=>$inicial, 'cantidad'=>$cantidad, 'final'=>$inicial + ($tipo * $cantidad), 'origen'=>$prod_entrada_id, 'origen_tipo'=>5, ];
										$prod_kardex_entrada = $this->model->prod_kardex->add($data); if($prod_kardex_entrada->response) {
											$seg_log = $this->model->seg_log->add('Acepta traspaso', 'prod_entrada_detalle', $prod_entrada_detalle->result); if(!$seg_log->response) { 
												$seg_log->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($seg_log);
											}
										} else { $prod_kardex_entrada->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($prod_kardex_entrada); }
									} else { $prod_entrada_detalle->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($prod_entrada_detalle); }
								} else { $prod_kardex_salida->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($prod_kardex_salida); }
							} else { $prod_salida_detalle->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($prod_salida_detalle); }
						}
					} else { $prod_entrada->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($prod_entrada); }
				} else { $prod_salida->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($prod_salida); }
			} else { $apartado->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($apartado); }

			$apartado->state = $this->model->transaction->confirmaTransaccion(); return $response->withJson($apartado);
		});*/

		$this->put('aceptarTraspaso/{id}', function($request, $response, $arguments) {
			ini_set('memory_limit','512M');
			$this->model->transaction->iniciaTransaccion();
			$id_traspaso = $arguments['id'];
			$infoTraspaso = $this->model->traspaso->get($id_traspaso)->result;
			error_log("ERROR 1 getByTraspaso : " . json_encode($infoTraspaso));
			$detTraspaso = $this->model->traspaso_detalle->getByTraspaso($id_traspaso)->result;
			error_log("ERROR 2 detTraspaso : " . json_encode($detTraspaso));
			$folio = $infoTraspaso->folio; $empleado = $infoTraspaso->empleado_id; $fecha = date('Y-m-d H:i:s');

			$apartado = $this->model->traspaso->edit(['status'=>1], $id_traspaso); error_log("ERROR 3 edit : " . json_encode($apartado)); if($apartado->response) {
				$data1 = [ 'sucursal_id'=>$infoTraspaso->origen, 'empleado_id'=>$empleado, 'fecha'=>$fecha, 'folio'=>"S$folio", ];
				$prod_salida = $this->model->prod_salida->add($data1); error_log("ERROR 4 prod_salida DATA 1 : " . json_encode($data1)); error_log("ERROR 4.1 prod_salida : " . json_encode($prod_salida)); if($prod_salida->response) { $prod_salida_id = $prod_salida->result;
					$data2 = [ 'empleado_id'=>$empleado, 'sucursal_id'=>$infoTraspaso->destino, 'fecha'=>$fecha, 'folio'=>"E$folio", 'subtotal'=>0, 'total'=>0, ];
					$prod_entrada = $this->model->prod_entrada->add($data2); error_log("ERROR 5 prod_entrada DATA 2 : " . json_encode($data2)); error_log("ERROR 5.1 prod_entrada : " . json_encode($prod_entrada)); if($prod_entrada->response) { $prod_entrada_id = $prod_entrada->result;
						foreach($detTraspaso as $detalle) { $detalle->traspaso_id = $id_traspaso; $producto = $detalle->producto_id; $cantidad = $detalle->cantidad;
							$data3 = [ 'producto_id'=>$producto, 'prod_salida_id'=>$prod_salida_id, 'cantidad'=>$cantidad, ]; if($detalle->sku != null) { $data3['sku'] = $detalle->sku; }
							$prod_salida_detalle = $this->model->prod_salida_detalle->add($data3); error_log("ERROR 6 prod_salida_detalle DATA 3 : " . json_encode($data3)); error_log("ERROR 6.1 prod_salida_detalle : " . json_encode($prod_salida_detalle)); if($prod_salida_detalle->response) { $tipo = -1; $inicial = intval($this->model->prod_kardex->getStockSuc($infoTraspaso->origen, $producto)->result->final);
								error_log("ERROR 7 inicial : " . json_encode($inicial));
								$data4 = [ 'sucursal_id'=>$infoTraspaso->origen, 'producto_id'=>$producto, 'empleado_id'=>$empleado, 'fecha'=>$fecha, 'tipo'=>$tipo, 'inicial'=>$inicial, 'cantidad'=>$cantidad, 'final'=>$inicial + ($tipo * $cantidad), 'origen'=>$prod_salida_id, 'origen_tipo'=>4, ];
								$prod_kardex_salida = $this->model->prod_kardex->add($data4); error_log("ERROR 8 prod_kardex_salida DATA 4 : " . json_encode($data4)); error_log("ERROR 8.1 prod_kardex_salida : " . json_encode($prod_kardex_salida)); if($prod_kardex_salida->response) {								
									$data5 = [ 'prod_entrada_id'=>$prod_entrada_id, 'producto_id'=>$producto, 'cantidad'=>$cantidad, 'costo'=>0, 'importe'=>0 ]; if($detalle->sku != null) { $data5['sku'] = $detalle->sku; }
									$prod_entrada_detalle = $this->model->prod_entrada_detalle->add($data5); error_log("ERROR 9 prod_entrada_detalle DATA 5 : " . json_encode($data5)); error_log("ERROR 9.1 prod_entrada_detalle : " . json_encode($prod_entrada_detalle)); if($prod_entrada_detalle->response) { $tipo = 1; $inicial = intval($this->model->prod_kardex->getStockSuc($infoTraspaso->destino, $producto)->result->final); error_log("ERROR 10 inicial : " . json_encode($inicial));
										$data6 = [ 'sucursal_id'=>$infoTraspaso->destino, 'producto_id'=>$producto, 'empleado_id'=>$empleado, 'fecha'=>$fecha, 'tipo'=>$tipo, 'inicial'=>$inicial, 'cantidad'=>$cantidad, 'final'=>$inicial + ($tipo * $cantidad), 'origen'=>$prod_entrada_id, 'origen_tipo'=>5, ];
										$prod_kardex_entrada = $this->model->prod_kardex->add($data6); error_log("ERROR 11 prod_kardex_entrada DATA 6 : " . json_encode($data6)); error_log("ERROR 11.1 prod_kardex_entrada : " . json_encode($prod_kardex_entrada)); if($prod_kardex_entrada->response) {
											$seg_log = $this->model->seg_log->add('Acepta traspaso', 'prod_entrada_detalle', $prod_entrada_detalle->result); error_log("ERROR 12 seg_log : " . $prod_entrada_detalle->result); error_log("ERROR 12.1 seg_log : " . json_encode($seg_log)); if(!$seg_log->response) { 
												$seg_log->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($seg_log);
											}
										} else { $prod_kardex_entrada->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($prod_kardex_entrada); }
									} else { $prod_entrada_detalle->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($prod_entrada_detalle); }
								} else { $prod_kardex_salida->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($prod_kardex_salida); }
							} else { $prod_salida_detalle->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($prod_salida_detalle); }
						}
					} else { $prod_entrada->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($prod_entrada); }
				} else { $prod_salida->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($prod_salida); }
			} else { $apartado->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($apartado); }

			$apartado->state = $this->model->transaction->confirmaTransaccion(); return $response->withJson($apartado);
		});

		$this->post('add/', function($request, $response, $arguments) {
			require_once './core/defines.php';
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody(); 
			$empleado = $_SESSION['usuario']->id; $origen = $parsedBody['origen']; $destino = $parsedBody['destino']; $folio = $parsedBody['folio']; $fecha = date('Y-m-d H:i:s');
			$detalles = $parsedBody['detalles']; unset($parsedBody['detalles']);
			$parsedBody['fecha'] = $fecha; $parsedBody['empleado_id'] = $empleado;

			$traspaso = $this->model->traspaso->add($parsedBody);
			if($traspaso->response) { $traspaso_id = $traspaso->result;
				$data = [ 'sucursal_id'=>$origen, 'empleado_id'=>$empleado, 'fecha'=>$fecha, 'folio'=>$folio, ];
				$prod_salida = $this->model->prod_salida->add($data);
				if($prod_salida->response) { $prod_salida_id = $prod_salida->result;
					$data = [ 'empleado_id'=>$empleado, 'sucursal_id'=>$destino, 'fecha'=>$fecha, 'folio'=>$folio, 'subtotal'=>0, 'total'=>0, ];
					$prod_entrada = $this->model->prod_entrada->add($data);
					if($prod_entrada->response) { $prod_entrada_id = $prod_entrada->result;
						foreach($detalles as $detalle) { $detalle['traspaso_id'] = $traspaso_id;
							$arrSku = ['']; if(isset($detalle['arrSku'])) { $arrSku = $detalle['arrSku']; unset($detalle['arrSku']); $detalle['cantidad'] = 1; }
							// print_r($arrSku);
							foreach($arrSku as $sku) { if(strlen(trim($sku))>0) { $detalle['sku'] = trim($sku); }
								$traspaso_detalle = $this->model->traspaso_detalle->add($detalle);
								// print_r($detalle);
								if($traspaso_detalle) { $producto = $detalle['producto_id']; $cantidad = intval($detalle['cantidad']);
									$data = [ 'producto_id'=>$producto, 'prod_salida_id'=>$prod_salida_id, 'cantidad'=>$cantidad, ];
									$prod_salida_detalle = $this->model->prod_salida_detalle->add($data);
									if($prod_salida_detalle->response) { $tipo = -1; $inicial = intval($this->model->prod_kardex->getStockSuc($origen, $producto)->result->final);
										$data = [ 'sucursal_id'=>$origen, 'producto_id'=>$producto, 'empleado_id'=>$empleado, 'fecha'=>$fecha, 'tipo'=>$tipo, 'inicial'=>$inicial, 'cantidad'=>$cantidad, 'final'=>$inicial + ($tipo * $cantidad), 'origen'=>$prod_salida_id, 'origen_tipo'=>4, ];
										$prod_kardex_salida = $this->model->prod_kardex->add($data);
										if($prod_kardex_salida->response) {
											$data = [ 'prod_entrada_id'=>$prod_entrada_id, 'producto_id'=>$producto, 'cantidad'=>$cantidad, 'costo'=>0, 'importe'=>0 ]; if(strlen($sku)>0) { $data['sku'] = $sku; }
											$prod_entrada_detalle = $this->model->prod_entrada_detalle->add($data);
											if($prod_entrada_detalle->response) { $tipo = 1; $inicial = intval($this->model->prod_kardex->getStockSuc($destino, $producto)->result->final);
												$data = [ 'sucursal_id'=>$destino, 'producto_id'=>$producto, 'empleado_id'=>$empleado, 'fecha'=>$fecha, 'tipo'=>$tipo, 'inicial'=>$inicial, 'cantidad'=>$cantidad, 'final'=>$inicial + ($tipo * $cantidad), 'origen'=>$prod_entrada_id, 'origen_tipo'=>5, ];
												$prod_kardex_entrada = $this->model->prod_kardex->add($data);
												if($prod_kardex_entrada->response) {
													$seg_log = $this->model->seg_log->add('Alta traspaso', 'traspaso', $traspaso_id);
													if($seg_log->response) {
														$this->response->result = $traspaso->result;
														$this->response->state = $this->model->transaction->confirmaTransaccion();
														$this->response->SetResponse(true);
													} else {
														$this->response->result = $seg_log->result;
														$this->response->errors = $seg_log->errors;
														$this->response->state = $this->model->transaction->regresaTransaccion();
														$this->response->SetResponse(false, $seg_log->message);
													}
												} else {
													$this->response->result = $prod_kardex_entrada->result;
													$this->response->errors = $prod_kardex_entrada->errors;
													$this->response->state = $this->model->transaction->regresaTransaccion();
													$this->response->SetResponse(false, $prod_kardex_entrada->message);
												}
											} else {
												$this->response->result = $prod_entrada_detalle->result;
												$this->response->errors = $prod_entrada_detalle->errors;
												$this->response->state = $this->model->transaction->regresaTransaccion();
												$this->response->SetResponse(false, $prod_entrada_detalle->message);
											}
										} else {
											$this->response->result = $prod_kardex_salida->result;
											$this->response->errors = $prod_kardex_salida->errors;
											$this->response->state = $this->model->transaction->regresaTransaccion();
											$this->response->SetResponse(false, $prod_kardex_salida->message);
										}
									} else {
										$this->response->result = $prod_salida_detalle->result;
										$this->response->errors = $prod_salida_detalle->errors;
										$this->response->state = $this->model->transaction->regresaTransaccion();
										$this->response->SetResponse(false, $prod_salida_detalle->message);
									}
								} else {
									$this->response->result = $traspaso_detalle->result;
									$this->response->errors = $traspaso_detalle->errors;
									$this->response->state = $this->model->transaction->regresaTransaccion();
									$this->response->SetResponse(false, $traspaso_detalle->message);
								}
							}
						}
					} else {
						$this->response->result = $prod_entrada->result;
						$this->response->errors = $prod_entrada->errors;
						$this->response->state = $this->model->transaction->regresaTransaccion();
						$this->response->SetResponse(false, $prod_entrada->message);
					}
				} else {
					$this->response->result = $prod_salida->result;
					$this->response->errors = $prod_salida->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->SetResponse(false, $prod_salida->message);
				}
			} else {
				$this->response->result = $traspaso->result;
				$this->response->errors = $traspaso->errors;
				$this->response->state = $this->model->transaction->regresaTransaccion();
				$this->response->SetResponse(false, $traspaso->message);
			}

			return $response->withJson($this->response);
		})->add( new MiddlewareToken() );

		$this->put('edit/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody(); $id_traspaso = $arguments['id']; $traspasoInfo = $this->model->traspaso->get($id_traspaso)->result;
			$areTheSame = true; foreach($parsedBody as $field => $value) { if($traspasoInfo->$field != $value) { 
				$areTheSame = false; break; 
			}}

			$resultado = $this->model->traspaso->edit($request->getParsedBody(), $arguments['id']); if($resultado->response || $areTheSame) { $this->response->areTheSame = $areTheSame;
				if(!$areTheSame) {
					$seg_log = $this->model->seg_log->add('Actualización información traspaso', 'traspaso', $arguments['id']);
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
			$this->model->transaction->iniciaTransaccion();
			$traspaso_id = $arguments['id']; $info = $this->model->traspaso->get($traspaso_id)->result; $origen = $info->origen; $destino = $info->destino; $empleado = $info->empleado_id; $fecha = date('Y-m-d H:i:s');
			$detalles = $this->model->traspaso_detalle->getByTraspaso($traspaso_id)->result;

			$traspaso = $this->model->traspaso->del($traspaso_id); if($traspaso->response) {
				$traspaso->bitacora = [];
				$traspaso->regresados = 0;
				$traspaso->incompletos = 0;
				if(intval($info->status) == 2) {
					$detalles = $this->model->traspaso_detalle->delByTraspaso($traspaso_id); if(!$detalles->response) {
						$detalles->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($detalles);
					}
				} else {
					$data = [ 'sucursal_id'=>$destino, 'empleado_id'=>$empleado, 'fecha'=>$fecha, 'folio'=>"CE$info->folio", ];
					$prod_salida = $this->model->prod_salida->add($data); if($prod_salida->response) { $prod_salida_id = $prod_salida->result;
						$data = [ 'empleado_id'=>$empleado, 'sucursal_id'=>$origen, 'fecha'=>$fecha, 'subtotal'=>0, 'total'=>0, 'folio'=>"CS$info->folio", ];
						$prod_entrada = $this->model->prod_entrada->add($data); if($prod_entrada->response) { $prod_entrada_id = $prod_entrada->result;
							foreach($detalles as $detalle) { $producto_id = $detalle->producto_id; $infoProducto = $this->model->producto->get($producto_id)->result; $cantidad = intval($detalle->cantidad);
								$del_detalle = $this->model->traspaso_detalle->del($detalle->id); if($del_detalle->response) {
									$categoria_id = $this->model->producto->get($producto_id)->result->prod_categoria_id; $categoria = $this->model->prod_categoria->get($categoria_id)->result;
									$infoTraspaso = $this->model->traspaso->get($traspaso_id)->result;
									if(intval($categoria->tiene_sku) == 0) {
										$stock_actual = intval($this->model->prod_kardex->getStockSuc($destino, $producto_id)->result->final);
										if($stock_actual < $cantidad) {
											if(intval($infoTraspaso->status)==1 || $regresarTraspaso=$this->model->traspaso->edit(['status'=>1], $traspaso_id)->response) {
												$regresarDetalle = $this->model->traspaso_detalle->edit(['status'=>1, 'cantidad'=>$cantidad-$stock_actual], $detalle->id); if(!$regresarDetalle->response) {
													$regresarDetalle->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($regresarDetalle);
												}
											} else { $regresarTraspaso->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($regresarTraspaso); }

											$traspaso->incompletos++;
											if($stock_actual > 0) {
												$traspaso->bitacora[] = [ 'id_producto'=>$producto_id, 'producto'=>$infoProducto->producto, 'cantidad'=>$cantidad, 'faltante'=>$cantidad-$stock_actual ];
												$traspaso->regresados++;
												$cantidad = $stock_actual;
											} else {
												$traspaso->bitacora[] = [ 'id_producto'=>$producto_id, 'producto'=>$infoProducto->producto, 'cantidad'=>$cantidad, 'faltante'=>$cantidad ];
												continue;
											}
										} else {
											$traspaso->bitacora[] = [ 'id_producto'=>$producto_id, 'producto'=>$infoProducto->producto, 'cantidad'=>$cantidad, 'faltante'=>0 ];
											$traspaso->regresados++;
										}
									} else {
										$sku = $detalle->sku;
										if(count($this->model->prod_entrada_detalle->getListaSkuDisp($producto_id, $destino, $sku)->result) == 0) {
											if(intval($infoTraspaso->status)==1 || $regresarTraspaso=$this->model->traspaso->edit(['status'=>1], $traspaso_id)->response) {
												$regresarDetalle = $this->model->traspaso_detalle->edit(['status'=>1], $detalle->id); if(!$regresarDetalle->response) {
													$regresarDetalle->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($regresarDetalle);
												}
											} else { $regresarTraspaso->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($regresarTraspaso); }

											$traspaso->incompletos++;
											$traspaso->bitacora[] = [ 'id_producto'=>$producto_id, 'producto'=>$infoProducto->producto, 'sku'=>$sku, 'cantidad'=>1, 'faltante'=>1 ];
											continue;
										} else {
											$traspaso->bitacora[] = [ 'id_producto'=>$producto_id, 'producto'=>$infoProducto->producto, 'sku'=>$sku, 'cantidad'=>1, 'faltante'=>0 ];
											$traspaso->regresados++;
										}
									}
	
									$data = [ 'producto_id'=>$producto_id, 'prod_salida_id'=>$prod_salida_id, 'cantidad'=>$cantidad, ]; if(isset($sku)) { $data['sku'] = $sku; }
									$prod_salida_detalle = $this->model->prod_salida_detalle->add($data); if($prod_salida_detalle->response) {
										$tipo = -1; $inicial = intval($this->model->prod_kardex->getStockSuc($destino, $producto_id)->result->final);
										$data = [ 'sucursal_id'=>$destino, 'producto_id'=>$producto_id, 'empleado_id'=>$empleado, 'fecha'=>$fecha, 'tipo'=>$tipo, 'inicial'=>$inicial, 'cantidad'=>$cantidad, 'final'=>$inicial + ($tipo * $cantidad), 'origen'=>$prod_salida_id, 'origen_tipo'=>6, ];
										$prod_kardex_salida = $this->model->prod_kardex->add($data); if($prod_kardex_salida->response) {
											$data = [ 'prod_entrada_id'=>$prod_entrada_id, 'producto_id'=>$producto_id, 'cantidad'=>$cantidad, 'costo'=>0, 'importe'=>0, ]; if(isset($sku)) { $data['sku'] = $sku; }
											$prod_entrada_detalle = $this->model->prod_entrada_detalle->add($data); if($prod_entrada_detalle->response) {
												$tipo = 1; $inicial = intval($this->model->prod_kardex->getStockSuc($origen, $producto_id)->result->final);
												$data = [ 'sucursal_id'=>$origen, 'producto_id'=>$producto_id, 'empleado_id'=>$empleado, 'fecha'=>$fecha, 'tipo'=>$tipo, 'inicial'=>$inicial, 'cantidad'=>$cantidad, 'final'=>$inicial + ($tipo * $cantidad), 'origen'=>$prod_entrada_id, 'origen_tipo'=>7, ];
												$prod_kardex_entrada = $this->model->prod_kardex->add($data); if(!$prod_kardex_entrada->response) {
													$prod_kardex_entrada->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($prod_kardex_entrada);
												}
											} else { $prod_entrada_detalle->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($prod_entrada_detalle); }
										} else { $prod_kardex_salida->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($prod_kardex_salida); }
									} else { $prod_salida_detalle->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($prod_salida_detalle); }
								} else { $del_detalle->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($del_detalle); }
							}

							$detallesSalida = $this->model->prod_salida_detalle->getBySalida($prod_salida_id)->result; if(count($detallesSalida) == 0) {
								$prod_salida = $this->model->prod_salida->del($prod_salida_id); if($prod_salida->response) {
									$prod_entrada = $this->model->prod_entrada->del($prod_entrada_id); if(!$prod_entrada->response) {
										$prod_entrada->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($prod_entrada);
									}
								} else { $prod_salida->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($prod_salida); }
							}
						} else { $prod_entrada->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($prod_entrada); }
					} else { $prod_salida->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($prod_salida); }
				}
			} else { $traspaso->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($traspaso); }

			$seg_log = $this->model->seg_log->add('Cancelación traspaso', 'traspaso', $traspaso_id); if(!$seg_log->response) { 
				$seg_log->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($seg_log);
			}

			$traspaso->state = $this->model->transaction->confirmaTransaccion(); 
			return $response->withJson($traspaso);
		})->add( new MiddlewareToken() );
	});
?>