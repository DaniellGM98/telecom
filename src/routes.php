<?php

use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

return function (App $app) {
	$container = $app->getContainer();

	$app->post('/pago/mercadopago', function(Request $request, Response $response, array $args) use($container) {
		require_once '../vendor/autoload.php';
		$this->response = new \App\Lib\Response();
		session_start();

		MercadoPago\SDK::initialize(); 
		$config = MercadoPago\SDK::config();
		$config->set('ACCESS_TOKEN', 'APP_USR-5164978426477581-050601-5637d8f2a89f591b59bfd035436cdca6-548343978');

		$parsedBody = $request->getParsedBody();
		$item = new MercadoPago\Item();
		$item->title = $parsedBody['titulo'];
		$item->quantity = intval($parsedBody['cantidad']);
		$item->unit_price = floatval($parsedBody['precio']);
		$item->currency_id = "MXN";

		$preference = new MercadoPago\Preference();
		$preference->items = array($item);

		$preference->external_reference = $parsedBody['external_reference'];
		$preference->auto_return = "approved";
		$preference->back_urls = array(
			"success" => $parsedBody['url']."?status=success",
			"pending" => $parsedBody['url']."?status=pending"
		);

		$preference->payment_methods = array(
			"excluded_payment_types" => array(
			  array("id" => "ticket"),
			  array("id" => "atm")
			),
			"installments" => 1
		  );
		
		$preference->save();

		$this->response->preference = $preference;
		$this->response->result = $preference->init_point;
		return $response->withHeader('Content-type', 'application/json')
			->write(json_encode($this->response->SetResponse(true)));
	});

	$app->get('/venta/print/{venta}', function (Request $request, Response $response, array $args) use ($container) {
		$this->logger->info("Slim-Skeleton '/print/' ".$args['venta']);

		session_start();
		if(isset($_SESSION['usuario'])) {
			$params = array('vista' => 'Venta');
			if($args['venta'] == '') {
				return $this->renderer->render($response, '404.phtml', $params);
			}else{
				try{
					$modulo = 1;
					$user = $_SESSION['usuario']->id;
					$perm = $this->model->usuario->getAcciones($user, $modulo);
					$arrPerm = getPermisos($perm);


					$idVenta = $args['venta'];
					$venta = $this->model->venta->get($idVenta)->result;
					$cliente = $this->model->cliente->get($venta->fk_cliente)->result;
					$det = $this->model->venta_detalle->getByVenta($idVenta)->result;
					$params = array('vista' => 'Venta', 'permisos' => $arrPerm, /*'todo' => $this, */
									'venta' => $venta, 'detalles' => $det, 'cliente' => $cliente );
					if(in_array($modulo, $arrPerm) && intval($venta->fk_cfdi) == 0) {
						return $this->view->render($response, 'nota_venta.phtml', $params);
					}else if(in_array($modulo, $arrPerm) && $venta->fk_cfdi > 0) {
						$cfdi = $this->model->cfdi->get($venta->fk_cfdi)->result;
						$params['cfdi'] = $cfdi;
						return $this->view->render($response, 'cfdi.phtml', $params);
					}
					else
						return $this->renderer->render($response, '403.phtml', $params);
				} catch (Throwable $t) { // PHP 7+
					echo '<br>throw 7+'; exit();
					return $this->renderer->render($response, '404.phtml', $params);
				} catch (Exception $e) { // PHP < 7
					return $this->renderer->render($response, '404.phtml', $params);
				}
			}
		}else{
			return $this->view->render($response, 'login.phtml', $args);
		}

	});

	$app->get('/cotizacion/print/{cotizacion}', function (Request $request, Response $response, array $args) use ($container) {
		$this->logger->info("Slim-Skeleton '/print/' ".$args['cotizacion']);

		if(!isset($_SESSION)) { session_start(); }
		if(isset($_SESSION['usuario'])) {
			$params = array('vista' => 'Cotización');
			if($args['cotizacion'] == '') {
				return $this->renderer->render($response, '404.phtml', $params);
			}else{
				try{
					$modulo = 10;
					$user = $_SESSION['usuario']->id;
					$perm = $this->model->usuario->getAcciones($user, $modulo);
					$arrPerm = getPermisos($perm);

					$idCotizacion = $args['cotizacion'];
					$cotizacion = $this->model->apartado->get($idCotizacion)->result;
					$cliente = $this->model->cliente->get($cotizacion->fk_cliente)->result;
					$det = $this->model->apartado_detalle->getByApartado($idCotizacion)->result;
					$params = array('vista' => 'Venta', 'permisos' => $arrPerm, /*'todo' => $this, */
									'cotizacion' => $cotizacion, 'detalles' => $det, 'cliente' => $cliente );
					// var_dump($cotizacion);
					// if(in_array($modulo, $arrPerm) && intval($cotizacion->fk_cfdi)==0) {
						return $this->view->render($response, 'nota_cotizacion.phtml', $params);
					// } 
					// else if(in_array($modulo, $arrPerm) && $venta->fk_cfdi>0) {
						// $cfdi = $this->model->cfdi->get($venta->fk_cfdi)->result;
						// $params['cfdi'] = $cfdi;
						// return $this->view->render($response, 'cfdi.phtml', $params);
					// }
					// else {
						// return $this->renderer->render($response, '403.phtml', $params);
					// }
				} catch (Throwable $t) { // PHP 7+
					echo '<br>throw 7+'; exit();
					return $this->renderer->render($response, '404.phtml', $params);
				} catch (Exception $e) { // PHP < 7
					return $this->renderer->render($response, '404.phtml', $params);
				}
			}
		}else{
			return $this->view->render($response, 'login.phtml', $args);
		}

	});

	$app->get('/confirm-email/{codigo}', function (Request $request, Response $response, array $arguments) use ($container) {
		// try{
			if(!isset($_SESSION)) { session_start(); }
			if(!isset($_SESSION['usuario'])) {
				$usuario = $this->model->usuario->getByCodigoVerificacion($arguments['codigo'])->result;
				// var_dump($usuario);
				if($usuario) {
					$data = ['status' => 1, 'codigo' => ''];
					$confirm = $this->model->usuario->edit($data, $usuario->id);
					if($confirm->response) {
						return $this->response->withRedirect('../login');
					}
				}
			} else {
				return $this->response->withRedirect('../tienda');
			}
		// } catch (Throwable $t) { // PHP 7+
		// 	// return $this->renderer->render($response, '404.phtml', []);
		// } catch (Exception $e) { // PHP < 7
		// 	// return $this->renderer->render($response, '404.phtml', []);
		// }
	});

	$app->get('/productos', function (Request $request, Response $response, array $args) use ($container) {
		try {
			$params = array('vista' => 'Productos');
			if(isset($_SESSION['usuario'])) {
				if($_SESSION['usuario']->usuario_tipo_id!=3) {
					$arrMod = array('usuarios' => 5, 'productos' => 2, 'kardex' => 2, 'sucursales' => 6, 'clientes' => 7, 'entradas' => 3, 'ventas' => 1, 'precios' => 9, 'apartados' => 10, 'cotizaciones' => 10, 'pagos' => 10, 'resultados' => 11, 'marcas' => 2, 'categorias' => 2, 'descuentos' => 9, 'utilidades' => 9, 'servicios' => 2, 'cobranza' => 12);
					if(array_key_exists('productos', $arrMod)) {
						// $modulo = 2;
						$modulo = 0;
						$user = $_SESSION['usuario']->id;
						$perm = $this->model->seg_permiso->getByUsuario($user, $modulo)->result;
						$arrPerm = getPermisos($perm);

						$params = array('vista' => 'Productos', 'permisos' => $arrPerm, 'todo' => $this);
						// if(hasPermission($modulo)) {
						if(hasPermission(2)) {
							$params['categorias'] = $this->model->prod_categoria->getAll()->result;
							$params['subcategorias'] = $this->model->prod_subcategoria->getAll()->result;
							$params['marcas'] = $this->model->marca->getAll()->result;
							$params['sucursales'] = $this->model->sucursal->getAll()->result;
							$params['listas_precio'] = $this->model->prod_lista_precio->getAll()->result;
							return $this->renderer->render($response, "productos.phtml", $params);
						} else {
							return $this->renderer->render($response, '403.phtml', $params);
						}
					}
					
					return $this->renderer->render($response, $args['name'].'.phtml', $params);
				}
			}
		} catch (Throwable $e) {
			return $this->renderer->render($response, '404.phtml', $params);
		} catch (Exception $e) {
			return $this->renderer->render($response, '404.phtml', $params);
		}
		
		return $this->view->render($response, "login.phtml", $params);
	});

	$app->get('/[{name}]', function (Request $request, Response $response, array $args) use ($container) {
		$this->logger->info("Slim-Skeleton '/' ".$args['name']);
		$params = array('vista' => ucfirst($args['name']));

		// $this->model->seg_sesion->logout();
		require_once './core/defines.php';
		/*$arrCategorias = [1, 2, 3, 4];
		$params['categories'] = [];
		foreach($arrCategorias as $categoria) {
			$category = $this->model->prod_categoria->get($categoria)->result;
			$category->subcategorias = $this->model->prod_subcategoria->getByCategoria($categoria)->result;
			// $category->subcategorias = $this->model->prod_categoria->getByCategoria($categoria)->result;
			$category->imagen = $this->model->prod_categoria->getImage($categoria)->result;
			$params['categories'][] = $category;
		}
		$event = $this->model->evento->getAll(1, 3, 2, 'inicio')->result;
		if(is_array($event)) {
			$params['events'] = $event;
		};

		$params['newArrivals'] = $this->model->producto->search(1, 20, 0, 0, 0, '_', -1, -1, 2, 'recien_llegados')->result;
		$params['bestSellers'] = $this->model->producto->search(1, 6, 0, 0, 0, '_', -1, -1, 2, 'best_seller')->result;
		$params['featured'] = $this->model->producto->search(1, 20, 0, 0, 0, '_', -1, -1, 2, 'featured')->result;
		$params['featured_side'] = $this->model->producto->search(1, 6, 0, 0, 0, '_', -1, -1, 2, 'featured')->result;

		$arrCategorias = [1, 2, 3, 4, 5, 6, 7, 8, 9];
		$params['productsByCategory'] = [];
		foreach($arrCategorias as $categoria) {
			$category = $this->model->prod_categoria->get($categoria)->result;
			$category->products = $this->model->producto->search(1, 5, 0, $category->id, 0, '_', -1, -1, 2, 'best_seller')->result;
			$category->icono = $this->model->prod_categoria->getImage($categoria)->result;
			$params['productsByCategory'][] = $category;
		}*/

		if(isset($_SESSION['usuario'])) {
			if($_SESSION['usuario']->usuario_tipo_id!=3) {
				try{
					if(isset($_SESSION['sucursal'])) {
						if(intval($_SESSION['sucursal'])!=0 && $this->model->sucursal->get($_SESSION['sucursal'])->result->empleado_id == null) { unset($_SESSION['sucursal']); }
					}
					$arrMod = array('usuarios' => 5, 'productos' => 2, 'kardex' => 2, 'sucursales' => 6, 'clientes' => 7, 'entradas' => 3, 
								'ventas' => 1, 'precios' => 9, 'apartados' => 10, 'cotizaciones' => 10, 'pagos' => 10, 'resultados' => 11, 
								'marcas' => 2, 'categorias' => 2, 'descuentos' => 9, 'utilidades' => 9, 'servicios' => 2, 'cobranza' => 12, 
								'traspasos' => 2, 'ingresos-gastos' => 13, 'reparaciones' => 15, 'recargas' => 1, 'corte' => 16, 'sueldos' => 17, 
								'buscar' => 2, 'reportes' => 4, 'perfil' => 5, 'distribuidores' => 8, 'garantias' => 18,'buscar1' => 2);
					if(array_key_exists($args['name'], $arrMod)) {
						$modulo = $arrMod[$args['name']];
						$user = $_SESSION['usuario']->id;
						// $perm = $this->model->seg_permiso->getByUsuario($user, $modulo)->result;
						$perm = $this->model->seg_permiso->getByUsuario($user)->result;
						$arrPerm = getPermisos($perm);

						$params = array('vista' => ucfirst($args['name']), 'permisos' => $arrPerm, 'todo' => $this);
						if($args['name'] == 'ingresos-gastos') $params['vista'] = 'Entradas y Retiros';
						if(isset($_SESSION['sucursal']) && intval($_SESSION['sucursal'])>0) {
							$solicitudes = $this->model->traspaso->getSolicitudesPendientes($_SESSION['sucursal'])->result;
							foreach($solicitudes as &$solicitud) {
								$solicitud->suc_origen = $this->model->sucursal->get($solicitud->origen)->result;
								$solicitud->suc_destino = $this->model->sucursal->get($solicitud->destino)->result;
								$solicitud->empleado = $this->model->usuario->get($solicitud->empleado_id)->result;
								$solicitud->detalles = $this->model->traspaso_detalle->getByTraspaso($solicitud->id)->result;
							}
							$params['solicitudes'] = $solicitudes;
						}
						/*if($args['name']=='resultados') {
							date_default_timezone_set('America/Mexico_City');
							$historial = $this->model->edo_resultados->getAll(0, 0, date('01-Y'), date('m-Y'));
							$params['total_ventas'] = $this->model->venta->getTotalVentas(date('m'), date('Y'))->result;
							$params['historial'] = $historial->result;
							$params['historial_total'] = $historial->total;

							$params['permisos'] = array_merge($arrPerm, getPermisos($this->model->usuario->getAcciones($user, 4)));
						}*/

						//if($modulo == 15) $param['sucursales'] = $this->model->sucursal
						if($args['name']=='entradas') $params['categorias'] = $this->model->prod_categoria->getAll()->result;
						if($args['name']=='recargas') $params['sucursales'] = $this->model->sucursal->getAll()->result;

						if(hasPermission($modulo))
							return $this->renderer->render($response, "$args[name].phtml", $params);
						else
							return $this->renderer->render($response, '403.phtml', $params);
					}
					
					return $this->renderer->render($response, $args['name'].'.phtml', $params);
				} catch (Throwable $e) {
					// return $this->renderer->render($response, '404.phtml', $params);
				} catch ( Exception $e) {
					// return $this->renderer->render($response, '404.phtml', $params);
				}
			}
		}
		if(!isset($args['name']) || $args['name'] == '')
		return $this->view->render($response, "index.phtml", $params);
		
		return $this->view->render($response, "$args[name].phtml", $params);
	});
	
	$app->get('/reporte/{tipo}', function(Request $request, Response $response, array $args) use ($container) {
		$this->logger->info("Slim-Skeleton '/' ".$args['tipo']);
		$params = array('vista' => 'Reporte '.ucfirst($args['tipo']));
		require_once './core/defines.php';

		if(isset($_SESSION['usuario'])) {
			if($_SESSION['usuario']->usuario_tipo_id == 1) {
				try{
					$perm = $this->model->seg_permiso->getByUsuario($user)->result;
					$params['permisos'] = getPermisos($perm);


					$params['categorias'] = $this->model->prod_categoria->getAll()->result;
					$params['vendedores'] = $this->model->empleado->getAll()->result;
					$params['sucursales'] = $this->model->sucursal->getAll()->result;

					if(hasPermission(4))
							return $this->renderer->render($response, "rpt_$args[tipo].phtml", $params);
						else
							return $this->renderer->render($response, '403.phtml', $params);
				} catch (Throwable $e) {
					// return $this->renderer->render($response, '404.phtml', $params);
				} catch ( Exception $e) {
					// return $this->renderer->render($response, '404.phtml', $params);
				}
			}
		}
		return $this->view->render($response, "login.phtml", $params);
	});


};

function getPermisos($arrPerm) {
	$res = array();
	foreach ($arrPerm as $perm) {
		$res[] = $perm->accion_id;
	}
	return $res;
}

function hasPermission($mod) {
	$hasPerm = false;
	foreach($_SESSION['permisos'] as $modulo) {
		if($modulo->id == $mod) {
			$hasPerm = true;
			break;
		}
	}
	return $hasPerm;
}