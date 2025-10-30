<?php
	use App\Lib\Response;
	use App\Lib\MiddlewareToken;

	/*** Grupo bajo la ruta evento ***/  
	$app->group('/evento/', function() {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de evento');
		})->add( new MiddlewareToken() );

		/*** 
		 * Ruta para obtener detalle del evento mediante su ID
		 * recibe {id} ID del evento
		 * regresa: registro con el detalle del evento
		 * ***/
		$this->get('get/{id}', function($request, $response, $arguments) {
			$this->response = new Response();

			$evento = $this->model->evento->get($arguments['id']);
			$this->response->result = $evento->result;
			if($evento->response) {
				$this->response->image = $this->model->evento->getImage($arguments['id'])->result;
				$this->response->SetResponse(true);
			} else	$this->response->SetResponse(false, $evento->message);
			
			return $response->withJson($this->response);
		});

		/***
		 * Ruta para obtener la información de los eventos en los que esta registrado el producto específico
		 * recibe {fk_producto} ID del producto
		 * recibe opcional {start} fecha inicial permitida
		 * recibe opcional {end} fecha final permitida. Si no se proporciona esta fecha, se buscarán los eventos que estén activos en la fecha inicial
		 */
		$this->get('getByProducto/{fk_producto}[/{start}[/{end}]]', function($request, $response, $arguments) {
			$arguments['start'] = isset($arguments['start'])? $arguments['start']: null;
			$arguments['end'] = isset($arguments['end'])? $arguments['end']: null;
			return $response->withJson($this->model->evento->getByProducto($arguments['fk_producto'], $arguments['start'], $arguments['end']));
		});

		/*** 
		 * Ruta para obtener los eventos mediante la lista de precios 
		 * recibe {fk_lista_precio} ID de la lista de precios
		 * regresa: objeto con todos los eventos con la lista de precios específica
		 * ***/
		$this->get('getByListaPrecio/{fk_lista_precio}', function($request, $response, $arguments) {
			return $response->withJson($this->model->evento->getByListaPrecio($arguments['fk_lista_precio']));
		});

		/*** 
		 * Ruta para obtener los eventos mediante un rango de fechas específicas
		 * recibe {start} fecha inicial a buscar
		 * recibe opcional {end} fecha final a buscar. Si no se proporciona esta fecha se buscarán los eventos que esten activos en la fecha anterior
		 * regresa: objeto con todos los eventos que se realizen entre dichas fechas
		 * ***/
		$this->get('getByDate/{start}[/{end}]', function($request, $response, $arguments) {
			$arguments['end'] = isset($arguments['end'])? $arguments['end']: null;
			return $response->withJson($this->model->evento->getByDate($arguments['start'], $arguments['end']));
		});

		/*** 
		 * Ruta para obtener la información del evento con el slug específico
		 * recibe {slug} slug a buscar en la base de datos
		 * regresa: registro con la información del evento
		 * ***/
		$this->get('getBySlug/{slug}', function($request, $response, $arguments) {
			$this->response = new Response();

			$evento = $this->model->evento->getBySlug($arguments['slug']);
			$this->response->result = $evento->result;
			if($evento->response) {
				$this->response->image = $this->model->evento->getImage($evento->result->id)->result;
				$this->response->SetResponse(true);
			} else {
				$this->response->SetResponse(false, $evento->message);
			}
			
			return $response->withJson($this->response);
		});

		/***
		 * Ruta para obtener todos los eventos
		 * recibe opcional {page} número de página a visualizar
		 * recibe opcional {limit} número máximo de registros por página
		 * recibe opcional {nextOrCurrent} bándera para no contemplar los eventos de fechas pasadas
		 * recibe opcional {order} nombre del campo por el cual ordenar los resultados, si no se proporciona, ordenará aleatoriamente
		 */
		$this->get('getAll/[{page}/{limit}[/{nextOrCurrent}[/{order}]]]', function($request, $response, $arguments) {
			$arguments['page']          = isset($arguments['page'])? $arguments['page']: 0;
			$arguments['limit']         = isset($arguments['limit'])? $arguments['limit']: 0;
			$arguments['nextOrCurrent'] = isset($arguments['nextOrCurrent'])? $arguments['nextOrCurrent']: 0;
			$arguments['order']         = isset($arguments['order'])? $arguments['order']: 'RAND()';
			return $response->withJson($this->model->evento->getAll($arguments['page'], $arguments['limit'], $arguments['nextOrCurrent'], $arguments['order']));
		});
		
		/***
		 * Ruta para agregar un nuevo registro en la base de datos
		 */
		$this->post('add/', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->evento->add($request->getParsedBody());
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Alta evento', 'evento', $resultado->result);
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
		 * Ruta para modificar un registro de la base de datos por medio del ID
		 * recibe {id} ID del evento a modificar
		 * ***/
		$this->put('edit/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->evento->edit($request->getParsedBody(), $arguments['id']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Actualización información evento', 'evento', $arguments['id']);
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
		 * Ruta para dar de baja un registro de la base de datos
		 * recibe {id} ID del evento
		 * ***/
		$this->put('del/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->evento->del($arguments['id']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Baja evento', 'evento', $arguments['id']);
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
		 * Ruta para obtener la ruta hacia la imagen del evento con el ID específico
		 * recibe {id} ID del evento
		 * regresa: ruta de la imagen del evento
		 * ***/
		$this->get('getImage/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->evento->getImage($arguments['id']));
		});


		$this->post('uploadFoto', function($request, $response, $arguments) {
			$this->response = new Response();
			$parsedBody = $request->getParsedBody();
			$files = $request->getUploadedFiles();
			$file = $files['imagen'];
			$evento = $parsedBody['evento'];

			if($file->getError() === UPLOAD_ERR_OK ) {
				$this->response->state = $this->model->transaction->iniciaTransaccion();

				$directory  = 'assets/image/eventos/';
				$extension  = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);
				$this->response->filename = $evento.'.'.$extension;
				$pathToImage = $directory.$this->response->filename;
				//$pathToThImage = $directory.'th_'.$this->response->filename;

				//$this->model->prod_imagen->resize($file->file, 470, $pathToThImage);
				//$this->model->prod_imagen->resize($file->file, 1024, $pathToImage);
				//unlink($file->file);
				$seg_log = $this->model->seg_log->add('Alta imagen evento', 'evento', $evento);
				if($seg_log->response) {
					$file->moveTo($pathToImage);

					$this->response->result = $evento;
					$this->response->image = $this->model->evento->getImage($evento)->result;
					$this->response->state = $this->model->transaction->confirmaTransaccion();
					$this->response->SetResponse(true, 'Archivo cargado con exito: '.$this->response->filename);
				} else {
					$this->response->result = $seg_log->result;
					$this->response->errors = $seg_log->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->SetResponse(false, $seg_log->message);
				}
			} else {
				$this->response->SetResponse(false, 'Fallo al subir la imagen');
			}
			
			return $response->withJson($this->response);
		})->add( new MiddlewareToken() );


		$this->post('uploadSlider', function($request, $response, $arguments) {
			$this->response = new Response();
			$parsedBody = $request->getParsedBody();
			$files = $request->getUploadedFiles();
			$file = $files['imagen'];
			$slider = $parsedBody['slider'];

			if($file->getError() === UPLOAD_ERR_OK ) {
				$this->response->state = $this->model->transaction->iniciaTransaccion();
				
				$directory  = 'assets/image/slider/';
				$extension  = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);
				//$this->response->filename = 'home-slider-'.$slider.'.'.$extension;
				$this->response->filename = $slider.'.'.$extension;
				$pathToImage = $directory.$this->response->filename;
				$seg_log = $this->model->seg_log->add('Alta slider evento', 'evento', $evento);
				if($seg_log->response) {
					$file->moveTo($pathToImage);

					$this->response->result = $slider;
					$this->response->filename .= '?'.rand();
					$this->response->state = $this->model->transaction->confirmaTransaccion();
					$this->response->SetResponse(true, 'Archivo cargado con exito: '.$this->response->filename);
				} else {
					$this->response->result = $seg_log->result;
					$this->response->errors = $seg_log->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->SetResponse(false, $seg_log->message);
				}
			}else{
				$this->response->SetResponse(false, 'Fallo al subir la imagen');
			}
			
			return $response->withJson($this->response);
		})->add( new MiddlewareToken() );
	});
?>