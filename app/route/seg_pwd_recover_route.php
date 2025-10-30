<?php
	use App\Lib\Response,
		App\Lib\MiddlewareToken;

	/*** Grupo bajo la ruta seg_pwd_recover ***/  
	$app->group('/seg_pwd_recover/', function () {
		$this->get('', function($request, $response, $arguments) {
			return $res->withHeader('Content-type', 'text/html')->write('Soy ruta de seg_pwd_recover');
		})->add( new MiddlewareToken() );

		/*** 
		 * Ruta para obtener un registro por medio del ID 
		 * recibe {id} ID del registro en la base de datos
		 * regresa: objeto con la información de la solicitud de restablecer contraseña
		 * ***/
		$this->get('get/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->seg_pwd_recover->get($arguments['id']));
		});

		/*** 
		 * Ruta para obtener un registro de la base de datos por medio de la clave generada
		 * recibe {codigo} clave de 8 caracteres generada cuando se da de alta la solicitud
		 * regresa: objeto con la información de la solicitud de restablecer la contraseña
		 * ***/
		$this->get('getByCodigo/{codigo}', function($request, $response, $arguments) {
			return $response->withJson($this->model->seg_pwd_recover->getByCodigo($arguments['codigo']));
		});

		/*** 
		 * Ruta para obtener las solicitudes de restablecimiento de contraseña por cliente
		 * recibe {page} número de página
		 * recibe {limit} número máximo de registros por página
		 * recibe {fk_cliente} id del cliente
		 * recibe opcional {since} fecha inicial desde la cual mostrar registros
		 * recibe opcional {to} fecha final desde la cual mostrar registros
		 * regresa: objeto con el historico de las solicitudes de restablecimiento de contraseña
		 * ***/
		$this->get('getByCliente/{page}/{limit}/{fk_cliente}[/{since}/{to}]', function($request, $response, $arguments) {
			$arguments['since'] = isset($arguments['since'])? $arguments['since']: null;
			$arguments['to'] = isset($arguments['to'])? $arguments['to']: null;
			return $response->withJson($this->model->seg_pwd_recover->getByCliente($arguments['page'], $arguments['limit'], $arguments['fk_cliente'], $arguments['since'], $arguments['to']));
		})->add( new MiddlewareToken() );

		/***
		 * Ruta para agregar un nuevo registro a la base de datos
		 * regresa: ID del nuevo registro
		 */
		$this->post('add/', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->seg_pwd_recover->add($request->getParsedBody());
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Solicitud de reestablecimiento de contraseña', 'seg_pwd_recover', $resultado->result);
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

		/***
		 * Ruta para modificar un registro de la base de datos mediante el ID
		 * recibe {id} ID de la solicitud de restablecimiento de contraseña
		 * ***/
		$this->put('edit/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->seg_pwd_recover->edit($request->getParsedBody(), $arguments['id']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Actualización solicitud reestablecimiento de contraseña', 'seg_pwd_recover', $arguments['id']);
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