<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class CFDIModel {
		private $db;
		private $table = 'cfdi'; 
		private $response;
		
		public function __CONSTRUCT($db) {
			$this->db = $db;
			$this->response = new Response();
		}

		public function get($id_cfdi) {
			$this->response->result = $this->db
				->from($this->table)
				->where('id_cfdi', $id_cfdi)
				->fetch();

			if($this->response->result) { $this->response->SetResponse(true); }
			else { $this->response->SetResponse(false, 'no existe el registro'); }

			return $this->response;
		}

		public function find($busqueda) {
			$this->response->result = $this->db
				->from($this->table)
				->select(NULL)->select('fk_venta, serie, folio, tipo')
				->where("CONCAT_WS(' ', fk_venta, serie, folio, tipo) LIKE ?" , "%$busqueda%")
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
				$this->response->SetResponse(false, 'catch: add model cfdi');
			}

			return $this->response;
		}

		public function edit($data, $id_cfdi) {
			try{
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id_cfdi', $id_cfdi)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, "id actualizado $id"); }
				else { $this->response->SetResponse(false, 'no se edito el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: edit model cfdi');
			}

			return $this->response;
		}
		
		public function del($id_cfdi) {
			try{
				$data['status'] = 0;
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id_cfdi', $id_cfdi)
					->execute();

				if($this->response->result!=0)	$this->response->SetResponse(true, 'id baja: '.$id_cfdi);
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: del model cfdi');
			}

			return $this->response;
		}
	}
?>