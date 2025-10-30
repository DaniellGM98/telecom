<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class ProdSalidaDetalleModel {
		private $db;
		private $table = 'prod_salida_detalle'; 
		private $tableP = 'producto'; 
		private $tableV = 'venta'; 
		private $tableS = 'prod_salida'; 
		private $tableC = 'prod_categoria'; 
		private $tableD = 'venta_detalle'; 
		private $tableU = 'usuario'; 
		private $response;
		
		public function __CONSTRUCT($db) {
			$this->db = $db;
			$this->response = new Response();
		}

		/*** get  por ID ***/
		public function get($id) {
			$this->response->result = $this->db
				->from($this->table)
				->where('id', $id)
				->fetch();

			if($this->response->result) { $this->response->SetResponse(true); }
			else { $this->response->SetResponse(false, 'no existe el registro'); }

			return $this->response;
		}

		/******************* getBySalida *******************
		** Metodo que recibe prod_salida_id el cual compara
		** con prod_salida_id de la tabla prod_salida_detalle
		** y regresa todos los campos de la tabla producto 
		** y de la tabla prod_salida_detalle
		** Joselyn 18/10/19
		****************************************************/
		public function getBySalida($prod_salida_id) {
			$this->response->result = $this->db
				->from($this->table)
				->select(null)->select("$this->tableP.*, $this->table.*, sku_nombre")
				->innerJoin("$this->tableP ON $this->table.producto_id = $this->tableP.id")
				->innerJoin("prod_categoria ON prod_categoria.id = producto.prod_categoria_id")
				->where('prod_salida_id', $prod_salida_id)
				->where("$this->table.status", 1)
				->fetchAll();

			return $this->response->SetResponse(true);
		}//fin de getBySalida 

		public function buscarPorSku($sku, $categoria_id) {
			$this->response->result = $this->db
				->from($this->table)
				->leftJoin("$this->tableP ON $this->tableP.id = producto_id")
				->leftJoin("$this->tableS ON $this->tableS.id = prod_salida_id")
				->leftJoin("$this->tableV ON $this->tableS.venta_id = $this->tableV.id")
				->where("$this->table.sku", $sku)
				->where('prod_categoria_id', $categoria_id)
				->where("$this->table.status", 1)
				->where("$this->tableV.status", 1)
				->fetchAll();

			if($this->response->result) { $this->response->SetResponse(true); }
			else { $this->response->SetResponse(false, 'NO existe el registro'); }
			return $this->response;
		}

		/*** getAll ***/
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
		}// fin de getAll 

		/*** add ***/
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

		/*** edit ***/
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

		public function getCorteOtros($sucursal_id, $fecha=null) {
			require_once './core/defines.php';
			if($fecha == null) { $fecha = date('Y-m-d'); }
			$this->response->result = $this->db->getPdo()->query(
				"SELECT
					$this->table.producto_id, 
					/*CONCAT(CASE WHEN $this->tableP.sku IS NOT NULL THEN CONCAT('[', $this->tableP.sku, '] ') ELSE '' END, ' ', $this->tableP.nombre) AS producto, */
					$this->tableP.nombre AS producto, 
					prod_categoria_id,
					sum($this->table.cantidad) AS cantidad,
					IFNULL(group_concat(distinct $this->table.sku separator ', '), '') AS codigo,
					sum(importe + iva) AS importe, 'venta' AS desde, 
					(CASE WHEN $this->table.sku IS NOT NULL 
						THEN IFNULL((SELECT costo FROM prod_entrada_detalle ed WHERE ed.sku = $this->table.sku AND (SELECT status FROM prod_entrada pe WHERE pe.id = ed.prod_entrada_id) = 1 LIMIT 1),0) * sum($this->table.cantidad) 
						ELSE IFNULL((SELECT AVG(costo) FROM prod_entrada_detalle ed WHERE ed.costo > 0 AND ed.producto_id = $this->table.producto_id AND (SELECT status FROM prod_entrada pe WHERE pe.id = ed.prod_entrada_id) = 1) * sum($this->table.cantidad),0) END) AS costo
				from $this->table
				left join $this->tableP on $this->tableP.id = $this->table.producto_id
				left join $this->tableS on $this->tableS.id = prod_salida_id
				left join $this->tableD on $this->tableD.venta_id = $this->tableS.venta_id AND $this->tableD.producto_id = $this->tableP.id
				where
					date_format($this->tableS.fecha, '%Y-%m-%d') = '$fecha' AND
					prod_categoria_id != $_SESSION[cat_telefono] AND
					$this->tableS.sucursal_id = $sucursal_id AND
					(SELECT status FROM $this->tableV WHERE id = $this->tableD.venta_id) = 1 AND
					$this->table.status = 1 AND
					(($this->table.sku IS NOT NULL AND $this->tableD.sku IS NOT NULL AND $this->table.sku = $this->tableD.sku) OR ($this->table.sku IS NULL AND $this->tableD.sku IS NULL))
				group by producto_id
				union
				select 
					producto_id,
					/*CONCAT(CASE WHEN $this->tableP.sku IS NOT NULL THEN CONCAT('[', $this->tableP.sku, '] ') ELSE '' END, ' ', $this->tableP.nombre) AS producto,*/
					CONCAT($this->tableP.nombre, ' ', (CASE producto_id WHEN 36 THEN $this->tableD.importe ELSE '' END)) AS producto,
					prod_categoria_id,
					sum(cantidad) as cantidad,
					'' as codigo,
					(sum(cantidad) * $this->tableD.importe) AS importe, 'otros' AS desde, 
					(CASE producto_id 
						WHEN 36 THEN (sum(cantidad) * $this->tableD.importe*0.93) 
						WHEN 39 THEN IFNULL((SELECT total - costo FROM reparacion r WHERE r.id = CONVERT(SUBSTRING($this->tableV.folio,-6),UNSIGNED INTEGER)),0)
						ELSE $this->tableD.importe
					END) AS costo
					/*(CASE WHEN $this->tableD.sku IS NOT NULL 
						THEN (SELECT costo FROM prod_entrada_detalle ed WHERE ed.sku = $this->tableD.sku AND (SELECT status FROM prod_entrada pe WHERE pe.id = ed.prod_entrada_id) = 1 LIMIT 1) 
						ELSE IFNULL((SELECT AVG(costo) FROM prod_entrada_detalle ed WHERE ed.producto_id = $this->tableD.producto_id AND (SELECT status FROM prod_entrada pe WHERE pe.id = ed.prod_entrada_id) = 1) * sum(cantidad),0) END) AS costo*/
				from $this->tableD
				left join $this->tableV on $this->tableV.id = venta_id
				left join $this->tableP on $this->tableP.id = $this->tableD.producto_id
				where
					date_format($this->tableV.fecha, '%Y-%m-%d') = '$fecha' AND
					/*prod_categoria_id = $_SESSION[cat_abono_apartado] AND*/
					(producto_id BETWEEN 36 AND 40) AND
					$this->tableV.sucursal_id = $sucursal_id and
					$this->tableD.status = 1 and
					$this->tableV.status = 1 AND
					CASE WHEN $this->tableD.producto_id = 38
						THEN (SELECT status FROM reparacion WHERE reparacion.fecha = $this->tableV.fecha)!=0
						ELSE true
					END
				group by producto_id, $this->tableD.importe;"
				)->fetchAll();

			return $this->response->SetResponse(true);
		}

		public function getCorteTelefonos($sucursal_id, $fecha=null) {
			require_once './core/defines.php';
			if($fecha == null) { $fecha = date('Y-m-d'); }
			$this->response->result = $this->db->getPdo()->query(
				"SELECT
					fecha,
					empleado_id,
					/*CONCAT_WS(' ', $this->tableU.nombre, $this->tableU.apellidos) AS usuario,*/
					$this->tableU.nombre AS usuario,
					$this->table.producto_id, 
					/*CONCAT(CASE WHEN $this->tableP.sku IS NOT NULL THEN CONCAT('[', $this->tableP.sku, '] ') ELSE '' END, ' ', $this->tableP.nombre) AS producto, */
					$this->tableP.nombre AS producto, 
					$this->table.sku AS codigo,
					($this->tableD.importe + $this->tableD.iva) AS importe, 'salida' AS origen,
					(SELECT costo FROM prod_entrada_detalle ed WHERE ed.sku = $this->table.sku AND (SELECT status FROM prod_entrada pe WHERE pe.id = ed.prod_entrada_id) = 1 LIMIT 1) AS costo
				from $this->table
				left join $this->tableP on $this->tableP.id = $this->table.producto_id
				left join $this->tableS on $this->tableS.id = prod_salida_id
				left join $this->tableU on $this->tableU.id = empleado_id
				left join $this->tableD on $this->tableD.venta_id = $this->tableS.venta_id AND $this->tableD.producto_id = $this->tableP.id AND $this->tableD.sku = $this->table.sku
				where
					date_format($this->tableS.fecha, '%Y-%m-%d') = '$fecha' and
					prod_categoria_id = $_SESSION[cat_telefono] AND
					$this->tableS.sucursal_id = $sucursal_id AND
					(SELECT status FROM $this->tableV WHERE id = $this->tableD.venta_id) = 1 AND
					$this->table.status = 1
				group by $this->table.id 
				/*union
				select 
					$this->tableV.fecha, $this->tableV.empleado_id, 
					CONCAT_WS(' ', $this->tableU.nombre, $this->tableU.apellidos) AS usuario,
					producto_id, 
					$this->tableP.nombre AS producto,
					$this->tableD.sku AS codigo,
					($this->tableD.importe + $this->tableD.iva) AS importe, 'venta' AS origen,
					(SELECT costo FROM prod_entrada_detalle ed WHERE ed.sku = $this->tableD.sku AND (SELECT status FROM prod_entrada pe WHERE pe.id = ed.prod_entrada_id) = 1 LIMIT 1) AS costo
				from $this->tableD
				left join $this->tableV on $this->tableV.id = venta_id
				left join $this->tableP on $this->tableP.id = $this->tableD.producto_id
				left join $this->tableU on $this->tableU.id = empleado_id
				where
					date_format($this->tableV.fecha, '%Y-%m-%d') = '$fecha' AND
					prod_categoria_id = $_SESSION[cat_telefono] AND
					$this->tableV.sucursal_id = $sucursal_id and
					$this->tableD.status = 1 and
					$this->tableV.status = 1
				group by producto_id, ($this->tableD.importe + $this->tableD.iva) */ "
				)->fetchAll();

			return $this->response->SetResponse(true);
		}
	}//fin de prod_salida_detalle
?>