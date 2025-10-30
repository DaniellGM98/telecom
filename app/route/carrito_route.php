<?php
	use App\Lib\Response, 
		Slim\App;
	use Envms\FluentPDO\Literal;
	use App\Lib\MiddlewareToken;
	if(!isset($_SESSION))	session_start();

	/*** Grupo bajo la ruta carrito ***/  
	$app->group('/carrito/', function() {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')
				->write('Soy ruta de carrito');
		})->add( new MiddlewareToken() );

		/*** 
		 * Ruta para obtener un registro de la tabla carrito mediante su ID
		 * recibe {ID} ID del carrito
		 * regresa: registro con el ID proporcionado
		 * ***/
		$this->get('get/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->carrito->get($arguments['id']));
		});

		/***
		 * Ruta para agregar un nuevo registro en la base de datos
		 * regresa el ID del nuevo registro
		 */
		$this->post('add/', function($request, $response, $arguments) {
			require_once './core/defines.php';
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody();
			$idProd = $parsedBody['producto_id'];	$cant = $parsedBody['cantidad'];

			$app = new App($this);
			$precio = json_decode((string) $app->subRequest('GET', "/prod_precio/getByProducto/$idProd")->getBody());

			if(isset($_SESSION['usuario'])) {
				$cliente = $_SESSION['usuario']->id;
				$itemExist = $this->model->carrito->getByProd($idProd, $cliente)->result;
				if(is_object($itemExist)) {
					$data = array('cantidad' => new Literal('cantidad + '.$cant));
					$added = $this->model->carrito->edit($data, $itemExist->id);
				} else {
					$parsedBody['fecha'] = new Literal('NOW()');
					$parsedBody['cliente_id'] = $cliente;
					$added = $this->model->carrito->add($parsedBody);
					if(!$added->response) {
						$this->response->result = $added->result;
						$this->response->errors = $added->errors;
						$this->response->state = $this->model->transaction->regresaTransaccion();
						return $response->withJson($this->response->SetResponse(false, $added->message));
					}
				}
			}

			$prodInfo = $this->model->producto->get($idProd)->result;
			$item = array(
				'producto_id' => $idProd, 
				'cantidad' => $cant, 
				'producto' => $prodInfo->articulo.' '.$prodInfo->marca.' '.$prodInfo->modelo,
				'url' => $prodInfo->friendly_url,
				'thumbnail' => $this->model->prod_imagen->getThumbnail($idProd),
				'precio' => $precio->result, //$this->model->prod_precio->get($idProd, $_SESSION['id_lista_precio'])->result->precio,
				'stock' => $this->model->prod_stock->get($idProd, $_SESSION['id_sucursal'])->result->cantidad,
			);
			$item['importe'] = $item['cantidad'] * $item['precio'];
			$prods = 0;
			$tot = 0;
			
			if(isset($_SESSION['cart']['items'])) {
				$existe = false; $index = 0;
				foreach($_SESSION['cart']['items'] as $index => $cartItem) {
					if($cartItem['producto_id'] == $idProd) {
						$item['cantidad'] = $cartItem['cantidad'] + $cant;
						$item['importe'] = $item['cantidad'] * $cartItem['precio'];
						$existe = true;
						$_SESSION['cart']['items'][$index]['cantidad'] = $item['cantidad'];
						$_SESSION['cart']['items'][$index]['importe'] = $item['importe'];
					}
					$prods += $_SESSION['cart']['items'][$index]['cantidad'];
					$tot += $_SESSION['cart']['items'][$index]['importe'];
					//$index++;
				}
				if(!$existe) {
					$_SESSION['cart']['items'][] = $item;
					$prods += $item['cantidad'];
					$tot += $item['importe'];
				}
				$_SESSION['cart']['productos'] = $prods;
				$_SESSION['cart']['total'] = $tot;
			} else {
				$_SESSION['cart'] = array('productos' => $item['cantidad'], 'total' => $item['importe'], 'items' => array());
				$_SESSION['cart']['items'][] = $item;
				$_SESSION['cart']['productos'] = $item['cantidad'];
				$_SESSION['cart']['total'] = $item['importe'];
			}
			
			if(isset($_SESSION['usuario'])) {
				$seg_log = $this->model->seg_log->add('Alta producto carrito', 'carrito', $added->result);
				if($seg_log->response) {
					$this->response = array('response' => true, 'item' =>$item, 'productos' => $_SESSION['cart']['productos'], 'total' => number_format($_SESSION['cart']['total'],2), 'sesion' => $_SESSION['cart']);
					$this->response->state = $this->model->transaction->confirmaTransaccion();
					$this->response->SetResponse(true);
				} else {
					$this->response->result = $seg_log->result;
					$this->response->errors = $seg_log->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->SetResponse(false, $seg_log->message);
				}
				
				return $response->withJson($this->response);
			} else {
				return $response->withJson(array('response' => true, 'item' =>$item, 'productos' => $_SESSION['cart']['productos'], 'total' => number_format($_SESSION['cart']['total'],2), 'sesion' => $_SESSION['cart']));
			}
		});

		$this->post('del/', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody();
			$idProd = $parsedBody['producto_id'];

			$deleted = array('response' => true);
			$prods = 0;
			$tot = 0;
			$index = 0;		$indexExiste = -1;
			foreach ($_SESSION['cart']['items'] as $key => $cartItem) {
				if($cartItem['producto_id'] == $idProd) {
					$indexExiste = $key;
				} else {
					$prods += $cartItem['cantidad'];
					$tot += $cartItem['importe'];
				}
				$index++;
			}
			if($indexExiste > -1) unset($_SESSION['cart']['items'][$indexExiste]);
			$_SESSION['cart']['productos'] = $prods;
			$_SESSION['cart']['total'] = $tot;
			$deleted['productos'] = $prods;
			$deleted['total'] = number_format($tot,2);
			$deleted['index'] = $indexExiste;
			$deleted['sesion'] = $_SESSION['cart']['items'];
			if(isset($_SESSION['usuario'])) {
				$carrito_item = $this->model->carrito->getByProd($idProd, $_SESSION['usuario']->id)->result;
				$carrito = $this->model->carrito->delByCliProd($_SESSION['usuario']->id, $idProd);
				if($carrito->response) {
					$seg_log = $this->model->seg_log->add('Baja producto carrito', 'carrito', $carrito_item->id);
					if($seg_log->response) {
						$this->response = $deleted;
						$this->response->state = $this->model->transaction->confirmaTransaccion();
						$this->response->SetResponse(true);
					} else {
						$this->response->result = $seg_log->result;
						$this->response->errors = $seg_log->errors;
						$this->response->state = $this->model->transaction->regresaTransaccion();
						$this->response->SetResponse(false, $seg_log->message);
					}
				} else {
					$this->response->result = $carrito->result;
					$this->response->errors = $carrito->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->SetResponse(false, $carrito->message);
				}
			}

			return $response->withJson($this->response);
		});

		/***
		 * Ruta para modificar el carrito hecho a un producto
		 * recibe {id} ID del carrito
		 * ***/
		$this->put('edit/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->carrito->edit($request->getParsedBody(), $arguments['id']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Actualización información carrito', 'carrito', $arguments['id']);
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
		 * Ruta para borrar un carrito mediante su ID
		 * recibe {id} ID del carrito a eliminar
		 * ***/
		$this->put('del/{id}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->carrito->del($arguments['id']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Baja producto carrito', 'carrito', $arguments['id']);
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


		$this->post('update/', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody();
			$idProd = $parsedBody['producto_id'];	$cant = $parsedBody['cantidad'];

			if(isset($_SESSION['usuario'])) {
				$cliente = $_SESSION['usuario']->id;
				$itemExist = $this->model->carrito->getByProd($idProd, $cliente)->result;
				if(is_object($itemExist)) {
					$data = array('cantidad' => $cant);
					$added = $this->model->carrito->edit($data, $itemExist->id);
					if(!$added->response) {
						$this->response->result = $added->result;
						$this->response->errors = $added->errors;
						$this->response->state = $this->model->transaction->regresaTransaccion();
						return $response->withJson($this->response->SetResponse(false, $added->message));
					}
				}
			}
			
			$prods = 0;
			$tot = 0;
			if(isset($_SESSION['cart']['items'])) {
				$index = 0;
				foreach ($_SESSION['cart']['items'] as $index => $cartItem) {
					if($cartItem['producto_id'] == $idProd) {
						$_SESSION['cart']['items'][$index]['cantidad'] = $cant;
						$importe = $cant * $_SESSION['cart']['items'][$index]['precio'];
						$_SESSION['cart']['items'][$index]['importe'] = $importe;
					}
					$prods += $_SESSION['cart']['items'][$index]['cantidad'];
					$tot += $_SESSION['cart']['items'][$index]['importe'];
					$index++;
				}
				$_SESSION['cart']['productos'] = $prods;
				$_SESSION['cart']['total'] = $tot;
			}

			if(isset($_SESSION['usuario'])) {
				$seg_log = $this->model->seg_log->add('Actualización producto carrito', 'carrito', $itemExist->id);
				if($seg_log->response) {
					$this->response = array('response' => true, 'productos' => $_SESSION['cart']['productos'], 'total' => number_format($_SESSION['cart']['total'],2), 'sesion' => $_SESSION['cart']);
					$this->response->state = $this->model->transaction->confirmaTransaccion();
					$this->response->SetResponse(true);
				} else {
					$this->response->result = $seg_log->result;
					$this->response->errors = $seg_log->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->SetResponse(false, $seg_log->message);
				}
				
				return $response->withJson($added);
			} else {
				return $response->withJson(array('response' => true, 'productos' => $_SESSION['cart']['productos'], 'total' => number_format($_SESSION['cart']['total'],2), 'sesion' => $_SESSION['cart']));
			}
		});

		$this->get('getNumProducts/', function($request, $response, $arguments) {
			if(!isset($_SESSION)) { session_start(); }
			$this->response = new Response();

			$this->response->result = $_SESSION['cart']['productos'];
			return $response->withJson($this->response->SetResponse(true));
		});
	});
?>