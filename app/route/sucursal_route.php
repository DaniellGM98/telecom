<?php
	use App\Lib\Response,
		App\Lib\MiddlewareToken;
 
	/*** Grupo bajo la ruta sucursal ***/  
	$app->group('/sucursal/', function () {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de sucursal');
		});
		
		/*** Ruta para obtener los datos de sucursal por medio del ID ***/
		$this->get('get/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->sucursal->get($arguments['id']));
		});

		/*** Ruta para buscar sucursal ***/
		$this->get('find/{busqueda}', function($request, $response, $arguments) {
			return $response->withJson($this->model->sucursal->find($arguments['busqueda']));
		});

		/*** Ruta para obtener los datos de los sucursal ***/
		$this->get('getAll/', function($request, $response, $arguments) {
			return $response->withJson($this->model->sucursal->getAll());
		});

		/* Ruta para obtener los datos de la sucursal 
		 * {pagina}: El número de página que quieres obtener 
		 * {limite}: El limite de registros que quieres en cada consulta, ejemplo: 25 registros
		 * {busqueda}: busqueda
		 */
		$this->get('getAllBusca/[{pagina}/{limite}[/{busqueda}]]', function($request, $response, $arguments) {
			$arguments['pagina'] = isset($arguments['pagina'])? $arguments['pagina']: 0;
			$arguments['limite'] = isset($arguments['limite'])? $arguments['limite']: 0;
			$arguments['busqueda'] = isset($arguments['busqueda'])? $arguments['busqueda']: '_';
			return $response->withJson($this->model->sucursal->getAllBusca($arguments['pagina'], $arguments['limite'], $arguments['busqueda']));
		});

		$this->post('checkPassword/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->sucursal->checkPassword($arguments['id'], $request->getParsedBody()['password']));
		});

		/* Agrega Sucursal
		 * Descripción:  una vez que se agrega la sucursal se debe copiar
		 * una lista general para crear la lista general de la sucursal
		 */
		$this->post('add/', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody();
			
			$parsedBody['contrasena'] = md5(sha1($parsedBody['contrasena']));
			$parsedBody['consecutivo_venta'] = 1;
			$sucursal = $this->model->sucursal->add($parsedBody);
			if($sucursal->response) {
				$data = [
					"sucursal_id" => $sucursal->result,
					"nombre" => 'General',
					"origen" => '0'
				];
				$listaPrecio = $this->model->prod_lista_precio->add($data);
				if($listaPrecio->response) {
					$precios = $this->model->prod_precio->getAllOrignial('1');
					foreach($precios->result as $precio) {
						$data = [
							"lista_precio_id" => $listaPrecio->result,
							"producto_id" => $precio->producto_id, 
							"precio" => $precio->precio,
						];
						$idProdPrecio = $this->model->prod_precio->add($data);
						if(!$idProdPrecio->response) {
							$this->response->SetResponse(false, "No se agrego el precio de la lista: $listaPrecio->result correspondiente a la sucursal: $sucursal->result se cancela la transacción");
							$this->response->state = $this->model->transaction->regresaTransaccion();
							$this->response->rollBack = $this->response->state;
							$this->response->result = $idProdPrecio;
							
							return $response->withJson($this->response);
						}
					}
					
					//inicia procedimiento para agregar productos a kardex con sucursal nueva
					if(!isset($_SESSION)) session_start();
					$data = [
						"sucursal_id" => $sucursal->result,
						"empleado_id" => $_SESSION['usuario']->id,
						"tipo" => '1',
						"origen" => '0',
						"origen_tipo" => '3',
						"inicial" => '0',
						"cantidad" => '0',
						"final" => '0',
					];
					$productos = $this->model->producto->getAll();
					foreach($productos->result as $producto) {
						$data['producto_id'] = $producto->id;
						$idProdKardex = $this->model->prod_kardex->add($data);
						if(!$idProdKardex->response){
							$this->response->SetResponse(false, "Error en insertar kardex: $idProdKardex->result correspondiente al producto: $producto->id se cancela la transacción");
							$this->response->state = $this->model->transaction->regresaTransaccion();
							$this->response->rollBack = $this->response->state;
							return $response->withJson($this->response);
						}	
					}
				} else {
					$this->response->SetResponse(false, "No se creo la lista de precio: $listaPrecio->result correspondiente a la sucursal: $sucursal->result se cancela la transacción");
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->rollBack = $this->response->state;
					return $response->withJson($this->response);
				}
			} else {
				$this->response->SetResponse(false, "No se creo la sucursal: $sucursal->result se cancela la transacción");
				return $response->withJson($this->response);
			}
			
			$seg_log = $this->model->seg_log->add('Alta nueva sucursal', 'sucursal', $sucursal->result);
			if($seg_log->response) {
				$this->response->result = $sucursal->result;
				$this->response->state = $this->model->transaction->confirmaTransaccion();
				$this->response->SetResponse(true);
			} else {
				$this->response->result = $seg_log->result;
				$this->response->errors = $seg_log->errors;
				$this->response->state = $this->model->transaction->regresaTransaccion();
				$this->response->SetResponse(false, $seg_log->message);
			}

			return $response->withJson($sucursal);
		});

		/*** Ruta para modificar un sucursal ***/
		$this->put('edit/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody(); $idSucursal = $arguments['id']; if(isset($parsedBody['contrasena'])) { $parsedBody['contrasena'] = md5(sha1($parsedBody['contrasena'])); }
			$areTheSame = $this->model->sucursal->areTheSame($idSucursal, $parsedBody)->result;

			$resultado = $this->model->sucursal->edit($parsedBody, $arguments['id']); if($resultado->response || $areTheSame) { $this->response->areTheSame = $areTheSame;
				if(!$areTheSame) {
					if(isset($parsedBody['contrasena'])) { $this->model->sucursal->liberarSucursal($idSucursal); }
					$seg_log = $this->model->seg_log->add('Actualización información sucursal', 'sucursal', $arguments['id']);
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
		});

		/*** Ruta para dar de baja un sucursal ***/
		$this->put('del/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$sucursal = $this->model->sucursal->del($arguments['id']);
			if($sucursal->response) {
				$lista_precio = $this->model->prod_lista_precio->delBySuc($arguments['id']);
				if($lista_precio->response) {
					$seg_log = $this->model->seg_log->add('Baja sucursal', 'sucursal', $arguments['id']);
					if($seg_log->response) {
						$this->response->result = $sucursal->result;
						$this->response->state = $this->model->transaction->confirmaTransaccion();
						$this->response->SetResponse(true);
					} else {
						$this->response->result = $seg_log->result;
						$this->response->errors = $seg_log->errors;
						$this->response->state = $this->model->transaction->regresaTransaccion();
						$this->response->SetResponse(false, $seg_log->message);
					}
				} else {
					$this->response->SetResponse(false, "No se eliminó las listas de precios de la sucursal, se cancela la transacción");
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->rollBack = $this->response->state;
				}
			} else {
				$this->response->SetResponse(false, "No se creo eliminó la sucursal, se cancela la transacción");
				$this->response->state = $this->model->transaction->regresaTransaccion();
				$this->response->rollBack = $this->response->state;
			}
			
			return $response->withJson($this->response);
		});

		$this->post('accedeSucursal/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody(); $idSucursal = $arguments['id']; $infoSucursal = $this->model->sucursal->get($idSucursal)->result;
			if($infoSucursal->empleado_id==null || intval($infoSucursal->empleado_id)==intval($_SESSION['usuario']->id)) {
				if($this->model->sucursal->loginSucursal($idSucursal, $parsedBody['password'])->response) {
					$_SESSION['sucursal'] = $idSucursal;
					$_SESSION['usuario']->sucursal_id = $idSucursal;
					$_SESSION['sucNombre'] = $infoSucursal->nombre;
					$sucursal = $this->model->sucursal->edit(['empleado_id'=>$_SESSION['usuario']->id], $idSucursal); if($sucursal->response || intval($infoSucursal->empleado_id)==intval($_SESSION['usuario']->id)) {
						$this->response->result = $idSucursal;
						$this->response->SetResponse(true, $sucursal->message);
						$this->response->state = $this->model->transaction->confirmaTransaccion();
					} else {
						$this->response->result = false;
						$this->response->SetResponse(false, $sucursal->message);
						$this->response->state = $this->model->transaction->regresaTransaccion();
					}
				} else {
					$this->response->result = false;
					$this->response->SetResponse(false, 'Contraseña incorrecta');
					$this->response->state = $this->model->transaction->regresaTransaccion();
				}
			} else {
				$this->response->result = false;
				$this->response->SetResponse(false, 'La sucursal ya se encuentra asignada a otro usuario');
				$this->response->state = $this->model->transaction->regresaTransaccion();
			}

			return $response->withJson($this->response);
		});

		$this->put('liberarSucursal/{id}', function($request, $response, $arguments) {
			if(!isset($_SESSION)) { session_start(); }
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$idSucursal = intval($arguments['id']);
			$sucursal = $this->model->sucursal->liberarSucursal($idSucursal); if($sucursal->response) {
				$this->response->result = $sucursal->result;
				$this->response->SetResponse(true, $sucursal->message);
				$this->response->state = $this->model->transaction->confirmaTransaccion();
				if(intval($_SESSION['sucursal']) == $idSucursal) {
					unset($_SESSION['sucursal']);
				}
			} else {
				$this->response->result = $sucursal->result;
				$this->response->SetResponse(false, $sucursal->message);
				$this->response->state = $this->model->transaction->regresaTransaccion();
			}

			return $response->withJson($this->response);
		});

		$this->get('getSaldos', function($request, $response, $arguments){
			$sucs = $this->model->sucursal->getAll();
			foreach ($sucs->result as $suc) {
				unset($suc->direccion);
				unset($suc->correo);
				unset($suc->consecutivo_venta);
				$saldo = $this->model->sucursal->getSaldo($suc->id);
				$suc->inicial = $saldo['inicial'];
				$suc->final = $saldo['final'];
				$suc->status = $saldo['status'];
				if($suc->empleado_id){
					$user = $this->model->usuario->get($suc->empleado_id)->result;
					$suc->usuario = $user->nombre.' '.$user->apellidos;
				}else{
					$suc->usuario = 'Libre';
				}
			}

			return $response->withJson($sucs);
		});

		$this->post('setSaldo', function($request, $response, $arguments) {
            $parsedBody = $request->getParsedBody();
            $data = array($parsedBody['name'] => $parsedBody['value']);
            $resultado = $this->model->sucursal->setSaldo($data, $parsedBody['pk']);
			if($resultado)
                $this->model->seg_log->add('Asigna Saldo Inicial '.json_encode($data), 'saldo', $parsedBody['pk']);
            return $response->withJson($resultado);
		});

		$this->get('importSaldos/', function($request, $response, $arguments){
			$ultimo = $this->db->from('import')->where('tabla', 'saldos')->fetch();
			$registros = $this->dbOld
				->from('saldos')
				->where('id_saldo > ?',$ultimo->ultimo)
				->orderBy('id_saldo')
				->fetchAll();

			$count = 0;
			foreach ($registros as $reg) {
                if($reg->fk_id_sucursal != 4 && $reg->fk_id_sucursal != 10){
                    $data = array(
                                'sucursal_id' => $reg->fk_id_sucursal, 
                                'fecha' => $reg->fecha.'', 
                                'inicial' => $reg->saldo_inicial, 
                                'final' => $reg->saldo_final, 
                                'status' => $reg->cerrado == 'true' ? 2 : 1, 
                            );
					$res = $this->db->insertInto('saldo', $data)->execute();
                    if($res){
                        $this->db->update('import', array('ultimo' => $reg->id_saldo))->where('tabla', 'saldos')->execute();
                        $count++;
                    }
                }
			}
			echo 'Listo se insertaron '.$count.' saldos';
		});

		$this->get('getCorte/{suc}[/{fecha}]', function($request, $response, $arguments){
			require_once './core/defines.php';
			$this->response = new Response();
			$fecha = isset($arguments['fecha']) ? $arguments['fecha'] : date('Y-m-d');

			$arrOtro = $this->model->prod_salida_detalle->getCorteOtros($arguments['suc'], $fecha)->result;
			//$sqlOtro = $this->model->prod_salida_detalle->getCorteOtros($arguments['suc'], $fecha)->sql;
			$arrTel = $this->model->prod_salida_detalle->getCorteTelefonos($arguments['suc'], $fecha)->result;
			$taire = $this->model->recarga->getCorte($arguments['suc'], $fecha)->result;
			$traspasos = $this->model->traspaso->getCorte($arguments['suc'], $fecha)->result;
			$regalo = $this->model->venta_detalle->getCorteRegalo($arguments['suc'], $fecha)->result;
			$saldo_inicial = $this->model->sucursal->getSaldo($arguments['suc'])['inicial'];
			$ingresos = $this->model->ingreso->getCorte($arguments['suc'], $fecha)->result;
			$egresos = $this->model->egreso->getCorte($arguments['suc'], $fecha)->result;

			$res = array('telefonos' => $arrTel, 'otros' => $arrOtro, /*'sqlotros' => $sqlOtro,*/ 'tia' => $taire, 'traspasos' => $traspasos, 'regalo' => $regalo, 'saldo_inicial' => $saldo_inicial, 'ingresos' => $ingresos, 'egresos' => $egresos);

			$res['stockVal'] = -1;
			if($fecha == date('Y-m-d') && $_SESSION['sucursal'] == 0){
				$stock = $this->model->producto->getValorStock($arguments['suc']);
				$stockVal = 0;
				//foreach ($stock['res'] as $prod) {
				foreach ($stock as $prod) {
					$stockVal += (floatval($prod->stock) * floatval($prod->precio));
				}
				//$res['stock'] = $stock;
				$res['stockVal'] = number_format($stockVal,2);
			}

			echo json_encode($res);
			exit(0);
		});

		$this->get('import/', function($request, $response, $arguments){
			$ultimo = $this->db->from('import')->where('tabla', 'sucursal')->fetch();
			$registros = $this->dbOld
				->from('sucursal')
				->where('id_sucursal > ?',$ultimo->ultimo)
				->orderBy('id_sucursal')
				->fetchAll();

			$count = 0;
			foreach ($registros as $suc) {
				$data = array('id' => $suc->id_sucursal, 'nombre' => $suc->nombre, 'direccion' => $suc->direccion, 'telefono' => $suc->telefono, 'contrasena' => md5(sha1($suc->contrasena)));
				$res = $this->model->sucursal->add($data);
				if($res){
					$dataPrecio = [
						"sucursal_id" => $res->result,
						"nombre" => 'General',
						"origen" => '0'
					];
					$listaPrecio = $this->model->prod_lista_precio->add($dataPrecio);
					$this->db->update('import', array('ultimo' => $suc->id_sucursal))->where('tabla', 'sucursal')->execute();
					$count++;
				}
			}
			echo 'Listo se insertaron '.$count.' sucursales';
		});
	})->add( new MiddlewareToken() );
?>