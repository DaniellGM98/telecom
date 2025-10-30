<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class MarcaModel {
		private $db;
		private $table = 'marca'; 
		private $response;

		public function __CONSTRUCT($db) {
			require_once './core/defines.php';
			$this->db = $db;
			$this->response = new Response();
		}

		public function find($busqueda) {
			$this->response->result = $this->db
				->from($this->table)
				->where("nombre LIKE '%$busqueda%'")
				->where('status = 1')
				->fetchAll();

			return $this->response->SetResponse(true);
		}
		
		public function getByNombreMD5($nombre) {
			$this->response->result = $this->db
				->from($this->table)
				->where('MD5(LOWER(nombre))', $nombre)
				->where('status', 1)
				->fetchAll();
			
			$this->response->total = $this->db
				->from($this->table)
				->select(NULL)->select('COUNT(*) AS total')
				->where('MD5(LOWER(nombre))', $nombre)
				->where('status', 1)
				->fetch()
				->total;

			return $this->response->SetResponse(true);;
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
					->where("nombre LIKE '%$busqueda%'")
					->where('status', 1)
					->orderBy('nombre ASC')
					->fetchAll();
			} else {
				$inicial = $pagina * $limite;
				$this->response->result = $this->db
					->from($this->table)
					->where("nombre LIKE '%$busqueda%'")
					->where('status', 1)
					->orderBy('nombre ASC')
					->limit("$inicial, $limite")
					->fetchAll();
			}

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) Total')
				->where("nombre LIKE '%$busqueda%'")
				->where('status', 1)
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