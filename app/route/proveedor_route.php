<?php
	use App\Lib\Response;
	use App\Lib\MiddlewareToken;
 
	/*** Grupo bajo la ruta proveedor ***/  
	$app->group('/proveedor/', function() {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de proveedor');
		});
		
		/*** Ruta para obtener los datos de proveedor por medio del ID ***/
		$this->get('get/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->proveedor->get($arguments['id']));
		});
		
		$this->get('getByNombreMD5/{nombre}', function($request, $response, $arguments) {
			return $response->withJson($this->model->proveedor->getByNombreMD5($arguments['nombre']));
		});

		/*** Ruta para buscar proveedor ***/
		$this->get('find/{filtro}', function($request, $response, $arguments) {
			return $response->withJson($this->model->proveedor->find($arguments['filtro']));
		});

		/*** Ruta para obtener los datos de los proveedor ***/
		$this->get('getAll/', function($request, $response, $arguments) {
			return $response->withJson($this->model->proveedor->getAll());
		});

		/* Ruta para obtener los datos de la sucursal 
		 * {pagina}: El número de página que quieres obtener 
		 * {limite}: El limite de registros que quieres en cada consulta, ejemplo: 25 registros
		 * {filtro}: busqueda 
		 */
		$this->get('getAllBusca/{pagina}/{limite}/{filtro}', function($request, $response, $arguments) {
			return $response->withJson($this->model->proveedor->getAllBusca($arguments['pagina'],$arguments['limite'],$arguments['filtro']));
		}); //end getAllBusca

		/*** Ruta para agregar un proveedor ***/
		$this->post('add/', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->proveedor->add($request->getParsedBody());
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Alta proveedor', 'proveedor', $resultado->result);
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

		/*** Ruta para modificar un proveedor ***/
		$this->put('edit/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody(); $id_proveedor = $arguments['id']; $proveedorInfo = $this->model->proveedor->get($id_proveedor)->result;
			$areTheSame = true; foreach($parsedBody as $field => $value) { if($proveedorInfo->$field != $value) { 
				$areTheSame = false; break; 
			}}

			$resultado = $this->model->proveedor->edit($parsedBody, $arguments['id']);
			if($resultado->response || $areTheSame) { $resultado->areTheSame = $areTheSame;
				if(!$areTheSame) {
					$seg_log = $this->model->seg_log->add('Actualización información proveedor', 'proveedor', $arguments['id']);
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

		/*** Ruta para dar de baja un proveedor ***/
		$this->put('del/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->proveedor->del($arguments['id']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Baja proveedor', 'proveedor', $arguments['id']);
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