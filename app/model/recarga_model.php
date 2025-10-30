<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class RecargaModel {
		private $db;
		private $table = 'recarga'; 
		private $tableC = 'recarga_costo'; 
		private $tableS = 'sucursal'; 
		private $response;

		public function __CONSTRUCT($db) {
			require_once './core/defines.php';
			$this->db = $db;
			$this->response = new Response();
		}

		public function find($busqueda) {
			$this->response->result = $this->db
				->from($this->table)
				->leftJoin("$this->tableC ON recarga_costo_id = $this->tableC.id")
				->where("CONCAT_WS(' ', numero, repetir_numero, monto, DATE_FORMAT(fecha, '%d/%m/%Y')) LIKE '%$busqueda%'")
				->where("$this->table.status = 1")
				->fetchAll();

			return $this->response->SetResponse(true);
		}

		public function getCorte($sucursal_id, $fecha=null) {
			if($fecha == null) { $fecha = date('Y-m-d'); }
			$this->response->result = $this->db
				->from($this->table)
				->select(NULL)->select('SUM(monto) AS total')
				->leftJoin("$this->tableC ON $this->tableC.id = recarga_costo_id")
				->where("date_format($this->table.fecha, '%Y-%m-%d')", $fecha)
				->where("sucursal_id", $sucursal_id)
				->where("$this->table.status", 1)
				->fetch()
				->total;

			if($this->response->result == null) { $this->response->result = 0; }
			return $this->response->SetResponse(true);
		}
		
		public function get($id) {
			$this->response->result = $this->db
				->from($this->table)
				->where('id', $id)
				->where('status', 1)
				->fetch();

			if($this->response->result) { $this->response->SetResponse(true); }
			else { $this->response->SetResponse(false, 'No existe el registro'); }

			return $this->response;
		}
		
		public function getAll($pagina=0, $limite=0, $busqueda=0) {
			$busqueda = $busqueda!='0'? $busqueda: '_';
			if($limite == 0) {
				$this->response->result = $this->db
					->from($this->table)
					->select('monto, nombre AS sucursal')
					->leftJoin("$this->tableC ON recarga_costo_id = $this->tableC.id")
					->leftJoin("$this->tableS ON sucursal_id = $this->tableS.id")
					->where("CONCAT_WS(' ', numero, repetir_numero, monto, DATE_FORMAT(fecha, '%d/%m/%Y')) LIKE '%$busqueda%'")
					->where("$this->table.status = 1")
					->orderBy('fecha DESC')
					->fetchAll();
			} else {
				$inicial = $pagina * $limite;
				$this->response->result = $this->db
					->from($this->table)
					->select('monto, nombre AS sucursal')
					->leftJoin("$this->tableC ON recarga_costo_id = $this->tableC.id")
					->leftJoin("$this->tableS ON sucursal_id = $this->tableS.id")
					->where("CONCAT_WS(' ', numero, repetir_numero, monto, DATE_FORMAT(fecha, '%d/%m/%Y')) LIKE '%$busqueda%'")
					->where("$this->table.status = 1")
					->orderBy('fecha DESC')
					->limit("$inicial, $limite")
					->fetchAll();
			}

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) Total')
				->leftJoin("$this->tableC ON recarga_costo_id = $this->tableC.id")
				->where("CONCAT_WS(' ', numero, repetir_numero, monto, DATE_FORMAT(fecha, '%d/%m/%Y')) LIKE '%$busqueda%'")
				->where("$this->table.status = 1")
				->fetch()
				->Total;

			return $this->response->SetResponse(true);
		}

		public function getByDateSuc($ini, $fin, $suc) {
			$suc = $suc == 0 ? 'TRUE' : 'sucursal_id='.$suc;
			$result = $this->db
				->from($this->table)
				->select(null)->select($this->table.'.*, sucursal.nombre AS sucursal, monto')
				->leftJoin("$this->tableC ON $this->tableC.id = recarga_costo_id")
				->where($suc)
				->where("DATE_FORMAT(recarga.fecha,'%Y-%m-%d') >= '$ini'")
				->where("DATE_FORMAT(recarga.fecha,'%Y-%m-%d') <= '$fin'")
				->where("$this->table.status", 1)
				->fetchAll();
			return $result;
		}
		
		public function add($data){
			try {
				$this->response->result = $this->db
					->insertInto($this->table, $data)
					->execute();

				if($this->response->result) { return $this->response->SetResponse(true, 'id del registro: '.$this->response->result); } 
				else { return $this->response->SetResponse(false, 'no se inserto el registro'); }
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				return $this->response->SetResponse(false, "catch: add model: $this->table");
			}
		}
		
		public function edit($data, $id){
			try {
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();
					
				if($this->response->result) { $this->response->SetResponse(true, "id actualizado: $id"); } 
				else { $this->response->SetResponse(false, 'no se edito el registro'); }
			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: edit model: $this->table");
			}

			return $this->response;
		}
		
		public function del($id){
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
				$this->response->SetResponse(false, "catch: del model: $this->table");
			}

			return $this->response;
		}
	}
?>