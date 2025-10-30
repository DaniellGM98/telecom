<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class VentaDetalleModel {
		private $db;
		private $table = 'venta_detalle'; 
		private $tableV = 'venta'; 
		private $tableP = 'producto'; 
		private $tableC = 'prod_categoria'; 
		private $tableS = 'prod_salida'; 
		private $tableD = 'prod_salida_detalle'; 
		private $response;
		
		public function __CONSTRUCT($db) {
			require_once './core/defines.php';
			$this->db = $db;
			$this->response = new Response();
		}

		public function get($id) {
			$this->response->result = $this->db
				->from($this->table)
				->where('id', $id)
				->fetch();

			if($this->response->result) $this->response->SetResponse(true,' ');
			else { $this->response->SetResponse(false, 'no existe el registro'); }

			return $this->response;
		}

		// public function getByVenta($venta_id) {
		// 	$this->response->result = $this->db
		// 		->from($this->table)
		// 		->where('venta_id', $venta_id)
		// 		->where('status', 1)
		// 		->fetchAll();

		// 	$this->response->total = $this->db
		// 		->from($this->table)
		// 		->select(null)->select('COUNT(*) AS total')
		// 		->where('venta_id', $venta_id)
		// 		->where('status', 1)
		// 		->fetch()
		// 		->total;

		// 	$this->response->SetResponse(true);
		// }

		public function find($filtro) {
			$this->response->result = $this->db
			->from($this->table)
			->select(NULL)->select('venta_id, producto_id, cantidad, precio, importe')
			->where("CONCAT_WS(' ', venta_id, producto_id, cantidad, precio, importe) LIKE ?" , "%$filtro%")
			->fetchAll();

			return $this->response->SetResponse(true);
		}

		public function findBySku($imei) {
			$this->response->result = $this->db
				->from($this->table)
				->select(null)->select('importe, venta_id, producto_id, venta.fecha, CONCAT(producto.nombre," ",producto.modelo) AS producto')
				->where($this->table.'.sku', $imei)
				->where($this->table.'.status', 1)
				->fetch();

			if($this->response->result) $this->response->SetResponse(true,' ');
			else { $this->response->SetResponse(false, 'no existe el registro'); }

			return $this->response;
		}

		public function getAll() {
			$this->response->result = $this->db
				->from($this->table)
				->where('status', 1)
				->fetchAll();

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) Total')
				->where('status', 1)
				->fetch()
				->Total;

			return $this->response->SetResponse(true);
		}

		/******************* getVentasByVendedor *******************
		 * Metodo que recibe 
		 ** {inicio}: fecha de inicio de operaciones que quieres obtener 
		 ** {fin}: Fecha de fin de operaciones que quieres obtener
		 ** {empleado_id}: empleado_id de la tabla venta
		 * Autor: Angel Gabriel Ramirez Alva 04/11/19
		 ***************************************************/
		public function getVentasByVendedor($inicio, $fin, $empleado_id) {
				$resultado = $this->db
					->from($this->table)
					->select(null)->select("SQL_CALC_FOUND_ROWS $this->table.venta_id as id")
					->innerJoin("$this->tableP ON $this->table.producto_id = $this->tableP.id")
					->innerJoin("$this->tableV on $this->table.venta_id = $this->tableV.id")
					->where("$this->table.status", 1)
					->where("CAST($this->tableV.fecha AS DATE) >= ?", $inicio)
					->where("CAST($this->tableV.fecha AS DATE) <= ?", $fin) 
					->where("$this->tableV.empleado_id", $empleado_id)
					->groupBy("$this->table.venta_id")
					->orderBy("$this->tableV.fecha DESC, $this->tableV.folio DESC")
					->fetchAll();

				$this->response->total = $this->db->getPdo()->query('SELECT FOUND_ROWS()')->fetchColumn();
				$ventas = array();
				foreach ($resultado as $id) {
					$ventas[] = $id->id;
				}

				$this->response->result = $ventas; 
				return $this->response->SetResponse(true);
		}

		/******************* getByVenta *******************
		 * Metodo que recibe id el cual compara
		 * con venta_id de la tabla venta_detalle
		 * y regresa todos los campos de la tabla producto 
		 * y de la tabla venta_detalle
		 * Joselyn 18/10/19
		 ***************************************************/
		public function getByVenta($id) {
			$this->response->result = $this->db
				->from($this->table)
				->select(null)->select("$this->tableP.*, $this->table.*, IFNULL($this->tableP.sku, '') AS prodSku, IFNULL($this->table.sku, '') AS sku, $this->tableC.nombre AS categoria")
				->innerJoin("$this->tableP ON $this->table.producto_id = $this->tableP.id")
				->innerJoin("$this->tableC ON $this->tableC.id = $this->tableP.prod_categoria_id")
				// ->innerJoin("$this->tableS ON $this->tableS.venta_id = $this->table.venta_id")
				// ->innerJoin("$this->tableD ON $this->tableS.id = $this->tableD.prod_salida_id")
				// ->where("$this->tableP.id = $this->tableD.producto_id")
				->where("$this->table.status", 1)
				->where('venta_detalle.venta_id', $id)
				->fetchAll();

			return $this->response->SetResponse(true);
		}

		/******************* getVentasByBuscaProd *******************
		 * Metodo que recibe 
		 * con fk_prod_entrada de la tabla prod_entrada_detalle
		 * y regresa todos los campos de la tabla producto 
		 * y de la tabla prod_entrada_detalle
		 * Si recibe valor en busqueda los filtra por nombre, modelo y sku
		 * Autor: Angel Gabriel Ramirez Alva 24/10/19
		 ***************************************************/
		public function getVentasByBuscaProd($inicio, $fin, $pagina, $limite, $sucursal_id, $cliente_id, $busqueda) {
			$busqueda = $busqueda!='0'? $busqueda: '_';
			$pagina = $pagina * $limite;
			$ids = $this->db
				->from($this->table)
				->select(null)->select("SQL_CALC_FOUND_ROWS $this->table.venta_id as id")
				->innerJoin("$this->tableP ON $this->table.producto_id = $this->tableP.id")
				->innerJoin("$this->tableV on $this->table.venta_id = $this->tableV.id")
				->where("CONCAT_WS(' ', $this->tableP.nombre, $this->tableP.modelo, $this->tableP.sku) LIKE ?" , "%$busqueda%")
				->where("$this->table.status", 1)
				->where("$this->tableV.sucursal_id ".($sucursal_id=='0'? '>': '=')." $sucursal_id")
				->where("$this->tableV.cliente_id ".($cliente_id=='0'? '>': '=')." $cliente_id")
				->where("CAST($this->tableV.fecha AS DATE) >= ?", $inicio)
				->where("CAST($this->tableV.fecha AS DATE) <= ?", $fin) 
				->where("$this->tableV.tipo", 1) 
				->groupBy("$this->table.venta_id")
				->orderBy("$this->tableV.fecha DESC, $this->tableV.folio DESC")
				->limit($limite)
				->offset($pagina) 
				->fetchAll();
				
				$this->response->total = $this->db->getPdo()->query('SELECT FOUND_ROWS()')->fetchColumn();
				$ventas = array();
				foreach ($ids as $id) {
					$ventas[] = $id->id;
				}
				
				$this->response->result = $ventas; 
				return $this->response->SetResponse(true);
		}

		public function add($data) {
			try{
				$this->response->result = $this->db
					->insertInto($this->table, $data)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, 'id del registro: '.$this->response->result); }
				else { $this->response->SetResponse(false, 'no se inserto el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: add model venta_detalle');
			}

			return $this->response;
		}

		public function edit($data, $id) {
			try{
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, "id actualizado $id"); }
				else { $this->response->SetResponse(false, 'no se edito el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: edit model venta_detalle');
			}

			return $this->response;
		}

		public function editByVenta($data, $venta_id) {
			try{
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('venta_id', $venta_id)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, "id actualizado"); }
				else { $this->response->SetResponse(false, 'no se edito el registro'); }

			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: edit model prod_entrada_detalle');
			}

			return $this->response;
		}

		public function del($id) {
			try{
				$data['status'] = 0;
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();

				if($this->response->result!=0)	$this->response->SetResponse(true, 'id baja: '.$id);
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: del model venta_detalle');
			}

			return $this->response;
		}

		public function getCorteRegalo($sucursal_id, $fecha=null) {
			if($fecha == null) { $fecha = date('Y-m-d'); }
			$this->response->result = $this->db
				->from($this->table)
				->select(NULL)->select("sum(cantidad) AS cantidad")
				->leftJoin("$this->tableV ON $this->tableV.id = venta_id")
				->where("date_format($this->tableV.fecha, '%Y-%m-%d') = '$fecha'")
				->where("$this->table.status", 1)
				->where("$this->tableV.status", 1)
				->where("$this->tableV.sucursal_id", $sucursal_id)
				->where("costo", 0)
				->fetch()
				->cantidad;

			if($this->response->result == null) { $this->response->result = 0; }
			return $this->response->SetResponse(true);
		}
	}
?>