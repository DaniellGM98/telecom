<?php
	use App\Lib\Response,
		PHPMailer\PHPMailer\PHPMailer,
		PHPMailer\PHPMailer\Exception;
	use Slim\Http\UploadedFile;
	use App\Lib\MiddlewareToken;

	require_once './core/defines.php';

	/*** Grupo bajo la ruta apartado ***/ 
	$app->group('/apartado/', function() use ($app) {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de apartado');
		});

		/**
		 * Ruta para obtener los registros por id
		 * recibe {id} del apartado
		 * regresa: arreglo con el registro que tiene el id especificado
		 */
		$this->get('get/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->apartado->get($arguments['id']));
		});

		/**
		 * Ruta para obtener los toda la informacion de un apartado
		 * refibe {id} del apartado
		 * regresa: arreglo con toda la informacion del registro que tiene el id especificado
		 */
		$this->get('getCotizacion/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->result = $this->model->apartado->get($arguments['id'])->result;
			$this->response->result->detalles = $this->model->apartado_detalle->getByApartado($arguments['id'])->result;
			$this->response->SetResponse(true);

			return $response->withJson($this->response);
		});

		/**
		 * Ruta para obtener todos los registros
		 */
		$this->get('getAll/', function($request, $response, $arguments) {
			return $response->withJson($this->model->apartado->getAll());
		});

		$this->get('sigFolio/{sucursal_id}', function($request, $response, $arguments) {
			$folio = $this->model->sucursal->getSigFolioApartado($arguments['sucursal_id'])->result;
			$folio = "A".str_pad($arguments['sucursal_id'], 3, '0', STR_PAD_LEFT).str_pad($folio, 6, '0', STR_PAD_LEFT);
			return $response->withJson($folio);
		});

		/**
		 * Ruta para obtener el listado de todas las cotizaciones hechas
		 * recibe {pagina} el numero de pagina para la cual traer la informacion
		 * recibe {limite} numero de registros por pagina
		 * recibe {estatus} 1 para cotizaciones y 2 para apartados
		 * recibe {inicio} fecha a partir de la cual buscar
		 * recibe {fin} fecha hasta la cual buscar
		 * recibe {estatus_pago} 0 todos, 1 apartados pagados completamente, 2 apartados sin terminar de pagar
		 * recibe {sucursal} sucursal
		 * recibe {cliente} id del cliente especifico o coincidencia con texto
		 * recibe {empleado_id} id del empleado empleado_id o 0 para todos
		 * recibe {producto} nombre, modelo o sku del producto
		 */
		// $this->get('listar/{pagina}/{limite}/{estatus}/{inicio}/{fin}/{estatus_pago}/{sucursal}/{cliente}/{empleado_id}/[{producto}]', function($request, $response, $arguments) {
		$this->get('listar/{pagina}/{limite}/{estatus}/{inicio}/{fin}/{estatus_pago}/{sucursal}/{empleado_id}/[{producto}]', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->result = [];
			$this->response->apartados = [];
			$this->response->total = 0;

			$contador = 0;
			$inicial = $arguments['pagina'] * $arguments['limite'];
			$final = $inicial + $arguments['limite'];

			// $cliente = $arguments['cliente'];
			// $clientes = [];
			// if(is_numeric($cliente))	$clientes[] = $cliente;
			// else {
			// 	$busquedaClientes = $this->model->cliente->find($cliente)->result;
			// 	foreach($busquedaClientes as $infoCliente) {
			// 		$clientes[] = $infoCliente->usuario_id;
			// 	}
			// }

			$arguments['producto'] = !isset($arguments['producto'])? '': $arguments['producto'];
			$productos = $this->model->producto->find($arguments['producto']);
			foreach($productos->result as $producto) {
				// foreach($clientes as $cliente) {
					// $apartados = $this->model->apartado_detalle->searchByProducto($producto->id, $arguments['inicio'], $arguments['fin'], $arguments['sucursal'], $cliente, $arguments['estatus'])->result;
					$apartados = $this->model->apartado_detalle->searchByProducto($producto->id, $arguments['inicio'], $arguments['fin'], $arguments['sucursal'], $arguments['estatus'])->result;
					foreach($apartados as $apartado) {
						if(!in_array($apartado->id, $this->response->apartados)) {
							$products = json_decode(json_encode($this->model->apartado_detalle->getByApartado($apartado->id, $arguments['estatus'])->result));
							$numProducts = sizeof($products);
							
							$this->response->apartados[] = $apartado->id;

							$status = $arguments['estatus_pago'];
							$pagado = $this->model->apartado_pago->getPaymentsSum($apartado->id)->result;
							if($status=='0' || ($status=='1' && $apartado->total-$pagado==0) || ($status=='2' && $apartado->total-$pagado>0)) {
								if($arguments['empleado_id']==0 || $apartado->empleado_id==$arguments['empleado_id']) {
									$this->response->total += 1;
									if($contador >= $inicial && $contador < $final) {
										$apartado->productos = $products;
										$this->response->result[] = $apartado;
									}
									
									$contador += 1;
								}
							}

							// $apartado->cliente = $this->model->usuario->get($apartado->cliente_id)->result;
							$apartado->venta_anticipo = $this->model->apartado_pago->getVentaAnticipo($apartado->id)->result;
						}
					}
				// }

			}

			usort($this->response->result, function($a, $b) {
				return (strtotime($b->fecha) - strtotime($a->fecha));
			});
			
			return $response->withJson($this->response->SetResponse(true));
		});

		/**
		 * Ruta para obtener el registro con el folio
		 * recibe {filtro} folio del apartado
		 */
		$this->get('searchByFolio/{filtro}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->result = [];
			$this->response->total = 0;

			$apartados = $this->model->apartado->searchByFolio($arguments['filtro'])->result;
			foreach($apartados as $apartado) {
				$apartado->productos = $this->model->apartado_detalle->getByApartado($apartado->id)->result;
				if(!in_array($apartado, $this->response->result)) {
					$this->response->total += sizeof($apartado->productos);
					$this->response->result[] = $apartado;
				}
			}
			
			return $response->withJson($this->response->SetResponse(true));
		});

		/**
		 * Ruta para agregar un nuevo registro
		 */
		$this->post('add/', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->apartado->add($request->getParsedBody());
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Alta apartado de productos', 'apartado', $resultado->result);
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

		/**
		 * Ruta para agregar una nueva cotización
		 */
		$this->post('addApartado/', function($request, $response, $arguments) {
			$this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody(); 
			$id_sucursal = $parsedBody['sucursal_id']; 
			$fecha = date('Y-m-d H:i:s');
			$corte = $this->model->sucursal->getSaldo($id_sucursal);
			if($corte['status'] == 2) $fecha = date('Y-m-d 00:00:00', strtotime('tomorrow'));

			$data = [ 'sucursal_id'=>$id_sucursal, 'cliente'=>$parsedBody['cliente'], 'empleado_id_registro'=>$parsedBody['empleado_id_registro'], 'empleado_id'=>$parsedBody['empleado_id'], 'folio'=>$parsedBody['folio'], 'fecha'=>$fecha, 'subtotal'=>$parsedBody['subtotal'], 'iva'=>$parsedBody['iva'], 'total'=>$parsedBody['total'], 'descuento'=>(isset($parsedBody['descuento'])? $parsedBody['descuento']: 0), 'status'=>2 ];
			$apartado = $this->model->apartado->add($data); if($apartado->response) { $id_apartado = $apartado->result;
				$apartado->skus = [];
				foreach($parsedBody['detalles'] as $detalle) { $producto_id = $detalle['producto_id'];
					$dataDet = [ 'apartado_id'=>$id_apartado, 'producto_id'=>$producto_id, 'cantidad'=>$detalle['cantidad'], 'costo'=>$detalle['costo'], 'importe'=>$detalle['importe'] ];
					$arrSkus = array(''); if(isset($detalle['arrImei'])) { $arrSkus = $detalle['arrImei']; unset($detalle['arrImei']); }
					foreach($arrSkus as $sku) {
						if(!isset($detalle['sku']) || strlen($detalle['sku'])>0) {
							if($sku != '') { $dataDet['sku'] = $sku; $dataDet['cantidad'] = 1; $dataDet['importe'] = $detalle['costo']; }
							else if(intval($dataDet['cantidad'] == 0)) { continue; }

							$dataDet['iva'] = floatval($dataDet['importe'])*(intval($_SESSION['iva'])/100);
							$det_apartado = $this->model->apartado_detalle->add($dataDet); if($det_apartado->response) {
								$tipo = -1; $cantidad = intval($dataDet['cantidad']); $inicial  = intval($this->model->prod_kardex->getStockSuc($id_sucursal, $producto_id)->result->final);
								$dataKardex = [ 'sucursal_id'=>$id_sucursal, 'producto_id'=>$producto_id, 'empleado_id'=>$parsedBody['empleado_id_registro'], 'fecha'=>$fecha, 'tipo'=>$tipo, 'inicial'=>$inicial, 'cantidad'=>$cantidad, 'final'=>$inicial + ($tipo * $cantidad), 'origen'=>$id_apartado, 'origen_tipo'=>4, ];
								$kardex = $this->model->prod_kardex->add($dataKardex); if($kardex->response) {
									$productoInfo = $this->model->producto->get($producto_id)->result; $final = intval($productoInfo->stock) + ($tipo * $cantidad);
									$producto = $this->model->producto->edit(['stock'=>$final], $producto_id);if(!$producto->response) {
										$producto->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($producto);}
								} else { $kardex->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($kardex); }
							} else { $det_apartado->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($det_apartado); }
						}
					}
				}

				$tipo_pago = $this->model->tipo_pago->get($parsedBody['tipo_pago_id']); $anticipo = floatval($parsedBody['anticipo']);
				if($tipo_pago->response && intval($tipo_pago->result->es_automatico)==1) {
					$data = [ 'apartado_id'=>$id_apartado, 'empleado_id'=>$parsedBody['empleado_id_registro'], 'fecha'=>$fecha, 'importe'=>$anticipo, 'saldo'=>floatval($parsedBody['total'])-$anticipo, 'tipo_pago_id'=>$parsedBody['tipo_pago_id'], 'metodo_pago'=>'PPD', 'status'=>1, ];
					$pago = $this->model->apartado_pago->add($data); if($pago->response) { $id_pago = $pago->result;
						$data = [ 'sucursal_id'=>$id_sucursal, 'empleado_id'=>$parsedBody['empleado_id'], 'empleado_id_registro'=>$parsedBody['empleado_id_registro'], 'fecha'=>$fecha, 'subtotal'=>$anticipo, 'iva'=>0, 'total'=>$anticipo, 'pagado'=>1, 'folio'=>"PA$parsedBody[folio]", 'tipo'=>2, 'apartado_pago_id'=>$id_pago ];
						$venta = $this->model->venta->add($data); if($venta->response) { $id_venta = $venta->result;
							$data = [ 'venta_id'=>$id_venta, 'producto_id'=>$_SESSION['abono_apartado'], 'origen_tipo'=>1, 'cantidad'=>1, 'costo'=>$anticipo, 'importe'=>$anticipo, 'iva'=>0, ];
							$venta_detalle = $this->model->venta_detalle->add($data); if($venta_detalle->response) {
								if(intval($tipo_pago->result->tiene_comprobante) == 1) {
									$files = $request->getUploadedFiles();
									if(isset($files['comprobante'])) {
										$data = ['comprobante' => $this->model->apartado_pago->saveImgComprobante($files['comprobante'], $id_pago)->filename];
										$pago = $this->model->apartado_pago->edit($data, $id_pago); if(!$pago->response) {
											$pago->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($pago);
										}
									}
									// else { $response = new Response(); $response->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($response->SetResponse(false, 'NO se cargo el comprobante del anticipo, se cancela la transacción')); }
								}
							} else { $venta_detalle->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($venta_detalle); }
						} else { $venta->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($venta); }
					} else { $pago->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($pago); }
				} else {
					$pago = $this->model->apartado_pago->getByApartado($id_apartado, 1)->result;
					if(count($pago)>0) { $pago = $pago[0];
						$data = [ 'sucursal_id'=>$id_sucursal, 'empleado_id'=>$parsedBody['empleado_id'], 'empleado_id_registro'=>$parsedBody['empleado_id_registro'], 'fecha'=>$fecha, 'subtotal'=>$anticipo, 'iva'=>0, 'total'=>$anticipo, 'pagado'=>1, 'folio'=>"PA$parsedBody[folio]", 'tipo'=>2, 'apartado_pago_id'=>$pago->id ];
						$venta = $this->model->venta->add($data); if($venta->response) { $id_venta = $venta->result;
							$data = [ 'venta_id'=>$id_venta, 'producto_id'=>$_SESSION['abono_apartado'], 'origen_tipo'=>1, 'cantidad'=>1, 'costo'=>$anticipo, 'importe'=>$anticipo, 'iva'=>0, ];
							$venta_detalle = $this->model->venta_detalle->add($data); if(!$venta_detalle->response) {
								$venta_detalle->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($venta_detalle);
							}
						} else { $venta->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($venta); }
					} else { $response = new Response(); $response->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($response->SetResponse(false, 'NO se registró el anticipo, se cancela la transacción')); }
				}

				$seg_log = $this->model->seg_log->add('Registro de apartado', 'apartado', $id_apartado);
				if(!$seg_log->response) {
					$seg_log->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($seg_log);
				}
			} else { $apartado->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($apartado); }

			$apartado->state = $this->model->transaction->confirmaTransaccion(); return $response->withJson($apartado);
		});

		$this->post('addCotizacion/', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody(); 
			date_default_timezone_set("America/Mexico_City"); 
			$fecha = date('Y-m-d H:i:s');

			$data = [
				'sucursal_id' => $parsedBody['sucursal_id'],
				'cliente_id' => $parsedBody['cliente_id'],
				'empleado_id_registro' => $parsedBody['empleado_id_registro'],
				'empleado_id' => $parsedBody['empleado_id'],
				'folio' => $parsedBody['folio'],
				'fecha' => $fecha,
				'subtotal' => $parsedBody['subtotal'],
				'iva' => $parsedBody['iva'],
				'total' => $parsedBody['total'],
				'descuento' => ((isset($parsedBody['descuento']) && strlen($parsedBody['descuento'])>0)? $parsedBody['descuento']: 0)
			];
			$apartado = $this->model->apartado->add($data);
			if($apartado->response) {
				foreach($parsedBody['detalles'] as $detalle) {
					$data = [ 'apartado_id'=>$apartado->result, 'producto_id'=>$detalle['producto_id'], 'cantidad'=>$detalle['cantidad'], 'costo'=>$detalle['costo'], 'importe'=>$detalle['importe'] ];
					$arrSkus = array(''); if(isset($detalle['arrImei'])) { $arrSkus = explode(',', $detalle['arrImei'][0]); unset($detalle['arrImei']); }
					foreach($arrSkus as $sku) {
						if(!isset($detalle['sku']) || strlen($detalle['sku'])>0) {
							if($sku != '') { $data['sku'] = $sku; $data['cantidad'] = 1; $data['importe'] = $data['costo']; }
							else if(intval($data['cantidad'] == 0)) { continue; }

							$data['iva'] = floatval($data['importe'])*(intval($_SESSION['iva'])/100);
							$det_apartado = $this->model->apartado_detalle->add($data);
							if(!$det_apartado->response) {
								$this->response->state = $this->model->transaction->regresaTransaccion();
								$this->response->result = $det_apartado;
								return $response->withJson($this->response->SetResponse(false, "Sucedio un error con el detalle del producto $data[producto_id]. Cancelando la operación"));
							}
						}
					}
				}
				
				$seg_log = $this->model->seg_log->add('Alta cotización', 'apartado', $apartado->result);
				if($seg_log->response) {
					$this->response->result = $apartado->result;
					$this->response->state = $this->model->transaction->confirmaTransaccion();
					$this->response->SetResponse(true);
				} else {
					$this->response->result = $seg_log->result;
					$this->response->errors = $seg_log->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->SetResponse(false, $seg_log->message);
				}
			} else {
				$this->response->state = $this->model->transaction->regresaTransaccion();
				$this->response->result = $apartado->result;
				$this->response->SetResponse(false, $apartado->message);
			}

			return $response->withJson($this->response);
		});

		$this->post('edit/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->apartado->edit($request->getParsedBody(), $arguments['id']));
		});

		$this->post('apartarPedido/', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody();

			date_default_timezone_set("America/Mexico_City"); 
			$fecha = date('Y-m-d H:i:s');
			$corte = $this->model->sucursal->getSaldo($parsedBody['sucursal_id']);
			if($corte['status'] == 2) $fecha = date('Y-m-d 00:00:00', strtotime('tomorrow'));
			$data = ['status' => 2, 'fecha' => $fecha];
			$apartado_id = $this->model->apartado->edit($data, $parsedBody['id']);
			if($apartado_id->response) {
				$continuar = true;
				$detalles = json_decode($parsedBody['detalles']);
				foreach($detalles as $detalle) {
					$stock  = $this->model->prod_kardex->getStockSuc($parsedBody['sucursal_id'], $detalle->producto_id)->result;
					$data = [
						'sucursal_id'	=> $parsedBody['sucursal_id'],
						'producto_id'	=> $detalle->producto_id,
						'empleado_id'	=> $parsedBody['usuario_id'],
						'fecha'			=> $fecha,
						'tipo'			=> -1,
						'inicial'		=> $stock->final,
						'cantidad'		=> $detalle->cantidad,
						'final'			=> $stock->final + (-1 * $detalle->cantidad),
						'origen'		=> $parsedBody['id'],
						'origen_tipo'	=> 4,
					];
					$kardex = $this->model->prod_kardex->add($data);

					if($kardex->response) {
						$data = ['stock' => $data['final']];
						$producto_id = $this->model->producto->edit($data, $detalle->producto_id);

						if(!$producto_id->response) {
							$continuar = false;
							$this->response->result = $producto_id->result;
							$this->response->errors = $producto_id->errors;
							$this->response->state = $this->model->transaction->regresaTransaccion();
							$this->response->SetResponse(false, $producto_id->message);
						}
					} else {
						$continuar = false;
						$this->response->result = $kardex->result;
						$this->response->errors = $kardex->errors;
						$this->response->state = $this->model->transaction->regresaTransaccion();
						$this->response->SetResponse(false, $kardex->message);
					}
				}

				$tipo_pago = $this->model->tipo_pago->get($parsedBody['tipo_pago_id']);
				$apartado = $this->model->apartado->get($parsedBody['id'])->result;
				if($continuar && $tipo_pago->response && intval($tipo_pago->result->es_automatico)==1) {
				// if($continuar && $parsedBody['forma_pago']!='03') {
					$pagado = $this->model->apartado_pago->getPaymentsSum($parsedBody['id'])->result;
					$data = [
						'apartado_id' => $parsedBody['id'],
						'empleado_id' => $parsedBody['usuario_id'],
						'fecha' => $fecha,
						'importe' => $parsedBody['importe'],
						'saldo' => floatval($apartado->total) - floatval($pagado),
						'tipo_pago_id' => $parsedBody['tipo_pago_id'],
						'metodo_pago' => $parsedBody['metodo_pago'],
						'status' => 1,
					];
					$pago = $this->model->apartado_pago->add($data);
					if($pago->response) { $id = $pago->result;
						$data = [ 'sucursal_id'=>$apartado->sucursal_id, 'empleado_id'=>$parsedBody['usuario_id'], 'cliente_id'=>$apartado->cliente_id, 'empleado_id_registro'=>$parsedBody['usuario_id'], 'fecha'=>$fecha, 'subtotal'=>$parsedBody['importe'], 'iva'=>0, 'total'=>$parsedBody['importe'], 'pagado'=>1, 'folio'=>"pa$apartado->folio", 'tipo'=>2, 'apartado_pago_id'=>$id ];
						$venta = $this->model->venta->add($data);
						if($venta->response) { $id_venta = $venta->result;
							$data = [ 'venta_id'=>$id_venta, 'producto_id'=>$_SESSION['abono_apartado'], 'origen_tipo'=>1, 'cantidad'=>1, 'costo'=>$parsedBody['importe'], 'importe'=>$parsedBody['importe'], 'iva'=>0, ];
							$venta_detalle = $this->model->venta_detalle->add($data);
							if($venta_detalle->response) {
								if(intval($tipo_pago->result->tiene_comprobante) == 1) {
									// if($parsedBody['tipo_pago_id'] == '02') {
									$files = $request->getUploadedFiles();
									$file = $files['comprobante'];
									$filename = $this->model->apartado_pago->saveImgComprobante($file, $id);
			
									$data = ['comprobante' => $filename->filename];
									$pago = $this->model->apartado_pago->edit($data, $id);
			
									if($pago->response) {
										$seg_log = $this->model->seg_log->add('Apartar Pedido', 'apartado', $apartado_id->result->id);
										if($seg_log->response) {
											$this->response->result = $apartado_id->result;
											$this->response->state = $this->model->transaction->confirmaTransaccion();
											$this->response->SetResponse(true);
										} else {
											$this->response->result = $seg_log->result;
											$this->response->errors = $seg_log->errors;
											$this->response->state = $this->model->transaction->regresaTransaccion();
											$this->response->SetResponse(false, $seg_log->message);
										}
									} else {
										$this->response->result = $pago->result;
										$this->response->errors = $pago->errors;
										$this->response->state = $this->model->transaction->regresaTransaccion();
										$this->response->SetResponse(false, $pago->message);
									}
								} else {
									$seg_log = $this->model->seg_log->add('Apartar Pedido', 'apartado', $apartado_id->result->id);
									if($seg_log->response) {
										$this->response->result = $apartado_id->result;
										$this->response->state = $this->model->transaction->confirmaTransaccion();
										$this->response->SetResponse(true);
									} else {
										$this->response->result = $seg_log->result;
										$this->response->errors = $seg_log->errors;
										$this->response->state = $this->model->transaction->regresaTransaccion();
										$this->response->SetResponse(false, $seg_log->message);
									}
								}
							} else {
								$this->response->result = $venta_detalle->result;
								$this->response->errors = $venta_detalle->errors;
								$this->response->state = $this->model->transaction->regresaTransaccion();
								$this->response->SetResponse(false, $venta_detalle->message);
							}
						} else {
							$this->response->result = $venta->result;
							$this->response->errors = $venta->errors;
							$this->response->state = $this->model->transaction->regresaTransaccion();
							$this->response->SetResponse(false, $venta->message);
						}
					} else {
						$this->response->result = $pago->result;
						$this->response->errors = $pago->errors;
						$this->response->state = $this->model->transaction->regresaTransaccion();
						$this->response->SetResponse(false, $pago->message);
					}
				} else if($continuar) {
					$pago = $this->model->apartado_pago->getByApartado($apartado->id, 1)->result;
					if(count($pago)>0) { $pago = $pago[0];
						$data = [ 'sucursal_id'=>$apartado->sucursal_id, 'empleado_id'=>$parsedBody['usuario_id'], 'cliente_id'=>$apartado->cliente_id, 'empleado_id_registro'=>$parsedBody['usuario_id'], 'fecha'=>$fecha, 'subtotal'=>$parsedBody['importe'], 'iva'=>0, 'total'=>$parsedBody['importe'], 'pagado'=>1, 'folio'=>"pa$apartado->folio", 'tipo'=>2, 'apartado_pago_id'=>$pago->id ];
						$venta = $this->model->venta->add($data);
						if($venta->response) { $id_venta = $venta->result;
							$data = [ 'venta_id'=>$id_venta, 'producto_id'=>$_SESSION['abono_apartado'], 'origen_tipo'=>1, 'cantidad'=>1, 'costo'=>$parsedBody['importe'], 'importe'=>$parsedBody['importe'], 'iva'=>0, ];
							$venta_detalle = $this->model->venta_detalle->add($data);
							if($venta_detalle->response) {
								$seg_log = $this->model->seg_log->add('Apartar Pedido', 'apartado', $apartado_id->result);
								if($seg_log->response) {
									$this->response->result = $apartado_id->result;
									$this->response->state = $this->model->transaction->confirmaTransaccion();
									$this->response->SetResponse(true);
								} else {
									$this->response->result = $seg_log->result;
									$this->response->errors = $seg_log->errors;
									$this->response->state = $this->model->transaction->regresaTransaccion();
									$this->response->SetResponse(false, $seg_log->message);
								}
							} else {
								$this->response->result = $venta_detalle->result;
								$this->response->errors = $venta_detalle->errors;
								$this->response->state = $this->model->transaction->regresaTransaccion();
								$this->response->SetResponse(false, $venta_detalle->message);
							}
						} else {
							$this->response->result = $venta->result;
							$this->response->errors = $venta->errors;
							$this->response->state = $this->model->transaction->regresaTransaccion();
							$this->response->SetResponse(false, $venta->message);
						}
					} else {
						$this->response->result = $apartado_id->result;
						$this->response->errors = $apartado_id->errors;
						$this->response->state = $this->model->transaction->regresaTransaccion();
						$this->response->SetResponse(false, 'NO se encontro ningún pago valido para el apartado actual');
					}
				}
			} else {
				$this->response->result = $apartado_id->result;
				$this->response->errors = $apartado_id->errors;
				$this->response->state = $this->model->transaction->regresaTransaccion();
				$this->response->SetResponse(false, $apartado_id->message);
			}
			
			return $response->withJson($this->response);
		});

		$this->post('sendCotizacion/', function($request, $response, $arguments) {
			$parsedBody = $request->getParsedBody();
			$cotizacion = $this->model->apartado->get($parsedBody['id'])->result;
			
			// require_once('venta_route.php');
			$filename = "COT_$cotizacion->folio.pdf";
			$files = [];
			$files[] = "data/cotizaciones/$filename";
			//$resMail = $this->model->usuario->sendEmail($parsedBody['correo'], 'Envio de cotizacion', $parsedBody['mensaje'], $files);
			return $response->withJson(array('error' => false, 'response' => $resMail));
		});
		
		/**
		 * Ruta para dar de baja un apartado
		 */
		$this->put('del/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody();
			
			$usuario = $parsedBody['usuario'];
			$id = $arguments['id'];
			$apartado = $this->model->apartado->get($id)->result;

			if(intval($apartado->status) > 1) {
				$pagos = $this->model->apartado_pago->getByApartado($arguments['id'], 1)->result;
				foreach($pagos as $pago) {
					$delVentaApartado = $this->model->venta->delByApartadoPago($pago->id); if(!$delVentaApartado->response) {
						$this->response->result = $delVentaApartado->result;
						$this->response->errors = $delVentaApartado->errors;
						$this->response->state = $this->model->transaction->regresaTransaccion();
						return $response->withJson($this->response->SetResponse(false, $delVentaApartado->message));
					}
				}

				$statusDeleted = 2;
				$pago = $this->model->apartado_pago->delByApartado($id, $statusDeleted);
				if($pago->response) {
					date_default_timezone_set("America/Mexico_City"); 
					$fecha = date('Y-m-d H:i:s');
					
					$detalles = $this->model->apartado_detalle->getByApartado($id, $apartado->status)->result;
					foreach($detalles as $detalle) {
						$stock  = $this->model->prod_kardex->getStockSuc($apartado->sucursal_id, $detalle->id)->result;
						$data = [
							'sucursal_id' => $apartado->sucursal_id,
							'producto_id' => $detalle->id,
							'empleado_id' => $usuario,
							'fecha' => $fecha,
							'tipo' => 1,
							'inicial' => $stock->final,
							'cantidad' => $detalle->cantidad,
							'final' => $stock->final + (1 * $detalle->cantidad),
							'origen' => $id,
							'origen_tipo' => 4
						];
						$kardex = $this->model->prod_kardex->add($data);

						if($kardex->response) {
							$data = ['stock' => $data['final']];
							$producto_id = $this->model->producto->edit($data, $detalle->id);
							if(!$producto_id->response) {
								$this->response->result = $producto_id->result;
								$this->response->errors = $producto_id->errors;
								$this->response->state = $this->model->transaction->regresaTransaccion();
								return $response->withJson($this->response->SetResponse(false, $producto_id->message));
							}
						} else {
							$this->response->result = $kardex->result;
							$this->response->errors = $kardex->errors;
							$this->response->state = $this->model->transaction->regresaTransaccion();
							return $response->withJson($this->response->SetResponse(false, $kardex->message));
						}
					}
				} else {
					$this->response->result = $pago->result;
					$this->response->errors = $pago->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					return $response->withJson($this->response->SetResponse(false, $pago->message));
				}
			}
			
			$detApartado = $this->response->detalles = $this->model->apartado_detalle->delByApartado($id);
			if($detApartado->response) {
				$apartado = $this->model->apartado->del($id);
				if($apartado->response) {
					$seg_log = $this->model->seg_log->add('Cancelar apartado', 'apartado', $apartado->result);
					if($seg_log->response) {
						$this->response->result = $apartado->result;
						$this->response->state = $this->model->transaction->confirmaTransaccion();
						$this->response->SetResponse(true);
					} else {
						$this->response->result = $seg_log->result;
						$this->response->errors = $seg_log->errors;
						$this->response->state = $this->model->transaction->regresaTransaccion();
						$this->response->SetResponse(false, $seg_log->message);
					}
				} else {
					$this->response->result = $apartado->result;
					$this->response->errors = $apartado->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->SetResponse(false, $apartado->message);
				}
			} else {
				$this->response->result = $detApartado->result;
				$this->response->errors = $detApartado->errors;
				$this->response->state = $this->model->transaction->regresaTransaccion();
				$this->response->SetResponse(false, $detApartado->message);
			}

			return $response->withJson($this->response);
		});

		/**
		 * Ruta para convertir un apartado en una venta
		 */
		$this->put('convertirVenta/{id}', function($request, $response, $arguments) {
			require_once './core/defines.php';
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			
			$apartado = $this->model->apartado->get($arguments['id'])->result;
			$apartado->detalles = $this->model->apartado_detalle->getByApartado($arguments['id'], 2)->result;
			$apartado->venta_anticipo = $this->model->apartado_pago->getVentaAnticipo($apartado->id)->result;
			$fecha = date('Y-m-d H:i:s');
			$corte = $this->model->sucursal->getSaldo($apartado->sucursal_id);
			if($corte['status'] == 2) $fecha = date('Y-m-d 00:00:00', strtotime('tomorrow'));

			$pagos = $this->model->apartado_pago->getByApartado($arguments['id'], 1)->result;
			foreach($pagos as $pago) {
				$delVentaApartado = $this->model->venta->delByApartadoPago($pago->id); if(!$delVentaApartado->response) {
					$this->response->result = $delVentaApartado->result;
					$this->response->errors = $delVentaApartado->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					return $response->withJson($this->response->SetResponse(false, $delVentaApartado->message));
				}
			}
			// $editVentaAnticipo = $this->model->venta->edit(['status'=>2], $apartado->venta_anticipo->id); if($editVentaAnticipo->response) {
			$data = [ 'sucursal_id'=>$apartado->sucursal_id, 'empleado_id'=>$apartado->empleado_id, 'empleado_id_registro'=>$_SESSION['usuario']->id, 'fecha'=>$fecha, 'subtotal'=>$apartado->subtotal, 'iva'=>$apartado->iva, 'total'=>$apartado->total, 'folio'=>$apartado->folio, 'descuento'=>$apartado->descuento, 'metodo_pago'=>'PPD', 'pagado'=>1, ];
			$venta = $this->model->venta->add($data); if($venta->response) {
				$venta_id = $venta->result;
				//$dataSalida = [ 'sucursal_id'=>$apartado->sucursal_id, 'empleado_id'=>$apartado->empleado_id, 'venta_id'=>$venta_id, 'fecha'=>$fecha, 'status'=>1, ];
				/*$prod_salida = $this->model->prod_salida->add($dataSalida); if($prod_salida->response) { $prod_salida_id = $prod_salida->result;
				}*/
				foreach($apartado->detalles as $detalle) {
					//$dataDetSalida = [ 'producto_id'=>$detalle->producto_id, 'prod_salida_id'=>$prod_salida_id, 'cantidad'=>$detalle->cantidad, ];
					//$prod_salida_detalle = $this->model->prod_salida_detalle->add($dataDetSalida);
					$data = [ 'venta_id'=>$venta_id, 'producto_id'=>$detalle->producto_id, 'cantidad'=>$detalle->cantidad, 'costo'=>$detalle->costo, 'importe'=>$detalle->importe, 'iva'=>$detalle->iva, 'sku'=>$detalle->sku, 'status'=>1 ];
					$det_venta = $this->model->venta_detalle->add($data); if($det_venta->response) {
						$stock  = $this->model->prod_kardex->getStockSuc($apartado->sucursal_id, $detalle->id)->result;
						$data = [ 'sucursal_id'=>$apartado->sucursal_id, 'producto_id'=>$detalle->id, 'empleado_id'=>$apartado->empleado_id, 'fecha'=>$fecha, 'tipo'=>1, 'inicial'=>$stock->final, 'cantidad'=>$detalle->cantidad, 'final'=>$stock->final + (1 * $detalle->cantidad), 'origen'=>$arguments['id'], 'origen_tipo'=>4 ];
						$kardex = $this->model->prod_kardex->add($data); if($kardex->response) {
							$stock = $data['final']; $data['tipo'] = -1; $data['inicial'] = $stock; $data['final'] = $stock + (-1 * $data['cantidad']); $data['origen'] = $venta->result; $data['origen_tipo'] = 2;
							$kardex = $this->model->prod_kardex->add($data); if($kardex->response) {
								$data = ['status'=>3, 'venta_id'=>$venta->result];
								$apartado_id = $this->model->apartado->edit($data, $arguments['id']); if($apartado_id->response) {
									$seg_log = $this->model->seg_log->add('Convertir apartado en venta', 'venta', $venta->result); if($seg_log->response) {
										$this->response->result = $venta->result;
										$this->response->state = $this->model->transaction->confirmaTransaccion();
										$this->response->SetResponse(true);
									} else {
										$this->response->result = $seg_log->result;
										$this->response->errors = $seg_log->errors;
										$this->response->state = $this->model->transaction->regresaTransaccion();
										$this->response->SetResponse(false, $seg_log->message);
									}
								} else {
									$this->response->result = $apartado_id->result;
									$this->response->errors = $apartado_id->errors;
									$this->response->state = $this->model->transaction->regresaTransaccion();
									$this->response->SetResponse(false, $apartado_id->message);
								}
							} else {
								$this->response->result = $kardex->result;
								$this->response->errors = $kardex->errors;
								$this->response->state = $this->model->transaction->regresaTransaccion();
								$this->response->SetResponse(false, $kardex->message);
							}
						} else {
							$this->response->result = $kardex->result;
							$this->response->errors = $kardex->errors;
							$this->response->state = $this->model->transaction->regresaTransaccion();
							$this->response->SetResponse(false, $kardex->message);
						}
					} else {
						$this->response->result = $det_venta->result;
						$this->response->errors = $det_venta->errors;
						$this->response->state = $this->model->transaction->regresaTransaccion();
						$this->response->SetResponse(false, $det_venta->message);
					}
				}
			} else {
				$this->response->result = $venta->result;
				$this->response->errors = $venta->errors;
				$this->response->state = $this->model->transaction->regresaTransaccion();
				$this->response->SetResponse(false, $venta->message);
			}
			// } else {
			// 	$this->response->result = $editVentaAnticipo->result;
			// 	$this->response->errors = $editVentaAnticipo->errors;
			// 	$this->response->state = $this->model->transaction->regresaTransaccion();
			// 	$this->response->SetResponse(false, $editVentaAnticipo->message);
			// }

			return $response->withJson($this->response);
		});
	})->add( new MiddlewareToken() );
?>