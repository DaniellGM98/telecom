<?php
	use App\Lib\Response;
	use App\Lib\MiddlewareToken;
 
	/*** Grupo bajo la ruta recarga ***/
	$app->group('/recarga/', function() {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de recarga');
		})->add( new MiddlewareToken() );
		
		$this->get('find/{busqueda}', function($request, $response, $arguments) {
			return $response->withJson($this->model->recarga->find($arguments['busqueda']));
		});
		
		$this->get('get/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->recarga->get($arguments['id']));
		});

		$this->get('getAll/[{pagina}/{limite}[/{busqueda}]]', function($request, $response, $arguments) {
			$arguments['pagina'] = isset($arguments['pagina'])? $arguments['pagina']: 0;
			$arguments['limite'] = isset($arguments['limite'])? $arguments['limite']: 0;
			$arguments['busqueda'] = isset($arguments['busqueda'])? $arguments['busqueda']: 0;
			
			return $response->withJson($this->model->recarga->getAll($arguments['pagina'], $arguments['limite'], $arguments['busqueda']));
		});

		$this->get('getByDateSuc/{ini}/{fin}/{suc}', function($request, $response, $arguments) {
			$resultado = $this->model->recarga->getByDateSuc($arguments['ini'], $arguments['fin'], $arguments['suc']);
			
			return $response->withJson($resultado);
		});

		$this->post('add/', function($request, $response, $arguments) {
			require_once './core/defines.php';
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody(); 
			$sucursal_id = $parsedBody['sucursal_id'];
			$fecha = date('Y-m-d H:i:s');
			$corte = $this->model->sucursal->getSaldo($sucursal_id);
			if($corte['status'] == 2) $fecha = date('Y-m-d 00:00:00', strtotime('tomorrow'));
			$parsedBody['fecha'] = $fecha;

			$resultado = $this->model->recarga->add($parsedBody);
			if($resultado->response) {
				$idRecarga = $resultado->result;
				$usuario_id = $_SESSION['usuario']->id; $costo = floatval($this->model->recarga_costo->get($parsedBody['recarga_costo_id'])->result->monto);
				$dataVenta = [ 'sucursal_id'=>$sucursal_id, 'empleado_id'=>$usuario_id, 'empleado_id_registro'=>$usuario_id, 'fecha'=>$fecha, 'subtotal'=>$costo, 'iva'=>0, 
					'total'=>$costo, 'pagado'=>1, 'recarga_id' => $idRecarga, 
					'folio' => "TA".str_pad($_SESSION['sucursal'], 3, "0", STR_PAD_LEFT).date('ymdHis')
					//'folio'=>'recarga_'.time() 
				];
				$venta = $this->model->venta->add($dataVenta);
				if($venta->response) { $id_venta = $venta->result;
					$data = [ 'venta_id'=>$id_venta, 'producto_id'=>$_SESSION['recarga'], 'origen_tipo'=>1, 'cantidad'=>1, 'costo'=>$costo, 'importe'=>$costo, 'iva'=>0, ];
					$venta_detalle = $this->model->venta_detalle->add($data);
					if($venta_detalle->response) {
						$seg_log = $this->model->seg_log->add('Alta de nueva recarga', 'recarga', $resultado->result);
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
				$this->response->result = $resultado->result;
				$this->response->errors = $resultado->errors;
				$this->response->state = $this->model->transaction->regresaTransaccion();
				$this->response->SetResponse(false, $resultado->message);
			}

			return $response->withJson($this->response);
		})->add( new MiddlewareToken() );

		$this->put('edit/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody(); $id_recarga = $arguments['id']; $recargaInfo = $this->model->recarga->get($id_recarga)->result;
			$areTheSame = true; foreach($parsedBody as $field => $value) { if($recargaInfo->$field != $value) { 
				$areTheSame = false; break; 
			}}

			$resultado = $this->model->recarga->edit($request->getParsedBody(), $arguments['id']); if($resultado->response || $areTheSame) { $this->response->areTheSame = $areTheSame;
				if(!$areTheSame) {
					$seg_log = $this->model->seg_log->add('Actualización información recarga', 'recarga', $arguments['id']);
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

			$resultado = $this->model->recarga->del($arguments['id']);
			if($resultado->response) {
				$cancelaVenta = $this->model->venta->cancelaByRecarga($arguments['id']);
				//$this->model->venta_detalle->editByVenta($datos, $arguments['id']);
				$seg_log = $this->model->seg_log->add('Cancelación recarga', 'recarga', $arguments['id']);
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