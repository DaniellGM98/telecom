<?php
	use App\Lib\Response;
	use App\Lib\MiddlewareToken;
 
	/*** Grupo bajo la ruta prod_categoria ***/  
	$app->group('/prod_categoria/', function() {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de prod_categoria');
		})->add( new MiddlewareToken() );
		
		/*** Ruta para obtener los datos de prod_categoria por medio del ID ***/
		$this->get('get/{id}', function($request, $response, $arguments) {
			$categoria = $this->model->prod_categoria->get($arguments['id']);
			if($categoria->response) {
				$categoria->result->subcategorias = $this->model->prod_subcategoria->getByCategoria($categoria->result->id)->result;
			}

			return $response->withJson($categoria);
		});

		$this->get('getByNombreMD5/{nombre}', function($request, $response, $arguments) {
			return $response->withJson($this->model->prod_categoria->getByNombreMD5($arguments['nombre']));
		});

		/*** Ruta para obtener los datos de los prod_categoria ***/
		$this->get('getAll/[{pagina}/{limite}[/{busqueda}]]', function($request, $response, $arguments) {
			$arguments['pagina'] = isset($arguments['pagina'])? $arguments['pagina']: 0;
			$arguments['limite'] = isset($arguments['limite'])? $arguments['limite']: 0;
			$arguments['busqueda'] = isset($arguments['busqueda'])? $arguments['busqueda']: 0;
			$categorias = $this->model->prod_categoria->getAll($arguments['pagina'], $arguments['limite'], $arguments['busqueda']);
			foreach($categorias->result as &$categoria) {
				$categoria->subcategorias = $this->model->prod_subcategoria->getAll(0, 0, 0, $categoria->id)->result;
				$categoria->productos = $this->model->producto->getAll(0, 0, $categoria->id)->result;
			}

			return $response->withJson($categorias);
		});

		/*** Ruta para agregar un prod_categoria ***/
		$this->post('add/', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody();

			$data = [ 'nombre'=>$parsedBody['nombre'], 'clave'=>$parsedBody['clave'], 'tiene_sku'=>$parsedBody['tiene_sku'], 'regalo'=>$parsedBody['regalo'], 'sku_nombre'=>$parsedBody['sku_nombre'] ];
			$resultado = $this->model->prod_categoria->add($data); if($resultado->response) { $id_categoria = $resultado->result;
				$files = $request->getUploadedFiles(); if(isset($files['imagen'])) {
					$filename = $this->model->prod_categoria->saveImg($files['imagen'], $id_categoria)->filename;
					$prod_categoria = $this->model->prod_categoria->edit(['imagen' => $filename], $id_categoria); if(!$prod_categoria->response) {
						$this->response->result = $prod_categoria->result;
						$this->response->errors = $prod_categoria->errors;
						$this->response->state = $this->model->transaction->regresaTransaccion();
						$this->response->SetResponse(false, $prod_categoria->message);			
					}
				}

				$seg_log = $this->model->seg_log->add('Alta categoria productos', 'prod_categoria', $resultado->result);
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

		/*** Ruta para modificar un prod_categoria ***/
		$this->post('edit/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody(); $id_categoria = $arguments['id']; $categoria = $this->model->prod_categoria->get($id_categoria)->result;
			$files = $request->getUploadedFiles();
			if(isset($files['imagen'])) { 
				if(strlen($categoria->imagen)>0 && file_exists("assets/image/categorias/$categoria->imagen")) {
					unlink("assets/image/categorias/$categoria->imagen");
				}
				$filename = $this->model->prod_categoria->saveImg($files['imagen'], $arguments['id'])->filename; 
			} else { $filename = $this->model->prod_categoria->get($arguments['id'])->result->imagen; }
			$data = [ 'nombre'=>$parsedBody['nombre'], 'clave'=>$parsedBody['clave'], 'tiene_sku'=>$parsedBody['tiene_sku'], 'regalo'=>$parsedBody['regalo'], 'sku_nombre'=>$parsedBody['sku_nombre'] ];
			if($filename != null) { $data['imagen'] = $filename; }
			$this->response->data = $data;
			$areTheSame = true; foreach($data as $field => $value) {
				if($categoria->$field != $value) {
					$areTheSame = false; break;
				}
			}

			$resultado = $this->model->prod_categoria->edit($data, $arguments['id']);
			if($resultado->response || $areTheSame) { $this->response->areTheSame = $areTheSame;
				if(!$areTheSame) {
					$seg_log = $this->model->seg_log->add('Actualización información categoria', 'prod_categoria', $arguments['id']);
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

		/*** Ruta para dar de baja un prod_categoria ***/
		$this->put('del/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->prod_categoria->del($arguments['id']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Baja categoria productos', 'prod_categoria', $arguments['id']);
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