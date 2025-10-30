<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class EmpleadoModel {
		private $db;
		private $table = 'empleado';
		private $tableU = 'usuario';
		private $response;
		
		public function __CONSTRUCT($db) {
			$this->db = $db;
			$this->response = new Response();
		}

		public function get($usuario_id) {
			$this->response->result = $this->db
				->from($this->table)
				->select("$this->tableU.*")
				->leftJoin("$this->tableU on $this->tableU.id = usuario_id")
				->where('usuario_id', $usuario_id)
				->where("status != ?", 0)
				->fetch();

			if($this->response->result) $this->response->SetResponse(true);
			else $this->response->SetResponse(false, 'no existe el registro');

			return $this->response;
		}

		public function getBySucursal($sucursal_id) {
			$this->response->result = $this->db
				->from($this->table)
				->select("nombre, apellidos, telefono, email, usuario_tipo_id, status")
				->leftJoin("$this->tableU on $this->tableU.id = usuario_id")
				->where('sucursal_id', $sucursal_id)
				->where("status != ?", 0)
				->fetchAll();

			if($this->response->result) $this->response->SetResponse(true);
			else $this->response->SetResponse(false, 'no existe el registro');

			return $this->response;
		}

		public function find($filtro) {
			$this->response->result = $this->db
				->from($this->table)
				->select("nombre, apellidos, telefono, email, usuario_tipo_id, status")
				->leftJoin("$this->tableU on $this->tableU.id = usuario_id")
				->where("CONCAT_WS(' ', nombre, apellidos, telefono, email) LIKE '%$filtro%'")
				->where("status", 1)
				->fetchAll();
				
			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select("COUNT(*) AS total")
				->leftJoin("$this->tableU on $this->tableU.id = usuario_id")
				->where("CONCAT_WS(' ', nombre, apellidos, telefono, email) LIKE '%$filtro%'")
				->where("status", 1)
				->fetch()
				->total;

			return $this->response->SetResponse(true);
		}

		public function getAll($pagina=0, $limite=0, $filtro=0) {
			$filtro = $filtro==0? "_": $filtro;
			if(intval($limite) == 0) {
				$this->response->result = $this->db
					->from($this->table)
					->select("nombre, apellidos, telefono, email, usuario_tipo_id, status")
					->leftJoin("$this->tableU on $this->tableU.id = usuario_id")
					->where("CONCAT_WS(' ', nombre, apellidos, telefono, email) LIKE '%$filtro%'")
					->where("status", 1)
					->orderBy('apellidos ASC')
					->fetchAll();
			} else {
				$inicial = $pagina * $limite;
				$this->response->result = $this->db
					->from($this->table)
					->select("nombre, apellidos, telefono, email, usuario_tipo_id, status")
					->leftJoin("$this->tableU on $this->tableU.id = usuario_id")
					->where("CONCAT_WS(' ', nombre, apellidos, telefono, email) LIKE '%$filtro%'")
					->where("status", 1)
					->limit($inicial, $limite)
					->orderBy('apellidos ASC')
					->fetchAll();
			}

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) Total')
				->leftJoin("$this->tableU on $this->tableU.id = usuario_id")
				->where("CONCAT_WS(' ', nombre, apellidos, telefono, email) LIKE '%$filtro%'")
				->where("status", 1)
				->fetch()
				->Total;

			return $this->response->SetResponse(true);
		}

		public function add($data) {
			try {
				$this->response->result = $this->db
					->insertInto($this->table, $data)
					->execute();

				// if($empleado!=0) { $this->response->SetResponse(true); }
				// else { $this->response->SetResponse(false, 'no se inserto el registro'); $this->response->errors = $empleado; }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: add model empleado');
			}
				
			return $this->response->SetResponse(true);
		}

		public function edit($data, $usuario_id) {
			try {
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('usuario_id', $usuario_id)
					->execute();

				$this->response->SetResponse(true);

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: edit model empleado');
			}

			return $this->response;
		}

		public function del($usuario_id) {
			try {
				$data['status'] = 0;
				$this->response = $this->edit($data, $usuario_id);
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: del model empleado');
			}

			return $this->response;
		}
	}
?>