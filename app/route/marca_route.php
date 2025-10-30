<?php
	use App\Lib\Response;
	use App\Lib\MiddlewareToken;
 
	/*** Grupo bajo la ruta marca ***/
	$app->group('/marca/', function() {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de marca');
		})->add( new MiddlewareToken() );
		
		$this->get('find/{busqueda}', function($request, $response, $arguments) {
			return $response->withJson($this->model->marca->find($arguments['busqueda']));
		});
		
		$this->get('getByNombreMD5/{nombre}', function($request, $response, $arguments) {
			return $response->withJson($this->model->marca->getByNombreMD5($arguments['nombre']));
		});

		$this->get('get/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->marca->get($arguments['id']));
		});

		$this->get('getAll/[{pagina}/{limite}[/{busqueda}]]', function($request, $response, $arguments) {
			$arguments['pagina'] = isset($arguments['pagina'])? $arguments['pagina']: 0;
			$arguments['limite'] = isset($arguments['limite'])? $arguments['limite']: 0;
			$arguments['busqueda'] = isset($arguments['busqueda'])? $arguments['busqueda']: 0;
			
			return $response->withJson($this->model->marca->getAll($arguments['pagina'], $arguments['limite'], $arguments['busqueda']));
		});

		$this->post('add/', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->marca->add($request->getParsedBody());
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Alta de nueva marca', 'marca', $resultado->result);
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

		$this->put('edit/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody(); $id_marca = $arguments['id']; $marcaInfo = $this->model->marca->get($id_marca)->result;
			$areTheSame = true; foreach($parsedBody as $field => $value) { if($marcaInfo->$field != $value) { 
				$areTheSame = false; break; 
			}}

			$resultado = $this->model->marca->edit($request->getParsedBody(), $arguments['id']); if($resultado->response || $areTheSame) { $this->response->areTheSame = $areTheSame;
				if(!$areTheSame) {
					$seg_log = $this->model->seg_log->add('Actualización información marca', 'marca', $arguments['id']);
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

			$resultado = $this->model->marca->del($arguments['id']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Inactivación marca', 'marca', $arguments['id']);
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