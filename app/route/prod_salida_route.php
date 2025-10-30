<?php
	use App\Lib\Response;
	use App\Lib\MiddlewareToken;
 
	/*** Grupo bajo la ruta prod_salida ***/
	$app->group('/prod_salida/', function() {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de prod_salida');
		});
		
		/*** Ruta para obtener los datos de prod_salida por medio del ID ***/
		$this->get('get/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->prod_salida->get($arguments['id']));
		});

		/*** Ruta para buscar prod_salida ***/
		$this->get('find/{filtro}', function($request, $response, $arguments) {   
			return $response->withJson($this->model->prod_salida->find($arguments['filtro']));
		});

		$this->get('getByVenta/{venta_id}[/{status}]', function($request, $response, $arguments) {
			$arguments['status'] = isset($arguments['status'])? $arguments['status']: 0;
			return $response->withJson($this->model->prod_salida->getByVenta($arguments['venta_id'], $arguments['status']));
		});

		/*** Ruta para obtener los datos de los prod_salida ***/
		$this->get('getAll/', function($request, $response, $arguments) {
			return $response->withJson($this->model->prod_salida->getAll());
		});

		/*** Ruta para agregar un prod_salida ***/
		$this->post('add/', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->prod_salida->add($request->getParsedBody());
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Alta salida de productos', 'prod_salida', $resultado->result);
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

		/*** Ruta para modificar un prod_salida ***/
		$this->put('edit/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->prod_salida->edit($request->getParsedBody(), $arguments['id']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Actualización salida de productos', 'prod_salida', $arguments['id']);
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

		/*** Ruta para dar de baja un prod_salida ***/
		$this->put('del/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->prod_salida->del($arguments['id']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Cancelación salida productos', 'prod_salida', $arguments['id']);
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