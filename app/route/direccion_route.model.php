<?php
	use App\Lib\Response;
	use App\Lib\MiddlewareToken;

	/*** Grupo bajo la ruta direccion ***/  
	$app->group('/direccion/', function () {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de direccion');
		});

		/*** 
		 * Ruta para obtener la información de una dirección mediante su ID
		 * recibe {id_direccion} ID del registro en la base de datos
		 * regresa: objeto con la información de la dirección
		 * ***/
		$this->get('get/{id_direccion}', function($request, $response, $arguments) {
			return $response->withJson($this->model->direccion->get($arguments['id_direccion']));
		});

		/*** 
		 * Ruta para obtener todas las direcciónes pertenecientes a un usuario específico
		 * recibe {fk_usuario} ID del usuario
		 * recibe opcional {predeterminada} Bándera para especificar si se desea obtener la dirección predeterminada únicamente, por default es 0
		 * regresa: objeto con la información de todas las direcciones registradas de un usuario
		 * ***/
		$this->get('getByUsuario/{fk_usuario}[/{predeterminada}]', function($request, $response, $arguments) {
			$predeterminada = isset($arguments['predeterminada'])? $arguments['predeterminada']: 0;
			return $response->withJson($this->model->direccion->getByUsuario($arguments['fk_usuario'], $predeterminada));
		});

		/***
		 * Ruta para obtener un registro de dirección mediante el ID del usuario y el nombre de la dirección
		 * recibe {fk_usuario} ID del usuario
		 * recibe {nombre} nombre de la dirección
		 * regresa: objeto con la información de la dirección, si es que existe.
		 */
		$this->get('getByNombre/{fk_usuario}/{nombre}', function($request, $response, $arguments) {
			return $response->withJson($this->model->direccion->getByNombre($arguments['fk_usuario'], $arguments['nombre']));
		});

		/***
		 * Ruta para agregar una nueva dirección a la base de datos
		 * regresa: ID del nuevo registro
		 */
		$this->post('add/', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody();
			// foreach($parsedBody as $key => $value) {
			// 	$parsedBody[$key] = utf8_encode($value);
			// }

			$direccion = $this->model->direccion->add($parsedBody);
			if($direccion->response) {
				if(!isset($_SESSION)) { session_start(); }
				if(isset($_SESSION['usuario'])) { $_SESSION['direcciones'] = $this->model->direccion->getByCliente($_SESSION['usuario']->id_cliente)->result; }

				$seg_log = $this->model->seg_log->add('Alta nueva dirección', 'direccion', $direccion->result);
				if($seg_log->response) {
					$this->response->result = $direccion->result;
					$this->response->state = $this->model->transaction->confirmaTransaccion();
					$this->response->SetResponse(true);
				} else {
					$this->response->result = $seg_log->result;
					$this->response->errors = $seg_log->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->SetResponse(false, $seg_log->message);
				}
			} else {
				$this->response->result = $direccion->result;
				$this->response->errors = $direccion->errors;
				$this->response->state = $this->model->transaction->regresaTransaccion();
				$this->response->SetResponse(false, $direccion->message);
			}

			return $response->withJson($this->response);
		});

		/***
		 * Ruta para modificar una dirección existente en la base de datos mediante su ID
		 * recibe {id_direccion} ID de la dirección a modificar
		 * ***/
		$this->put('edit/{id_direccion}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody();
			// foreach($parsedBody as $key => $value) {
			// 	$parsedBody[$key] = utf8_encode($value);
			// }

			$resultado = $this->model->direccion->edit($parsedBody, $arguments['id_direccion']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Actualización información dirección', 'direccion', $arguments['id_direccion']);
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

		/***
		 * Ruta para dar de baja una dirección de la base de datos
		 * recibe {id_direccion} ID de la dirección a modificar
		 * ***/
		$this->put('del/{id_direccion}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->direccion->del($arguments['id_direccion']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Baja dirección', 'direccion', $arguments['id']);
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