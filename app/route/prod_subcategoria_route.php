<?php
	use App\Lib\Response;
	use App\Lib\MiddlewareToken;
 
	/*** Grupo bajo la ruta prod_subcategoria ***/
	$app->group('/prod_subcategoria/', function() {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de prod_subcategoria');
		})->add( new MiddlewareToken() );
		
		$this->get('find/{busqueda}', function($request, $response, $arguments) {
			return $response->withJson($this->model->prod_subcategoria->find($arguments['busqueda']));
		});
		
		$this->get('get/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->prod_subcategoria->get($arguments['id']));
		});

		$this->get('getByNombreMD5/{nombre}/{prod_categoria_id}', function($request, $response, $arguments) {
			$arguments['prod_categoria_id'] = isset($arguments['prod_categoria_id'])? $arguments['prod_categoria_id']: 0;
			return $response->withJson($this->model->prod_subcategoria->getByNombreMD5($arguments['nombre'], $arguments['prod_categoria_id']));
		});

		/*** Ruta para obtener los datos de los prod_subcategoria ***/
		$this->get('getAll/[{pagina}/{limite}[/{busqueda}[/{prod_categoria_id}]]]', function($request, $response, $arguments) {
			$arguments['pagina'] = isset($arguments['pagina'])? $arguments['pagina']: 0;
			$arguments['limite'] = isset($arguments['limite'])? $arguments['limite']: 0;
			$arguments['busqueda'] = isset($arguments['busqueda'])? $arguments['busqueda']: 0;
			$arguments['prod_categoria_id'] = isset($arguments['prod_categoria_id'])? $arguments['prod_categoria_id']: 0;
			return $response->withJson($this->model->prod_subcategoria->getAll($arguments['pagina'], $arguments['limite'], $arguments['busqueda'], $arguments['prod_categoria_id']));
		});
		
		$this->get('getByCategoria/{prod_categoria_id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->prod_subcategoria->getByCategoria($arguments['prod_categoria_id']));
		});

		/*** Ruta para agregar un prod_subcategoria ***/
		$this->post('add/', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->prod_subcategoria->add($request->getParsedBody());
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Alta subcategoria productos', 'prod_subcategoria', $resultado->result);
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

		/*** Ruta para modificar un prod_subcategoria ***/
		$this->put('edit/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody(); $id_subcategoria = $arguments['id']; $subcategoria = $this->model->prod_subcategoria->get($id_subcategoria)->result;
			$areTheSame = true; foreach($parsedBody as $field => $value) {
				if($subcategoria->$field != $value) {
					$areTheSame = false; break;
				}
			}

			$resultado = $this->model->prod_subcategoria->edit($parsedBody, $id_subcategoria); if($resultado->response || $areTheSame) { $this->response->areTheSame = $areTheSame;
				if($areTheSame) {
					$seg_log = $this->model->seg_log->add('Actualización información subcategoria', 'prod_subcategoria', $id_subcategoria);
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

		/*** Ruta para dar de baja un prod_subcategoria ***/
		$this->put('del/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->prod_subcategoria->del($arguments['id']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Baja subcategoria productos', 'prod_subcategoria', $arguments['id']);
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