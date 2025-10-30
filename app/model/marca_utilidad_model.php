<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class MarcaUtilidadModel {
		private $db;
		private $table = 'marca_utilidad'; 
		private $tableM = 'marca'; 
		private $tableC = 'prod_categoria'; 
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

			if($this->response->result) { $this->response->SetResponse(true); }
			else { $this->response->SetResponse(false, 'No existe el registro'); }

			return $this->response;
		}
		
		public function getAll($pagina, $limite, $prod_categoria_id=0, $marca_id=0) {
			if($limite == 0) {
				$this->response->result = $this->db
					->from($this->table)
					->select("$this->tableM.nombre AS marca, $this->tableC.nombre AS categoria")
					->leftJoin("$this->tableM on $this->tableM.id = marca_id")
					->leftJoin("$this->tableC on $this->tableC.id = prod_categoria_id")
					->where("prod_categoria_id ".($prod_categoria_id!=0? "=": ">").$prod_categoria_id)
					->where("marca_id ".($marca_id!=0? "=": ">").$marca_id)
					->where("$this->tableM.status", 1)
					->where("$this->tableC.status", 1)
					->orderBy('id')
					->fetchAll();
			} else {
				$inicial = $pagina * $limite;
				$this->response->result = $this->db
					->from($this->table)
					->select("$this->tableM.nombre AS marca, $this->tableC.nombre AS categoria")
					->leftJoin("$this->tableM on $this->tableM.id = marca_id")
					->leftJoin("$this->tableC on $this->tableC.id = prod_categoria_id")
					->where("prod_categoria_id ".($prod_categoria_id!=0? "=": ">").$prod_categoria_id)
					->where("marca_id ".($marca_id!=0? "=": ">").$marca_id)
					->where("$this->tableM.status", 1)
					->where("$this->tableC.status", 1)
					->orderBy('id')
					->limit("$inicial, $limite")
					->fetchAll();
			}

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) Total')
				->where("prod_categoria_id ".($prod_categoria_id!=0? "=": ">").$prod_categoria_id)
				->where("marca_id ".($marca_id!=0? "=": ">").$marca_id)
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