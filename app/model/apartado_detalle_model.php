<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class ApartadoDetalleModel {
		private $db;
		private $table = 'apartado_detalle'; 
		private $tableA = 'apartado'; 
		private $tableP = 'producto'; 
		private $response;
		
		public function __CONSTRUCT($db) {
			$this->db = $db;
			$this->response = new Response();
		}
	
		/*** get por ID ***/
		public function get($id) {
			$this->response->result = $this->db
				->from($this->table)
				->where('id', $id)
				->fetch();

			if($this->response->result)	$this->response->SetResponse(true);
			else	$this->response->SetResponse(false, 'no existe el registro');

			return $this->response;
		}// fin de get

		/*** get por apartado_id ***/
		public function getByApartado($apartado_id, $status=1) {
			$this->response->result = $this->db
				->from($this->table)
				->select("$this->tableP.*, $this->table.id AS detalle_id, $this->table.sku")
				->leftJoin("$this->tableP ON $this->tableP.id = producto_id")
				->leftJoin("$this->tableA ON $this->tableA.id = apartado_id")
				->where('apartado_id', $apartado_id)
				->where("$this->tableA.status", $status)
				->where("$this->table.status", 1)
				->fetchAll();

			if($this->response->result)	$this->response->SetResponse(true);
			else	$this->response->SetResponse(false, 'no existe el registro');

			return $this->response;
		}// fin de get

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

		/*** searchByProducto ***/
		// public function searchByProducto($producto_id, $inicio=null, $final=null, $sucursal_id=null, $cliente_id=null, $status=1) {
		public function searchByProducto($producto_id, $inicio=null, $final=null, $sucursal_id=null, $status=1) {
			// if(isset($inicio) && isset($final) && isset($sucursal_id) && isset($cliente_id)) {
			if(isset($inicio) && isset($final) && isset($sucursal_id)) {
				$this->response->result = $this->db
					->from($this->table)
					->select(null)->select("DISTINCT $this->tableA.*")
					->leftJoin("$this->tableA ON $this->tableA.id = apartado_id")
					->where('producto_id', $producto_id)
					->where("CAST(fecha AS DATE) BETWEEN '$inicio' AND '$final'")
					->where("sucursal_id ".($sucursal_id==0? ">": "=")." $sucursal_id")
					// ->where("cliente_id ".($cliente_id==0? ">": "=")." $cliente_id")
					->where("$this->table.status", 1)
					->where("$this->tableA.status", $status)
					->orderBy('apartado_id')
					->fetchAll();
			} else {
				$this->response->result = $this->db
					->from($this->table)
					->where('producto_id', $producto_id)
					->where('status', 1)
					->fetchAll();
			}

			return $this->response->SetResponse(true);
		}// fin de searchByProducto 

		/*** add ***/
		public function add($data) {
			try{
				$this->response->result = $this->db
					->insertInto($this->table, $data)
					->execute();

				if($this->response->result)	$this->response->SetResponse(true);
				else	$this->response->SetResponse(false, 'no se agrego el registro');

			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: add model apartado_detalle:");
			}
			
			return $this->response;
		}//fin de add

		/*** edit */
		public function edit($data, $id) {
			try{
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();

				if($this->response->result)	$this->response->SetResponse(true);
				else	$this->response->SetResponse(false, 'no se agrego el registro');
	
			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				
				$this->response->SetResponse(false, "catch: edit model apartado detalle");
			}

			return $this->response;
		}//fin de edit

		/*** del */
		public function del($id) {
			try{
				$data = ['status' => 0];
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();

				if($this->response->result)	$this->response->SetResponse(true);
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }

			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del model venta");
			}

			return $this->response;
		}
		// fin de del

		/*** delByApartado */
		public function delByApartado($apartado_id) {
			try{
				$data = ['status' => 0];
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('apartado_id', $apartado_id)
					->execute();

				if($this->response->result)	$this->response->SetResponse(true);
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }

			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: delByApartado model apartado_detalle");
			}

			return $this->response;
		}
		// fin de delByApartado
	}//fin de apartado_detalle
?>