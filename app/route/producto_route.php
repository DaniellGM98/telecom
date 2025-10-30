<?php
	use App\Lib\Response,
		App\Lib\MiddlewareToken;
use Envms\FluentPDO\Literal;

/*** Grupo bajo la ruta producto ***/
	$app->group('/producto/', function() {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de producto');
		})->add( new MiddlewareToken() );

		$this->get('findFromAllSucursales/{busqueda}', function($request, $response, $arguments) {
			return $response->withJson($this->model->producto->findFromAllSucursales($arguments['busqueda']));
		});

		$this->get('findProducto/{busqueda}/{sucursal_id}', function($request, $response, $arguments) {
			$productos = $this->model->producto->findProducto($arguments['busqueda'], $arguments['sucursal_id'])->result;
			foreach ($productos as $producto) {
					$producto->stockSuc = $this->model->producto->stockProduct($producto->id)->result;
			}
			return $response->withJson($productos);
		});
		
		/*** Ruta para buscar producto ***/
		$this->get('find/{filtro}[/{sucursal_id}[/{prod_categoria_id}]]', function($request, $response, $arguments) {
			$arguments['prod_categoria_id'] = isset($arguments['prod_categoria_id'])? $arguments['prod_categoria_id']: 0;
			$productos = $this->model->producto->find($arguments['filtro'], 0, 0, $arguments['prod_categoria_id'])->result;
			if(isset($arguments['sucursal_id']) && intval($arguments['sucursal_id'])>0) {
				foreach($productos as $producto) {
					$producto->stock = $this->model->prod_kardex->getStockSuc($arguments['sucursal_id'], $producto->id);
				}
			}

			return $response->withJson($productos);
		});

		/*** Ruta para obtener los datos de producto por medio del ID ***/
		$this->get('get/{id_producto}', function($request, $response, $arguments) {
			$producto = $this->model->producto->get($arguments['id_producto']);
			$precio_lista = $this->model->prod_precio->getListaPrecio($producto->result->id);
			$producto->result->precios = $precio_lista->result? $precio_lista->result: array();

			return $response->withJson($producto);
		});

		$this->get('getByMD5/{md5Value}[/{campo}]', function($request, $response, $arguments) {
			$arguments['campo'] = isset($arguments['campo'])? $arguments['campo']: 'nombre';
			return $response->withJson($this->model->producto->getByMD5($arguments['md5Value'], $arguments['campo']));
		});

		$this->get('getProductosServicios/{filtro}[/{sucursal_id}/{stockMin}[/{prod_categoria_id}]]', function($request, $response, $arguments) {
			$arguments['sucursal_id'] = isset($arguments['sucursal_id'])? $arguments['sucursal_id']: 0;
			$arguments['stockMin'] = isset($arguments['stockMin'])? $arguments['stockMin']: 0;
			$arguments['prod_categoria_id'] = isset($arguments['prod_categoria_id'])? $arguments['prod_categoria_id']: 0;
			$resultado = [];
			$productos = $this->model->producto->find($arguments['filtro'], $arguments['sucursal_id'], $arguments['stockMin'], $arguments['prod_categoria_id'])->result;
			foreach($productos as $producto) {
				$producto->tipo = "producto";
				$producto->identificador = $producto->producto;
				$resultado[] = $producto;
			}

			$servicios = $this->model->servicio->find($arguments['filtro'])->result;
			foreach($servicios as $servicio) {
				$productos = $this->model->prod_servicio->getByServicio($servicio->id)->result;
				if(count($productos)>0 || $servicio->tipo==1) {
					$servicio->tipo = $servicio->tipo == 1? "servicio": "paquete";
					$servicio->identificador = "$servicio->nombre ($servicio->tipo)";
					$servicio->regalo = 0;
					$resultado[] = $servicio;
				}
			}

			return $response->withJson($resultado);
		});

		/*** Ruta para obtener los datos de los producto ***/
		$this->get('getAll/', function($request, $response, $arguments) {
			$productos = $this->model->producto->getAll();
			foreach ($productos->result as $producto) {
				$precio_lista = $this->model->prod_precio->getListaPrecio($producto->id);
				$producto->precios = $precio_lista->result? $precio_lista->result: 0;
			}

			return $response->withJson($productos);
		});

		/* Ruta para obtener los datos del producto 
		 * {pagina}: El número de página que quieres obtener 
		 * {limite}: El limite de registros que quieres en cada consulta, ejemplo: 25 registros
		 * {prod_categoria_id}: prod_categoria_id del producto
		 * {prod_subcategoria_id}: prod_subcategoria_id del producto
		 * {sucursal_id}: sucursal
		 * {prod_lista_precio_id}: id del precio de lista
		 * {busqueda}: busqueda 
		 */
		$this->get('getAllBusca/[{pagina}/{limite}/{prod_categoria_id}[/{prod_subcategoria_id}[/{marca_id}/{sucursal_id}/{prod_lista_precio_id}/{busqueda}]]]', function($request, $response, $arguments) {   
			$arguments['pagina'] = isset($arguments['pagina'])? $arguments['pagina']: 0;
			$arguments['limite'] = isset($arguments['limite'])? $arguments['limite']: 0;
			$arguments['prod_categoria_id'] = isset($arguments['prod_categoria_id'])? $arguments['prod_categoria_id']: 0;
			$arguments['prod_subcategoria_id'] = isset($arguments['prod_subcategoria_id'])? $arguments['prod_subcategoria_id']: 0;
			$arguments['marca_id'] = isset($arguments['marca_id'])? $arguments['marca_id']: 0;
			$arguments['sucursal_id'] = isset($arguments['sucursal_id'])? $arguments['sucursal_id']: $_SESSION['id_sucursal'];
			$arguments['prod_lista_precio_id'] = isset($arguments['prod_lista_precio_id'])? $arguments['prod_lista_precio_id']: $_SESSION['id_prod_lista_precio'];
			$arguments['busqueda'] = isset($arguments['busqueda'])? $arguments['busqueda']: '_';
			$productos = $this->model->producto->getAllBusca($arguments['pagina'], $arguments['limite'], $arguments['prod_categoria_id'], $arguments['prod_subcategoria_id'], $arguments['marca_id'], $arguments['busqueda']);
			//$lista = $this->model->prod_lista_precio->getSucLista($arguments['sucursal_id'], $arguments['prod_lista_precio_id']);

			foreach($productos->result as $producto) {
				$precio_base = -1;
				$kardexSuc = $this->model->prod_kardex->getStockSuc($arguments['sucursal_id'], $producto->id);
				if($kardexSuc->result) {
					$producto->stockSuc = $kardexSuc->result->final;
				} else {$producto->stockSuc = '0';}
				//Verifico si es lista de descuento o general
				//$precio_lista = $this->model->prod_precio->getProdPrecio($producto->id, $lista->id_prod_lista_precio);
				/*if($lista->origen > 0) {
					$precio_base = $this->model->prod_precio->getProdPrecio($producto->id, $lista->origen)->result->precio;
					$producto->precio_lista = number_format($precio_base * (1 - ($lista->descuento/100)), 2);
				} else {
					$producto->precio_lista = number_format($this->model->prod_precio->getProdPrecio($producto->id, $lista->id)->result->precio,2);
				}*/
			}
			return $response->withJson($productos);
		});

		$this->get('getAllBuscaTemplate/{pagina}/{limite}/{prod_categoria_id}/{prod_subcategoria_id}/{marca_id}/{sucursal_id}/{prod_lista_precio_id}/{busqueda}', function($request, $response, $arguments) {
			if(!isset($_SESSION)) { session_start(); }
			if(!defined('MOD_PRODUCTOS_EDIT')) { define('MOD_PRODUCTOS_EDIT',	18); }
			if(!defined('MOD_PRODUCTOS_DEL')) { define('MOD_PRODUCTOS_DEL',	19); }
			if(!defined('MOD_TRASPASOS_ADD')) { define('MOD_TRASPASOS_ADD',	87); }
			$modulo = 2; $user = $_SESSION['usuario']->id; $perm = $this->model->usuario->getAcciones($user, $modulo); $permisos = getPermisos($perm);
			// print_r($perm);
			$data = [];
			
			$pagina				= isset($arguments['pagina'])? $arguments['pagina']: 0;
			$limite				= isset($_GET['length'])? $_GET['length']: (isset($arguments['limite'])? $arguments['limite']: 0);
			$inicial			= isset($_GET['start'])? $_GET['start']: $pagina*$limite;
			$categoria_id		= isset($arguments['prod_categoria_id'])? $arguments['prod_categoria_id']: 0;
			$subcategoria_id	= isset($arguments['prod_subcategoria_id'])? $arguments['prod_subcategoria_id']: 0;
			$marca_id			= isset($arguments['marca_id'])? $arguments['marca_id']: 0;
			$sucursal_id		= isset($arguments['sucursal_id'])? $arguments['sucursal_id']: $_SESSION['id_sucursal'];
			$lista_precio_id	= isset($arguments['prod_lista_precio_id'])? $arguments['prod_lista_precio_id']: $_SESSION['id_prod_lista_precio'];
			// $busqueda			= isset($arguments['busqueda'])? $arguments['busqueda']: ((isset($_GET['search']['value']) && strlen($_GET['search']['value'])>0)? $_GET['search']['value']: '_');
			$busqueda			= isset($_GET['search']['value'])? (strlen($_GET['search']['value'])>0? $_GET['search']['value']: $arguments['busqueda']): $arguments['busqueda'];
			$orden				= isset($_GET['order'])? $_GET['columns'][$_GET['order'][0]['column']]['data']: 'producto';
			$orden			   .= isset($_GET['order'])? " ".$_GET['order'][0]['dir']: " asc";
			$productos			= $this->model->producto->getAllBuscaTemplate($inicial, $limite, $categoria_id, $subcategoria_id, $marca_id, $sucursal_id, $lista_precio_id, $busqueda, $orden);
			//$lista				= $this->model->prod_lista_precio->getSucLista($sucursal_id, $lista_precio_id);

			foreach($productos->result as $producto) {
				$producto_id = $producto->id;
				//$producto->stock_sucursal = $this->model->prod_kardex->getStockSuc($sucursal_id, $producto->id)->result->final;
				$class = intval($producto->stock)==0? 'class="table-danger"': (intval($producto->stock_sucursal)==0? 'class="table-warning"': '');
				$estatus = intval($producto->stock)==0? 'RESURTIR<br/>(PRODUCTO AGOTADO)"': (intval($producto->stock_sucursal)==0? 'RESURTIR SUCURSAL"': 'NORMAL');
				$data[] = array(
					"data_id"				=> $producto_id,
					"sku"					=> "<small class='sku'>".$producto->sku."</small>",
					"producto"				=> "<small class='producto' data-id='$producto_id'>".$producto->producto."</small>",
					"categoria"				=> "<small class='categoria' data-id='$producto->prod_categoria_id'".(intval($producto->tiene_sku)==1? "data-sku='$producto->sku_nombre'": "").">".$producto->categoria."</small>",
					"subcategoria"			=> "<small class='subcategoria' data-id='$producto->prod_subcategoria_id'>".$producto->subcategoria."</small>",
					"marca"					=> "<small class='marca' data-id='$producto->marca_id'>".$producto->marca."</small>",
					"stock"					=> "<small class='stock'>$producto->stock</small>",
					"stock_sucursal"		=> "<small class='stock_sucursal'>$producto->stock_sucursal</small>",
					"precio_lista_precio"	=> "<small class='precio_lista'>$ ".number_format($producto->precio_lista_precio, 2)."</small>",
					"estatus"				=> "<small class='estatus'>$estatus</small>",
					"acciones"				=> "<div class='pull-right'>".(intval($producto->tiene_sku)==1? "<a href='#' data-popup='tooltip' title='Ver ".$producto->sku_nombre." en existencia' class='btn btn-xs btn-info btnSku' type='button'><i class='fa fa-lg fa-eye'></i></a> ": "").(in_array(MOD_TRASPASOS_ADD, $permisos)? "<a href='#' data-popup='tooltip' title='Traspaso' class='btn btn-xs btn-primary btnAddTraspaso' type='button'><i class='fa fa-lg fa-exchange'></i></a> ": "").(in_array(MOD_PRODUCTOS_EDIT, $permisos)? "<a href='#' data-popup='tooltip' title='Editar' class='btn btn-xs btn-success btnEditProducto' type='button'><i class='fa fa-lg fa-pencil'></i></a> ": "").(in_array(MOD_PRODUCTOS_DEL, $permisos)? "<a href='#' data-popup='tooltip' title='Eliminar' class='btn btn-xs btn-danger btnDelProducto' type='button'><i class='fa fa-lg fa-trash'></i></a>": "")."</div>",
					"class"					=> $class,
				);
			}
			return $response->withJson(array(
				'draw'				=> isset($_GET['draw'])? $_GET['draw']: 0,
				'data'				=> $data,
				'recordsTotal'		=> $productos->total,
				'recordsFiltered'	=> $productos->filtered,
			));
		});

		/*** 
		 * Ruta para buscar productos en base a filtros como categoría, subcategoría, precio máximo y mínimo, etc
		 * recibe {pagina} número de página.
		 * recibe {limite} número máximo de registros por página.
		 * recibe opcional {evento_id} ID del evento. Si este valor es 0, o no se proporciona, devolverá los registros sin tomar en cuenta la tabla evento
		 * recibe opcional {prod_categoria_id} arreglo con los IDs de las líneas de los cuales se quiere buscar productos. Si es 0, o si no se proporciona se buscarán de todas.
		 * recibe opcional {prod_subcategoria_id} arreglo con los IDs de las sublíneas de las cuales se quiere buscar productos. Si es 0, o si no se proporciona se buscarán de todas.
		 * recibe opcional {busqueda} puede ser articulo, marca, modelo o tag del producto
		 * recibe opcional {precio_min} precio mínimo que tendrán los productos. Si el campo no existe o es -1 no se contemplará este filtro
		 * recibe opcional {precio_max} precio máximo que tendrán los productos. Si el campo no existe o es -1 no se contemplará este filtro
		 * recibe opcional {order} forma en que se ordenarán los productos [1, ascendentemente], [2, descendentemente], [(0, o nada), no ordenar]
		 * recibe opcional {order_field} campo en base al cual se ordenarán los resultados
		 * regresa: objeto con la información de todas las subcategorías que comparten la misma categoría
		 * ***/
		$this->get('search/{pagina}/{limite}[/{evento_id}[/{prod_categoria_id}[/{prod_subcategoria_id}[/{busqueda}[/{price_min}/{price_max}[/{order}/{order_field}]]]]]]', function($request, $response, $arguments) {
			$arguments['evento_id'] = isset($arguments['evento_id'])? $arguments['evento_id']: 0;
			$arguments['prod_categoria_id'] = isset($arguments['prod_categoria_id'])? $arguments['prod_categoria_id']: 0;
			$arguments['prod_subcategoria_id'] = isset($arguments['prod_subcategoria_id'])? $arguments['prod_subcategoria_id']: 0;
			$arguments['busqueda'] = isset($arguments['busqueda'])? $arguments['busqueda']: '_';
			$arguments['price_min'] = isset($arguments['price_min'])? $arguments['price_min']: -1;
			$arguments['price_max'] = isset($arguments['price_max'])? $arguments['price_max']: -1;
			$arguments['order'] = isset($arguments['order'])? $arguments['order']: 1;
			$arguments['order_field'] = isset($arguments['order_field'])? $arguments['order_field']: 'id_producto';

			return $response->withJson($this->model->producto->search($arguments['pagina'], $arguments['limite'], $arguments['evento_id'], $arguments['prod_categoria_id'], $arguments['prod_subcategoria_id'], $arguments['busqueda'], $arguments['price_min'], $arguments['price_max'], $arguments['order'], $arguments['order_field']));
		});

		/*** Ruta para agregar un producto ***/
		$this->post('add/', function($request, $response, $arguments) {
			require_once './core/defines.php';
			$parsedBody = $request->getParsedBody();  
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			if(isset($parsedBody['precios'])) { $precios = $parsedBody['precios']; unset($parsedBody['precios']); }
			else { $precio = $parsedBody['precio']; unset($parsedBody['precio']); }
			if(isset($parsedBody['id'])) { unset($parsedBody['id']); }

			$producto = $this->model->producto->add($parsedBody);
			if($producto->response) { $id_producto = $producto->result;
				$fecha = date('Y-m-d H:i:s');
				$usuario = $_SESSION['usuario']->id;
				$data = [
					"producto_id" => $id_producto,
					"empleado_id" => $usuario,
					"fecha" => $fecha,
					"tipo" =>'1',
					"inicial" =>'0',
					"cantidad" => '0',
					"final" => '0',
					"origen" =>'0',
					"origen_tipo" =>'3',
				];
				$sucursales = $this->model->sucursal->getAll();
				foreach($sucursales->result as $sucursal) {
					$data['sucursal_id'] = $sucursal->id;
					$prod_kardex = $this->model->prod_kardex->add($data);
					if(!$prod_kardex->response) {
						$this->response->errors = $prod_kardex->errors;
						$this->response->result = $prod_kardex->result;
						$this->response->state = $this->model->transaction->regresaTransaccion();
						return $response->withJson($this->response->SetResponse(false, $prod_kardex->message));
					}
				}

				if(isset($precios)) {
					foreach ($precios as $precio) {
						$datos = [
							"producto_id" => $id_producto,
							"lista_precio_id" => $precio['fk_lista_precio'],
							"precio" => $precio['precio'],
							"actualizado" => $fecha,
							"empleado_id" => $usuario,
						];
						$prod_precio = $this->model->prod_precio->add($datos);
						if(!$prod_precio->response) {
							$this->response->errors = $prod_precio->errors;
							$this->response->result = $prod_precio->result;
							$this->response->state = $this->model->transaction->regresaTransaccion();
							return $response->withJson($this->response->SetResponse(false, $prod_precio->message));
						}
					}
				} else {
					$listas_precios = $this->model->prod_lista_precio->getAll()->result;
					foreach($listas_precios as $lista) {
						$datos = ['producto_id'=>$id_producto, 'lista_precio_id'=>$lista->id, 'precio'=>$precio, 'actualizado'=>$fecha, 'empleado_id'=>$usuario];
						$prod_precio = $this->model->prod_precio->add($datos);
						if(!$prod_precio->response) {
							$this->response->errors = $prod_precio->errors;
							$this->response->result = $prod_precio->result;
							$this->response->state = $this->model->transaction->regresaTransaccion();
							return $response->withJson($this->response->SetResponse(false, $prod_precio->message));
						}
					}
				}

				$seg_log = $this->model->seg_log->add('Registro nuevo producto', 'producto', $producto->result);
				if($seg_log->response) {
					$this->response->result = $producto->result;
					$this->response->state = $this->model->transaction->confirmaTransaccion();
					$this->response->SetResponse(true, 'Producto Agregado: '.$producto->result);
				} else {
					$this->response->result = $seg_log->result;
					$this->response->errors = $seg_log->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->SetResponse(false, $seg_log->message);
				}
			} else {
				$this->response->errors = $producto->errors;
				$this->response->state = $this->model->transaction->regresaTransaccion();
				$this->response->SetResponse(false, 'No se agrego el producto, revise su información: '.$producto->message);
			}

			$this->response->result = $producto->result;
			return $response->withJson($this->response);
			
		})->add( new MiddlewareToken() );//end add producto

		$this->get('import/', function($request, $response, $arguments){
			$ultimo = $this->db->from('import')->where('tabla', 'producto')->fetch();
			$registros = $this->dbOld
				->from('cat_producto')
				->where('id_producto > ?',$ultimo->ultimo)
				->orderBy('id_producto')
				->fetchAll();

			$count = 0;
			foreach ($registros as $reg) {
				// Buscar / Insertar Marca
				$marca = $this->db->from('marca')->where('nombre', $reg->marca)->fetch();
				if(!is_object($marca)){
					$marca = $this->model->marca->add(array('nombre' => $reg->marca));
					$idMarca = $marca->result;
				}else{
					$idMarca = $marca->id;
				}

				// Producto
				$data = array(
					'id' => $reg->id_producto,
					'prod_categoria_id' => $reg->fk_id_tipo_producto,
					'marca_id' => $idMarca,
					'nombre' => $reg->descripcion,
					'modelo' => $reg->modelo,
					'stock' => 0,
				);
				$producto = $this->model->producto->add($data);
				if($producto->response) {
					$idProd = $producto->result;

					// Kardex
					$dataKardex = [
						"producto_id" => $idProd,
						"empleado_id" => $_SESSION['usuario']->id,
						"fecha" => new Literal('NOW()'),
						"tipo" =>'1',
						"inicial" =>'0',
						"cantidad" => '0',
						"final" => '0',
						"origen" =>'0',
						"origen_tipo" =>'3',
					];
					$sucursales = $this->model->sucursal->getAll();
					foreach($sucursales->result as $sucursal) {
						$dataKardex['sucursal_id'] = $sucursal->id;
						$prod_kardex = $this->model->prod_kardex->add($dataKardex);
					}

					// Precio
					$listas = $this->model->prod_lista_precio->getAll()->result;
					foreach ($listas as $lista) {
						$dataPrecio = [
							"producto_id" => $idProd,
							"lista_precio_id" => $lista->id,
							"precio" => $reg->precio,
							"actualizado" => new Literal('NOW()'),
							"empleado_id" => $_SESSION['usuario']->id,
						];
						$prod_precio = $this->model->prod_precio->add($dataPrecio);
					}

					$this->db->update('import', array('ultimo' => $reg->id_producto))->where('tabla', 'producto')->execute();
					$count++;
				}
			}
			echo 'Listo se insertaron '.$count.' productos';
		});

		$this->get('importExistencias/', function($request, $response, $arguments){
			$ultimo = $this->db->from('import')->where('tabla', 'existencias')->fetch();
			$registros = $this->dbOld
				->from('inventario')
				->where('fecha > ?', new Literal($ultimo->fecha))
				->where('fk_id_producto >= '.$ultimo->ultimo)
				->where('fk_id_sucursal > 0')
				->where('estado',0)
				->orderBy('fecha')
				->orderBy('fk_id_producto')
				->orderBy('codigo')
				//->limit('78')
				->fetchAll();
			/*echo '<pre>';
			print_r($registros);
			echo '</pre>';
			exit(0);*/
			$user = 0; $suc = 0; $idEntrada = 0;
			$entradas = 0; $productos = 0;
			$noUser = array(); $noProd = array(); $noSuc = array();
			echo '<pre>';
			foreach ($registros as $reg) {
				$existeUser = $this->db->from('usuario')->where('id',$reg->fk_id_usuario)->fetch();
				if(is_object($existeUser)){
					$usuario = $reg->fk_id_usuario;
				}else{
					$usuario = 1;
					if(!in_array($reg->fk_id_usuario, $noUser)) $noUser[] = $reg->fk_id_usuario;
				}
				$existeProd = $this->db->from('producto')->where('id',$reg->fk_id_producto)->fetch();
				if(is_object($existeProd)){
					$existeSuc = $this->db->from('sucursal')->where('id',$reg->fk_id_sucursal)->fetch();
					if(is_object($existeSuc)){
						$sucursal = $reg->fk_id_sucursal;
					}else{
						if(!in_array($reg->fk_id_sucursal, $noSuc)) $noSuc[] = $reg->fk_id_sucursal;
						$sucursal = 4;
					}
					if($reg->fk_id_usuario != $user && $reg->fk_id_sucursal != $suc){
						$dataEntrada = array(
							'empleado_id' => $usuario, 
							'sucursal_id' => $sucursal, 
							'fecha' => $reg->fecha.'', 
							'folio' => $reg->fk_id_sucursal.date('Ymd'),
							'subtotal' => 0, 
							'total' => 0
						);
						$entrada = $this->model->prod_entrada->add($dataEntrada);
						if($entrada->result > 0) {
							$idEntrada = $entrada->result;
							$user = $reg->fk_id_usuario;
							$suc = $reg->fk_id_sucursal;
						}
						//echo json_encode($entrada->errors);
						//exit(0);
						$entradas++;
					}
					echo $user.'::'.$suc.'::'.$idEntrada.'::'.$reg->fk_id_producto;
					$dataDetalle = array(
						'prod_entrada_id' => $idEntrada, 
						'producto_id' => $reg->fk_id_producto,
						'cantidad' => 1,
						'costo' => 0,
						'importe' => 0, 
						'sku' => $reg->codigo,
					);
					$detalleEntrada = $this->model->prod_entrada_detalle->add($dataDetalle);
					//echo json_encode($detalleEntrada->errors);
					//exit(0);
					if($detalleEntrada->result > 0) {
						$dataKardex = [
							"empleado_id" => $usuario,
							"producto_id" => $reg->fk_id_producto,
							"sucursal_id" => $sucursal,
							"fecha" => $reg->fecha.'',
							"tipo" => 1,
							"inicial" => 0,
							"cantidad" => 1,
							"final" => 1,
							"origen" => $idEntrada,
							"origen_tipo" => 1,
						];
						$idAddKardex = $this->model->prod_kardex->add($dataKardex);
						$productos++;
						$this->db->update('import', array('ultimo' => $reg->fk_id_producto, 'fecha' => $reg->fecha))->where('tabla', 'existencias')->execute();
					}
					print_r($reg);
				}else{
					if(!in_array($reg->fk_id_producto, $noProd)) $noProd[] = $reg->fk_id_producto;
				}

			}
			echo '<hr>';
			echo "Se insertaron $productos productos en $entradas entradas<hr>";
			echo count($noUser).' usuarios no registrados';
			print_r($noUser);

			echo '<hr>';
			echo count($noProd).' productos no registrados';
			print_r($noProd);

			echo '<hr>';
			echo count($noSuc).' sucursales no registradas';
			print_r($noSuc);
			echo '</pre>';
			exit(0);
		});

		/*** Ruta para modificar un producto ***/
		$this->put('edit/{id}', function($request, $response, $arguments) {
			require_once './core/defines.php';
			$this->response = new Response();
			$parsedBody = $request->getParsedBody();
			$id_producto = $arguments['id']; $orgInfo = $this->model->producto->get($id_producto)->result;
			$areTheSame = true; foreach($parsedBody as $field => $value) {
				if($orgInfo->$field != $value) {
					$areTheSame = false; break;
				}
			}
			
			$producto = $this->model->producto->edit($request->getParsedBody(), $arguments['id']);
			if($producto->response || $areTheSame) { $this->response->areTheSame = $areTheSame;
				if(!$this->response->areTheSame) {
					$seg_log = $this->model->seg_log->add('Actualización información producto', 'producto', $arguments['id']);
					if($seg_log->response) {
						$this->response->result = $producto->result;
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
				$this->response->result = $producto->result;
				$this->response->errors = $producto->errors;
				$this->response->state = $this->model->transaction->regresaTransaccion();
				$this->response->SetResponse(false, $producto->message);
			}
			return $response->withJson($this->response);
		})->add( new MiddlewareToken() );

		/*** Ruta para dar de baja un producto ***/
		$this->put('del/{id}', function($request, $response, $arguments) {
			require_once './core/defines.php';
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$producto = $this->model->producto->del($arguments['id']);
			if($producto->response) {
				$seg_log = $this->model->seg_log->add('Producto dado de baja', 'producto', $arguments['id']);
				if($seg_log->response) {
					$this->response->result = $producto->result;
					$this->response->state = $this->model->transaction->confirmaTransaccion();
					$this->response->SetResponse(true);
				} else {
					$this->response->result = $seg_log->result;
					$this->response->errors = $seg_log->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->SetResponse(false, $seg_log->message);
				}
			}
			return $response->withJson($this->response);
		})->add( new MiddlewareToken() );

		$this->post('maxDescuento/', function($request, $response, $arguments) {
			require_once './core/defines.php';
			$this->response = new Response();
			$parsedBody = $request->getParsedBody();

			$prods = explode(',', $parsedBody['productos']);
			$totalSinDescuento = 0;
			$totalConDescuento = 0;
			foreach($prods as $prod) {
				$infoProd = explode('-', $prod);
				$id_prod = $infoProd[0];	$cant_prod = $infoProd[1];

				$prod_precio = $this->model->prod_precio->getProdPrecio($id_prod, $_SESSION['id_prod_lista_precio'])->result->precio;
				$producto = $this->model->producto->get($id_prod)->result;
				if($producto->utilidad != null) {
					$totalConDescuento += $prod_precio * $cant_prod * (1 - ($producto->utilidad / 100));
				} else {
					if($producto->marca_id != null) {
						$utilidad = $this->model->marca_utilidad->getAll(0, 0, $producto->prod_categoria_id, $producto->marca_id)->result;
						if(count($utilidad) > 0) {
							$totalConDescuento += $prod_precio * $cant_prod * (1 - ($utilidad[0]->utilidad / 100));
						} else {
							$totalConDescuento += $prod_precio * $cant_prod;
						}
					} else {
						$totalConDescuento += $prod_precio * $cant_prod;
					}
				}

				$totalSinDescuento += $prod_precio * $cant_prod;
			}

			$this->response->totalConDescuento = $totalConDescuento;
			$this->response->totalSinDescuento = $totalSinDescuento;
			$this->response->result = intval(100 - ($totalConDescuento * 100 / $totalSinDescuento));
			return $response->withJson($this->response->SetResponse(true));
		})->add( new MiddlewareToken() );
	});
?>