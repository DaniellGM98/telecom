<?php
	use App\Lib\Response;
	use App\Lib\MiddlewareToken;
 
	/*** Grupo bajo la ruta pos ***/
	$app->group('/pos/', function () {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de pos');
		});

        $this->get('getIngresoGasto/{ini}/{fin}', function($request, $response, $arguments){
            $ingresos = $this->model->pos->getIngresos($arguments['ini'], $arguments['fin'])->result;
            $egresos = $this->model->pos->getEgresos($arguments['ini'], $arguments['fin'])->result;

            foreach ($ingresos as $ingreso) {
				$ingreso->fecha = date('d/m/Y', strtotime($ingreso->fecha));
                $ingreso->hora = date('H:i', strtotime($ingreso->fecha));
            }

            foreach ($egresos as $egreso) {
				$egreso->fecha = date('d/m/Y', strtotime($egreso->fecha));
                $egreso->hora = date('H:i', strtotime($egreso->fecha));
            }

            return $response->withJson(array('ingresos' => $ingresos, 'egresos' => $egresos));
        });

        $this->post('addIngreso/', function($request, $response, $arguments) {
            $resultado = $this->model->pos->addIngreso($request->getParsedBody());
            $resultado->hora = date('H:i');
			if($resultado)
                $this->model->seg_log->add('Agrega ingreso', 'ingreso', $resultado->result);
            return $response->withJson($resultado);
		});

		$this->get('importIngreso/', function($request, $response, $arguments){
			$ultimo = $this->db->from('import')->where('tabla', 'entrada')->fetch();
			$registros = $this->dbOld
				->from('entrada')
				->where('id_entrada > ?',$ultimo->ultimo)
				->orderBy('id_entrada')
				->fetchAll();

			$count = 0;
			foreach ($registros as $reg) {
				$data = array(
                            'usuario_id' => $reg->fk_id_usuario, 
                            'sucursal_id' => $reg->fk_id_sucursal, 
                            'fecha' => $reg->fecha.'', 
                            'importe' => $reg->importe, 
                            'concepto' => $reg->concepto, 
                        );
				$res = $this->model->pos->addIngreso($data);
				if($res){
					$this->db->update('import', array('ultimo' => $reg->id_entrada))->where('tabla', 'entrada')->execute();
					$count++;
				}
			}
			echo 'Listo se insertaron '.$count.' entradas';
		});

        $this->put('delIngreso/{id}', function($request, $response, $arguments) {
            $resultado = $this->model->pos->delIngreso($arguments['id']);
			if($resultado)
                $this->model->seg_log->add('Elimina ingreso', 'ingreso', $arguments['id']);
            return $response->withJson($resultado);
		});

        $this->post('addEgreso/', function($request, $response, $arguments) {
			$resultado = $this->model->pos->addEgreso($request->getParsedBody());
            $resultado->hora = date('H:i');
			if($resultado)
                $this->model->seg_log->add('Agrega egreso', 'egreso', $resultado->result);
            return $response->withJson($resultado);
		});

        $this->get('importEgreso/', function($request, $response, $arguments){
			$ultimo = $this->db->from('import')->where('tabla', 'retiro')->fetch();
			$registros = $this->dbOld
				->from('retiro')
				->where('id_retiro > ?',$ultimo->ultimo)
				->orderBy('id_retiro')
				->fetchAll();

			$count = 0;
			foreach ($registros as $reg) {
				$data = array(
                            'usuario_id' => $reg->fk_id_usuario, 
                            'sucursal_id' => $reg->fk_id_sucursal, 
                            'fecha' => $reg->fecha.'', 
                            'importe' => $reg->importe, 
                            'concepto' => $reg->observaciones, 
                        );
				$res = $this->model->pos->addEgreso($data);
				if($res){
					$this->db->update('import', array('ultimo' => $reg->id_retiro))->where('tabla', 'retiro')->execute();
					$count++;
				}
			}
			echo 'Listo se insertaron '.$count.' egresos';
		});

        $this->put('delEgreso/{id}', function($request, $response, $arguments) {
            $resultado = $this->model->pos->delEgreso($arguments['id']);
			if($resultado)
                $this->model->seg_log->add('Elimina egreso', 'egreso', $arguments['id']);
            return $response->withJson($resultado);
		});


        $this->get('getReparaciones/{suc}', function($request, $response, $arguments){
            $items = $this->model->pos->getReparaciones($arguments['suc'])->result;

            foreach ($items as $item) {
                $item->fecha = date('d/m/Y', strtotime($item->fecha));
            }

            return $response->withJson($items);
        });

        $this->get('getReparaciones/{ini}/{fin}', function($request, $response, $arguments){
            $items = $this->model->pos->getReparacionesDate($arguments['ini'], $arguments['fin'])->result;

            foreach ($items as $item) {
                $item->fecha = date('d/m/Y', strtotime($item->fecha));
            }

            return $response->withJson($items);
        });

        $this->post('addReparacion/', function($request, $response, $arguments) {
            $data = $request->getParsedBody();
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			if(!isset($data['anticipo'])) $data['anticipo'] = 0.00;
			$resultado = $this->model->pos->addReparacion($data);
            $resultado->fecha = date('d/m/Y');
			if($resultado){
				$idRep = $resultado->result;
                $this->model->seg_log->add('Agrega reparacion', 'reparacion', $idRep);

				if(floatval($data['anticipo']) > 0.00){
					$fecha = date('Y-m-d H:i:s');
					$corte = $this->model->sucursal->getSaldo($_SESSION['sucursal']);
					if($corte['status'] == 2) $fecha = date('Y-m-d 00:00:00', strtotime('tomorrow'));
					$dataVenta = array(
						'sucursal_id'=>$_SESSION['sucursal'], 
						'empleado_id'=>$_SESSION['usuario']->id, 
						'cliente_id' =>$_SESSION['cliente_general'], 
						'empleado_id_registro'=>$_SESSION['usuario']->id, 
						'fecha'=>$fecha, 
						'subtotal'=>$data['anticipo'], 
						'iva'=>0, 
						'total'=>$data['anticipo'], 
						'pagado'=>1, 
						'folio'=>"AR".str_pad($_SESSION['sucursal'], 3, "0", STR_PAD_LEFT).str_pad($idRep, 6, "0", STR_PAD_LEFT), 
						'tipo'=>3, 
						//'apartado_pago_id'=>$id_pago 
					);
					$venta = $this->model->venta->add($dataVenta); 
					if($venta->response) { 
						$id_venta = $venta->result;
						$dataDetVenta = [ 'venta_id'=>$id_venta, 'producto_id'=>$_SESSION['anticipo_rep'], 'origen_tipo'=>1, 'cantidad'=>1, 
						'costo'=>$data['anticipo'], 'importe'=>$data['anticipo'], 'iva'=>0, ];
						$venta_detalle = $this->model->venta_detalle->add($dataDetVenta); 
						if($venta_detalle->response) {
							$seg_log = $this->model->seg_log->add('Abono Reparación', 'reparacion', $idRep); if($seg_log->response) {
								$this->response->result = $idRep;
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
				}else{
					$this->response->state = $this->model->transaction->confirmaTransaccion();
				}
			}else{
				$this->response->result = $resultado->result;
				$this->response->errors = $resultado->errors;
				$this->response->state = $this->model->transaction->regresaTransaccion();
				$this->response->SetResponse(false, $resultado->message);
			}
            return $response->withJson($resultado);
		});

        $this->get('importReparacion/', function($request, $response, $arguments){
			$ultimo = $this->db->from('import')->where('tabla', 'reparacion')->fetch();
			$registros = $this->dbOld
				->from('reparacion')
				->where('id_reparacion > ?',$ultimo->ultimo)
				->orderBy('id_reparacion')
				->fetchAll();

			$count = 0;
			foreach ($registros as $reg) {
                if($reg->fk_id_sucursal != 4){
                    $data = array(
                                'usuario_id' => $reg->fk_id_usuario, 
                                'sucursal_id' => $reg->fk_id_sucursal, 
                                'fecha' => $reg->fecha.'', 
                                'cliente' => $reg->nombre, 
                                'marca' => $reg->marca, 
                                'modelo' => $reg->modelo, 
                                'imei' => $reg->imei, 
                                'anticipo' => $reg->anticipo, 
                                'total' => $reg->total, 
                                'observaciones' => $reg->observaciones, 
                                'estado' => $reg->estado, 
                            );
                    if(strpos($reg->estado,'Entregado')>-1) $data['status'] = 2;
                    $res = $this->model->pos->addReparacion($data);
                    if($res){
                        $this->db->update('import', array('ultimo' => $reg->id_reparacion))->where('tabla', 'reparacion')->execute();
                        $count++;
                    }
                }
			}
			echo 'Listo se insertaron '.$count.' reparaciones';
		});

        $this->put('delReparacion/{id}', function($request, $response, $arguments) {
            $resultado = $this->model->pos->delReparacion($arguments['id']);
			if($resultado)
                $this->model->seg_log->add('Elimina reparacion', 'reparacion', $arguments['id']);
            return $response->withJson($resultado);
		});

        $this->put('reparacion/delivery/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$idRep = $arguments['id'];
			$data = $request->getParsedBody();
			$repInfo = $this->model->pos->getReparacion($idRep);
			$saldo = floatval($repInfo->total) - floatval($repInfo->anticipo);
            $resultado = $this->model->pos->deliveryReparacion($idRep);
			if($resultado){
                $this->model->seg_log->add('Entrega reparacion', 'reparacion', $idRep);
				$fecha = date('Y-m-d H:i:s');
				$corte = $this->model->sucursal->getSaldo($_SESSION['sucursal']);
				if($corte['status'] == 2) $fecha = date('Y-m-d 00:00:00', strtotime('tomorrow'));
				$dataVenta = array(
					'sucursal_id'=>$_SESSION['sucursal'], 
					'empleado_id'=>$_SESSION['usuario']->id, 
					'cliente_id' =>$_SESSION['cliente_general'], 
					'empleado_id_registro'=>$_SESSION['usuario']->id, 
					'fecha'=>$fecha, 
					'subtotal'=>$saldo, 
					'iva'=>0, 
					'total'=>$saldo, 
					'pagado'=>1, 
					'folio'=>"PR".str_pad($_SESSION['sucursal'], 3, "0", STR_PAD_LEFT).str_pad($idRep, 6, "0", STR_PAD_LEFT), 
					'tipo'=>3, 
					//'apartado_pago_id'=>$id_pago 
				);
				$venta = $this->model->venta->add($dataVenta); 
				if($venta->response) { 
					$id_venta = $venta->result;
					$dataDetVenta = [ 'venta_id'=>$id_venta, 'producto_id'=>$_SESSION['pago_rep'], 'origen_tipo'=>1, 'cantidad'=>1, 
					'costo'=>$saldo, 'importe'=>$saldo, 'iva'=>0, ];
					$venta_detalle = $this->model->venta_detalle->add($dataDetVenta); 
					if($venta_detalle->response) {
						$seg_log = $this->model->seg_log->add('Pago Reparación', 'reparacion', $idRep); if($seg_log->response) {
							$this->response->result = $idRep;
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
			}else{
				$this->response->result = $resultado->result;
				$this->response->errors = $resultado->errors;
				$this->response->state = $this->model->transaction->regresaTransaccion();
				$this->response->SetResponse(false, $resultado->message);
			}
            return $response->withJson($resultado);
		});

        $this->post('reparacion/set', function($request, $response, $arguments) {
            $parsedBody = $request->getParsedBody();
            $data = array($parsedBody['name'] => $parsedBody['value']);
            $resultado = $this->model->pos->setReparacion($data, $parsedBody['pk']);
			if($resultado)
                $this->model->seg_log->add('Edita reparacion '.json_encode($data), 'reparacion', $parsedBody['pk']);
            return $response->withJson($resultado);
		});


		$this->get('getGarantias/{suc}', function($request, $response, $arguments){
            $items = $this->model->pos->getGarantias($arguments['suc'])->result;

            foreach ($items as $item) {
                $item->fecha = date('d/m/Y', strtotime($item->fecha));
				$item->marca = $item->marca_id != null ? $this->model->marca->get($item->marca_id)->result->nombre : '';
				$item->importe = $this->model->venta_detalle->findBySku($item->imei)->result->importe;
				$item->distribuidor = $this->model->prod_entrada_detalle->getDistSku($item->imei)->proveedor;
            }

            return $response->withJson($items);
        });

        $this->get('getGarantias/{ini}/{fin}', function($request, $response, $arguments){
            $items = $this->model->pos->getGarantiasDate($arguments['ini'], $arguments['fin'])->result;

            foreach ($items as $item) {
                $item->fecha = date('d/m/Y', strtotime($item->fecha));
				$item->marca = $item->marca_id != null ? $this->model->marca->get($item->marca_id)->result->nombre : '';
				$item->importe = $this->model->venta_detalle->findBySku($item->imei)->result->importe;
				$item->distribuidor = $this->model->prod_entrada_detalle->getDistSku($item->imei)->proveedor;
            }

            return $response->withJson($items);
        });

        $this->post('addGarantia/', function($request, $response, $arguments) {
            $data = $request->getParsedBody();
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			unset($data['producto']);
			unset($data['fecha']);
			unset($data['importe']);
			
			$resultado = $this->model->pos->addGarantia($data);
            $resultado->fecha = date('d/m/Y');
			if($resultado){
				$idGar = $resultado->result;
                $this->model->seg_log->add('Agrega garantia', 'garantia', $idGar);
				$this->response->state = $this->model->transaction->confirmaTransaccion();
			}else{
				$this->response->result = $resultado->result;
				$this->response->errors = $resultado->errors;
				$this->response->state = $this->model->transaction->regresaTransaccion();
				$this->response->SetResponse(false, $resultado->message);
			}
            return $response->withJson($resultado);
		});

        $this->put('delGarantia/{id}', function($request, $response, $arguments) {
            $resultado = $this->model->pos->delGarantia($arguments['id']);
			if($resultado)
                $this->model->seg_log->add('Elimina garantia', 'garantia', $arguments['id']);
            return $response->withJson($resultado);
		});

        $this->post('garantia/set', function($request, $response, $arguments) {
            $parsedBody = $request->getParsedBody();
            $data = array($parsedBody['name'] => $parsedBody['value']);
            $resultado = $this->model->pos->setGarantia($data, $parsedBody['pk']);
			if($resultado)
                $this->model->seg_log->add('Edita garantia '.json_encode($data), 'garantia', $parsedBody['pk']);
            return $response->withJson($resultado);
		});

		
		/*** Ruta para obtener los datos de pos por medio del ID ***/
		$this->get('get/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->pos->get($arguments['id']));
		});

		/*** Ruta para obtener los datos de los pos ***/
		$this->get('getAll/', function($request, $response, $arguments) {
			return $response->withJson($this->model->pos->getAll());
		});
		
		/*** Ruta para obtener los datos de los pos de un mismo usuario ***/
		$this->get('geByUsuario/{fk_usuario}[/{since}/{to}]', function($request, $response, $arguments) {
			$arguments['since'] = isset($arguments['since'])? $arguments['since']: null;
			$arguments['to'] = isset($arguments['to'])? $arguments['to']: null;
			return $response->withJson($this->model->pos->getAll($arguments['fk_usuario'], $arguments['since'], $arguments['to']));
		});

		/*** Ruta para agregar un pos ***/
		$this->post('add/', function($request, $response, $arguments) {
			return $response->withJson($this->model->pos->add($request->getParsedBody()));
		});

		/*** Ruta para modificar un pos ***/
		$this->put('edit/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->pos->edit($request->getParsedBody(), $arguments['id']));
		});

		/*** Ruta para dar de baja un pos ***/
		$this->put('del/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->pos->del($arguments['id']));
		});
	})->add( new MiddlewareToken() );
?>