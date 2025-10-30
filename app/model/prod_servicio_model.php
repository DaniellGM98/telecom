<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class ProdServicioModel {
		private $db;
		private $table = 'prod_servicio'; 
		private $tableP = 'producto'; 
		private $tableL = 'prod_lista_precio'; 
		private $response;

		public function __CONSTRUCT($db) {
			require_once './core/defines.php';
			$this->db = $db;
			$this->response = new Response();
		}

		public function get($id) {
			$this->response->result = $this->db
				->from($this->table)
				->select("sku, CONCAT('[', sku, ']', $this->tableP.nombre, ' ', modelo, ' ') AS producto")
				->leftJoin("$this->tableP on $this->tableP.id = producto_id")
				->where("$this->table.id", $id)
				->where("status", 1)
				->fetch();

			if($this->response->result) { $this->response->SetResponse(true); }
			else { $this->response->SetResponse(false, 'No existe el registro'); }

			return $this->response;
		}

		public function getByServicio($servicio_id, $producto_id=0) {
			$this->response->result = $this->db
				->from($this->table)
				->select("sku, CONCAT('[', sku, ']', $this->tableP.nombre, ' ', modelo, ' ') AS producto")
				->leftJoin("$this->tableP on $this->tableP.id = producto_id")
				->where('servicio_id', $servicio_id)
				->where("producto_id".($producto_id==0? ">": "=").$producto_id)
				->where("status", 1)
				->fetchAll();

			if($this->response->result) { $this->response->SetResponse(true); }
			else { $this->response->SetResponse(false, 'No existe el registro'); }

			return $this->response;
		}
		
		public function getAll($pagina, $limite, $producto_id=0, $servicio_id=0) {
			if($limite == 0) {
				$this->response->result = $this->db
					->from($this->table)
					->where('servicio_id', $servicio_id)
					->where('producto_id', $producto_id)
					->orderBy('id')
					->fetchAll();
			} else {
				$inicial = $pagina * $limite;
				$this->response->result = $this->db
					->from($this->table)
					->where('servicio_id', $servicio_id)
					->where('producto_id', $producto_id)
					->orderBy('id')
					->limit("$inicial, $limite")
					->fetchAll();
			}

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) Total')
				->where('servicio_id', $servicio_id)
				->where('producto_id', $producto_id)
				->fetch()
				->Total;

			return $this->response->SetResponse(true);
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
				$this->response->result = $this->db
					->deleteFrom($this->table)
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