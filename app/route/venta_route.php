<?php
	use App\Lib\Response,
		App\Lib\MiddlewareToken,
		PHPMailer\PHPMailer\PHPMailer,
		PHPMailer\PHPMailer\Exception,
		Envms\FluentPDO\Literal;
 
	/*** Grupo bajo la ruta venta ***/ 
	$app->group('/venta/', function() use($app) {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de venta');
		});

		$this->get('getInfoVenta/', function($request, $response, $arguments) {
			if(!isset($_SESSION)) { session_start(); }
			$info = new Response();
			$info->empleados = $this->model->empleado->getAll()->result;
			$info->clientes = $this->model->cliente->getAll()->result;
			$info->tipos_pago = $this->model->tipo_pago->getAll()->result;
			$info->categorias = $this->model->prod_categoria->getAll()->result;
			$info->proveedores = $this->model->proveedor->getAll()->result;
			$info->sucursales = $this->model->sucursal->getAll()->result;
			if(intval($_SESSION['sucursal']) > 0) {
				$info->sucursal = $this->model->sucursal->get($_SESSION['sucursal'])->result;
			}

			return $response->withJson($info);
		});

		$this->get('verificarTraspaso/{sku}/{sucursal_id}', function($request, $response, $arguments) {
			$traspasodeorigen = $this->model->venta->verificarTraspaso($arguments['sku'], $arguments['sucursal_id']);
			if(count($traspasodeorigen->result) != 0){
				$disponible= true;
			}else{
			$traspasoDestino = $this->model->venta->verificarSucursal($arguments['sku'], $arguments['sucursal_id']);
			if($traspasoDestino->response){
					$disponible = false;
					}
					else{
						$disponible = true;
					}
			}
			return $response->withJson(array('disponible'=>$disponible));
		});

		/*** Ruta para obtener los datos de venta por medio del ID ***/
		$this->get('get/{id}', function($request, $response, $arguments) {
			$this->response = $this->model->venta->get($arguments['id']);
			$this->response->detalle = $this->model->venta_detalle->getByVenta($arguments['id'])->result;
			$this->response->pagos = $this->model->venta_pago->getByVenta($arguments['id'])->result;
			$pagado = 0;
			foreach($this->response->pagos as $pago) {
				if($pago->status == 1) {
					$pagado += $pago->importe;
				}
			}
			$this->response->pagado = $pagado;

			return $response->withJson($this->response);
		});

		/*** Ruta para buscar venta ***/
		$this->get('find/{filtro}', function($request, $response, $arguments) {  
			return $response->withJson($this->model->venta->find($arguments['filtro']));
		});

		/* 
		 * Buscar venta por folo de venta 
		 * Autor: Angel Gabriel Ramirez Alva
		 * Fecha: 25 Octubre 2019
		 */
		$this->get('buscaFolio/{folio}', function($request, $response, $arguments) {
			$ventas = $this->model->venta->buscaFolio($arguments['folio']);
			foreach($ventas->result as $venta) {
				$venta->detalles = $this->model->venta_detalle->getByVenta($venta->id)->result;
				$venta->fecha = date('d/m/Y', strtotime($venta->fecha));
				$venta->hora = date('H:i:s', strtotime($venta->fecha));
			}
			$ventas->suma = number_format($venta->total,2);
			return $response->withJson($ventas);
		});

		/* 
		 * Buscar venta por cliente 
		 * Recibe filtro: nombre, apellido, rfc, razón social
		 * Autor: Angel Gabriel Ramirez Alva
		 * Fecha: 25 Octubre 2019
		 */
		$this->get('buscaVentaByCliente/{filtro}', function($request, $response, $arguments) {  
			$ventas = $this->model->cliente->buscaVentaByCliente($arguments['filtro']);
			foreach($ventas->result as $venta) {
				$venta->detalles = $this->model->venta_detalle->getByVenta($venta->id)->result;
				$venta->fecha = date('d/m/Y', strtotime($venta->fecha));
			}

			return $response->withJson($ventas);
		});

		$this->get('rptVentasVendedor/{inicio}/{fin}/{sucursal_id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->venta->rptVentasVendedor($arguments['inicio'], $arguments['fin'], $arguments['sucursal_id']));
		});

		$this->get('rptTelefonosVendedor/{inicio}/{fin}/{sucursal_id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->venta->rptTelefonosVendedor($arguments['inicio'], $arguments['fin'], $arguments['sucursal_id']));
		});

		$this->get('rptVentasCategoria/{inicio}/{fin}/{sucursal_id}/{empleado_id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->venta->rptVentasCategoria($arguments['inicio'], $arguments['fin'], $arguments['sucursal_id'], $arguments['empleado_id']));
		});

		/* Ruta para obtener las ventas por producto 
		 * {inicio}: fecha de inicio de operaciones que quieres obtener 
		 * {fin}: Fecha de fin de operaciones que quieres obtener
		 */
		$this->get('getVentasByProducto/{inicio}/{fin}/{suc}/{cat}', function($request, $response, $arguments) {
			ini_set('memory_limit', '64M');
			$ventas = $this->model->venta->getVentasByProducto($arguments['inicio'], $arguments['fin'], $arguments['suc']);
			// $total = 0;
			// $arrVentas = array();
			// foreach($ventas->result as $venta) {
			// 	$venta->detalles = $this->model->venta_detalle->getByVenta($venta->id)->result;
			// 	if($arguments['cat'] > 0){
			// 		$existeCat = false;
			// 		foreach ($venta->detalles as $det) {
			// 			if($det->prod_categoria_id == $arguments['cat']){
			// 				$existeCat = true; break;
			// 			}
			// 		}
			// 	}else{ $existeCat = true; }
			// 	if($existeCat){
			// 		$venta->fecha = date('d/m/Y H:i:s', strtotime($venta->fecha));
			// 		$total += floatval($venta->total);
			// 		$arrVentas[] = $venta;
			// 	}
			// }
			
			// return $response->withJson(array('ventas' => $ventas, 'total' => number_format($total, 2)));
			return $response->withJson(array( 'total' => number_format(array_reduce($ventas->result, function($suma, $venta){return $suma += $venta->total; })), 'result' => $ventas->result));
		});

		/* Ruta para obtener las ventas por vendedor 
		 * {inicio}: fecha de inicio de operaciones que quieres obtener 
		 * {fin}: Fecha de fin de operaciones que quieres obtener
		 * {empleado_id}: empleado_id de la tabla venta
		 */
		$this->get('getVentasByVendedor/{inicio}/{fin}/{empleado_id}/{suc}/{cat}', function($request, $response, $arguments) {
			ini_set('memory_limit', '64M');
			$ventas = $this->model->venta->getVentasByVendedor($arguments['inicio'], $arguments['fin'], $arguments['empleado_id'], $arguments['suc']);
			$total = 0;
			$arrVentas = array();
			foreach($ventas->result as $venta) {
				$venta->detalles = $this->model->venta_detalle->getByVenta($venta->id)->result;
				if($arguments['cat'] > 0){
					$existeCat = false;
					foreach ($venta->detalles as $det) {
						if($det->prod_categoria_id == $arguments['cat']){
							$existeCat = true; break;
						}
					}
				}else{ $existeCat = true; }
				if($existeCat){
					$venta->fecha = date('d/m/Y H:i:s', strtotime($venta->fecha));
					$total += floatval($venta->total);
					$arrVentas[] = $venta;
				}
			}
			
			return $response->withJson(array('ventas' => $arrVentas, 'total' => number_format($total, 2)));
		});

		/*** Ruta para obtener los datos de VENTAS ***/
		$this->get('getAll/{inicio}/{fin}/{pagina}/{limite}/{sucursal_id}/{cliente_id}/{filtro}', function($request, $response, $arguments) {
			ini_set('memory_limit', '64M');
			$idVentas = $this->model->venta_detalle->getVentasByBuscaProd($arguments['inicio'], $arguments['fin'], $arguments['pagina'], $arguments['limite'], $arguments['sucursal_id'], $arguments['cliente_id'], $arguments['filtro']);
			$arrIdes = (count($idVentas->result) > 0)? $idVentas->result: [0];
			$ventas = $this->model->venta->getAll($arrIdes);
			foreach($ventas->result as $venta) {
				$venta->detalles = $this->model->venta_detalle->getByVenta($venta->id)->result;
				$venta->hora = date('H:i:s', strtotime($venta->fecha));
				$venta->fecha = date('Y/m/d', strtotime($venta->fecha));
			}
			$ventas->total = $idVentas->total;

			$ventas->suma = number_format($this->model->venta->getTotalVentasIn($arguments['inicio'], $arguments['fin'], $arguments['sucursal_id']),2);
			
			return $response->withJson($ventas);
		});

		$this->get('getAllBusca/{inicio}/{fin}/{pagina}/{limite}/{sucursal_id}/{cliente_id}/{filtro}', function($request, $response, $arguments) {
			$ventas = $this->model->venta->getAllBusca($arguments['inicio'], $arguments['fin'], $arguments['pagina'], $arguments['limite'], $arguments['sucursal_id'], $arguments['cliente_id'], $arguments['filtro']);
			// foreach($ventas->result as $venta) {
			// 	$cliente = $this->model->cliente->get($venta->cliente_id)->result;
			// 	// $venta->mail = $cliente->correo;
			// 	$venta->detalles = $this->model->venta_detalle->getByVenta($venta->id)->result;
			// 	$venta->fecha = date('d/m/Y', strtotime($venta->fecha));
			// }
			// $ventas->total = $idVentas->total;
			
			return $response->withJson($ventas);
		});
		
		/*** Ruta para obtener el total de las ventas realizadas durante un mes */
		$this->get('getTotalVentas/[{month}/{year}]', function($request, $response, $arguments) {
			$arguments['month'] = isset($arguments['month'])? $arguments['month']: null;
			$arguments['year'] = isset($arguments['year'])? $arguments['year']: null;
			return $response->withJson($this->model->venta->getTotalVentas($arguments['month'], $arguments['year']));
		});

		/*
		 * RECIBE: $producto_id y $sucursal_id
		 * REGRESA: nombre y precios de lista
		 */
		$this->get('getPrecio/{producto_id}/{sucursal_id}', function($request, $response, $arguments) {
			$arrListas = array();
			$count = 0;
			$listas = $this->model->prod_precio->getPrecio($arguments['producto_id'], $arguments['sucursal_id']);
			foreach($listas->result as $lista) {
				$d_listas = $this->model->prod_lista_precio->getByOrigen($lista->id, $arguments['sucursal_id']);
				foreach($d_listas->result as $d_lista) {
					$arrListas[$count]['nombre'] = $d_lista->nombre;
					$arrListas[$count]['precio'] = $lista->precio * (1 - ($d_lista->descuento / 100));
					$count++;
				}
				$arrListas[] = $lista;
				unset($lista->id);
				$count++;
			}
			$result = [
				'stock' => $this->model->prod_kardex->getStockSuc($arguments['sucursal_id'], $arguments['producto_id'])->result->final,
				'listas' => $arrListas
			];

			return $response->withJson($result);
		});

		/*** Ruta para agregar una venta ***/
		$this->post('add/', function($request, $response, $arguments) use($app) {
			require_once './core/defines.php';
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody();
			$parsedBody['empleado_id_registro'] = $_SESSION['usuario']->id;

			$fecha = date('Y-m-d H:i:s');
			$corte = $this->model->sucursal->getSaldo($parsedBody['sucursal_id']);
			if($corte['status'] == 2) $fecha = date('Y-m-d 00:00:00', strtotime('tomorrow'));
			$parsedBody['fecha'] = $fecha;
			$detalles = $parsedBody['detalles']; unset($parsedBody['detalles']);
			$arrDetalles = array();
			$tipo = -1;

			$venta = $this->model->venta->add($parsedBody); if($venta->result > 0) { $venta_id = $venta->result;
				$this->response->venta = $venta;
				$data = [ 'sucursal_id'=>$parsedBody['sucursal_id'], 'empleado_id'=>$parsedBody['empleado_id'], 'venta_id'=>$venta_id, 'fecha'=>$parsedBody['fecha'], 'status'=>0, 'folio'=>"V$parsedBody[folio]", ];
				$prod_salida = $this->model->prod_salida->add($data); if($prod_salida->response) { $prod_salida_id = $prod_salida->result; $mano_obra = 0;
					foreach($detalles as $detalle) { $detalle['venta_id'] = $venta_id;
						if(isset($detalle['productos'])) { $productos = $detalle['productos']; unset($detalle['productos']); }
						$tipo = $detalle['tipo']; unset($detalle['tipo']);
						$detalle['iva'] = floatval($detalle['importe']) * intval($_SESSION['iva']) / 100;
						
						$arrSkus = array(''); if(isset($detalle['arrImei'])) { $arrSkus = $detalle['arrImei']; unset($detalle['arrImei']); }
						foreach($arrSkus as $sku) {
							$sku = trim($sku);
							if(!isset($detalle['sku']) || strlen($detalle['sku'])>0) {
								// print_r($detalle);
								if($sku != '') { $detalle['sku'] = $sku; $detalle['cantidad'] = 1; $detalle['importe'] = intval($detalle['cantidad'])*floatval($detalle['costo']); $detalle['iva'] = floatval($detalle['importe'])*(intval($_SESSION['iva'])/100); }
								$det_venta = $this->model->venta_detalle->add($detalle); if($det_venta->result > 0) {
									if($tipo == 'producto') {
										$data = [ 'producto_id'=>$detalle['producto_id'], 'prod_salida_id'=>$prod_salida_id, 'cantidad'=>$detalle['cantidad'], ]; if(isset($detalle['sku'])) { $data['sku'] = $detalle['sku']; }
										$prod_salida_detalle = $this->model->prod_salida_detalle->add($data); if(!$prod_salida_detalle->response) {
											$this->response->result = $prod_salida_detalle->result;
											$this->response->errors = $prod_salida_detalle->errors;
											$this->response->state = $this->model->transaction->regresaTransaccion();
											return $response->withJson($this->response->SetResponse(false, "El detalle no fue agregado, correspondiente a la salida: $prod_salida_id se cancela la transacción"));
										}
									} else {
										if(isset($productos)) {
											foreach($productos as $producto) {
												$data = [ 'producto_id'=>$producto['producto_id'], 'prod_salida_id'=>$prod_salida_id, 'cantidad'=>$producto['cantidad'],]; if(isset($detalle['sku'])) { $data['sku'] = $detalle['sku']; }
												$prod_salida_detalle = $this->model->prod_salida_detalle->add($data); if(!$prod_salida_detalle->response) {
													$this->response->result = $prod_salida_detalle->result;
													$this->response->errors = $prod_salida_detalle->errors;
													$this->response->state = $this->model->transaction->regresaTransaccion();
													return $response->withJson($this->response->SetResponse(false, "El detalle no fue agregado, correspondiente a la salida: $prod_salida_id se cancela la transacción"));
												}
											}
										}
										if($tipo == 'servicio') { $mano_obra += floatval($this->model->servicio->get($detalle['producto_id'])->result->mano_obra); }
									}
								} else {
									$this->response->result = $det_venta->result;
									$this->response->errors = $det_venta->errors;
									$this->response->state = $this->model->transaction->regresaTransaccion();
									return $response->withJson($this->response->SetResponse(false, "El detalle no fue agregado, correspondiente a la venta: $venta_id se cancela la transacción"));
								}
							}
						}
					}

					if($mano_obra != 0) {
						$edit_venta = $this->model->venta->edit(['mano_obra'=>$mano_obra], $venta_id); if(!$edit_venta->response) {
							$this->response->result = $edit_venta->result;
							$this->response->errors = $edit_venta->errors;
							$this->response->state = $this->model->transaction->regresaTransaccion();
							return $response->withJson($this->response->SetResponse(false, "La mano de obra no fue actualizada, correspondiente a la venta: $venta_id se cancela la transacción"));
						}
					}

					$seg_log = $this->model->seg_log->add('Registro venta', 'venta', $venta_id);
					if($seg_log->response) {
						$this->response->result = $venta->result;
						$this->response->detalles = $arrDetalles;
						$this->response->state = $this->model->transaction->confirmaTransaccion();
						$this->response->SetResponse(true);
					} else {
						$this->response->result = $seg_log->result;
						$this->response->errors = $seg_log->errors;
						$this->response->state = $this->model->transaction->regresaTransaccion();
						$this->response->SetResponse(false, $seg_log->message);
					}
				} else {
					$this->response->result = $prod_salida->result;
					$this->response->errors = $prod_salida->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					return $response->withJson($this->response->SetResponse(false, "NO se realizo la prod_salida: $prod_salida->result Se cancela la transacción"));
				}
			} else {
				$this->response->result = $venta->result;
				$this->response->errors = $venta->errors;
				$this->response->state = $this->model->transaction->regresaTransaccion();
				return $response->withJson($this->response->SetResponse(false, "NO se realizo la venta: $venta->result Se cancela la transacción"));
			}

			return $response->withJson($this->response);
		});
		
		$this->get('importVentas/', function($request, $response, $arguments){
			ini_set('max_execution_time', 0);
			$time_start = microtime(true); 
			$ultimo = $this->db->from('import')->where('tabla', 'venta')->fetch();
			$registros = $this->dbOld
				->from('venta')
				->where('fecha > ?', new Literal($ultimo->fecha))
				->where('id_venta > '.$ultimo->ultimo)
				->where('fk_id_sucursal > 0')
				->orderBy('id_venta')
				->limit('10000')
				->fetchAll();
			/*echo '<pre>';
			print_r($registros);
			echo '</pre>';
			exit(0);*/
			$user = 0; $suc = 0; $idEntrada = 0;
			$entradas = 0; $productos = 0;
			$noUser = array(); $noProd = array(); $noSuc = array();
			echo '<pre>';
			foreach ($registros as $reg) {
				$detalles = $this->dbOld->from('det_venta')->select(null)->select('*, (SELECT fk_id_producto FROM inventario WHERE codigo = fk_codigo) AS producto_id')->where('fk_id_venta', $reg->id_venta)->fetchAll();
				
				//print_r($reg);
				//print_r($detalles);
				
				if(count($detalles) > 0){
					$existeSuc = $this->db->from('sucursal')->where('id',$reg->fk_id_sucursal)->fetch();
					if(is_object($existeSuc)){
						$sucursal = $reg->fk_id_sucursal;
					}else{
						if(!in_array($reg->fk_id_sucursal, $noSuc)) $noSuc[] = $reg->fk_id_sucursal;
						$sucursal = 4;
					}
					$existeUser = $this->db->from('usuario')->where('id',$reg->fk_id_usuario)->fetch();
					if(is_object($existeUser)){
						$usuario = $reg->fk_id_usuario;
					}else{
						$usuario = 1;
						if(!in_array($reg->fk_id_usuario, $noUser)) $noUser[] = $reg->fk_id_usuario;
					}

					$dataEntrada = array(
						'empleado_id' => $usuario, 
						'sucursal_id' => $sucursal, 
						'fecha' => $reg->fecha.'', 
						//'folio' => $reg->fk_id_sucursal.date('Ymd'),
						'folio' => $reg->identificador,
						'subtotal' => 0, 
						'total' => 0
					);
					$entrada = $this->model->prod_entrada->add($dataEntrada);
					if($entrada->result > 0) {
						$idEntrada = $entrada->result;
						$entradas++;
					}


					$dataVenta = array(
						'id' => $reg->id_venta, 
						'empleado_id' => $usuario, 
						'empleado_id_registro' => $usuario, 
						'sucursal_id' => $sucursal, 
						'cliente_id' => 7,
						'fecha' => $reg->fecha.'', 
						'pagado' => 1,
						'folio' => $reg->identificador,
						'subtotal' => $reg->total, 
						//'descuento' => 0,
						'iva' => $reg->total,
						'total' => 0
					);
					$venta = $this->model->venta->add($dataVenta);
					if($venta->result > 0) {
						$idVenta = $venta->result;
					}
					$dataSalida = array(
						'sucursal_id'=>$sucursal, 
						'empleado_id'=>$usuario, 
						'venta_id'=>$idVenta, 
						'fecha'=>$reg->fecha.'', 
					);
					$salida = $this->model->prod_salida->add($dataSalida);
					if($salida->result > 0) {
						$idSalida = $salida->result;
					}

					foreach ($detalles as $det) {
						$existeProd = $this->db->from('producto')->where('id',$det->producto_id)->fetch();
						if(is_object($existeProd)){
							$dataDetalle = array(
								'prod_entrada_id' => $idEntrada, 
								'producto_id' => $det->producto_id,
								'cantidad' => 1,
								'costo' => 0,
								'importe' => 0, 
								'sku' => $det->fk_codigo,
							);
							$detalleEntrada = $this->model->prod_entrada_detalle->add($dataDetalle);
							//echo json_encode($detalleEntrada->errors);
							//exit(0);

							$dataDetalleVenta = array(
								'id' => $det->id_det_venta, 
								'venta_id' => $idVenta, 
								'producto_id' => $det->producto_id,
								'cantidad' => 1,
								'costo' => $det->precio,
								'importe' => $det->precio, 
								'iva' => 0, 
								'sku' => $det->fk_codigo, 
								'origen_tipo' => 1, 
							);
							$detalleVenta = $this->model->venta_detalle->add($dataDetalleVenta);

							$dataDetalleSalida = array(
								'prod_salida_id' => $idSalida, 
								'producto_id' => $det->producto_id,
								'cantidad' => 1,
								'sku' => $det->fk_codigo,
							);
							$detalleSalida = $this->model->prod_salida_detalle->add($dataDetalleSalida);
							if($detalleEntrada->result > 0 && $detalleSalida->result > 0) {
								$dataKardex = [
									"empleado_id" => $usuario,
									"producto_id" => $det->producto_id,
									"sucursal_id" => $sucursal,
									"fecha" => $reg->fecha.'',
									"tipo" => 1,
									"inicial" => 0,
									"cantidad" => 1,
									"final" => 1,
									"origen" => $idEntrada,
									"origen_tipo" => 1,
								];

								$dataKardex = [
									"empleado_id" => $usuario,
									"producto_id" => $det->producto_id,
									"sucursal_id" => $sucursal,
									"fecha" => $reg->fecha.'',
									"tipo" => -1,
									"inicial" => 1,
									"cantidad" => 1,
									"final" => 0,
									"origen" => $idVenta,
									"origen_tipo" => 2,
								];
								$productos++;
							}
							print_r($reg);
						}else{
							if(!in_array($det->producto_id, $noProd)) $noProd[] = $det->producto_id;
						}
						
					}
					$this->db->update('import', array('ultimo' => $reg->id_venta, 'fecha' => $reg->fecha))->where('tabla', 'venta')->execute();
				}

				

			}
			$time_end = microtime(true);
			$execution_time = ($time_end - $time_start);
			echo '<hr>';
			echo "Se insertaron $productos productos en $entradas entradas en $execution_time segundos<hr>";
			echo count($noUser).' usuarios no registrados';
			print_r($noUser);

			echo '<hr>';
			echo count($noProd).' productos no registrados';
			print_r($noProd);

			echo '<hr>';
			echo count($noSuc).' sucursales no registradas';
			print_r($noSuc);
			echo '</pre>';
			exit(0);
		});

		$this->post('finalizar/{id}', function($request, $response, $arguments) {
			require_once './core/defines.php';
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody();
			$info_venta = $this->model->venta->get($arguments['id'])->result;
			
			$salida = $this->model->prod_salida->getByVenta($arguments['id'], 0); $info_prod_salida = $salida->result;
			// $this->response->salida = $salida;
			// $this->response->info_prod_salida = $salida->result;
			// $this->response->prod_salida = $salida[0];
			// print_r($salida);
			if($salida->response && count($salida->result)>0) {
				$venta = $this->model->venta->edit(['pagado' => $parsedBody['pagado']], $arguments['id']);
				if($info_venta->pagado==$parsedBody['pagado'] || $venta->response) {
					foreach($info_prod_salida as $prod_salida) {
						$prod_salida = $this->model->prod_salida->edit(['status' => 1], $prod_salida->id);
						if(!$prod_salida->response) {
							$this->response->result = $prod_salida->result;
							$this->response->errors = $prod_salida->errors;
							$this->response->state = $this->model->transaction->regresaTransaccion();
							return $response->withJson($this->response->SetResponse(false, $prod_salida->message));
						}
					}
					
					$detalles = $this->model->prod_salida_detalle->getBySalida($info_prod_salida[0]->id)->result;
					foreach($detalles as $detalle) {
						$stockFinal = $this->model->prod_kardex->getStockSuc($info_venta->sucursal_id, $detalle->producto_id)->result->final;
						$fecha = date('Y-m-d H:i:s');
						$tipo = -1;
						$data = [
							"sucursal_id" => $info_venta->sucursal_id,
							"producto_id" => $detalle->producto_id,
							"empleado_id" => $info_venta->empleado_id,
							"fecha" => $fecha,
							"tipo" => $tipo,
							"inicial" => $stockFinal,
							"cantidad" => $detalle->cantidad,
							"final" => $stockFinal + ($tipo * $detalle->cantidad),
							"origen" => $arguments['id'],
							"origen_tipo" => 2,
						];
	
						$kardex = $this->model->prod_kardex->add($data); if($kardex->response) {
							$stockProd = $this->model->producto->get($detalle->producto_id)->result->stock;
							$datos['stock'] = $stockProd + ($tipo * $detalle->cantidad);
							$prod = $this->model->producto->edit($datos, $detalle->producto_id);
							if(!$prod->response) {
								$this->response->result = $prod->result;
								$this->response->errors = $prod->errors;
								$this->response->state = $this->model->transaction->regresaTransaccion();
								return $response->withJson($this->response->SetResponse(false, "No se actualizo stock del producto: $detalle->producto_id correspondiente a la entrada: $arguments[id] se cancela la transacción"));
							}
						} else {
							$this->response->result = $kardex->result;
							$this->response->errors = $kardex->errors;
							$this->response->state = $this->model->transaction->regresaTransaccion();
							return $response->withJson($this->response->SetResponse(false, "No se inserto en kardex los valores correspondientes a la entrada: $arguments[id] se cancela la transacción"));
						}
					}
				} else {
					$this->response->result = $venta->result;
					$this->response->errors = $venta->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					return $response->withJson($this->response->SetResponse(false, "No se actualizo la información de la venta: $arguments[id] se cancela la transacción"));
				}
			}

			$seg_log = $this->model->seg_log->add('Venta de productos, descuento de stock', 'venta', $arguments['id']);
			if($seg_log->response) {
				// $this->response->result = $resultado->result;
				$this->response->state = $this->model->transaction->confirmaTransaccion();
				$this->response->SetResponse(true);
			} else {
				$this->response->result = $seg_log->result;
				$this->response->errors = $seg_log->errors;
				$this->response->state = $this->model->transaction->regresaTransaccion();
				$this->response->SetResponse(false, $seg_log->message);
			}

			return $response->withJson($this->response->SetResponse(true));
		});

		/*** Ruta para modificar un venta ***/
		$this->put('edit/{id}', function($request, $response, $arguments) {
			require_once './core/defines.php';
			$this->response = new Response();
			$this->response->state = $this->model->transaction->comienzaTransaccion();

			$venta = $this->model->venta->edit($request->getParsedBody(), $arguments['id']);
			if($venta->response) {
				$seg_log = $this->model->seg_log->add('Actualización venta', 'venta', $arguments['id']);
				if($seg_log->response) {
					$this->response->result = $venta->result;
					$this->response->state = $this->model->transaction->confirmaTransaccion();
					$this->response->SetResponse(true, $venta->message);
				} else {
					$this->response->result = $seg_log->result;
					$this->response->errors = $seg_log->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->SetResponse(false, $seg_log->message);
				}
			}

			return $response->withJson($this->response);
		});

		/* 
		 * Ruta cancela una venta de producto 
		 * Recibe: el id de la venta
		 * Regresa: Un 1 si se realizo la cancelación y 0 si no se cancelo.
		 */
		$this->put('del/{id}', function($request, $response, $arguments) {
			require_once './core/defines.php';
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody();
			$tipo = 1;
			$arrKardex = array();
			$empleado_id_registro = $_SESSION['usuario']->id;

			$venta = $this->model->venta->get($arguments['id'])->result;
			$detalles = $this->model->venta_detalle->getByVenta($arguments['id'])->result;
			
			$datos['status'] = 0;
			$ven = $this->model->venta->edit($datos, $arguments['id']);
			if($ven->result > 0) {
				$det_venta = $this->model->venta_detalle->editByVenta($datos, $arguments['id']);
				if($det_venta->result > 0) {
					if($venta->recarga_id > 0){
						$delRecarga = $this->model->recarga->del($venta->recarga_id);
						if(!$delRecarga->response){
							$this->response->result = $delRecarga->result;
							$this->response->errors = $delRecarga->errors;
							$this->response->state = $this->model->transaction->regresaTransaccion();
							return $response->withJson($this->response->SetResponse(false, "No se cancelo la recarga: $venta->recarga_id correspondiente a la venta: $arguments[id] se cancela la transacción"));
						}
					}else{
						foreach($detalles as $detalle) {
							$fecha = date('Y-m-d H:i:s');
							$stockSuc = $this->model->prod_kardex->getStockSuc($venta->sucursal_id, $detalle->producto_id)->result->final;
							$data = [
								"empleado_id" => $empleado_id_registro,
								"producto_id" => $detalle->producto_id,
								"sucursal_id" => $venta->sucursal_id,
								"fecha" => $fecha,
								"tipo" => $tipo,
								"cantidad" => $detalle->cantidad,
								"inicial" => $stockSuc,
								"final" => $stockSuc + ($tipo * $detalle->cantidad),
								"origen" => $arguments['id'],
								"origen_tipo" => 2,
							];
		
							$kardex = $this->model->prod_kardex->add($data);
							if($kardex->result > 0) {
									$arrKardex[] = $kardex->result;
									$datos['stock'] = $detalle->stock + ($tipo * $detalle->cantidad);
									unset($datos['status']);
									$prod = $this->model->producto->edit($datos, $detalle->producto_id);
									if($prod->result > 0) {
										$arrKardex[] = $prod->result;
									} else {
										$this->response->result = $prod->result;
										$this->response->errors = $prod->errors;
										$this->response->state = $this->model->transaction->regresaTransaccion();
										return $response->withJson($this->response->SetResponse(false, "No se actualizo stock del producto: $detalle[producto_id] correspondiente a la venta: $arguments[id] se cancela la transacción"));
									}
								} else {
									$this->response->result = $kardex->result;
									$this->response->errors = $kardex->errors;
									$this->response->state = $this->model->transaction->regresaTransaccion();
									return $response->withJson($this->response->SetResponse(false, "No se inserto en kardex los valores correspondientes a la cancelación venta: $arguments[id] se cancela la transacción"));
								}
						}
					}
				} else {
					$this->response->result = $det_venta->result;
					$this->response->result = $det_venta->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					return $response->withJson($this->response->SetResponse(false, "NO se elimino el detalle de la venta: $arguments[id] Se cancela la transacción"));
				}	
			} else {
				$this->response->result = $ven->result;
				$this->response->errors = $ven->errors;
				$this->response->state = $this->model->transaction->regresaTransaccion();
				return $response->withJson($this->response->SetResponse(false, "NO se elimino la venta: $arguments[id] Se cancela la transacción"));
			}

			$seg_log = $this->model->seg_log->add('Cancelar venta', 'venta', $arguments['id']);
			if($seg_log->response) {
				$this->response->result = $ven->result;
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
		});//fin cancela venta 

		/*
		 * Método sigFolio
		 * Regresa el siguiente folio de ventas
		 * by isantosp
		 */
		$this->get('sigFolio/{sucursal_id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->timbres = $this->model->timbres->getDisponibles()->result;

			$folio = $this->model->sucursal->getSigFolioVenta($arguments['sucursal_id'])->result;
			$this->response->folio = str_pad($arguments['sucursal_id'], 3, '0', STR_PAD_LEFT).str_pad($folio, 6, '0', STR_PAD_LEFT);
			return $response->withJson($this->response);
		});

		/** 
		 * Método facturaVenta
		 * Dado el id de una venta, manda a timbrar y genera el PDF de la factura
		 * by isantosp
		 */
		$this->get('facturaVenta/{id}', function($request, $response, $arguments) {  
			error_reporting(0);
			$idVenta = $arguments['id'];
			$venta = $this->model->venta->get($idVenta)->result;
			$cliente = $this->model->cliente->get($venta->cliente_id)->result;
			$det = $this->model->venta_detalle->getByVenta($idVenta)->result;
			
			$rfcReg = '/^[A-Z&Ñ]{3,4}[0-9]{2}(0[1-9]|1[012])(0[1-9]|[12][0-9]|3[01])[A-Z0-9]{2}[0-9A]$/';
			if(!preg_match($rfcReg, $cliente->rfc)) {
				$urlCli = $request->getUri()->getBaseUrl().'/clientes';
				$arrRes = array('error' => true, 'result' => $idVenta, 'cfdi_id' => 0, 'timbrado' => true, 'msg' => 'El cliente no tiene un RFC válido.<br><small>Debe modificar los datos en el catálogo de <a href="'.$urlCli.'" title="Clientes" target="_blank">clientes</a></small>', 'request' => $request);
				return $response->withJson($arrRes);
			}
			
			$disp = $this->model->timbres->getDisponibles()->result;
			if(intval($disp->disponibles) > 0) {
				$idUnico = 'A'.$venta->folio;
				$doc = $idVenta;
				require_once("../sat/doc2Array33.php");
				require_once("../sat/doc2XML33.php");
				$data = doc2Array($doc, 'A', $venta->folio, 1, $venta, $cliente, $det);
				$resXML = doc2XML33($data);
				if(!$resXML['error']) {
					include_once("../sat/timbre4G.php");
					$arrRes = timbrarXML($idUnico, $resXML['xml']);
					$arrRes['disp'] = $disp->disponibles;

					$xmlAntes = utf8_encode($resXML['xml']);
					$jsonXml = json_encode($resXML);
					$jsonPac = json_encode($arrRes);
					$errorLog = 0;
					if($arrRes['error']) $errorLog = 1;
					// INSERTAR EN LOG_TIMBRADO
					$dataLog = array();
					$dataLog['venta_id'] = $idVenta;
					$dataLog['fecha'] = new Literal('NOW()');
					$dataLog['xml'] = $xmlAntes;
					$dataLog['xml_res'] = $jsonXml;
					$dataLog['pac_res'] = $jsonPac;
					$dataLog['error'] = $errorLog;
					$dataLog['tipo'] = 1;
					$this->model->log_timbrado->add($dataLog);
					if(!$arrRes['error']) {
						$result = $arrRes['cfdi'];
						$arrT = explode('.', $data['total']);
						$totStr = str_pad($arrT[0],10,'0',STR_PAD_LEFT).'.'.str_pad($arrT[1],6,'0',STR_PAD_RIGHT);
						$qrcode = "?re=".$data['Emisor']['rfc']."&rr=".$data['Receptor']['rfc']."&tt=".$totStr."&id=".$result['uuid'];

						// INSERTAR EN TABLA CFDI
						$cfdiData = array();
						$cfdiData['venta_id'] = $idVenta;
						$cfdiData['serie'] = 'A';
						$cfdiData['folio'] = $venta->folio;
						$cfdiData['folio_fiscal'] = $result['uuid'];
						$cfdiData['fecha'] = $result['fecha'];
						$cfdiData['certificado'] = $result['certSAT'];
						$cfdiData['cadena_original'] = $result['cadena'];
						$cfdiData['sello_emisor'] = $result['selloCFD'];
						$cfdiData['sello_sat'] = $result['selloSAT'];
						$cfdiData['qr_code'] = $qrcode;
						$cfdiData['tipo'] = 1;
						$cfdiData['uso_cfdi'] = $cliente->uso_cfdi;
						$cfdiReg = $this->model->cfdi->add($cfdiData);
						$idCFDi = $cfdiReg->result;
						$url = '/cfdi/33/'.$idCFDi;

						// ACTUALIZAR FK_CFDI EN TABLA VENTA
						$dataVta = array('cfdi_id' => $idCFDi);
						$this->model->venta->edit($dataVta, $idVenta);

						// GUARDA ARCHIVO XML
						$xmlFinal = utf8_encode($result['xml']);
						$cfdiDir = 'data/cfdi';
						$cfdiUrl = $cfdiDir.'/A'.$venta->folio.'.xml';
						file_put_contents($cfdiUrl, $xmlFinal);

						$params['doc'] = $idCFDi;
						$params['empr'] = '1';
						$urlScriptPDF = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
						$urlScriptPDF = str_replace('facturaVenta', 'print', $urlScriptPDF);
						asyncCall($urlScriptPDF, $params);

						// ACTUALIZA TIMBRES USADOS / DISPONIBLES
						$dataTimbres = array('disponibles' => new Literal('disponibles - 1'), 'ultimo_timbre' => new Literal('NOW()'));
						$this->model->timbres->edit($dataTimbres, $disp->id_timbre);

						$arrRes = array('error' => false, 'id' => $idCFDi, 'url' => $url, 'result' => $idVenta, 'cfdi_id' => $idCFDi, 'response' => true, 'mail' => $cliente->correo /*, 'xml' => utf8_encode($result['xml'])*/);
					}
				} else {
					//$this->response->SetResponse(false, 'No se timbro el XML');
					if(!isset($_SESSION)) { session_start(); }
					$arrRes = array('error' => true, 'result' => $idVenta, 'cfdi_id' => 0, 'timbrado' => true, 'msg' => 'No se pudo timbrar.<br><small>'.$resXML['msg'].'</small>', 'xml' => $resXML['xml'], 'data' => $data, 'response' => $resXML);
					$xmlMal = "Error al crear el xml para el documento $doc \n\n";
					$xmlMal .= "CONFORTA \nMensaje: ".$resXML['msg']." \n\n";
					$xmlMal .= utf8_encode($resXML['xml']);
					sendMailSMTP($_SESSION['mail_username'], 'Error al crear el XML', $xmlMal, '', array());
				}
			} else {
				//$this->response->SetResponse(false, 'No tiene timbres disponibles');
				//$this->response->result = -1;
				$arrRes = array('error' => true, 'result' => $idVenta, 'cfdi_id' => 0, 'timbrado' => true, 'msg' => 'No tiene timbres disponibles');
			}

			return $response->withJson($arrRes);
		})->setName('facturaVenta');


		$this->get('facturaTest', function($request, $response, $arguments) {
			$xml = '<?xml version="1.0" encoding="UTF-8"?> <cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/3" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sat.gob.mx/cfd/3 http://www.sat.gob.mx/sitio_internet/cfd/3/cfdv33.xsd" Version="3.3" TipoDeComprobante="I" Fecha="2019-11-16T12:46:04" LugarExpedicion="43998" MetodoPago="PUE" FormaPago="28" SubTotal="1163.79" Total="1350.00" Moneda="MXN" TipoCambio="1" NoCertificado="30001000000300023708" Certificado="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" Serie="A" Folio="1281" Sello="KVSePZscDWEILsm6Tu1/l2gOhlI1ZvUSfsAt/0yZ+LxaoFwp6UmZADD5BUOFmCVT7VDw5yVdV4kYy4zDyt2hKu9MwXjZMGD6XfwnKTzOBilyj9EDMOfWNZZjN4EXkHjivJIGjA53jsZ1DpU1Md1432V9lLMVEVRldyk5fTPkpQAp6chF+hena07VK2HNVu52O71m6KXN3bCo2qRt/o/cLqIgxwL66gMr/9HOrxLglQyEmE0s1n3shroj25wXN7d1w4xKPsUUrtT2SF1f74fLf4qYFcrc0FG00jo9lM/KSODopZWu8IoVqzYzQp5nXn0dJUjx4sEKiB91t+Zsc2b0/A==">   <cfdi:Emisor Rfc="AAA010101AAA" Nombre="Conforta SA de CV" RegimenFiscal="601"/>   <cfdi:Receptor Rfc="XAXX010101000" Nombre="PUBLICO EN GENERAL" UsoCFDI="P01"/>   <cfdi:Conceptos>     <cfdi:Concepto ClaveProdServ="56101508" NoIdentificacion="123456789" Cantidad="1" ClaveUnidad="H87" Unidad="PZA" Descripcion="Colchon Aurora Individual" ValorUnitario="1163.79" Importe="1163.79">       <cfdi:Impuestos>         <cfdi:Traslados>           <cfdi:Traslado Base="1163.79" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.160000" Importe="186.21"/>         </cfdi:Traslados>       </cfdi:Impuestos>     </cfdi:Concepto>   </cfdi:Conceptos>   <cfdi:Impuestos TotalImpuestosTrasladados="186.21">     <cfdi:Traslados>       <cfdi:Traslado Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.160000" Importe="186.21"/>     </cfdi:Traslados>   </cfdi:Impuestos></cfdi:Comprobante>';
			$idUnico = 'A1281';

			include_once("../sat/timbre4G.php");
			$arrRes = timbrarXML($idUnico, $xml);
			//getToken();

			//print_r($arrRes);
		});

		/** 
		 * Método getXML
		 * Dado el id de un CFDi, descarga el XML
		 * by isantosp
		 */
		$this->get('getXML/{id_cfdi}', function($request, $response, $arguments) {
			$idCfdi = $arguments['id_cfdi'];
			$cfdi = $this->model->cfdi->get($idCfdi)->result;

			$filename = $cfdi->serie.$cfdi->folio.'.xml';
			if(file_exists('data/cfdi/'.$filename)) {
				header('Content-Disposition: attachment; filename='.$filename.';');
				header('Content-Type: application/xml');
				readfile('data/cfdi/'.$filename);
				exit();
			} else {
				echo 'NO EXISTE: data/cfdi/'.$filename;
			}
		});

		$this->post('sendCfdi', function($request, $response, $arguments) {
			$data = $request->getParsedBody();
			$idVenta = $data['id_cfdi'];
			$venta = $this->model->venta->get($idVenta)->result;
			$idCfdi = $venta->cfdi_id;
			$cfdi = $this->model->cfdi->get($idCfdi)->result;

			$filename = $cfdi->serie.$cfdi->folio.'.xml';
			$files = array();
			$files[] = 'data/cfdi/'.$filename;
			$files[] = 'data/cfdi/CFDI_'.str_replace('.xml', '.pdf', $filename);
			$resMail = sendMailSMTP($data['correo'], 'Envio de CFDi', $data['mensaje'], '', $files);
			
			return $response->withJson(array('error' => false, 'response' => $resMail));
		});

		$this->get('rptCobranza/{inicio}/{fin}/{pagina}/{limite}[/{sucursal_id}/{cliente_id}]', function($request, $response, $arguments) {
			$arguments['sucursal_id'] = isset($arguments['sucursal_id'])? $arguments['sucursal_id']: 0;
			$arguments['cliente_id'] = isset($arguments['cliente_id'])? $arguments['cliente_id']: 0;
			$infoRptCobranza = $this->model->venta->rptCobranza($arguments['inicio'], $arguments['fin'], $arguments['pagina'], $arguments['limite'], $arguments['sucursal_id'], $arguments['cliente_id']);
			foreach($infoRptCobranza->result as &$rptRow) {
				$rptRow->pagado = number_format($this->model->venta_pago->getImportePagado($rptRow->id), 2);
				$rptRow->restante = number_format($rptRow->total-$rptRow->pagado, 2);
				$rptRow->cliente = $this->model->cliente->get($rptRow->cliente_id)->result;
			}
			
			return $response->withJson($infoRptCobranza);
		});

		$this->get('printTicket/{venta_id}', function($request, $response, $arguments) {
			$venta = $this->model->venta->get($arguments['venta_id'])->result;
			$empleado = $this->model->usuario->get($venta->empleado_id)->result;
			$cliente = $this->model->usuario->get($venta->cliente_id)->result;
			$det_venta = $this->model->venta_detalle->getByVenta($arguments['venta_id'])->result;
			$pagos = $this->model->venta_pago->getByVenta($arguments['venta_id'])->result;
			$sucursal = $this->model->sucursal->get($venta->sucursal_id)->result;

			$params = ['venta' => $venta, 'empleado' => $empleado, 'cliente' => $cliente, 'detVenta' => $det_venta, 'pagos' => $pagos, 'sucursal' => $sucursal];
			//print_r($params);
			return $this->view->render($response, 'ticket_venta.phtml', $params);
		});
	})->add( new MiddlewareToken() );

	function sendMailSMTP($to, $subject, $body, $cc, $files) {
	//require "class.phpmailer.php";

		$disc = "<br><br><br><small>======================================================<br>";
		$disc .="Este correo fue enviado desde una cuenta no monitoreada. Por favor no responda este correo.</small>";
		$body = $body.$disc;

		$mail = new PHPMailer;

		//$mail->SMTPDebug = 3;
		$mail->isSMTP();
		$mail->Host = "mail.viama.com.mx";
		$mail->SMTPAuth = true;
		$mail->Username = "informacion@viama.com.mx";
		$mail->Password = "S-WNq2WK0?oR";
		
		$mail->Port = 587;  

		$mail->From = "informacion@viama.com.mx";
		$mail->FromName = "Conforta Facturas";

		$mail->addAddress($to);//, $to);
		if($cc != '')	$mail->AddCC($cc);//, $cc);

		$mail->isHTML(true);
		$mail->Subject = $subject;
		$mail->Body = $body;

		for($x=0;$x<count($files);$x++) {
			$filename = explode('/', $files[$x]);
			$filename = $filename[count($filename)-1];

			$mail->AddAttachment($files[$x], $filename);
		}

		if(!$mail->send())	return "Mailer Error: " . $mail->ErrorInfo;
		else	return "TRUE";
	}

	function asyncCall($url, array $params) {
		foreach($params as $key => &$val) {
			if(is_array($val)) $val = implode(',', $val);
			$post_params[] = $key.'='.urlencode($val);
		}

		$post_string = implode('&', $post_params);
		$parts=parse_url($url);
		$fp = fsockopen($parts['host'], isset($parts['port'])?$parts['port']:80, $errno, $errstr, 30);

		$out = "POST ".$parts['path']." HTTP/1.1\r\n";
		$out.= "Host: ".$parts['host']."\r\n";
		$out.= "Content-Type: application/x-www-form-urlencoded\r\n";
		$out.= "Content-Length: ".strlen($post_string)."\r\n";
		$out.= "Connection: Close\r\n\r\n";
		if (isset($post_string)) $out.= $post_string;

		fwrite($fp, $out);
		fclose($fp);
	}
?>