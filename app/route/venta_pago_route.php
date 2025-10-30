<?php
	use App\Lib\Response,
		App\Lib\MiddlewareToken;
 
	/*** Grupo bajo la ruta venta_pago ***/  
	$app->group('/venta_pago/', function () {
		$this->get('', function ($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de venta_pago');
		});

		/*** Ruta para buscar venta_pago ***/
		$this->get('find/{f}', function ($request, $response, $arguments) {   
			return $response->withJson($this->model->venta_pago->find($arguments['f']));
		});
		
		/*** Ruta para obtener los datos de venta_pago por medio del ID ***/
		$this->get('get/{id}', function ($request, $response, $arguments) {
			return $response->withJson($this->model->venta_pago->get($arguments['id']));
		});

		/*** Ruta para obtener los datos de venta_pago por medio de el ID de la venta ***/
		$this->get('getByVenta/{venta_id}', function ($request, $response, $arguments) {
			return $response->withJson($this->model->venta_pago->getByVenta($arguments['venta_id']));
		});

		/*** Ruta para obtener los datos de los venta_pago ***/
		$this->get('getAll/', function ($request, $response, $arguments) {
			return $response->withJson($this->model->venta_pago->getAll());
		});

		/*** Ruta para agregar un venta_pago ***/
		$this->post('add/', function ($request, $response, $arguments) {
			require_once './core/defines.php';
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody();
			
			$venta_id = $parsedBody['venta_id'];
			$data = [
				'venta_id' => $venta_id,
				'fecha' => $parsedBody['fecha'],
				'tipo_pago_id' => $parsedBody['tipo_pago_id'],
				'importe' => $parsedBody['importe'],
				'status' => $parsedBody['status'],
			];
			$pago = $this->model->venta_pago->add($data);
			if($pago->response) {
				$totalPagado = $this->model->venta_pago->getImportePagado($venta_id);
				if($totalPagado == $this->model->venta->get($venta_id)->result->total) {
					$pagado = $this->model->venta->edit(['pagado' => 1], $venta_id);
					if(!$pagado->response) {
						$this->response->result = $pagado->result;
						$this->response->errors = $pagado->errors;
						$this->response->state = $this->model->transaction->regresaTransaccion();
						return $response->withJson($this->response->SetResponse(false, $pagado->message));
					}
				}

				$tipo_pago = $this->model->tipo_pago->get($parsedBody['tipo_pago_id']);
				if($tipo_pago->response && intval($tipo_pago->result->tiene_comprobante)==1) {
					$id = is_object($pago)? $pago->result[0]->id: $pago->result;
					$files = $request->getUploadedFiles();
					$file = $files['comprobante'];
					$filename = $this->model->venta_pago->saveImgComprobante($file, $id);
					$data = ['comprobante' => $filename->filename];
					$comprobante = $this->model->venta_pago->edit($data, $id);
					
					if($comprobante->response) {
						$seg_log = $this->model->seg_log->add('Alta pago venta', 'venta_pago', $id);
						if($seg_log->response) {
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
					$id = is_object($pago)? $pago->result[0]->id: $pago->result;
					$seg_log = $this->model->seg_log->add('Alta pago venta', 'venta_pago', $id);
					if($seg_log->response) {
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
				$this->response->result = $pago->result;
				$this->response->errors = $pago->errors;
				$this->response->state = $this->model->transaction->regresaTransaccion();
				$this->response->SetResponse(false, $pago->message);
			}

			return $response->withJson($this->response);
		});


		/*** Ruta para modificar un venta_pago ***/
		$this->put('edit/{id}', function ($request, $response, $arguments) {
			require_once './core/defines.php';
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$venta_id = $this->model->venta_pago->get($arguments['id'])->result->venta_id;
			$venta = $this->model->venta->get($venta_id)->result;
			$venta_pago = $this->model->venta_pago->edit($request->getParsedBody(), $arguments['id']);
			if($venta_pago->response) {
				$totalPagado = $this->model->venta_pago->getImportePagado($venta_id);
				$data = ['pagado' => ($totalPagado == $venta->total)? 1: 0];
				$pagado = $this->model->venta->edit($data, $venta_id);
				if($venta->pagado==$data['pagado'] || $pagado->response) {
					$seg_log = $this->model->seg_log->add('Actualización información pago venta', 'venta_pago', $arguments['id']);
					if($seg_log->response) {
						$this->response->result = $venta_pago->result;
						$this->response->state = $this->model->transaction->confirmaTransaccion();
						$this->response->SetResponse(true);
					} else {
						$this->response->result = $seg_log->result;
						$this->response->errors = $seg_log->errors;
						$this->response->state = $this->model->transaction->regresaTransaccion();
						$this->response->SetResponse(false, $seg_log->message);
					}
				} else {
					$this->response->result = $pagado->result;
					$this->response->errors = $pagado->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->SetResponse(false, $pagado->message);
				}
			} else {
				$this->response->result = $venta_pago->result;
				$this->response->errors = $venta_pago->errors;
				$this->response->state = $this->response->transaction->regresaTransaccion();
				$this->response->SetResponse(false, $venta_pago->message);
			}

			return $response->withJson($this->response);
		});

		/*** Ruta para dar de baja un venta_pago ***/
		$this->put('del/{id}', function ($request, $response, $arguments) {
			require_once './core/defines.php';
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$venta_id = $this->model->venta_pago->get($arguments['id'])->result->venta_id;
			$venta = $this->model->venta->get($venta_id)->result;
			$venta_pago = $this->model->venta_pago->del($arguments['id']);
			if($venta_pago->response) {
				$totalPagado = $this->model->venta_pago->getImportePagado($venta_id);
				$data = ['pagado' => ($totalPagado == $venta->total)? 1: 0];
				$pagado = $this->model->venta->edit($data, $venta_id);
				if($venta->pagado==$data['pagado'] || $pagado->response) {
					$seg_log = $this->model->seg_log->add('Baja pago venta', 'venta_pago', $arguments['id']);
					if($seg_log->response) {
						$this->response->result = $venta_pago->result;
						$this->response->state = $this->model->transaction->confirmaTransaccion();
						$this->response->SetResponse(true);
					} else {
						$this->response->result = $seg_log->result;
						$this->response->errors = $seg_log->errors;
						$this->response->state = $this->model->transaction->regresaTransaccion();
						$this->response->SetResponse(false, $seg_log->message);
					}
				} else {
					$this->response->result = $pagado->result;
					$this->response->errors = $pagado->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->SetResponse(false, $pagado->message);
				}
			} else {
				$this->response->result = $venta_pago->result;
				$this->response->errors = $venta_pago->errors;
				$this->response->state = $this->model->transaction->regresaTransaccion();
				$this->response->SetResponse(false, $venta_pago->message);
			}

			return $response->withJson($this->response);
		});
	})->add( new MiddlewareToken() );
?>