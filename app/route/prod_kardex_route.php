<?php
	use App\Lib\Response;
	use App\Lib\MiddlewareToken;

	/*** Grupo bajo la ruta prod_kardex ***/  
	$app->group('/prod_kardex/', function () {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de prod_kardex');
		});

		/*** Ruta para buscar prod_kardex ***/
		$this->get('find/{filtro}', function($request, $response, $arguments) {
			return $response->withJson($this->model->prod_kardex->find($arguments['filtro']));
		});
		
		/*** Ruta para obtener los datos de prod_kardex por medio del ID ***/
		$this->get('get/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->prod_kardex->get($arguments['id']));
		});

		/*
		 * Ruta para obtener el stock por medio de la sucursal
		 * Recibe $sucursal_id id de sucursal, $producto_id id del producto 
		 * Actualización: 17-10-19
		 * Actualizo: Angel Gabriel Ramirez Alva
		 */
		$this->get('getStockSuc/{sucursal_id}/{producto_id}', function($request, $response, $arguments) {
			$prod_stock = $this->model->prod_kardex->getStockSuc($arguments['sucursal_id'], $arguments['producto_id']);
			if(isset($_GET['prodInfo'])) { $prod_stock->prodInfo = $_GET['prodInfo']; }
			if(isset($_GET['tipo'])) { $prod_stock->tipo = $_GET['tipo']; }
			if(isset($_GET['cantidad'])) { $prod_stock->cantidad = $_GET['cantidad']; }

			$producto = $this->model->producto->get($arguments['producto_id'])->result;
			$prod_categoria = $this->model->prod_categoria->get($producto->prod_categoria_id)->result;
			if(intval($prod_categoria->tiene_sku) == 1) {
				$disponibles = $this->model->prod_entrada_detalle->getListaSkuDisp($arguments['producto_id'], $arguments['sucursal_id']);
				$prod_stock->skuDisponibles = [];
				foreach($disponibles->result as $disp) {
					$prod_stock->skuDisponibles[] = $disp->sku;
				}
			}

			return $response->withJson($prod_stock);
		});

		/*
		 * Ruta para obtener el kardex completo de un producto por medio de la sucursal
		 * Recibe $producto $sucursal, $inicio, $fin  
		 * Fecha de creación: 18-10-2019
		 * Autor: Angel Gabriel Ramirez Alva
		 */
		$this->get('getKardexSucursal/{producto_id}/{sucursal_id}/{inicio}/{fin}', function($request, $response, $arguments) {
			$productos = $this->model->prod_kardex->getKardexSucursal($arguments['producto_id'], $arguments['sucursal_id'], $arguments['inicio'], $arguments['fin']);
			foreach($productos->result as $producto) {
				$empleado = $this->model->usuario->get($producto->empleado_id)->result;
				$producto->nombre = $empleado->nombre;
				$producto->apellidos = $empleado->apellidos;
				if($producto->origen_tipo == 1) { $folio = $this->model->prod_entrada->get($producto->origen)->result->folio; }
				else if($producto->origen_tipo == 2) { $folio = $this->model->venta->get($producto->origen)->result->folio; }
				else { $folio = " "; }
				$producto->folio = $folio;
			}

			return $response->withJson( $productos );
		});	

		$this->get('getByProducto/{producto_id}/{inicio}/{fin}', function($request, $response, $arguments) {
			$productos = $this->model->prod_kardex->getByProducto($arguments['producto_id'], $arguments['inicio'], $arguments['fin']);
			foreach($productos->result as $producto) {
				$empleado = $this->model->usuario->get($producto->empleado_id)->result;
				$producto->nombre = $empleado->nombre;
				$producto->apellidos = $empleado->apellidos;
				if($producto->origen_tipo == 1) { $folio = $this->model->prod_entrada->get($producto->origen)->result->folio; }
				else if($producto->origen_tipo == 2) { $folio = $this->model->venta->get($producto->origen)->result->folio; }
				else { $folio = " "; }
				$producto->folio = $folio;
			}

			return $response->withJson( $productos );
		});	

		/*** Ruta para obtener los datos de los prod_kardex ***/
		$this->get('getAll/', function($request, $response, $arguments) {
			return $response->withJson($this->model->prod_kardex->getAll());
		});

		/*** Ruta para agregar un prod_kardex ***/
		$this->post('add/', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody();

			$resultado = $this->model->prod_kardex->add($parsedBody);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Alta prod_kardex', 'prod_kardex', $resultado->response);
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

		/* Ruta para agregar un movimiento en el kardex de productos 
		 * Recibe: producto, sucursal, cantidad, tipo 
		 * Autor: Angel Gabriel Ramirez Alva
		 */
		$this->post('addKardex/', function($request, $response, $arguments) {
			require_once './core/defines.php';
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody();
			$stockFinal = ($this->model->prod_kardex->getStockSuc($parsedBody['sucursal_id'], $parsedBody['producto_id']))->result->final;
			$parsedBody['inicial'] = $stockFinal;
			$parsedBody['final'] = $stockFinal + ($parsedBody['tipo'] * $parsedBody['cantidad']);
			$parsedBody['empleado_id'] = $_SESSION['usuario']->id;

			$resultado = $this->model->prod_kardex->add($parsedBody);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Alta prod_kardex', 'prod_kardex', $resultado->response);
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

		/*** Ruta para modificar un prod_kardex ***/
		$this->put('edit/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->prod_kardex->edit($request->getParsedBody(), $arguments['id']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Actualización información prod_kardex', 'prod_kardex', $arguments['id']);
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

		/*** Ruta para dar de baja un prod_kardex ***/
		$this->put('del/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->prod_kardex->del($arguments['id']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Baja prod_kardex', 'prod_kardex', $arguments['id']);
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

		$this->get('fix/{producto_id}/{sucursal_id}/[{desde}]', function($request, $response, $arguments) {
			$kardex = $this->model->prod_kardex->getKardexSucFrom($arguments['producto_id'], $arguments['sucursal_id'], $arguments['desde']);

			$inicial = 0; $final = 0;
			echo "\nINICIAL\tCANT\tFINAL\t\tERROR\n";
			foreach ($kardex as $mov) {
				$final = $inicial + ($mov->tipo * $mov->cantidad);
				echo "$inicial\t".($mov->cantidad*$mov->tipo)."\t$final\t\t".strval($inicial != $mov->inicial)."\n";
				if($inicial != $mov->inicial || $final != $mov->final){
					//$this->model->prod_kardex->edit(['inicial' => $inicial, 'final' => $final], $mov->id);
				}
				$inicial = $final;
			}
			echo "\n";
			

			return $response->withJson( $kardex );
		});	
	});
?>