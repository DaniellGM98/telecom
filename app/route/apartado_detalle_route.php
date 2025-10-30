<?php
	use App\Lib\Response,
		PHPMailer\PHPMailer\PHPMailer,
		PHPMailer\PHPMailer\Exception;
	use Slim\Http\UploadedFile;
	use App\Lib\MiddlewareToken;

	/*** Grupo bajo la ruta apartado ***/ 
	$app->group('/apartado_detalle/', function() use ($app) {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de apartado detalle');
		});

		/**
		 * Ruta para obtener los registros por id
		 * refibe {id} del apartado
		 * regresa: arreglo con el registro que tiene el id especificado
		 */
		$this->get('get/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->apartado_detalle->get($arguments['id']));
		});

		/**
		 * Ruta para obtener todos los registros pertenecientes a un apartado
		 * recibe {apartado_id} id del apartado
		 */
		$this->get('getByApartado/{apartado_id}[/{status}]', function($request, $response, $arguments) {
			$arguments['status'] = isset($arguments['status'])? $arguments['status']: 1;
			return $response->withJson($this->model->apartado_detalle->getByApartado($arguments['apartado_id'], $arguments['status']));
		});

		/**
		 * Ruta para obtener todos los registros
		 */
		$this->get('getAll/', function($request, $response, $arguments) {
			return $response->withJson($this->model->apartado_detalle->getAll());
		});

		/**
		 * Ruta para obtener todos los registros por id producto
		 * {producto_id}: El fk del producto
		 */
		$this->get('searchByProducto/{producto_id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->apartado_detalle->searchByProducto($arguments['producto_id']));
		});

		/**
		 * Ruta para modificar un apartado_detalle por id
		 */
		$this->put('edit/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->apartado_detalle->edit($request->getParsedBody(), $arguments['id']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Actualización detalle apartado', 'apartado_detalle', $arguments['id']);
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
		 * Ruta para dar de baja un producto de una cotizacion
		 */
		$this->put('del/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->apartado_detalle->del($arguments['id']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Cancelación información detalle apartado', 'apartado_detalle', $arguments['id']);
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