<?php
	//use Slim\App;

	//return function (App $app) {
		$container = $app->getContainer();

		// view renderer
		$container['renderer'] = function ($c) {
			$settings = $c->get('settings')['renderer'];
			return new \Slim\Views\PhpRenderer($settings['template_path']);
		};

		// monolog
		$container['logger'] = function ($c) {
			$settings = $c->get('settings')['logger'];
			$logger = new \Monolog\Logger($settings['name']);
			$logger->pushProcessor(new \Monolog\Processor\UidProcessor());
			$logger->pushHandler(new \Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
			return $logger;
		};

		// Database
			$container['db'] = function($c) {
				$connectionString = $c->get('settings')['connectionString'];
				
				$pdo = new PDO($connectionString['dns'], $connectionString['user'], $connectionString['pass']);

				$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

				return new \Envms\FluentPDO\Query($pdo);
				
			};
			
			// Register component view 
			$container['view'] = function ($container) {
				return new \Slim\Views\PhpRenderer('../templates/');
			};

			// Models
			$container['model'] = function($c) {
				return (object)[
					'seg_modulo' => new App\Model\SegModuloModel($c->db),
					'seg_accion' => new App\Model\SegAccionModel($c->db),
					'seg_permiso' => new App\Model\SegPermisoModel($c->db),
					'seg_sesion' => new App\Model\SegSesionModel($c->db),
					'seg_log' => new App\Model\SegLogModel($c->db),
					'seg_pwd_recover' => new App\Model\SegPwdRecoverModel($c->db),
					'usuario_tipo' => new App\Model\UsuarioTipoModel($c->db),
					'usuario' => new App\Model\UsuarioModel($c->db),
					'cliente' => new App\Model\ClienteModel($c->db),
					'empleado' => new App\Model\EmpleadoModel($c->db),
					'direccion' => new App\Model\DireccionModel($c->db),
					'apartado' => new App\Model\ApartadoModel($c->db),
					'apartado_detalle' => new App\Model\ApartadoDetalleModel($c->db),
					'apartado_pago' => new App\Model\ApartadoPagoModel($c->db),
					'sucursal' => new App\Model\SucursalModel($c->db),
					'producto' => new App\Model\ProductoModel($c->db),
					'prod_imagen' => new App\Model\ProdImagenModel($c->db),
					'prod_categoria' => new App\Model\ProdCategoriaModel($c->db),
					'prod_subcategoria' => new App\Model\ProdSubcategoriaModel($c->db),
					'prod_lista_precio' => new App\Model\ProdListaPrecioModel($c->db),
					'prod_precio' => new App\Model\ProdPrecioModel($c->db),
					'prod_entrada' => new App\Model\ProdEntradaModel($c->db),
					'prod_entrada_detalle' => new App\Model\ProdEntradaDetalleModel($c->db),
					'prod_salida' => new App\Model\ProdSalidaModel($c->db),
					'prod_salida_detalle' => new App\Model\ProdSalidaDetalleModel($c->db),
					'prod_kardex' => new App\Model\ProdKardexModel($c->db),
					'evento' => new App\Model\EventoModel($c->db),
					'prod_evento' => new App\Model\ProdEventoModel($c->db),
					'venta' => new App\Model\VentaModel($c->db),
					'venta_detalle' => new App\Model\VentaDetalleModel($c->db),
					'venta_pago' => new App\Model\VentaPagoModel($c->db),
					'tipo_pago' => new App\Model\TipoPagoModel($c->db),
					'venta_historia' => new App\Model\VentaHistoriaModel($c->db),
					'timbres' => new App\Model\TimbresModel($c->db),
					'cfdi' => new App\Model\CfdiModel($c->db),
					'log_timbrado' => new App\Model\LogTimbradoModel($c->db),
					'estado_resultados' => new App\Model\EstadoResultadosModel($c->db),
					'resena' => new App\Model\ResenaModel($c->db),
					'carrito' => new App\Model\CarritoModel($c->db),
					'lista_deseos' => new App\Model\ListaDeseosModel($c->db),
					'proveedor' => new App\Model\ProveedorModel($c->db),
					'marca' => new App\Model\MarcaModel($c->db),
					'marca_utilidad' => new App\Model\MarcaUtilidadModel($c->db),
					'monto_minimo' => new App\Model\MontoMinimoModel($c->db),
					'servicio' => new App\Model\ServicioModel($c->db),
					'prod_servicio' => new App\Model\ProdServicioModel($c->db),
					'traspaso' => new App\Model\TraspasoModel($c->db),
					'traspaso_detalle' => new App\Model\TraspasoDetalleModel($c->db),
					'recarga' => new App\Model\RecargaModel($c->db),
					'recarga_costo' => new App\Model\RecargaCostoModel($c->db),
					'transaction' => new App\Lib\Transaction($c->db),
					'pos' => new App\Model\PosModel($c->db),
					'ingreso' => new App\Model\IngresoModel($c->db),
					'egreso' => new App\Model\EgresoModel($c->db),
				];
			};
	//};
?>