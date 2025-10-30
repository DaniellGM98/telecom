<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class TimbresModel {
		private $db;
		private $table = 'timbres'; 
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

		public function find($busqueda) {
			$this->response->result = $this->db
				->from($this->table)
				->select(NULL)->select('fecha, asignados, disponibles, ultimo_timbre')
				->where("CONCAT_WS(' ', fecha, asignados, disponibles, ultimo_timbre) LIKE ?" , "%$busqueda%")
				->fetchAll();

			return $this->response->SetResponse(true);
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

		public function add($data) {
			try{
				$this->response->result = $this->db
					->insertInto($this->table, $data)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, 'id del registro: '.$this->response->result); }
				else { $this->response->SetResponse(false, 'no se inserto el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: add model timbres');
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
				$this->response->SetResponse(false, 'catch: edit model timbres');
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
				$this->response->SetResponse(false, 'catch: del model timbres');
			}

			return $this->response;
		}

		/** 
		 * Método getDisponibles
		 * Regresa los timbres disponibles y el id de la ultima asignación
		 * by isantosp
		 */
		public function getDisponibles() {
			$this->response->result = $this->db
				->from($this->table)
				->select(NULL)->select('IFNULL(id,0) AS id, IFNULL(SUM(disponibles),0) AS disponibles')
				->where('disponibles > ?' , 0)
				->orderBy('id DESC')
				->limit(1)
				->fetch();

			return $this->response->SetResponse(true);
		}
	}//fin de timbres
?>