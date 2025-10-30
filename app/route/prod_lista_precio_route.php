<?php
	use App\Lib\Response;
	use App\Lib\MiddlewareToken;
 
	/*** Grupo bajo la ruta prod_lista_precio ***/
	$app->group('/prod_lista_precio/', function () {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de prod_lista_precio');
		});
		
		/*** Ruta para obtener los datos de prod_lista_precio por medio del ID ***/
		$this->get('get/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->prod_lista_precio->get($arguments['id']));
		});

		/*** Ruta para buscar prod_lista_precio ***/
		$this->get('find/{busqueda}', function($request, $response, $arguments) {
			return $response->withJson($this->model->prod_lista_precio->find($arguments['busqueda']));
		});

		/*** Ruta para obtener los datos de los prod_lista_precio ***/
		$this->get('getAll/', function($request, $response, $arguments) {
			return $response->withJson($this->model->prod_lista_precio->getAll());
		});

		/*** Agrega una lista de precios ****/
		$this->post('add/', function($request, $response, $arguments) {
			$this->response = new Response();
			$parsedBody = $request->getParsedBody();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			if($parsedBody['origen'] == '0') {
				$idLista = $this->model->prod_lista_precio->add($parsedBody)->result;
				$fkLista = $this->model->prod_lista_precio->getGeneral($parsedBody['sucursal_id'])->result->id;
				$prodPrecios = $this->model->prod_precio->getAllOrignial($fkLista)->result;
				foreach($prodPrecios as $prodPrecio) {
					$data['producto_id'] = $prodPrecio->producto_id;
					$data['precio'] = $prodPrecio->precio;
					$data['lista_precio_id'] = $idLista;
					$idProdPrecio = $this->model->prod_precio->add($data);
					if(!$idLista>0 && !$fkLista>0 && !$idProdPrecio->result>0) {
						$idProdPrecio->result->idLista = $idLista;
						$idProdPrecio->result->fkLista = $fkLista;
						$idProdPrecio->result->comit = 'false';
						$this->model->transaction->regresaTransaccion();
						return $response->withJson($idProdPrecio);
					}
				}
				
				$this->response->result = $idLista;
			} else { $this->response->result = $this->model->prod_lista_precio->add($parsedBody)->result; }

			if($this->response->result != 0) {
				$seg_log = $this->model->seg_log->add('Alta lista de precios', 'prod_lista_precio', $this->response->result);
				if($seg_log->response) {
					$this->response->state = $this->model->transaction->confirmaTransaccion();
					$this->response->SetResponse(true, 'Alta de lista completado');
				} else {
					$this->response->result = $seg_log->result;
					$this->response->errors = $seg_log->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->SetResponse(false, $seg_log->message);
				}
			} else {
				$this->response->state = $this->model->transaction->regresaTransaccion();
				$this->response->SetResponse(false);
			}

			return $response->withJson($this->response);
		});

		/*** Ruta para modificar un prod_lista_precio ***/
		$this->put('edit/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->prod_lista_precio->edit($request->getParsedBody(), $arguments['id']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Actualización lista de precios', 'prod_lista_precio', $arguments['id']);
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

		/*** Ruta para dar de baja un prod_lista_precio ***/
		$this->put('del/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->prod_lista_precio->del($arguments['id']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Cancelación lista de precios', 'prod_lista_precio', $arguments['id']);
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