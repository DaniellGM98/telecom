<?php
	use App\Lib\Response;
	use App\Lib\MiddlewareToken;

	/*** Grupo bajo la ruta prod_precio ***/
	$app->group('/prod_precio/', function () {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de prod_precio');
		});
		
		/*** Ruta para obtener los datos de prod_precio por medio del ID ***/
		$this->get('get/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->prod_precio->get($arguments['id']));
		});

		/*** Ruta para buscar prod_precio ***/
		$this->get('find/{filtro}', function($request, $response, $arguments) {
			return $response->withJson($this->model->prod_precio->find($arguments['filtro']));
			
		});

		/*** Ruta para obtener los datos de los prod_precio ***/
		$this->get('getAll/', function($request, $response, $arguments) {
			return $response->withJson($this->model->prod_precio->getAll());
		});

		/*** Ruta para obtener los datos de prod_precio por medio del ID ***/
		$this->get('getProdPrecio/{prod}/{lista}', function($request, $response, $arguments) {
			return $response->withJson($this->model->prod_precio->getProdPrecio($arguments['prod'],$arguments['lista']));
		});

		/*** Ruta para agregar un prod_precio ***/
		$this->post('add/', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->prod_precio->add($request->getParsedBody());
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Alta precio', 'prod_precio', $resultado->response);
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

		/*** Ruta para modificar un prod_precio ***/
		$this->put('edit/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->prod_precio->edit($request->getParsedBody(), $arguments['id']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Actualización precio', 'prod_precio', $arguments['id']);
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

		/*** Ruta para modificar un prod_precio ***/
		$this->put('editProdLista/{producto_id}/{prod_lista_precio_id}/{precio}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$prod_precio = $this->model->prod_precio->getProdPrecio($arguments['producto_id'], $arguments['prod_lista_precio_id']);
			if($prod_precio->response) { $prod_precio = $prod_precio->result;
				$resultado = $this->model->prod_precio->editProdLista($arguments['producto_id'], $arguments['prod_lista_precio_id'], $arguments['precio']);
				if($resultado->response) {
					$seg_log = $this->model->seg_log->add('Actualización precio', 'prod_precio', $prod_precio->id);
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
			} else {
				if(!isset($_SESSION)) { session_start(); }
				$data = [ 'producto_id'=>$arguments['producto_id'], 'lista_precio_id'=>$arguments['prod_lista_precio_id'], 'precio'=>$arguments['precio'], 'empleado_id'=>$_SESSION['usuario']->id, ];
				$resultado = $this->model->prod_precio->add($data); if($resultado->response) { $id_prod_precio = $resultado->result;
					$seg_log = $this->model->seg_log->add('Alta precio', 'prod_precio', $id_prod_precio);
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
			}




			return $response->withJson($this->response);
		});

		/*** Ruta para dar de baja un prod_precio ***/
		$this->put('del/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->prod_precio->del($arguments['id']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Baja precio', 'prod_precio', $arguments['id']);
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
	})->add( new MiddlewareToken() );
?>