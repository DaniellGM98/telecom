<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class VentaModel {
		private $db;
		private $table = 'venta';
		private $tableD = 'venta_detalle';
		private $tableCat = 'prod_categoria';
		private $tableC = 'cliente';
		private $tableU = 'usuario';
		private $tableP = 'producto';
		private $tableS = 'servicio';
		private $response;
		
		public function __CONSTRUCT($db) {
			$this->db = $db;
			$this->response = new Response();
		}

		public function get($id) {
			$this->response->result = $this->db
				->from($this->table)
				->where('id', $id)
				->fetch();

			if($this->response->result) { $this->response->SetResponse(true); }
			else { $this->response->SetResponse(false, 'no existe el registro'); }

			return $this->response;
		}

		public function find($filtro) {
			$this->response->result = $this->db
				->from($this->table)
				->select(NULL)->select('cfdi_id, sucursal_id, cliente_id, fecha, subtotal, total, folio, descuento')
				->where("CONCAT_WS(' ', cfdi_id, sucursal_id, cliente_id, fecha, subtotal, total, folio, descuento) LIKE ?" , "%$filtro%")
				->fetchAll();

			return $this->response->SetResponse(true);
		}

		/*** 
		 **** Buscar venta por folo de venta 
		**** Recibe: $folio de la venta
		**** Autor: Angel Gabriel Ramirez Alva
		**** Fecha: 25 Octubre 2019
		***/
		public function buscaFolio($folio) {
			$this->response->result = $this->db
				->from($this->table)
				->select(null)->select('SQL_CALC_FOUND_ROWS venta.*')
				->where('folio', $folio)
				->where('status', 1)
				->fetchAll();

			$this->response->total = $this->db
				->getPdo()->query('SELECT FOUND_ROWS()')->fetchColumn();
			
			return $this->response->SetResponse(true);
		}

		public function getAll($data) {
				$this->response->result = $this->db
					->from($this->table)
					->leftJoin("$this->tableC on usuario_id = cliente_id")
					->where('id', $data)
					->where("(pagado=1 OR tiene_credito=1)")
					->where('status', 1)
					->orderBy('fecha DESC')
					->fetchAll();
			
				return $this->response->SetResponse(true);
		}

		public function getAllBusca($inicio, $fin, $pagina, $limite, $sucursal_id, $cliente_id, $filtro) {
			$inicial = $pagina * $limite;
			$filtro = intval($filtro)==0? '_': $filtro;
			$this->response->result = $this->db->getPdo()->query(
				"SELECT
					$this->table.folio,
					$this->table.fecha,
					$this->table.cliente_id,
					(SELECT CONCAT_WS(' ', nombre, apellidos) FROM $this->tableU WHERE $this->tableU.id = $this->table.cliente_id) AS cliente,
					$this->tableD.producto_id AS origen,
					$this->tableD.origen_tipo,
					CASE 
						WHEN $this->tableD.origen_tipo = 1 THEN 'PRODUCTO ' 
						ELSE CASE 
							WHEN (SELECT tipo FROM $this->tableS WHERE $this->tableS.id = $this->tableD.producto_id) = 1 THEN 'SERVICIO'
							ELSE 'PAQUETE'
						END
					END AS tipo,
					CASE 
						WHEN $this->tableD.origen_tipo = 1 THEN (SELECT CONCAT('[', sku, ']', nombre, ' ', modelo, ' ') FROM $this->tableP WHERE $this->tableP.id = $this->tableD.producto_id)
						ELSE (SELECT nombre FROM $this->tableS WHERE $this->tableS.id = $this->tableD.producto_id)
					END AS producto,
					$this->tableD.costo,
					$this->tableD.importe,
					$this->tableD.iva
				FROM $this->table
				LEFT JOIN $this->tableD ON $this->table.id = $this->tableD.venta_id
				WHERE
					CASE WHEN $this->tableD.origen_tipo = 1 THEN (SELECT CONCAT('[', sku, ']', nombre, ' ', modelo, ' ') FROM $this->tableP WHERE $this->tableP.id = $this->tableD.producto_id) ELSE (SELECT nombre FROM $this->tableS WHERE $this->tableS.id = $this->tableD.producto_id) END LIKE '%$filtro%' AND
					DATE_FORMAT($this->table.fecha, '%Y-%m-%d') BETWEEN '$inicio' AND '$fin' AND
					$this->table.sucursal_id ".(intval($sucursal_id)==0? ">": "")."= $sucursal_id AND
					$this->table.cliente_id ".(intval($cliente_id)==0? ">": "")."= $cliente_id AND
					$this->table.status = 1
				ORDER BY $this->table.fecha DESC, $this->table.folio DESC
				LIMIT $inicial, $limite"
			)->fetchAll();

			return $this->response->SetResponse(true);
		}

		public function rptVentasVendedor($inicio, $fin, $sucursal_id) {
			$this->response->result = $this->db
				->from($this->table)
				->select(NULL)->select("$this->tableU.id, nombre, apellidos, SUM(total) AS total")
				->leftJoin("$this->tableU ON $this->tableU.id = empleado_id")
				->where("DATE_FORMAT(fecha, '%Y-%m-%d') BETWEEN '$inicio' AND '$fin'")
				->where(intval($sucursal_id)>0? "sucursal_id = $sucursal_id": "true")
				->where("$this->table.status", 1)
				->where("$this->table.pagado", 1)
				->where("$this->tableU.status", 1)
				->groupBy("empleado_id")
				->fetchAll();

			return $this->response->SetResponse(true);
		}

		public function rptTelefonosVendedor($inicio, $fin, $sucursal_id) {
			if(!isset($_SESSION)) { session_start(); }
			$this->response->result = $this->db
				->from($this->table)
				//->select(NULL)->select("$this->tableU.id, $this->tableU.nombre, apellidos, SUM(total) AS total")
				->select(NULL)->select("$this->tableU.id, $this->tableU.nombre, apellidos, COUNT(*) AS total")
				->leftJoin("$this->tableD ON $this->table.id = venta_id")
				->leftJoin("$this->tableP ON $this->tableD.producto_id = $this->tableP.id")
				->leftJoin("$this->tableCat ON $this->tableCat.id = prod_categoria_id")
				->leftJoin("$this->tableU ON $this->tableU.id = empleado_id")
				->where("DATE_FORMAT(fecha, '%Y-%m-%d') BETWEEN '$inicio' AND '$fin'")
				->where(intval($sucursal_id)>0? "sucursal_id = $sucursal_id": "true")
				->where("$this->table.status", 1)
				->where("$this->table.pagado", 1)
				->where("$this->tableU.status", 1)
				->where("$this->tableCat.id", $_SESSION['cat_telefono'])
				->groupBy("empleado_id")
				->fetchAll();

			return $this->response->SetResponse(true);
		}

		public function rptVentasCategoria($inicio, $fin, $sucursal_id, $empleado_id) {
			$this->response->result = $this->db
				->from($this->table)
				->select(NULL)->select("$this->tableCat.id, CONCAT_WS(' ', $this->tableCat.nombre, CASE WHEN (clave IS NOT NULL AND LENGTH(clave)>0) THEN CONCAT(' (', clave, ')') ELSE '' END) AS nombre, SUM(total) AS total")
				->leftJoin("$this->tableD ON $this->table.id = venta_id")
				->leftJoin("$this->tableP ON $this->tableD.producto_id = $this->tableP.id")
				->leftJoin("$this->tableCat ON $this->tableCat.id = prod_categoria_id")
				->where("DATE_FORMAT(fecha, '%Y-%m-%d') BETWEEN '$inicio' AND '$fin'")
				->where(intval($sucursal_id)>0? "sucursal_id = $sucursal_id": "true")
				->where(intval($empleado_id)>0? "empleado_id = $empleado_id": "true")
				->where("$this->table.status", 1)
				->where("$this->table.pagado", 1)
				->where("$this->tableD.status", 1)
				// ->where("$this->tableP.status", 1)
				// ->where("$this->tableCat.status", 1)
				->groupBy("prod_categoria_id")
				->fetchAll();

			return $this->response->SetResponse(true);
		}

		/******************* getVentasByVendedor *******************
		 * Metodo que recibe 
		 ** {inicio}: fecha de inicio de operaciones que quieres obtener 
		 ** {fin}: Fecha de fin de operaciones que quieres obtener
		 ** {empleado_id}: empleado_id de la tabla venta
		 ****************************************************/
		public function getVentasByVendedor($inicio, $fin, $empleado_id, $suc=0) {
			$suc = $suc > 0 ? 'sucursal_id = '.$suc : 'TRUE';
			$this->response->result = $this->db
				->from($this->table)
				->select(null)->select("SQL_CALC_FOUND_ROWS venta.*, sucursal.nombre AS sucursal, IFNULL(group_concat(distinct sku separator ','), '') AS sku")
				->leftJoin("$this->tableD ON $this->table.id = venta_id")
				->where('CAST(fecha AS DATE) >= ?', $inicio)
				->where('CAST(fecha AS DATE) <= ?', $fin) 
				->where("$this->table.empleado_id", $empleado_id)
				->where("$this->table.status", 1)
				->where("tipo", 1)
				->where("pagado", 1)
				->where($suc)
				->orderBy('fecha DESC, folio DESC')
				->groupBy("$this->table.id")
				->fetchAll();
		
			return $this->response->SetResponse(true);

			$this->response->total = $this->db->getPdo()->query('SELECT FOUND_ROWS()')->fetchColumn();
			$ventas = array();
			foreach ($this->response->result as $id) {
				$ventas[] = $id->id;
			}
			$this->response->result = $ventas;
			
			return $this->response->SetResponse(true);
		}

		/******************* getVentasByProducto *******************
		 * Metodo que recibe 
		 ** {inicio}: fecha de inicio de operaciones que quieres obtener 
		 ** {fin}: Fecha de fin de operaciones que quieres obtener
		 ****************************************************/
		public function getVentasByProducto($inicio, $fin, $suc=0) {
			$suc = $suc > 0 ? 'sucursal_id = '.$suc : 'TRUE';
			$this->response->result = $this->db
				->from($this->table)
				->select(null)->select("$this->tableP.*, $this->tableD.*, sum($this->tableD.cantidad) as total_cantidad, sum($this->tableD.importe) as total")
				->leftJoin("$this->tableD ON $this->table.id = venta_id")
				->leftJoin("$this->tableP ON $this->tableD.producto_id = $this->tableP.id")
				->where('CAST(fecha AS DATE) >= ?', $inicio)
				->where('CAST(fecha AS DATE) <= ?', $fin) 
				// ->where("$this->table.empleado_id", $empleado_id)
				->where("$this->table.status", 1)
				->where("tipo", 1)
				->where("pagado", 1)
				->where("$this->tableD.producto_id != 0")
				->where($suc)
				->orderBy('fecha DESC, folio DESC')
				// ->groupBy("$this->table.id")
				->groupBy("$this->tableD.producto_id")
				// ->limit(100)
				->fetchAll();
		
			return $this->response->SetResponse(true);

			$this->response->total = $this->db->getPdo()->query('SELECT FOUND_ROWS()')->fetchColumn();
			$ventas = array();
			foreach ($this->response->result as $id) {
				$ventas[] = $id->id;
			}
			$this->response->result = $ventas;
			
			return $this->response->SetResponse(true);
		}

		/***
		 * Método para obtener el total de las ventas realizadas durante un mes
		 * recibe opcional {month} mes. Si no lo recibe toma el actual
		 * recibe opcional {year} año. Si no lo recibe toma el actual
		 */
		public function getTotalVentas($month=null, $year=null) {
			$month = $month!=null? $month: intval(date('m'));
			$year = $year!=null? $year: intval(date('Y'));

			$this->response->result = $this->db
				->from($this->table)
				->select(null)->select('COALESCE(SUM(total), 0) AS total')
				->where('status', 1)
				->where("pagado", 1)
				->where("CAST(fecha AS DATE) BETWEEN '".date('Y-m-01')."' AND '".date('Y-m-d', mktime(0, 0, 0, $month, date('d', mktime(0, 0, 0, $month+1, 0, $year)), $year))."'")
				->fetch()
				->total;

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
				$this->response->SetResponse(false, "catch: add model $this->table");
			}

			return $this->response;
		}

		public function edit($data, $id) {
			$this->response = new Response();
			try{
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, "id actualizado $id"); }
				else { $this->response->SetResponse(false, 'no se edito el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: edit model $this->table");
			}

			return $this->response;
		}//fin de edit

		/*** del ***/
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
				$this->response->SetResponse(false, "catch: del model $this->table");
			}

			return $this->response;
		}

		/** 
		 * Método sigFolio
		 * Regresa el siguiente folio de ventas
		 * by isantosp
		 */
		public function sigFolio() {
			$this->response->result = $this->db
				->from($this->table)
				->select(NULL)->select('folio')
				->orderBy('fecha DESC')
				->fetch();

			$folio = '1';
			if(is_object($this->response->result)) {
				$folio = $this->response->result->folio;
				$folio++;
			}

			$this->response->result = $folio;
			return $this->response->SetResponse(true);
		}

		public function rptCobranza($inicio, $fin, $pagina, $limite=0, $sucursal_id=0, $cliente_id=0) {
			if($limite == '0') {
				$this->response->result = $this->db
					->from($this->table)
					->leftJoin("$this->tableC on usuario_id = cliente_id")
					->where("CAST(fecha AS DATE) BETWEEN '$inicio' AND '$fin'")
					->where($sucursal_id==0? "TRUE": "sucursal_id = $sucursal_id")
					->where($cliente_id==0? "TRUE": "cliente_id = $cliente_id")
					->where("tiene_credito", 1)
					->where('pagado', 0)
					->where('status', 1)
					->fetchAll();
			} else {
				$inicial = $pagina * $limite;
				$this->response->result = $this->db
					->from($this->table)
					->leftJoin("$this->tableC on usuario_id = cliente_id")
					->where("CAST(fecha AS DATE) BETWEEN '$inicio' AND '$fin'")
					->where($sucursal_id==0? "TRUE": "sucursal_id = $sucursal_id")
					->where($cliente_id==0? "TRUE": "cliente_id = $cliente_id")
					->where("tiene_credito", 1)
					->where('pagado', 0)
					->where('status', 1)
					->limit("$inicial, $limite")
					->fetchAll();
			}

			$this->response->total = $this->db
				->from($this->table)
				->select(NULL)->select('COUNT(*) AS total')
				->leftJoin("$this->tableC on usuario_id = cliente_id")
				->where("CAST(fecha AS DATE) BETWEEN '$inicio' AND '$fin'")
				->where($sucursal_id==0? "TRUE": "sucursal_id = $sucursal_id")
				->where($cliente_id==0? "TRUE": "cliente_id = $cliente_id")
				->where("tiene_credito", 1)
				->where('pagado', 0)
				->where('status', 1)
				->fetch()
				->total;

			return $this->response->SetResponse(true);
		}

		function delByApartadoPago($apartado_pago_id) {
			try{
				$data['status'] = 0;
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('apartado_pago_id', $apartado_pago_id)
					->execute();

				if($this->response->result!=0)	$this->response->SetResponse(true);
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del model $this->table");
			}

			return $this->response;
		}


		public function cancelaByRecarga($idRecarga) {
			try{
				$data['status'] = 0;
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('recarga_id', $idRecarga)
					->execute();

				if($this->response->result!=0){
					/*$this->response->result = $this->db
						->update($this->tableD, $data)
						->where('recarga_id', $idRecarga)
						->execute();*/
					$this->response->SetResponse(true, 'id baja: '.$idRecarga);
				}	
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del model $this->table");
			}

			return $this->response;
		}

		public function getTotalVentasIn($ini=null, $fin=null, $suc) {
			$total = $this->db
				->from($this->table)
				->select(null)->select('COALESCE(SUM(total), 0) AS total')
				->where('status', 1)
				->where("pagado", 1)
				->where("CAST(fecha AS DATE) >= ?", $ini)
				->where("CAST(fecha AS DATE) <= ?", $fin) 
				->where(($suc==0?'TRUE':'sucursal_id='.$suc))
				->fetch()
				->total;

			return $total;
		}
		public function verificarTraspaso($sku, $sucursal_id=0) {
			$this->response->result = $this->db
					->from('traspaso_detalle')
					->where('sku', $sku)
					->innerJoin('traspaso on traspaso.id = traspaso_detalle.traspaso_id')
					->where('(traspaso.status = 2)')
					->where("traspaso.origen",$sucursal_id)
					->fetchAll();
			return $this->response;
		}

		public function verificarSucursal($sku, $sucursal_id = 0) {
			$this->response->result = $this->db
					->from('prod_entrada_detalle')
					->innerJoin('prod_entrada_detalle ON prod_entrada_detalle.prod_entrada_id = prod_entrada.id')
		 			->select('sucursal_id')
					->where("prod_entrada_detalle.sku = '$sku'")
					->orderBy('prod_entrada.fecha DESC')
					->fetch();
				
					if($this->response->result->sucursal_id == $sucursal_id) { $this->response->SetResponse(true); }
					else { $this->response->SetResponse(false); }
					return $this->response;
		}
	}//fin de venta
?>