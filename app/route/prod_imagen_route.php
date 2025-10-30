<?php
	use App\Lib\Response;
	use App\Lib\MiddlewareToken;

	/*** Grupo bajo la ruta prod_imagen ***/  
	$app->group('/prod_imagen/', function() {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de prod_imagen');
		})->add( new MiddlewareToken() );

		/*** 
		 * Ruta para obtener la información de un registro mediante su ID
		 * recibe {id} ID del registro en la base de datos
		 * regresa: objeto con la información de la imagen
		 * ***/
		$this->get('get/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->prod_imagen->get($arguments['id']));
		});
		
		/*** 
		 * Ruta para obtener todas las imagenes pertenecientes a un mismo producto
		 * recibe {producto_id} ID del producto
		 * regresa: objeto con la información de todas las imagenes ligadas al producto
		 * ***/
		$this->get('getByProducto/{producto_id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->prod_imagen->getByProducto($arguments['producto_id']));
		});
		
		/***
		 * Ruta para agregar un nuevo registro en la base de datos
		 * regresa: ID del nuevo registro
		 */
		$this->post('add/', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody();
			$id_producto = $parsedBody['id_producto'];

			$files = $request->getUploadedFiles(); if(isset($files['imagen'])) { $file = $files['imagen'];
				// $filename = $this->model->prod_imagen->saveImg($files['imagen'], $id_producto)->filename;
				$directory  = 'assets/image/productos/';
				$extension  = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);
				$filename = $id_producto."_".time().".".$extension;
				$pathToImage = $directory.$filename;
				$pathToThImage = $directory.'th_'.$filename;
				$this->model->prod_imagen->resize($file->file, 470, $pathToThImage);
				$this->model->prod_imagen->resize($file->file, 1024, $pathToImage);
				unlink($file->file);

				$data = [ 'producto_id' => $id_producto, 'imagen' => $filename, ];
				$prod_imagen = $this->model->prod_imagen->add($data);
				if($prod_imagen->response) {
					$seg_log = $this->model->seg_log->add('Alta imagen producto', 'prod_imagen', $prod_imagen->result);
					if($seg_log->response) {
						$this->response->result = $prod_imagen->result;
						$this->response->filename = $filename;
						$this->response->state = $this->model->transaction->confirmaTransaccion();
						$this->response->SetResponse(true);
					} else {
						$this->response->result = $seg_log->result;
						$this->response->errors = $seg_log->errors;
						$this->response->state = $this->model->transaction->regresaTransaccion();
						$this->response->SetResponse(false, $seg_log->message);
					}
				} else {
					$this->response->result = $prod_imagen->result;
					$this->response->errors = $prod_imagen->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->SetResponse(false, $prod_imagen->message);
				}
			} else {
				$this->response->result = false;
				$this->response->SetResponse(false, 'NO se cargo la imagen, vuelva a intentarlo más tarde');
			}

			return $response->withJson($this->response);
		})->add( new MiddlewareToken() );

		/***
		 * Ruta para modificar un registro de la tabla prod_imagen mediante su ID
		 * recibe {id} ID de la imagen
		 * ***/
		$this->put('edit/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->prod_imagen->edit($request->getParsedBody(), $arguments['id']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Actualización imagen producto', 'prod_imagen', $arguments['id']);
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
		 * Ruta para dar de baja una imagen de la base de datos mediante su ID
		 * recibe {id} ID de la imagen a eliminar de la base de datos
		 * ***/
		$this->put('del/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			
			$imagen = $this->model->prod_imagen->get($arguments['id']);
			if($imagen->response) {
				$this->response->state = $this->model->transaction->iniciaTransaccion();

				$imagen = $imagen->result;
				$img = $this->response = $this->model->prod_imagen->del($arguments['id']);
				if($img->response) {
					$seg_log = $this->model->seg_log->add('Baja imagen producto', 'prod_imagen', $arguments['id']);
					if($seg_log->response) {
						unlink("assets/image/productos/$imagen->nombre");
						unlink("assets/image/productos/th_$imagen->nombre");

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
					$this->response->result = $img->result;
					$this->response->errors = $img->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->SetResponse(false, $img->message);
				}
			} else {
				$this->response->SetResponse(false, $imagen->message);
			}

			return $response->withJson($this->response);
		})->add( new MiddlewareToken() );
		
		/*** 
		 * Ruta para mover una imagen de un producto al servidor
		 */
		$this->post('uploadFoto', function($request, $response, $arguments) {
			$this->response = new Response();
			$parsedBody = $request->getParsedBody();
			$files = $request->getUploadedFiles();
			$file = $files['imagen'];

			if($file->getError() === UPLOAD_ERR_OK ) {
				$this->response->state = $this->model->transaction->iniciaTransaccion();
				$data = ['producto_id' => $parsedBody['producto_id']];
				$imagen = $this->model->prod_imagen->add($data);
				if($imagen->response) {
					$id_imagen = $imagen->result;

					$directory  = 'assets/image/productos/';
					$extension  = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);
					$this->response->filename = $parsedBody['producto_id'].'_'.$id_imagen.'.'.$extension;
					$pathToImage = $directory.$this->response->filename;
					$pathToThImage = $directory.'th_'.$this->response->filename;

					$this->model->prod_imagen->resize($file->file, 470, $pathToThImage);
					$this->model->prod_imagen->resize($file->file, 1024, $pathToImage);
					unlink($file->file);

					$data = ['nombre' => $this->response->filename];
					$this->model->prod_imagen->edit($data, $id_imagen);
					
					$seg_log = $this->model->seg_log->add('Alta imagen producto', 'prod_imagen', $id_imagen);
					if($seg_log->response) {
						$this->response->result = $id_imagen;
						$this->response->image = $this->model->prod_imagen->get($id_imagen)->result;
						$this->response->state = $this->model->transaction->confirmaTransaccion();
						$this->response->SetResponse(true, 'Archivo cargado con exito: '.$this->response->filename);
					} else {
						$this->response->result = $seg_log->result;
						$this->response->errors = $seg_log->errors;
						$this->response->state = $this->model->transaction->regresaTransaccion();
						$this->response->SetResponse(false, $seg_log->message);
					}
				} else {
					$this->response->result = $imagen->result;
					$this->response->errors = $imagen->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->SetResponse(false, $imagen->message);
				}
			}
			
			return $response->withJson($this->response);
		})->add( new MiddlewareToken() );
	});
?>