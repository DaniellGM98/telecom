<?php
	use App\Lib\Response,
		PHPMailer\PHPMailer\PHPMailer,
		PHPMailer\PHPMailer\Exception;
	use Slim\Http\UploadedFile;
	use App\Lib\MiddlewareToken;

	/*** Grupo bajo la ruta apartado ***/ 
	$app->group('/apartado_pago/', function() use ($app) {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de apartado pago');
		});

		/**
		 * Ruta para obtener los registros por id
		 * refibe {id} del apartado
		 * regresa: arreglo con el registro que tiene el id especificado
		 */
		$this->get('get/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->apartado_pago->get($arguments['id']));
		});

		/**
		 * Ruta para obtener todos los registros de pagos
		 * {pagina}: El número de página que quieres obtener 
		 * {limite}: El limite de registros que quieres en cada consulta, ejemplo: 25 registros
		 * {apartado_id}: ID del apartado del pago
		 * {inicio}: Fecha inicial a buscar
		 * {fin}: Fecha máxima
		 * {cliente}: ID del cliente que realizo los pagos, o también cadena de texto para buscar a los clientes
		 * {empleado_id}: ID del vendedor
		 * {status}: 
		 */
		$this->get('getAll/{pagina}/{limite}/{apartado_id}/{inicio}/{fin}/{cliente}/{empleado_id}/{status}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->result = [];
			$this->response->pagos = [];
			$this->response->total = 0;

			$clientes = [];
			$cliente = $arguments['cliente'];
			if(is_numeric($cliente)) {
				$clientes[] = $cliente;
			} else {
				$infoClientes = $this->model->cliente->find($cliente)->result;
				foreach($infoClientes as $c) {
					$clientes[] = $c->usuario_id;
				}
			}
		
			$start = $arguments['pagina'] * $arguments['limite'];
			$end = $start + $arguments['limite'];

			foreach($clientes as $cliente) {
				$pagos = $this->model->apartado_pago->getAll($arguments['pagina'], $arguments['limite'], $arguments['apartado_id'], $arguments['inicio'], $arguments['fin'], $cliente, $arguments['status']);
				foreach($pagos->result as $pago) {
					if(!in_array($pago->id, $this->response->pagos)) {
						if($arguments['empleado_id']==0 || $pago->empleado_id==$arguments['empleado_id']) {
							if($this->response->total >= $start && $this->response->total < $end) {
								$pago->posicion = $this->model->apartado_pago->getPosition($pago->apartado_id, $pago->fecha)->result;
								$this->response->result[] = $pago;
							}
	
							$this->response->pagos[] = $pago->id;
							$this->response->total += 1;
						}
					}
				}
			}

			usort($this->response->result, function($a, $b) {
				return (strtotime($b->fecha) - strtotime($a->fecha));
			});

			return $response->withJson($this->response->SetResponse(true));
		});

		/**
		 * Ruta para conseguir el numero de pago de un apartado
		 */
		$this->get('getPosition/{apartado_id}/{fecha}', function($request, $response, $arguments) {
			return $response->withJson($this->model->apartado_pago->getPosition($arguments['apartado_id'], $arguments['fecha']));
		});

		/**
		 * Ruta para conseguir todos los pagos realizados a un apartado por id de apartado
		 */
		$this->get('getByApartado/{apartado_id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->apartado_pago->getByApartado($arguments['apartado_id']));
		});

		/**
		 * Ruta para conseguir la suma total de todos los pagos realizados a un apartado por id de apartado
		 */
		$this->get('getPaymentsSum/{apartado_id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->apartado_pago->getPaymentsSum($arguments['apartado_id']));
		});

		/**
		 * Ruta para obtener el siguiente folio
		 * select coalesce(max(folio)+1, 1) as 'new id' from venta;
		 */
		$this->get('sigFolio/', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->result = $this->model->apartado->sigFolio()->result;
			$this->response->timbres = $this->model->timbres->getDisponibles()->result->disponibles;
			$this->response->SetResponse(true);

			return $response->withJson($this->response);
		});

		/**
		 * Ruta para agregar un nuevo registro
		 */
		$this->post('add/', function($request, $response, $arguments) {
			$parsedBody = $request->getParsedBody();
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$apartado = $this->model->apartado->get($parsedBody['apartado_id'])->result;
			
			require_once './core/defines.php';
			$fecha = date('Y-m-d H:i:s');
			$corte = $this->model->sucursal->getSaldo($apartado->sucursal_id);
			if($corte['status'] == 2) $fecha = date('Y-m-d 00:00:00', strtotime('tomorrow'));
			$data = [ 'apartado_id'=>$parsedBody['apartado_id'], 'empleado_id'=>$_SESSION['usuario']->id, 'fecha'=>$parsedBody['fecha'], 'importe'=>$parsedBody['importe'], 'tipo_pago_id'=>$parsedBody['tipo_pago_id'], 'metodo_pago'=>$parsedBody['metodo_pago'], 'status'=>$parsedBody['status'], ];
			$pago = $this->model->apartado_pago->add($data); if($pago->response) { $id_pago = $pago->result;
				$data = [ 'sucursal_id'=>$apartado->sucursal_id, 'empleado_id'=>$apartado->empleado_id, 'empleado_id_registro'=>$_SESSION['usuario']->id, 'fecha'=>$fecha, 'subtotal'=>$parsedBody['importe'], 'iva'=>0, 'total'=>$parsedBody['importe'], 'pagado'=>1, 'folio'=>"PA$apartado->folio", 'tipo'=>2, 'apartado_pago_id'=>$id_pago ];
				// 'folio' => "PA".str_pad($_SESSION['sucursal'], 3, "0", STR_PAD_LEFT).str_pad($apartado->folio, 6, "0", STR_PAD_LEFT),
				$venta = $this->model->venta->add($data); if($venta->response) { $id_venta = $venta->result;
					$data = [ 'venta_id'=>$id_venta, 'producto_id'=>$_SESSION['abono_apartado'], 'origen_tipo'=>1, 'cantidad'=>1, 'costo'=>$parsedBody['importe'], 'importe'=>$parsedBody['importe'], 'iva'=>0, ];
					$venta_detalle = $this->model->venta_detalle->add($data); if($venta_detalle->response) {
						$tipo_pago = $this->model->tipo_pago->get($parsedBody['tipo_pago_id']);
						if($tipo_pago->response && intval($tipo_pago->result->tiene_comprobante)==1) {
							$id = $pago->result;
							$files = $request->getUploadedFiles();
							$file = $files['comprobante'];
							$filename = $this->model->apartado_pago->saveImgComprobante($file, $id);
							$data = ['comprobante' => $filename->filename];

							$comprobante = $this->model->apartado_pago->edit($data, $id); if($comprobante->response) {
								$seg_log = $this->model->seg_log->add('Alta pago apartado', 'apartado_pago', $id); if($seg_log->response) {
									$this->response->result = $id;
									$this->response->state = $this->model->transaction->confirmaTransaccion();
									$this->response->SetResponse(true);
								} else {
									$this->response->result = $seg_log->result;
									$this->response->errors = $seg_log->errors;
									$this->response->state = $this->model->transaction->regresaTransaccion();
									$this->response->SetResponse(false, $seg_log->message);
								}
							} else {
								$this->response->result = $comprobante->result;
								$this->response->errors = $comprobante->errors;
								$this->response->SetResponse(false, $comprobante->message);
								$this->response->state = $this->model->transaction->regresaTransaccion();
							} 
						} else {
							$seg_log = $this->model->seg_log->add('Alta pago apartado', 'apartado_pago', $pago->result); if($seg_log->response) {
								$this->response->result = $pago->result;
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

			return $response->withJson($this->response);
		});

		/**
		 * Ruta para modificar un apartado_pago por id
		 */
		$this->put('edit/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody();

			$resultado = $this->model->apartado_pago->edit($parsedBody, $arguments['id']);
			if($resultado->response) {
				if(isset($parsedBody['status']) && intval($parsedBody['status'])==3) {
					$delVentaApartado = $this->model->venta->delByApartadoPago($arguments['id']); if(!$delVentaApartado->response) {
						$this->response->result = $delVentaApartado->result;
						$this->response->errors = $delVentaApartado->errors;
						$this->response->state = $this->model->transaction->regresaTransaccion();
						return $response->withJson($this->response->SetResponse(false, $delVentaApartado->message));
					}
				}

				$seg_log = $this->model->seg_log->add('Actualización información pago apartado', 'apartado_pago', $arguments['id']);
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
		 * Ruta para dar de baja un pago
		 */
		$this->put('delByApartado/{apartado_id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->apartado_pago->del($arguments['apartado_id']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Cancelación información pago apartado', 'apartado', $arguments['apartado_id']);
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
		 * Ruta para dar de baja un pago
		 */
		$this->put('del/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->apartado_pago->del($arguments['id'], $request->getParsedBody()['usuario']); if($resultado->response) {
				$delVentaApartado = $this->model->venta->delByApartadoPago($arguments['id']); if($delVentaApartado->response) {
					$seg_log = $this->model->seg_log->add('Cancelación información pago apartado', 'apartado_pago', $arguments['id']); if($seg_log->response) {
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
					$this->response->result = $delVentaApartado->result;
					$this->response->errors = $delVentaApartado->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->SetResponse(false, $delVentaApartado->message);
				}
			} else {
				$this->response->result = $resultado->result;
				$this->response->errors = $resultado->errors;
				$this->response->state = $this->model->transaction->regresaTransaccion();
				$this->response->SetResponse(false, $resultado->message);
			}

			return $response->withJson($this->response);
		});
	})->add( new MiddlewareToken() );
?>