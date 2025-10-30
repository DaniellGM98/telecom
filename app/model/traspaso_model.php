<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class TraspasoModel {
		private $db;
		private $table = 'traspaso';
		private $tableD = 'traspaso_detalle';
		private $tableS = 'sucursal';
		private $tableU = 'usuario';
		private $response;
		
		public function __CONSTRUCT($db) {
			require_once './core/defines.php';
			$this->db = $db;
			$this->response = new Response();
		}
		
		public function find($busqueda) {
			$this->response->result = $this->db
				->from($this->table)
				->where("CONCAT_WS(' ', folio) LIKE ?" , "%$busqueda%")
				->where('status', 1)
				->fetchAll();

			$this->response->total = $this->db
				->from($this->table)
				->select(NULL)->select("COUNT(*) AS total")
				->where("CONCAT_WS(' ', folio) LIKE ?" , "%$busqueda%")
				->where('status', 1)
				->fetch()
				->total;

			return $this->response->SetResponse(true);
		}

		public function get($id) {
			$this->response->result = $this->db
				->from($this->table)
				->where('id', $id)
				->fetch();

			if($this->response->result)	$this->response->SetResponse(true);
			else { $this->response->SetResponse(false, 'no existe el registro'); }
			return $this->response;
		}

		public function getByFolio($folio) {
			$this->response->result = $this->db
				->from($this->table)
				->where('folio', $folio)
				->fetch();

			if($this->response->result)	$this->response->SetResponse(true);
			else { $this->response->SetResponse(false, 'no existe el registro'); }
			return $this->response;
		}

		public function buscaFolio($folio, $inicio, $fin) {
			$this->response->result = $this->db
				->from($this->table)
				->select(null)->select("SQL_CALC_FOUND_ROWS prod_entrada.*, origen.nombre AS origen_nombre, destino.nombre AS destino_nombre, CONCAT_WS(' ', $this->tableU.nombre, apellidos) AS empleado")
				->leftJoin("$this->tableS origen ON origen.id = origen")
				->leftJoin("$this->tableS destino ON destino.id = destino")
				->leftJoin("$this->tableU ON $this->tableU.id = empleado_id")
				->where('folio', $folio)
				->where("DATE_FORMAT(fecha, '%Y-%m-%d') BETWEEN '$inicio' AND '$fin'")
				->where('status', 1)
				->fetchAll();

			$this->resposne->total = $this->db->getPdo()->query('SELECT FOUND_ROWS()')->fetchColumn();
			return $this->response->SetResponse(true);
		}

		public function getAll($status=0, $suc_origen=0, $producto_id=0, $arrSku=null) {
			if(intval($status)>0 && intval($suc_origen)>0 && intval($producto_id)>0) {
				if($arrSku){
					$arr = explode(',',$arrSku);
					$res = "";
					foreach ($arr as $sku) {
						$res .= "'".strval($sku)."',";
					}
					$arrSku = substr($res,0,-1);
				}
				$this->response->result = $this->db
					->from($this->table)
					->select('SUM(cantidad) AS cantidad')
					->leftJoin("$this->tableD ON $this->table.id = traspaso_id")
					->where("$this->table.status", $status)
					->where("$this->table.origen", $suc_origen)
					->where("$this->tableD.producto_id", $producto_id)
					->where($arrSku!=null? "$this->tableD.sku IN ($arrSku)": "TRUE")
					->groupBy("$this->table.id")
					->fetchAll();
			} else {
				$this->response->result = $this->db
					->from($this->table)
					->where('status', 1)
					->fetchAll();
			}

			$this->response->total = count($this->response->result);

			return $this->response->SetResponse(true);
		}

		public function getAllBusca($inicio, $fin) {
			$this->response->result = $this->db
				->from($this->table)
				->select("origen.nombre AS origen_nombre, destino.nombre AS destino_nombre, CONCAT_WS(' ', $this->tableU.nombre, apellidos) AS empleado")
				->leftJoin("$this->tableS origen ON origen.id = origen")
				->leftJoin("$this->tableS destino ON destino.id = destino")
				->leftJoin("$this->tableU ON $this->tableU.id = $this->table.empleado_id")
				->where("DATE_FORMAT(fecha, '%Y-%m-%d') BETWEEN '$inicio' AND '$fin'")
				->where("$this->table.status > 0")
				->fetchAll();
	
			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) total')
				->where("DATE_FORMAT(fecha, '%Y-%m-%d') BETWEEN '$inicio' AND '$fin'")
				->where("$this->table.status > 0")
				->fetch()
				->total;

			return $this->response->SetResponse(true);
		}

		public function getSolicitudesPendientes($sucursal_id=0) {
			$this->response->result = $this->db
				->from($this->table)
				->where(intval($sucursal_id)==0? 'true': "destino = $sucursal_id")
				->where('status', 2)
				->fetchAll();

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
			try{
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, "id actualizado: $id"); }
				else { $this->response->SetResponse(false, 'no se edito el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: edit model $this->table");
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

				if($this->response->result!=0) { $this->response->SetResponse(true, "id baja: $id"); }
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del model $this->table");
			}

			return $this->response;
		}

		public function getCorte($sucursal_id, $fecha = null) {
			if($fecha == null) { $fecha = date('Y-m-d'); }
			$this->response->result = $this->db
				->from($this->table)
				->select(NULL)->select("SUM(cantidad) AS cantidad")
				->leftJoin("$this->tableD ON traspaso_id = $this->table.id")
				->where("$this->table.origen = $sucursal_id")
				->where("date_format($this->table.fecha, '%Y-%m-%d') = '$fecha'")
				->where("$this->table.status", 1)
				->fetch()
				->cantidad;

			if($this->response->result == null) { $this->response->result = 0; }
			return $this->response->SetResponse(true);
		}
	}
?>